<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicDirectoryController extends Controller
{
    public const TYPES = [
        'schools' => ['school', 'Schools'],
        'colleges' => ['college', 'Colleges'],
        'universities' => ['university', 'Universities'],
        'institutes' => ['training_institute', 'Training institutes'],
    ];

    public function home(): View
    {
        return view('directory.home', [
            'listings' => $this->listings()->orderByDesc('schools.published_at')->orderByDesc('schools.id')->limit(3)->get(),
            'cities' => $this->cities(),
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
        ]);
        $cities = $this->cities();
        $city = $filters['city'] = $citySlug ?? $filters['city'] ?? '';
        $area = $filters['area'] = $areaSlug ?? $filters['area'] ?? '';
        $search = $filters['q'] ?? '';
        $selectedCity = $cities->firstWhere('slug', $city);
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
            ->when(($filters['sort'] ?? '') === 'recent', fn (Builder $query) => $query->orderByDesc('schools.published_at'))
            ->orderBy('schools.name')->orderBy('schools.id')->paginate(12)->withQueryString();

        return view('directory.index', [
            'listings' => $listings, 'category' => $category, 'filters' => $filters, 'cities' => $cities, 'areas' => $areas,
            'title' => $categoryName.($selectedArea ? ' in '.$selectedArea->name.', '.$selectedCity->name : ($selectedCity ? ' in '.$selectedCity->name : '')),
        ]);
    }

    public function show(string $slug): View
    {
        return view('directory.profile', ['listing' => $this->listings()->where('schools.slug', $slug)->firstOrFail()]);
    }

    public function collection(Request $request, string $collection): View
    {
        $slugs = $request->session()->get("directory.{$collection}", []);
        $listings = $this->listings()->whereIn('schools.slug', $slugs)->get()->keyBy('slug');

        return view('directory.collection', [
            'collection' => $collection, 'listings' => collect($slugs)->map(fn ($slug) => $listings->get($slug))->filter(),
        ]);
    }

    public function toggle(Request $request, string $collection, string $slug): RedirectResponse
    {
        $key = "directory.{$collection}";
        $current = $request->session()->get($key, []);
        if (in_array($slug, $current, true)) {
            $request->session()->put($key, array_values(array_diff($current, [$slug])));

            return back();
        }

        $this->listings()->where('schools.slug', $slug)->firstOrFail();
        $current = $this->listings()->whereIn('schools.slug', $current)->pluck('schools.slug')->all();
        $limit = $collection === 'compare' ? 3 : 100;
        if (count($current) >= $limit) {
            return back()->with('directory_message', "You can add up to {$limit} institutions to this list.");
        }

        $request->session()->put($key, [...$current, $slug]);

        return back()->with('directory_message', $collection === 'compare' ? 'Added to comparison.' : 'Listing saved.');
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
        return School::query()->where('schools.status', 'active')->where('schools.listing_status', 'published')
            ->whereNotNull('schools.verified_at')
            ->leftJoin('cities', fn ($join) => $join->on('schools.city_id', '=', 'cities.id')->where('cities.is_active', true))
            ->leftJoin('areas', fn ($join) => $join->on('schools.area_id', '=', 'areas.id')->on('areas.city_id', '=', 'cities.id')->where('areas.is_active', true))
            ->select([
                'schools.id', 'schools.slug', 'schools.name', 'schools.short_name', 'schools.branch_name',
                'schools.institute_type', 'schools.tagline', 'schools.description', 'schools.address_line',
                'schools.phone', 'schools.email', 'schools.website_url', 'schools.published_at',
                'cities.name as city_name', 'areas.name as area_name',
            ]);
    }
}
