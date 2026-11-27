<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\BranchModules;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicSchoolSiteController extends Controller
{
    public function __invoke(BranchModules $modules, string $school, string $page = 'home'): View
    {
        $branch = School::publicDirectory()->where('slug', $school)->firstOrFail(['id', 'name', 'slug']);
        $module = $modules->forBranch($branch->id)->firstWhere('slug', 'website');
        abort_unless($module['enabled'] ?? false, 404);

        $site = DB::table('school_site_settings as settings')
            ->join('site_themes as theme', 'theme.id', '=', 'settings.site_theme_id')
            ->where('settings.school_id', $branch->id)->where('settings.site_enabled', true)
            ->where('theme.is_active', true)->firstOrFail(['settings.footer_text', 'settings.primary_color', 'settings.secondary_color', 'settings.font_family']);
        $pages = DB::table('school_pages')->where('school_id', $branch->id)->where('is_published', true);
        $current = (clone $pages)->where('slug', $page)->firstOrFail();
        $navigation = $pages->where('show_in_menu', true)->orderBy('sort_order')->orderBy('id')->get(['title', 'slug']);

        $content = json_decode($current->content ?? 'null', true);
        $blocks = is_array($content) ? ($content['blocks'] ?? $content) : [];

        return view('directory.school-site', [
            'branch' => $branch, 'site' => $site, 'page' => $current,
            'navigation' => $navigation, 'document' => $content['document'] ?? null, 'blocks' => is_array($blocks) ? $blocks : [],
        ]);
    }
}
