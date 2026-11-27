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
            ->firstOrFail(['settings.footer_text', 'settings.primary_color', 'settings.secondary_color', 'settings.font_family']);
        $pages = DB::table('school_pages')->where('school_id', $branch->id)->where('is_published', true);
        $current = (clone $pages)->where('slug', $page)->firstOrFail();
        $navigation = $pages->where('show_in_menu', true)->orderBy('sort_order')->orderBy('id')->get(['title', 'slug']);

        $content = json_decode($current->content ?? 'null', true);
        $blocks = is_array($content) ? ($content['blocks'] ?? $content) : [];

        return view('directory.school-site', [
            'branch' => $branch, 'site' => $site, 'page' => $current, 'siteSubdomain' => $subdomain,
            'navigation' => $navigation, 'document' => $content['document'] ?? null, 'blocks' => is_array($blocks) ? $blocks : [],
        ]);
    }
}
