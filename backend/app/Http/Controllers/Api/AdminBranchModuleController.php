<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\BranchModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBranchModuleController extends Controller
{
    public function index(School $branch, BranchModules $modules): JsonResponse
    {
        return response()->json(['data' => $modules->forBranch($branch->id)]);
    }

    public function update(Request $request, School $branch, string $module, BranchModules $modules): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        return DB::transaction(function () use ($request, $branch, $module, $modules, $data): JsonResponse {
            School::query()->lockForUpdate()->findOrFail($branch->id);
            $catalogue = $modules->forBranch($branch->id)->keyBy('slug');
            $entry = $catalogue->get($module);
            abort_unless($entry, 404);
            abort_if($entry['core'] || ! $entry['available'], 409, 'This module cannot be changed.');

            if ($data['enabled']) {
                abort_unless($entry['entitled'], 409, 'The organisation is not entitled to this module.');
                foreach ($entry['requires'] as $requirement) {
                    abort_unless($catalogue[$requirement]['enabled'], 409, "Enable {$catalogue[$requirement]['name']} first.");
                }
            }

            DB::table('school_modules')->upsert([
                'school_id' => $branch->id, 'module_id' => $entry['id'], 'is_enabled' => $data['enabled'],
                'enabled_by_user_id' => $request->user()->id, 'enabled_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ], ['school_id', 'module_id'], ['is_enabled', 'enabled_by_user_id', 'enabled_at', 'updated_at']);

            return response()->json(['data' => $modules->forBranch($branch->id)]);
        }, 3);
    }

    public function reset(School $branch, string $module, BranchModules $modules): JsonResponse
    {
        return DB::transaction(function () use ($branch, $module, $modules): JsonResponse {
            School::query()->lockForUpdate()->findOrFail($branch->id);
            $entry = $modules->forBranch($branch->id)->firstWhere('slug', $module);
            abort_unless($entry, 404);
            abort_if($entry['core'], 409, 'The core module is always enabled.');
            DB::table('school_modules')->where('school_id', $branch->id)->where('module_id', $entry['id'])->delete();

            return response()->json(['data' => $modules->forBranch($branch->id)]);
        }, 3);
    }
}
