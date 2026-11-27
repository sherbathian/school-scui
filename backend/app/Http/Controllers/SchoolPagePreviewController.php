<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SchoolPagePreviewController extends Controller
{
    public function __invoke(Request $request, string $school, string $page = 'home'): Response|RedirectResponse
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        if (! $request->user()) {
            return redirect()->guest(route('admin.login'));
        }
        Gate::authorize('moderateListings', School::class);

        $branch = School::query()->where('slug', $school)->firstOrFail(['id', 'name', 'slug']);
        $pages = DB::table('school_pages')->where('school_id', $branch->id);
        $current = (clone $pages)->where('slug', $page)->firstOrFail();
        $navigation = $pages->where('show_in_menu', true)->orderBy('sort_order')->orderBy('id')->get(['title', 'slug']);
        $decoded = json_decode($current->content ?? 'null', true);
        $blocks = is_array($decoded) ? ($decoded['blocks'] ?? $decoded) : [];

        return response()->view('school-page-preview', [
            'branch' => $branch, 'page' => $current, 'navigation' => $navigation,
            'document' => $decoded['document'] ?? null, 'blocks' => is_array($blocks) ? $blocks : [],
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
