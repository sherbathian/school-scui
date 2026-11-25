<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use App\Services\BranchAuditLog;
use App\Services\ListingClaimVerification;
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
        return view('directory.institute-account', ['mode' => 'register', 'listing' => $request->query('listing'), 'directoryAccount' => $request->routeIs('directory.sign-up')]);
    }

    public function signInPage(Request $request): View
    {
        return view('directory.institute-account', ['mode' => 'login', 'listing' => $request->query('listing'), 'directoryAccount' => $request->routeIs('directory.sign-in')]);
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
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['sometimes', Rule::in(['en', 'ur'])],
            'listing' => ['nullable', 'string', 'max:255'],
        ]);

        if (strlen($data['password']) > 72) {
            throw ValidationException::withMessages(['password' => ['Use a password no longer than 72 bytes.']]);
        }

        try {
            $user = User::forceCreate([
                ...collect($data)->only(['name', 'email', 'password', 'phone', 'locale'])->all(),
                'last_login_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => ['This email address is already registered.']]);
        }

        Auth::guard('web')->login($user->refresh());
        $request->session()->regenerate();

        return $request->routeIs('directory.sign-up.submit', 'directory.sign-in.submit')
            ? redirect()->route('directory.dashboard') : $this->afterSignIn($data['listing'] ?? null);
    }

    public function login(Request $request, AuthController $auth): RedirectResponse
    {
        abort_if(Auth::check(), 409);
        $data = $request->validate(['listing' => ['nullable', 'string', 'max:255']]);
        $auth->login($request);

        return $request->routeIs('directory.sign-up.submit', 'directory.sign-in.submit')
            ? redirect()->route('directory.dashboard') : $this->afterSignIn($data['listing'] ?? null);
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

        return redirect()->route('institute.claim', $slug);
    }

    public function logout(Request $request, AuthController $auth): RedirectResponse
    {
        $auth->logout($request);

        return redirect()->route('directory.home');
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        if (! $request->user()) {
            School::publicDirectory()->where('slug', $slug)->firstOrFail();
            return redirect()->route('institute.register', ['listing' => $slug]);
        }
        abort_unless($request->user()->is_active, 403);

        $school = $this->claimListing($request, $slug);

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
            if ($school->claim_status === 'claimed') {
                throw ValidationException::withMessages(['claim' => ['This listing has already been claimed.']]);
            }

            if ($duplicate) {
                return;
            }
            $this->recordClaim($school, $user, $data);
            $school->forceFill(['claim_status' => 'pending'])->save();
        }, 3);

        return redirect()->route('institute.claim', $slug);
    }

    public function sendCode(Request $request, string $slug, ListingClaimVerification $verification): RedirectResponse
    {
        $school = $this->claimListing($request, $slug);
        $verification->send($school->id, $request->user());

        return redirect()->route('institute.claim', $slug)->with('claim_message', 'Verification code sent.');
    }

    public function verifyCode(Request $request, string $slug, ListingClaimVerification $verification): RedirectResponse
    {
        $school = $this->claimListing($request, $slug);
        $code = $request->isJson() ? $request->json('code') : $request->post('code');
        $request->query->remove('code');
        $request->replace($request->except('code'));
        validator(['code' => $code], ['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']])->validate();
        $verification->verify($school->id, $request->user(), $code);

        return redirect()->route('institute.claim', $slug);
    }

    private function claimListing(Request $request, string $slug): School
    {
        abort_unless($request->user()?->is_active, 403);
        $school = School::publicDirectory()->where('slug', $slug)->first();

        return $school ?? School::query()->where('slug', $slug)->where('source', 'self_submitted')
            ->whereIn('id', DB::table('listing_claims')->where('user_id', $request->user()->id)->select('school_id'))
            ->firstOrFail();
    }

    public function queue(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['sometimes', 'in:pending,verified,rejected,all']]);
        $status = $filters['status'] ?? 'pending';

        return response()->json(DB::table('listing_claims')->join('schools', 'schools.id', '=', 'listing_claims.school_id')
            ->leftJoin('users', 'users.id', '=', 'listing_claims.user_id')
            ->when($status === 'pending', fn ($query) => $query->whereNull('schools.deleted_at'))
            ->when($status !== 'all', fn ($query) => $query->where('listing_claims.status', $status))
            ->select('listing_claims.*', 'schools.name as school_name', 'users.email as account_email')
            ->orderBy('listing_claims.id')->paginate(25)->through(function (object $claim): object {
                $claim->email_verified = $claim->email_verified_at !== null && $claim->email === $claim->account_email;
                unset($claim->account_email);

                return $claim;
            }));
    }

    public function review(Request $request, int $claim, SchoolOrganisations $organisations, BranchAuditLog $audit): JsonResponse
    {
        $details = $request->validate([
            'decision' => ['required', 'in:verified,rejected'],
            'review_note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $claim, $details, $organisations, $audit): void {
            $claims = DB::table('listing_claims');
            $schoolId = (clone $claims)->where('id', $claim)->value('school_id');
            $school = School::query()->lockForUpdate()->findOrFail($schoolId);
            $record = (clone $claims)->where('id', $claim)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending', 409, 'This claim has already been reviewed.');

            if ($details['decision'] === 'verified') {
                abort_unless($school->status === 'active' && $school->verified_at && $school->claim_status !== 'claimed', 409);
                $claimant = User::query()->whereKey($record->user_id)->where('is_active', true)->lockForUpdate()->first();
                abort_unless($claimant && $record->email_verified_at && $record->email === $claimant->email,
                    409, 'The claimant must verify their account email before approval.');
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

            $action = $details['decision'] === 'verified' ? 'claim.approved' : 'claim.declined';
            $audit->record($request->user(), $school->id, $action, $claim,
                ['status' => $record->status], ['status' => $details['decision']]);

            User::query()->find($record->user_id)?->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'listing.claim.reviewed',
                'data' => [
                    'title' => $details['decision'] === 'verified' ? 'Listing claim approved' : 'Listing claim declined',
                    'message' => $school->name,
                    'claim_id' => $claim,
                ],
            ]);

            $remaining = (clone $claims)->where('school_id', $school->id)->where('status', 'pending');
            if ($details['decision'] === 'verified') {
                $competingIds = (clone $remaining)->orderBy('id')->pluck('id');
                $remaining->update([...$review, 'status' => 'rejected', 'review_note' => 'Another claim was approved for this listing.']);
                foreach ($competingIds as $competingId) {
                    $audit->record($request->user(), $school->id, 'claim.declined', $competingId,
                        ['status' => 'pending'], ['status' => 'rejected', 'approved_claim_id' => $claim]);
                }
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
