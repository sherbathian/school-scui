<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\BranchModules;
use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;

final readonly class ModuleAdoption implements Widget
{
    public function __construct(private BranchModules $modules) {}

    public function definition(): array
    {
        return [
            'key' => 'platform.module_adoption', 'title' => 'Module adoption', 'type' => 'bar',
            'size' => 'lg', 'scope' => 'platform', 'requiredModule' => null,
            'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $items = $this->modules->adoption($context->asOf);

        return [
            'summary' => $items->map(fn (array $item) => $item['label'].': '.$item['value'])->join(', '),
            'data' => ['items' => $items->all()],
        ];
    }
}
