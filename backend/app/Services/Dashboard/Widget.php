<?php

namespace App\Services\Dashboard;

interface Widget
{
    /** @return array{key: string, title: string, type: string, size: string, scope: string, requiredModule: ?string, requiredPermission: string} */
    public function definition(): array;

    /** @return array{summary: string, data: array} */
    public function data(DashboardContext $context): array;
}
