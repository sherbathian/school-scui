<?php

use App\Services\Dashboard\Widgets\AdmissionsFunnel;
use App\Services\Dashboard\Widgets\AttendanceToday;
use App\Services\Dashboard\Widgets\AttendanceTrend;
use App\Services\Dashboard\Widgets\ClassStrength;
use App\Services\Dashboard\Widgets\ClaimsQueue;
use App\Services\Dashboard\Widgets\ListingsByStatus;
use App\Services\Dashboard\Widgets\NewStudentsMonth;
use App\Services\Dashboard\Widgets\StudentAttendanceMonth;
use App\Services\Dashboard\Widgets\UnmarkedClasses;

return [
    'widgets' => [
        'platform.listings_by_status' => ListingsByStatus::class,
        'platform.claims_queue' => ClaimsQueue::class,
        'branch.attendance_today' => AttendanceToday::class,
        'branch.class_strength' => ClassStrength::class,
        'branch.attendance_trend' => AttendanceTrend::class,
        'branch.admissions_funnel' => AdmissionsFunnel::class,
        'branch.new_students_month' => NewStudentsMonth::class,
        'branch.unmarked_classes' => UnmarkedClasses::class,
        'student.attendance_month' => StudentAttendanceMonth::class,
    ],
    'layouts' => [
        'platform' => ['platform.listings_by_status', 'platform.claims_queue'],
        'admin' => [
            'branch.attendance_today', 'branch.class_strength', 'branch.attendance_trend',
            'branch.admissions_funnel', 'branch.new_students_month', 'branch.unmarked_classes',
        ],
        'teacher' => ['branch.unmarked_classes'],
        'student' => ['student.attendance_month'],
    ],
];
