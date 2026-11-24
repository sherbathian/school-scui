<?php

use App\Services\Dashboard\Widgets\AdmissionsFunnel;
use App\Services\Dashboard\Widgets\AttendanceToday;
use App\Services\Dashboard\Widgets\AttendanceTrend;
use App\Services\Dashboard\Widgets\ClassStrength;
use App\Services\Dashboard\Widgets\ClaimsQueue;
use App\Services\Dashboard\Widgets\ListingsByCity;
use App\Services\Dashboard\Widgets\ListingsByStatus;
use App\Services\Dashboard\Widgets\NewDemoRequests;
use App\Services\Dashboard\Widgets\NewStudentsMonth;
use App\Services\Dashboard\Widgets\StudentAttendanceMonth;
use App\Services\Dashboard\Widgets\StudentAttendanceTrend;
use App\Services\Dashboard\Widgets\StudentRecentAbsences;
use App\Services\Dashboard\Widgets\SubscriptionWatch;
use App\Services\Dashboard\Widgets\UnmarkedClasses;
use App\Services\Dashboard\Widgets\VerificationQueue;

return [
    'widgets' => [
        'platform.listings_by_status' => ListingsByStatus::class,
        'platform.claims_queue' => ClaimsQueue::class,
        'platform.verification_queue' => VerificationQueue::class,
        'platform.listings_by_city' => ListingsByCity::class,
        'platform.new_demo_requests' => NewDemoRequests::class,
        'platform.subscription_watch' => SubscriptionWatch::class,
        'branch.attendance_today' => AttendanceToday::class,
        'branch.class_strength' => ClassStrength::class,
        'branch.attendance_trend' => AttendanceTrend::class,
        'branch.admissions_funnel' => AdmissionsFunnel::class,
        'branch.new_students_month' => NewStudentsMonth::class,
        'branch.unmarked_classes' => UnmarkedClasses::class,
        'student.attendance_month' => StudentAttendanceMonth::class,
        'student.attendance_trend' => StudentAttendanceTrend::class,
        'student.recent_absences' => StudentRecentAbsences::class,
    ],
    'layouts' => [
        'platform' => [
            'platform.listings_by_status', 'platform.claims_queue', 'platform.verification_queue',
            'platform.listings_by_city', 'platform.new_demo_requests', 'platform.subscription_watch',
        ],
        'admin' => [
            'branch.attendance_today', 'branch.class_strength', 'branch.attendance_trend',
            'branch.admissions_funnel', 'branch.new_students_month', 'branch.unmarked_classes',
        ],
        'teacher' => ['branch.unmarked_classes'],
        'student' => ['student.attendance_month', 'student.attendance_trend', 'student.recent_absences'],
    ],
];
