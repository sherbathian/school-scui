<?php

namespace App\Services\Dashboard;

use App\Models\School;
use App\Models\User;
use App\Services\BranchModules;
use Illuminate\Support\Facades\Gate;

final readonly class WidgetRegistry
{
    public function __construct(private BranchModules $modules) {}

    public function find(string $key): ?Widget
    {
        $class = config('dashboard.widgets')[$key] ?? null;

        return $class === null ? null : app($class);
    }

    public function layout(User $user, bool $platform, ?School $branch): array
    {
        $descriptors = [];
        foreach ($this->keysFor($user, $platform, $branch) as $key) {
            $definition = $this->find($key)->definition();
            if ($this->allows($definition, $user, $platform, $branch)) {
                $descriptor = array_intersect_key($definition, array_flip(['key', 'title', 'type', 'size']));
                $parameters = ['key' => $key];
                if (! $platform) {
                    $parameters['branch'] = $branch->id;
                }
                $descriptors[] = $descriptor + ['endpoint' => route('admin.dashboard.widget', $parameters, false)];
            }
        }

        return $descriptors;
    }

    public function allows(array $definition, User $user, bool $platform, ?School $branch): bool
    {
        if (! in_array($definition['key'], $this->keysFor($user, $platform, $branch), true)) {
            return false;
        }

        $permission = $definition['requiredPermission'];
        if ($definition['scope'] === 'platform') {
            return Gate::forUser($user)->allows($permission, School::class);
        }

        return $branch !== null
            && ($permission === 'student.self'
                ? $user->schoolAccessRole($branch) === 'student'
                : $user->hasSchoolPermission($branch, $permission))
            && $this->modules->forBranch($branch->id)->contains(
                fn (array $module) => $module['slug'] === $definition['requiredModule'] && $module['enabled'],
            );
    }

    private function keysFor(User $user, bool $platform, ?School $branch): array
    {
        $role = $platform ? 'platform' : ($branch ? $user->schoolAccessRole($branch) : null);
        $role = $role === 'school_admin' ? 'admin' : $role;

        return config('dashboard.layouts')[$role] ?? [];
    }
}
