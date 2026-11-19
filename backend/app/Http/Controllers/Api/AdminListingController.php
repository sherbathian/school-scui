<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminListingResource;
use App\Models\School;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminListingController extends Controller
{
    private const INSTITUTE_TYPES = ['school', 'college', 'university', 'training_institute'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'listing_status' => ['sometimes', Rule::in(['draft', 'pending_review', 'published', 'suspended'])],
            'institute_type' => ['sometimes', Rule::in(self::INSTITUTE_TYPES)],
            'city_id' => ['sometimes', 'integer', 'min:1'],
            'area_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $options = $request->validate([
            'needs_verification' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $search = $options['search'] ?? '';
        $reviewQueue = fn ($query) => $query->whereNull('verified_at')
            ->where('status', 'active')->whereIn('listing_status', ['draft', 'pending_review']);

        $schools = School::query()
            ->where($filters)
            ->when($options['needs_verification'] ?? false, $reviewQueue)
            ->when($search !== '', fn ($query) => $query->where(fn ($membership) => $membership
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('short_name', "%{$search}%")
                ->orWhereLike('slug', "%{$search}%")))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return AdminListingResource::collection($schools)->additional([
            'summary' => [
                'published' => School::query()->where('listing_status', 'published')->where('status', 'active')->whereNotNull('verified_at')->count(),
                'needs_verification' => $reviewQueue(School::query())->count(),
                'suspended' => School::query()->where('listing_status', 'suspended')->count(),
            ],
        ]);
    }

    public function store(Request $request): AdminListingResource
    {
        $details = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'institute_type' => $this->instituteTypeRules(),
            ...$this->profileRules(),
        ]);

        $this->validateArea($details['city_id'] ?? null, $details['area_id'] ?? null);
        $slug = Str::slug(($details['slug'] ?? '') ?: $details['name']);
        validator(['slug' => $slug], ['slug' => ['required', 'max:255', 'unique:schools,slug']])->validate();

        try {
            $school = new School;
            $school->forceFill([...$details, 'slug' => $slug])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => ['The slug has already been taken.']]);
        }

        return new AdminListingResource($school->refresh());
    }

    public function show(School $listing): AdminListingResource
    {
        return new AdminListingResource($listing);
    }

    public function update(Request $request, School $listing): AdminListingResource
    {
        $details = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'institute_type' => ['sometimes', ...$this->instituteTypeRules()],
            ...$this->profileRules(),
        ]);

        return $this->updateLocked($listing, function (School $school) use ($details): void {
            $school->forceFill($details);
            $this->validateArea($school->city_id, $school->area_id);
        });
    }

    public function verify(School $listing): AdminListingResource
    {
        return $this->updateLocked($listing, function (School $school): void {
            $school->verified_at ??= now();
        });
    }

    public function publish(School $listing): AdminListingResource
    {
        return $this->updateLocked($listing, function (School $school): void {
            abort_unless($school->verified_at && $school->status === 'active', 409, 'Only active, verified listings can be published.');

            $school->listing_status = 'published';
            $school->published_at ??= now();
        });
    }

    public function suspend(School $listing): AdminListingResource
    {
        return $this->updateLocked($listing, function (School $school): void {
            $school->listing_status = 'suspended';
        });
    }

    private function updateLocked(School $listing, callable $apply): AdminListingResource
    {
        return DB::transaction(function () use ($listing, $apply): AdminListingResource {
            $school = School::query()->lockForUpdate()->findOrFail($listing->id);
            $apply($school);
            $school->save();

            return new AdminListingResource($school);
        }, 3);
    }

    private function instituteTypeRules(): array
    {
        return ['required', Rule::in(self::INSTITUTE_TYPES)];
    }

    private function validateArea(int|string|null $city, int|string|null $area): void
    {
        if ($area !== null) {
            validator(['area_id' => $area], [
                'area_id' => [Rule::exists('areas', 'id')->where('city_id', $city)->where('is_active', true)],
            ])->validate();
        }
    }

    private function profileRules(): array
    {
        return [
            'short_name' => ['nullable', 'string', 'max:80'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'area_id' => ['nullable', 'integer'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }
}
