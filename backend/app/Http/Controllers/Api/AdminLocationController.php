<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdminLocationController extends Controller
{
    public function cities(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('cities')->when(! $request->boolean('all'), fn ($query) => $query->where('is_active', true))
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

    public function allAreas(): JsonResponse
    {
        return response()->json(['data' => DB::table('areas')->join('cities', 'cities.id', '=', 'areas.city_id')
            ->orderBy('cities.name')->orderBy('areas.name')
            ->get(['areas.id', 'areas.name', 'areas.slug', 'areas.area_type', 'areas.city_id', 'cities.name as city_name'])]);
    }

    public function updateCity(Request $request, int $city): JsonResponse
    {
        return $this->updateLocation('cities', $city, $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('cities')->ignore($city)],
            'province' => ['nullable', 'string', 'max:100'],
        ]));
    }

    public function updateArea(Request $request, int $area): JsonResponse
    {
        $record = DB::table('areas')->where('id', $area)->firstOrFail(['*']);

        return $this->updateLocation('areas', $area, $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('areas')->where('city_id', $record->city_id)->ignore($area)],
            'area_type' => ['required', Rule::in(['sector', 'locality', 'housing_scheme'])],
        ]));
    }

    public function deleteCity(int $city): Response
    {
        return $this->deleteLocation('cities', $city);
    }

    public function deleteArea(int $area): Response
    {
        return $this->deleteLocation('areas', $area);
    }

    private function updateLocation(string $table, int $id, array $data): JsonResponse
    {
        $query = DB::table($table)->where('id', $id);
        abort_unless($query->exists(), 404);
        $query->update([...$data, 'updated_at' => now()]);

        return response()->json(['data' => $query->firstOrFail(['*'])]);
    }

    private function deleteLocation(string $table, int $id): Response
    {
        try {
            abort_unless(DB::table($table)->where('id', $id)->delete(), 404);
        } catch (QueryException $exception) {
            if (! in_array($exception->errorInfo[0] ?? null, ['23000', '23503'], true)) {
                throw $exception;
            }

            abort(409, 'This location is still used by a listing or area. Remove those references first.');
        }

        return response()->noContent();
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
