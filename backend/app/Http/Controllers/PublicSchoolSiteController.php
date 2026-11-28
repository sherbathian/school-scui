<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\BranchModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicSchoolSiteController extends Controller
{
    public const RESERVED_SUBDOMAINS = ['admin', 'api', 'app', 'mail', 'school', 'www'];

    public function forHostname(Request $request, BranchModules $modules, string $subdomain, string $page = 'home'): View
    {
        abort_unless(in_array($request->getMethod(), ['GET', 'HEAD'], true) && preg_match('/^[a-z0-9_-]+$/D', $page), 404);

        $schoolId = DB::table('school_site_settings')->where('subdomain', $subdomain)->value('school_id');
        $branch = School::publicDirectory()->whereKey($schoolId)->firstOrFail(['id', 'name', 'slug']);

        return $this->renderSite($modules, $branch, $page, $subdomain);
    }

    public function __invoke(BranchModules $modules, string $school, string $page = 'home'): View
    {
        $branch = School::publicDirectory()->where('slug', $school)->firstOrFail(['id', 'name', 'slug']);
        return $this->renderSite($modules, $branch, $page);
    }

    private function renderSite(BranchModules $modules, School $branch, string $page, ?string $subdomain = null): View
    {
        $module = $modules->forBranch($branch->id)->firstWhere('slug', 'website');
        abort_unless($module['enabled'] ?? false, 404);

        $site = DB::table('school_site_settings as settings')
            ->join('site_themes as theme', 'theme.id', '=', 'settings.site_theme_id')
            ->where('settings.school_id', $branch->id)->where('settings.site_enabled', true)
            ->where('theme.is_active', true)
            ->when($subdomain !== null, fn ($query) => $query->where('settings.subdomain', $subdomain))
            ->firstOrFail(['settings.footer_text', 'settings.primary_color', 'settings.secondary_color', 'settings.font_family', 'theme.slug as theme_slug']);
        $pages = DB::table('school_pages')->where('school_id', $branch->id)->where('is_published', true);
        $current = (clone $pages)->where('slug', $page)->firstOrFail();
        $navigation = $pages->where('show_in_menu', true)->orderBy('sort_order')->orderBy('id')->get(['title', 'slug']);

        $content = json_decode($current->content ?? 'null', true);
        $blocks = is_array($content) ? ($content['blocks'] ?? $content) : [];

        $photos = $site->theme_slug === 'ifs' && $page === 'home'
            ? DB::table('school_media')->where('school_id', $branch->id)->where('kind', 'photo')
                ->where('is_approved', true)->whereNotNull('path')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'path', 'caption', 'alt_text'])
                ->filter(fn ($photo) => preg_match('~\Alisting-media/[A-Za-z0-9]+\.(?:jpe?g|png|webp)\z~', $photo->path))
                ->take(4)->values()
            : collect();

        return view('directory.school-site', [
            'branch' => $branch, 'site' => $site, 'page' => $current, 'siteSubdomain' => $subdomain,
            'navigation' => $navigation, 'photos' => $photos, 'document' => $content['document'] ?? null, 'blocks' => is_array($blocks) ? $blocks : [],
        ]);
    }
}
