<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDirectoryController extends Controller
{
    public const TYPES = [
        'schools' => ['school', 'Schools'],
        'colleges' => ['college', 'Colleges'],
        'universities' => ['university', 'Universities'],
        'institutes' => ['training_institute', 'Training institutes'],
    ];

    public const CATEGORY_LABELS = ['schools' => 'School', 'colleges' => 'College', 'universities' => 'University', 'institutes' => 'Training institute'];

    public function home(Request $request): View
    {
        $listings = $this->listings()
            ->addSelect([
                'curriculum' => DB::table('boards')->join('school_board', 'boards.id', '=', 'school_board.board_id')
                    ->whereColumn('school_board.school_id', 'schools.id')->where('boards.is_active', true)
                    ->orderBy('boards.sort_order')->orderBy('boards.name')->limit(1)->select('boards.name'),
                'education_level' => DB::table('education_levels')->join('school_education_level', 'education_levels.id', '=', 'school_education_level.education_level_id')
                    ->whereColumn('school_education_level.school_id', 'schools.id')
                    ->orderBy('education_levels.sort_order')->orderBy('education_levels.name')->limit(1)->select('education_levels.name'),
                'monthly_fee' => DB::table('school_listing_fees')->whereColumn('school_id', 'schools.id')->whereNotNull('monthly_fee')
                    ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', today()))
                    ->orderByDesc('effective_from')->orderByDesc('id')->limit(1)->select('monthly_fee'),
                'fee_currency' => DB::table('school_listing_fees')->whereColumn('school_id', 'schools.id')->whereNotNull('monthly_fee')
                    ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', today()))
                    ->orderByDesc('effective_from')->orderByDesc('id')->limit(1)->select('currency'),
            ])
            ->orderByDesc('schools.is_featured')->orderByDesc('schools.published_at')->orderByDesc('schools.id')->limit(3)->get();
        $facilities = DB::table('facilities')->join('school_facility', 'facilities.id', '=', 'school_facility.facility_id')
            ->whereIn('school_facility.school_id', $listings->modelKeys())->where('facilities.is_active', true)
            ->orderBy('facilities.sort_order')->orderBy('facilities.name')->get(['school_facility.school_id', 'facilities.name'])->groupBy('school_id');

        return view('directory.home', [
            'savedSlugs' => $this->savedSlugs($request),
            'listings' => $listings,
            'facilities' => $facilities,
            'cities' => $this->cities(),
            'boards' => DB::table('boards')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
            'areas' => DB::table('areas')->join('cities', 'cities.id', '=', 'areas.city_id')
                ->where('areas.is_active', true)->where('cities.is_active', true)->orderBy('areas.name')->limit(12)
                ->get(['areas.name', 'areas.slug', 'cities.slug as city_slug', 'cities.name as city_name']),
        ]);
    }

    public function index(Request $request, ?string $category = null, ?string $citySlug = null, ?string $areaSlug = null): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:name,recent'],
            'view' => ['nullable', 'in:list,grid'],
            'board' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:100'],
            'has_fees' => ['nullable', 'boolean'],
        ]);
        $cities = $this->cities();
        $city = $filters['city'] = $citySlug ?? $filters['city'] ?? '';
        $area = $filters['area'] = $areaSlug ?? $filters['area'] ?? '';
        $search = $filters['q'] ?? '';
        $selectedCity = $cities->firstWhere('slug', $city);
        if ($category === 'schools' && $citySlug !== null && ! $selectedCity && $areaSlug === null
            && $this->listings()->where('schools.slug', $citySlug)->where('schools.institute_type', 'school')->exists()) {
            return $this->show($request, $citySlug);
        }
        abort_if($citySlug !== null && ! $selectedCity, 404);
        $areas = $selectedCity ? DB::table('areas')->where('city_id', $selectedCity->id)->where('is_active', true)
            ->orderBy('name')->get(['name', 'slug']) : collect();
        $selectedArea = $areas->firstWhere('slug', $area);
        abort_if($areaSlug !== null && ! $selectedArea, 404);
        $categoryName = self::TYPES[$category][1] ?? 'Education directory';

        $listings = $this->listings()
            ->when($category, fn (Builder $query) => $query->where('schools.institute_type', self::TYPES[$category][0]))
            ->when($city !== '', fn (Builder $query) => $query->where('cities.slug', $city))
            ->when($area !== '', fn (Builder $query) => $query->where('areas.slug', $area))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->whereLike('schools.name', "%{$search}%")->orWhereLike('schools.short_name', "%{$search}%")
                ->orWhereLike('schools.address_line', "%{$search}%")->orWhereLike('areas.name', "%{$search}%")))
            ->when(! empty($filters['board']), fn (Builder $query) => $query->whereExists(fn ($linked) => $linked
                ->from('school_board')->join('boards', 'boards.id', '=', 'school_board.board_id')
                ->whereColumn('school_board.school_id', 'schools.id')->where('boards.is_active', true)->where('boards.slug', $filters['board'])))
            ->when(! empty($filters['level']), fn (Builder $query) => $query->whereExists(fn ($linked) => $linked
                ->from('school_education_level')->join('education_levels', 'education_levels.id', '=', 'school_education_level.education_level_id')
                ->whereColumn('school_education_level.school_id', 'schools.id')->where('education_levels.slug', $filters['level'])))
            ->when(! empty($filters['has_fees']), fn (Builder $query) => $query->whereExists(fn ($linked) => $linked
                ->from('school_listing_fees')->whereColumn('school_listing_fees.school_id', 'schools.id')
                ->whereNotNull('monthly_fee')->where(fn ($date) => $date->whereNull('effective_from')->orWhere('effective_from', '<=', today()))))
            ->when(($filters['sort'] ?? '') === 'recent', fn (Builder $query) => $query->orderByDesc('schools.published_at'))
            ->orderBy('schools.name')->orderBy('schools.id')->paginate(12)->withQueryString();

        return view('directory.index', [
            'savedSlugs' => $this->savedSlugs($request),
            'boards' => DB::table('boards')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
            'levels' => DB::table('education_levels')->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
            'listings' => $listings, 'category' => $category, 'filters' => $filters, 'cities' => $cities, 'areas' => $areas,
            'categoryLabel' => self::CATEGORY_LABELS[$category] ?? null,
            'title' => $categoryName.($selectedArea ? ' in '.$selectedArea->name.', '.$selectedCity->name : ($selectedCity ? ' in '.$selectedCity->name : '')),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $listing = $this->listings()
            ->addSelect('schools.seo_title', 'schools.seo_description', 'schools.established_year', 'schools.medium')
            ->where('schools.slug', $slug)->firstOrFail();
        $this->countActivity($listing->id, 'profile_views');

        $school = $listing->id;
        $tags = [];
        foreach (['boards' => ['school_board', 'board_id'], 'education_levels' => ['school_education_level', 'education_level_id'],
            'facilities' => ['school_facility', 'facility_id']] as $table => [$pivot, $key]) {
            $tags[$table] = DB::table($table)->join($pivot, "{$pivot}.{$key}", '=', "{$table}.id")
                ->where("{$pivot}.school_id", $school)
                ->when($table !== 'education_levels', fn ($query) => $query->where("{$table}.is_active", true))
                ->orderBy("{$table}.sort_order")->orderBy("{$table}.name")->pluck("{$table}.name");
        }

        return view('directory.profile', [
            'listing' => $listing, 'savedSlugs' => $this->savedSlugs($request), 'tags' => $tags,
            'fees' => DB::table('school_listing_fees')->leftJoin('education_levels', 'education_levels.id', '=', 'school_listing_fees.education_level_id')
                ->where('school_id', $school)->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', today()))
                ->orderByDesc('effective_from')->orderBy('school_listing_fees.id')->get(['school_listing_fees.*', 'education_levels.name as level_name']),
            'media' => DB::table('school_media')->where('school_id', $school)->where('is_approved', true)->orderBy('sort_order')->get(),
            'reviews' => DB::table('reviews')->where('school_id', $school)->where('status', 'approved')->latest()->limit(6)
                ->get(['rating', 'title', 'body', 'relationship', 'school_reply']),
            'programs' => DB::table('programs')->where('school_id', $school)->where('status', 'active')->orderBy('title')->get(),
            'similarListings' => $this->listings()->where('schools.id', '!=', $school)
                ->where('schools.institute_type', $listing->institute_type)->where('schools.city_id', $listing->city_id)
                ->orderBy('schools.name')->limit(3)->get(),
        ]);
    }

    public function enquire(Request $request, string $slug): RedirectResponse
    {
        $listing = $this->listings()->where('schools.slug', $slug)->firstOrFail();
        $data = $request->validate([
            'parent_name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'], 'grade_interested' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:2000'], 'consent_given' => ['accepted'],
        ]);
        DB::transaction(function () use ($request, $listing, $data): void {
            DB::table('listing_leads')->insert([
                ...$data, 'consent_given' => true, 'school_id' => $listing->id, 'user_id' => $request->user()?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->countActivity($listing->id, 'leads');
        }, 3);

        return back()->with('directory_message', 'Your enquiry was submitted.');
    }

    public function logo(string $slug): StreamedResponse
    {
        $logo = $this->listings()->where('schools.slug', $slug)->firstOrFail()->logo_path;
        $disk = Storage::disk('public');
        abort_unless(is_string($logo) && preg_match('~\A(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(?:png|jpe?g|webp|gif|avif)\z~i', $logo) && $disk->exists($logo), 404);
        $type = $disk->mimeType($logo);
        abort_unless(in_array($type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif'], true), 404);

        return $disk->response($logo, null, ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function media(string $slug, int $media): StreamedResponse
    {
        $listing = $this->listings()->where('schools.slug', $slug)->firstOrFail();
        $row = DB::table('school_media')->where('school_id', $listing->id)->where('id', $media)
            ->where('is_approved', true)->firstOrFail();
        $disk = Storage::disk('public');
        abort_unless(is_string($row->path) && preg_match('~\Alisting-media/[A-Za-z0-9]+\.(?:jpg|jpeg|png|webp|pdf)\z~', $row->path)
            && $disk->exists($row->path), 404);

        return $disk->response($row->path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function collection(Request $request, string $collection): View
    {
        $slugs = $collection === 'saved' ? $this->savedSlugs($request) : $request->session()->get("directory.{$collection}", []);
        $listings = $this->listings()->whereIn('schools.slug', $slugs)->get()->keyBy('slug');

        return view('directory.collection', [
            'collection' => $collection, 'savedSlugs' => $collection === 'saved' ? $slugs : $this->savedSlugs($request), 'listings' => collect($slugs)->map(fn ($slug) => $listings->get($slug))->filter(),
        ]);
    }

    public function toggle(Request $request, string $collection, string $slug): RedirectResponse
    {
        if ($collection === 'saved' && $request->user()) {
            return $this->toggleAccountSave($request, $slug);
        }

        $key = "directory.{$collection}";
        $current = $request->session()->get($key, []);
        if (in_array($slug, $current, true)) {
            $request->session()->put($key, array_values(array_diff($current, [$slug])));

            return back();
        }

        $listing = $this->listings()->where('schools.slug', $slug)->firstOrFail();
        $current = $this->listings()->whereIn('schools.slug', $current)->pluck('schools.slug')->all();
        $limit = $collection === 'compare' ? 3 : 100;
        if (count($current) >= $limit) {
            return back()->with('directory_message', "You can add up to {$limit} institutions to this list.");
        }

        $request->session()->put($key, [...$current, $slug]);
        $this->countActivity($listing->id, $collection === 'compare' ? 'compare_adds' : 'saves');

        return back()->with('directory_message', $collection === 'compare' ? 'Added to comparison.' : 'Listing saved.');
    }

    private function savedSlugs(Request $request): array
    {
        if (! $request->user()) {
            return $request->session()->get('directory.saved', []);
        }

        return DB::table('saved_schools')->join('schools', 'schools.id', '=', 'saved_schools.school_id')
            ->where('saved_schools.user_id', $request->user()->id)->whereNull('schools.deleted_at')
            ->orderByDesc('saved_schools.created_at')->orderBy('schools.id')->pluck('schools.slug')->all();
    }

    private function toggleAccountSave(Request $request, string $slug): RedirectResponse
    {
        return DB::transaction(function () use ($request, $slug): RedirectResponse {
            $user = $request->user();
            DB::table('users')->where('id', $user->id)->lockForUpdate()->firstOrFail();
            $saved = DB::table('saved_schools')->where('user_id', $user->id);
            $schoolIds = DB::table('schools')->where('slug', $slug)->select('id');
            if ((clone $saved)->whereIn('school_id', $schoolIds)->delete()) {
                return back();
            }
            $listing = $this->listings()->where('schools.slug', $slug)->firstOrFail();
            if ($saved->whereIn('school_id', School::publicDirectory()->select('schools.id'))->count() >= 100) {
                return back()->with('directory_message', 'You can save up to 100 institutions.');
            }

            DB::table('saved_schools')->insert(['user_id' => $user->id, 'school_id' => $listing->id, 'created_at' => now()]);
            $this->countActivity($listing->id, 'saves');

            return back()->with('directory_message', 'Listing saved.');
        }, 3);
    }

    private function countActivity(int $school, string $counter): void
    {
        $now = now();
        $key = ['school_id' => $school, 'stat_date' => $now->toDateString()];
        DB::table('school_stats_daily')->upsert(
            [...$key, 'created_at' => $now, 'updated_at' => $now],
            ['school_id', 'stat_date'],
            ['updated_at'],
        );
        DB::table('school_stats_daily')->where($key)->increment($counter);
    }

    public function sitemap(): Response
    {
        return response()->view('directory.sitemap', [
            'listings' => $this->listings()->reorder('schools.id')->cursor(),
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function cities()
    {
        return DB::table('cities')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
    }

    private function listings(): Builder
    {
        return School::publicDirectory()
            ->leftJoin('cities', fn ($join) => $join->on('schools.city_id', '=', 'cities.id')->where('cities.is_active', true))
            ->leftJoin('areas', fn ($join) => $join->on('schools.area_id', '=', 'areas.id')->on('areas.city_id', '=', 'cities.id')->where('areas.is_active', true))
            ->select([
                'schools.id', 'schools.slug', 'schools.name', 'schools.city_id', 'schools.short_name', 'schools.branch_name',
                'schools.logo_path', 'schools.institute_type', 'schools.tagline', 'schools.description', 'schools.address_line',
                'schools.phone', 'schools.email', 'schools.website_url', 'schools.published_at',
                'cities.name as city_name', 'areas.name as area_name',
            ]);
    }
}
