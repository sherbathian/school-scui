<?php

use App\Http\Controllers\Api\AdminListingController;
use App\Http\Controllers\Api\AdminLocationController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FamilyFeeReceiptController;
use App\Http\Controllers\Api\FeeDiscountController;
use App\Http\Controllers\Api\FeeGroupController;
use App\Http\Controllers\Api\FeeInvoiceController;
use App\Http\Controllers\Api\FeeMasterController;
use App\Http\Controllers\Api\FeeTypeController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\LegacyFeeTypeController;
use App\Http\Controllers\Api\SchoolRoleController;
use App\Http\Controllers\Api\SchoolSessionController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StaffLookupController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentInquiryController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolUserController;
use App\Http\Middleware\RequireSchoolPermission;
use App\Http\Middleware\ResolveSchoolPermissionTeam;
use App\Models\School;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:6,1');

Route::prefix('admin')->middleware(['auth:sanctum', 'can:moderateListings,'.School::class])->group(function () {
    Route::get('cities', [AdminLocationController::class, 'cities']);
    Route::post('cities', [AdminLocationController::class, 'storeCity']);
    Route::patch('cities/{city}', [AdminLocationController::class, 'updateCity'])->whereNumber('city');
    Route::delete('cities/{city}', [AdminLocationController::class, 'deleteCity'])->whereNumber('city');
    Route::get('areas', [AdminLocationController::class, 'allAreas']);
    Route::patch('areas/{area}', [AdminLocationController::class, 'updateArea'])->whereNumber('area');
    Route::delete('areas/{area}', [AdminLocationController::class, 'deleteArea'])->whereNumber('area');
    Route::get('cities/{city}/areas', [AdminLocationController::class, 'areas'])->whereNumber('city');
    Route::post('cities/{city}/areas', [AdminLocationController::class, 'storeArea'])->whereNumber('city');
    Route::apiResource('listings', AdminListingController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('listings/{listing}/verify', [AdminListingController::class, 'verify']);
    Route::post('listings/{listing}/publish', [AdminListingController::class, 'publish']);
    Route::post('listings/{listing}/suspend', [AdminListingController::class, 'suspend']);
});

Route::middleware(['auth:sanctum', ResolveSchoolPermissionTeam::class, RequireSchoolPermission::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/schools', [SchoolController::class, 'index']);
    Route::post('/schools', [SchoolController::class, 'store']);
    Route::get('/schools/{school}', [SchoolController::class, 'show']);
    Route::patch('/schools/{school}', [SchoolController::class, 'update']);
    Route::delete('/schools/{school}', [SchoolController::class, 'destroy']);
    Route::get('/schools/{school}/users', [SchoolUserController::class, 'index']);
    Route::post('/schools/{school}/users', [SchoolUserController::class, 'store']);
    Route::patch('/schools/{school}/users/{user}', [SchoolUserController::class, 'update']);
    Route::delete('/schools/{school}/users/{user}', [SchoolUserController::class, 'destroy']);
    Route::get('/schools/{school}/roles', [SchoolRoleController::class, 'index']);
    Route::post('/schools/{school}/roles', [SchoolRoleController::class, 'store']);
    Route::patch('/schools/{school}/roles/{role}', [SchoolRoleController::class, 'update']);
    Route::delete('/schools/{school}/roles/{role}', [SchoolRoleController::class, 'destroy']);
    Route::apiResource('schools.grades', GradeController::class);
    Route::apiResource('schools.sessions', SchoolSessionController::class);
    Route::apiResource('schools.fee-discounts', FeeDiscountController::class);
    Route::apiResource('schools.fee-groups', FeeGroupController::class);
    Route::get('schools/{school}/fee-masters/{feeMaster}/assignments', [FeeMasterController::class, 'assignments']);
    Route::post('schools/{school}/fee-masters/{feeMaster}/assignments', [FeeMasterController::class, 'assignStudents']);
    Route::apiResource('schools.fee-masters', FeeMasterController::class);
    Route::get('/schools/{school}/expense-heads', [ExpenseController::class, 'heads']);
    Route::post('/schools/{school}/expense-heads', [ExpenseController::class, 'storeHead']);
    Route::patch('/schools/{school}/expense-heads/{head}', [ExpenseController::class, 'updateHead']);
    Route::delete('/schools/{school}/expense-heads/{head}', [ExpenseController::class, 'destroyHead']);
    Route::get('/schools/{school}/expenses', [ExpenseController::class, 'index']);
    Route::post('/schools/{school}/expenses', [ExpenseController::class, 'store']);
    Route::patch('/schools/{school}/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/schools/{school}/expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::get('/schools/{school}/staff-lookups/{lookupType}', [StaffLookupController::class, 'index']);
    Route::post('/schools/{school}/staff-lookups/{lookupType}', [StaffLookupController::class, 'store']);
    Route::patch('/schools/{school}/staff-lookups/{lookupType}/{lookup}', [StaffLookupController::class, 'update']);
    Route::delete('/schools/{school}/staff-lookups/{lookupType}/{lookup}', [StaffLookupController::class, 'destroy']);
    Route::get('/schools/{school}/staff', [StaffController::class, 'index']);
    Route::post('/schools/{school}/staff', [StaffController::class, 'store']);
    Route::get('/schools/{school}/staff/{staff}', [StaffController::class, 'show']);
    Route::patch('/schools/{school}/staff/{staff}', [StaffController::class, 'update']);
    Route::delete('/schools/{school}/staff/{staff}', [StaffController::class, 'destroy']);
    Route::get('/schools/{school}/staff/{staff}/leave-balances', [StaffController::class, 'leaveBalances']);
    Route::post('/schools/{school}/staff/{staff}/leave-balances', [StaffController::class, 'storeLeaveBalance']);
    Route::patch('/schools/{school}/staff/{staff}/leave-balances/{balance}', [StaffController::class, 'updateLeaveBalance']);
    Route::delete('/schools/{school}/staff/{staff}/leave-balances/{balance}', [StaffController::class, 'destroyLeaveBalance']);
    Route::put('/schools/{school}/grades/{grade}/sections', [GradeController::class, 'syncSections']);
    Route::apiResource('schools.sections', SectionController::class);
    Route::apiResource('schools.subjects', SubjectController::class);
    Route::apiResource('schools.students', StudentController::class);
    Route::get('/schools/{school}/student-inquiries', [StudentInquiryController::class, 'index']);
    Route::post('/schools/{school}/student-inquiries', [StudentInquiryController::class, 'store']);
    Route::patch('/schools/{school}/student-inquiries/{inquiry}', [StudentInquiryController::class, 'update']);
    Route::delete('/schools/{school}/student-inquiries/{inquiry}', [StudentInquiryController::class, 'destroy']);
    Route::post('/schools/{school}/students/{student}/withdraw', [StudentController::class, 'withdraw']);
    Route::post('/schools/{school}/students/{student}/rejoin', [StudentController::class, 'rejoin']);
    Route::get('/schools/{school}/guardians', [GuardianController::class, 'index']);
    Route::post('/schools/{school}/guardians', [GuardianController::class, 'store']);
    Route::get('/schools/{school}/guardians/{guardian}', [GuardianController::class, 'show']);
    Route::patch('/schools/{school}/guardians/{guardian}', [GuardianController::class, 'update']);
    Route::delete('/schools/{school}/guardians/{guardian}', [GuardianController::class, 'destroy']);
    Route::post('/schools/{school}/students/{student}/enrollments', [StudentController::class, 'enroll']);
    Route::get('/schools/{school}/attendance', [AttendanceController::class, 'index']);
    Route::put('/schools/{school}/attendance', [AttendanceController::class, 'update']);
    Route::get('/schools/{school}/fee-types', [FeeTypeController::class, 'index']);
    Route::post('/schools/{school}/fee-types', [FeeTypeController::class, 'store']);
    Route::get('/schools/{school}/fee-types/{feeType}', [FeeTypeController::class, 'show']);
    Route::patch('/schools/{school}/fee-types/{feeType}', [FeeTypeController::class, 'update']);
    Route::delete('/schools/{school}/fee-types/{feeType}', [FeeTypeController::class, 'destroy']);
    Route::get('/schools/{school}/legacy-fee-types', [LegacyFeeTypeController::class, 'index']);
    Route::post('/schools/{school}/legacy-fee-types', [LegacyFeeTypeController::class, 'store']);
    Route::patch('/schools/{school}/legacy-fee-types/{feeType}', [LegacyFeeTypeController::class, 'update']);
    Route::delete('/schools/{school}/legacy-fee-types/{feeType}', [LegacyFeeTypeController::class, 'destroy']);
    Route::get('/schools/{school}/fee-invoices', [FeeInvoiceController::class, 'index']);
    Route::post('/schools/{school}/fee-invoices', [FeeInvoiceController::class, 'store']);
    Route::delete('/schools/{school}/fee-invoices/{feeInvoice}', [FeeInvoiceController::class, 'destroy']);
    Route::post('/schools/{school}/fee-invoices/{feeInvoice}/payments', [FeeInvoiceController::class, 'recordPayment']);
    Route::get('/schools/{school}/family-fee-receipts', [FamilyFeeReceiptController::class, 'index']);
    Route::post('/schools/{school}/family-fee-receipts', [FamilyFeeReceiptController::class, 'store']);
});

Route::get('/schools/{school}/students/{student}/media/{kind}', [StudentController::class, 'media'])
    ->where('kind', 'photo|document')
    ->middleware('signed:relative')
    ->name('students.media');

Route::get('/schools/{school}/guardians/{guardian}/media/{kind}', [GuardianController::class, 'media'])
    ->where('kind', 'father|mother|guardian')
    ->middleware('signed:relative')
    ->name('guardians.media');

Route::get('/schools/{school}/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])
    ->middleware('signed:relative')
    ->name('expenses.attachment');
