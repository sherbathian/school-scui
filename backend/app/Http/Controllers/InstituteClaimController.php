<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use App\Services\SchoolOrganisations;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstituteClaimController extends Controller
{
    public function registration(Request $request): View
    {
        return view('directory.institute-account', ['mode' => 'register', 'listing' => $request->query('listing')]);
    }

    public function signInPage(Request $request): View
    {
        return view('directory.institute-account', ['mode' => 'login', 'listing' => $request->query('listing')]);
    }

    public function register(Request $request): RedirectResponse
    {
        abort_if(Auth::check(), 409);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
            'listing' => ['nullable', 'string', 'max:255'],
        ]);

        if (strlen($data['password']) > 72) {
            throw ValidationException::withMessages(['password' => ['Use a password no longer than 72 bytes.']]);
        }

        try {
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => ['This email address is already registered.']]);
        }

        Auth::guard('web')->login($user->refresh());
        $request->session()->regenerate();

        return $this->afterSignIn($data['listing'] ?? null);
    }

    public function login(Request $request, AuthController $auth): RedirectResponse
    {
        abort_if(Auth::check(), 409);
        $data = $request->validate(['listing' => ['nullable', 'string', 'max:255']]);
        $auth->login($request);

        return $this->afterSignIn($data['listing'] ?? null);
    }

    public function submissions(Request $request): View
    {
        abort_unless($request->user()->is_active, 403);

        return view('directory.institute-submission', [
            'submissions' => DB::table('listing_claims')->join('schools', 'schools.id', '=', 'listing_claims.school_id')
                ->where('listing_claims.user_id', $request->user()->id)->where('schools.source', 'self_submitted')
                ->whereNull('schools.deleted_at')->orderByDesc('listing_claims.id')
                ->get(['schools.name', 'schools.slug', 'schools.listing_status', 'listing_claims.status']),
            'types' => PublicDirectoryController::TYPES,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $types = array_column(PublicDirectoryController::TYPES, 0);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'institute_type' => ['required', Rule::in($types)],
            'address_line' => ['nullable', 'string', 'max:255'],
            'claimant_role' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $slug = Str::slug($data['name']);
        validator(['name' => $slug], ['name' => ['required', 'max:255']])->validate();

        try {
            DB::transaction(function () use ($data, $slug, $user): void {
                $school = School::forceCreate([
                    'name' => $data['name'], 'slug' => $slug, 'institute_type' => $data['institute_type'],
                    'address_line' => $data['address_line'] ?? null, 'source' => 'self_submitted',
                    'listing_status' => 'pending_review', 'claim_status' => 'pending',
                ]);
                $this->recordClaim($school, $user, [
                    'claimant_name' => $user->name, 'claimant_role' => $data['claimant_role'] ?? null, 'phone' => $data['phone'],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => ['A listing with this name already exists. Search the directory to claim it or contact support.']]);
        }

        return redirect()->route('institute.new')->with('directory_message', 'Your institution was submitted for review.');
    }

    public function logout(Request $request, AuthController $auth): RedirectResponse
    {
        $auth->logout($request);

        return redirect()->route('directory.home');
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $school = School::publicDirectory()->where('slug', $slug)->firstOrFail();
        if (! $request->user()) {
            return redirect()->route('institute.register', ['listing' => $slug]);
        }
        abort_unless($request->user()->is_active, 403);

        return view('directory.institute-claim', [
            'school' => $school,
            'claim' => DB::table('listing_claims')->where('school_id', $school->id)
                ->where('user_id', $request->user()->id)->latest('id')->first(),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user()?->is_active, 403);
        $data = $request->validate([
            'claimant_name' => ['required', 'string', 'max:150'],
            'claimant_role' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($slug, $data, $user): void {
            $school = School::publicDirectory()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $duplicate = DB::table('listing_claims')->where('school_id', $school->id)
                ->where('user_id', $user->id)->where('status', 'pending')->exists();
            if ($school->claim_status === 'claimed' || $duplicate) {
                throw ValidationException::withMessages(['claim' => ['This listing has already been claimed or you have a pending request.']]);
            }

            $this->recordClaim($school, $user, $data);
            $school->forceFill(['claim_status' => 'pending'])->save();
        }, 3);

        return redirect()->route('institute.claim', $slug);
    }

    public function queue(): JsonResponse
    {
        return response()->json(DB::table('listing_claims')->join('schools', 'schools.id', '=', 'listing_claims.school_id')
            ->whereNull('schools.deleted_at')->where('listing_claims.status', 'pending')
            ->select('listing_claims.*', 'schools.name as school_name')->orderBy('listing_claims.id')->paginate(25));
    }

    public function review(Request $request, int $claim, SchoolOrganisations $organisations): JsonResponse
    {
        $details = $request->validate([
            'decision' => ['required', 'in:verified,rejected'],
            'review_note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $claim, $details, $organisations): void {
            $claims = DB::table('listing_claims');
            $schoolId = (clone $claims)->where('id', $claim)->value('school_id');
            $school = School::query()->lockForUpdate()->findOrFail($schoolId);
            $record = (clone $claims)->where('id', $claim)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending', 409, 'This claim has already been reviewed.');

            if ($details['decision'] === 'verified') {
                abort_unless($school->status === 'active' && $school->verified_at && $school->claim_status !== 'claimed', 409);
                abort_unless(User::query()->whereKey($record->user_id)->where('is_active', true)->exists(), 409);
                $group = $organisations->ensureFor($school);
                $groupRecord = DB::table('school_groups')->where('id', $group)->lockForUpdate()->firstOrFail();
                abort_unless($groupRecord->status === 'active', 409, 'This organisation is suspended.');
                $owners = DB::table('school_group_user')->where('school_group_id', $group)
                    ->where('role', 'owner')->where('is_active', true)->where('user_id', '!=', $record->user_id);
                abort_if($owners->exists(), 409, 'This organisation already has an owner.');

                DB::table('school_group_user')->upsert([
                    'school_group_id' => $group, 'user_id' => $record->user_id, 'role' => 'owner',
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ], ['school_group_id', 'user_id'], ['role', 'is_active', 'updated_at']);
            }

            $review = [
                'reviewed_by_user_id' => $request->user()->id, 'review_note' => $details['review_note'],
                'reviewed_at' => now(), 'updated_at' => now(),
            ];
            (clone $claims)->where('id', $claim)->update([
                ...$review, 'status' => $details['decision'],
                'verified_at' => $details['decision'] === 'verified' ? now() : null,
            ]);

            $remaining = (clone $claims)->where('school_id', $school->id)->where('status', 'pending');
            if ($details['decision'] === 'verified') {
                $remaining->update([...$review, 'status' => 'rejected', 'review_note' => 'Another claim was approved for this listing.']);
                $school->forceFill(['claim_status' => 'claimed'])->save();
            } elseif ($school->claim_status !== 'claimed') {
                $school->forceFill(['claim_status' => $remaining->exists() ? 'pending' : 'unclaimed'])->save();
            }
        }, 3);

        return response()->json(['status' => $details['decision']]);
    }

    private function recordClaim(School $school, User $user, array $data): void
    {
        DB::table('listing_claims')->insert([
            ...$data, 'school_id' => $school->id, 'user_id' => $user->id, 'email' => $user->email,
            'method' => 'manual', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function afterSignIn(?string $listing): RedirectResponse
    {
        return $listing ? redirect()->route('institute.claim', $listing) : redirect()->route('institute.new');
    }
}
