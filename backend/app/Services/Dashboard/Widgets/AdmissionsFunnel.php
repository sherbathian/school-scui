<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class AdmissionsFunnel implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'branch.admissions_funnel', 'title' => 'Admissions funnel', 'type' => 'bar', 'size' => 'md',
            'scope' => 'branch', 'requiredModule' => 'inquiries', 'requiredPermission' => 'students.view',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $counts = DB::table('student_inquiries')->where('school_id', $context->branch->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $stages = [
            'new' => 'New',
            'contacted' => 'Contacted',
            'visit_scheduled' => 'Visit scheduled',
            'registered' => 'Registered',
            'rejected' => 'Rejected',
        ];
        $items = [];
        foreach ($stages as $status => $label) {
            $items[] = ['label' => $label, 'value' => (int) $counts->get($status, 0)];
        }

        return [
            'summary' => collect($items)->map(fn (array $item) => $item['label'].': '.$item['value'])->join(', '),
            'data' => ['items' => $items],
        ];
    }
}
