<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminLocationController extends Controller
{
    public function cities(): JsonResponse
    {
        return response()->json(['data' => DB::table('cities')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'province'])]);
    }

    public function areas(int $city): JsonResponse
    {
        $this->requireCity($city);

        return response()->json(['data' => DB::table('areas')->where('city_id', $city)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'slug', 'area_type'])]);
    }

    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->createLocation('cities', $data, 100);
    }

    public function storeArea(Request $request, int $city): JsonResponse
    {
        $this->requireCity($city);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'area_type' => ['sometimes', Rule::in(['sector', 'locality', 'housing_scheme'])],
        ]);

        return $this->createLocation('areas', [...$data, 'city_id' => $city], 120);
    }

    private function requireCity(int $city): void
    {
        abort_unless(DB::table('cities')->where('id', $city)->where('is_active', true)->exists(), 404);
    }

    private function createLocation(string $table, array $data, int $slugLimit): JsonResponse
    {
        $slug = Str::slug($data['name']);
        validator(['name' => $slug], ['name' => ['required', "max:{$slugLimit}"]])->validate();

        try {
            $id = DB::table($table)->insertGetId([...$data, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => ['This location already exists.']]);
        }

        return response()->json(['data' => ['id' => $id, ...$data, 'slug' => $slug]], 201);
    }
}
