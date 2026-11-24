<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\WidgetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AdminDashboardWidgetController extends Controller
{
    public function __invoke(Request $request, WidgetRegistry $registry, string $key): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canEnterSchoolhouse(), 403);

        $widget = $registry->find($key);
        abort_unless($widget, 403);
        $definition = $widget->definition();
        $platform = Gate::forUser($user)->allows('moderateListings', School::class);
        abort_unless($platform === ($definition['scope'] === 'platform'), 403);

        $branch = null;
        if (! $platform) {
            $id = $request->query('branch');
            $branches = $user->activeSchools();
            if (! is_string($id) || ! ctype_digit($id) || strlen($id) > 10) {
                abort_unless($branches->lazyById()->contains(
                    fn (School $school) => $registry->allows($definition, $user, false, $school),
                ), 403);

                return response()->json(['message' => 'A numeric branch ID of at most 10 digits is required.'], 422);
            }

            $branch = $branches->find($id);
        }
        abort_unless($registry->allows($definition, $user, $platform, $branch), 403);

        $context = new DashboardContext($branch);

        return response()->json([
            ...array_intersect_key($definition, array_flip(['key', 'title', 'type'])),
            'asOf' => $context->asOf->toIso8601String(),
            ...$widget->data($context),
        ])->header('Cache-Control', 'private, no-store');
    }
}
