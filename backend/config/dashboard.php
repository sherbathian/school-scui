<?php

use App\Services\Dashboard\Widgets\AttendanceToday;
use App\Services\Dashboard\Widgets\ClassStrength;
use App\Services\Dashboard\Widgets\ClaimsQueue;
use App\Services\Dashboard\Widgets\ListingsByStatus;
use App\Services\Dashboard\Widgets\StudentAttendanceMonth;

return [
    'widgets' => [
        'platform.listings_by_status' => ListingsByStatus::class,
        'platform.claims_queue' => ClaimsQueue::class,
        'branch.attendance_today' => AttendanceToday::class,
        'branch.class_strength' => ClassStrength::class,
        'student.attendance_month' => StudentAttendanceMonth::class,
    ],
    'layouts' => [
        'platform' => ['platform.listings_by_status', 'platform.claims_queue'],
        'admin' => ['branch.attendance_today', 'branch.class_strength'],
        'student' => ['student.attendance_month'],
    ],
];
