# Repository map (generated 20 Nov 2026 from `main` at c6ebc42)

Read this INSTEAD of exploring the repository. Update the relevant section when you add files.

## Commands (run from `backend/`)

| Purpose | Command |
|---|---|
| One test class or method (fast loop) | `vendor/bin/phpunit --filter ClassOrMethodName` |
| Whole suite on SQLite in memory (default in phpunit.xml) | `vendor/bin/phpunit` |
| Whole suite on MySQL (disposable database only) | `DB_CONNECTION=mysql DB_DATABASE=<disposable> vendor/bin/phpunit` |
| Frontend build (only when JS/CSS changed) | `npm run build` |
| Local migrate | `php artisan migrate` |

CI (`.github/workflows/ci.yml`) runs PHPUnit on SQLite and MySQL 8.4 plus the frontend build. Rely on CI for the full matrix; run only targeted tests while working.

## Routes

`routes/web.php` and `routes/api.php` (route lines only):

```
# web.php
Route::get('/', [PublicDirectoryController::class, 'home'])->name('directory.home');
Route::get('/directory', [PublicDirectoryController::class, 'index'])->name('directory.index');
Route::get('/{category}/{citySlug?}/{areaSlug?}', [PublicDirectoryController::class, 'index'])
Route::get('/institutions/{slug}', [PublicDirectoryController::class, 'show'])->name('directory.profile');
Route::post('/institutions/{slug}/enquire', [PublicDirectoryController::class, 'enquire'])
Route::get('/institutions/{slug}/logo', [PublicDirectoryController::class, 'logo'])->name('directory.logo');
Route::get('/institutions/{slug}/media/{media}', [PublicDirectoryController::class, 'media'])
Route::get('/{collection}', [PublicDirectoryController::class, 'collection'])
Route::post('/{collection}/{slug}', [PublicDirectoryController::class, 'toggle'])
Route::get('/{resource}', [PublicResourcesController::class, 'index'])
Route::get('/{resource}/{record}', [PublicResourcesController::class, 'show'])
Route::get('/study-resources/{record}/download', [PublicResourcesController::class, 'download'])
Route::post('/books/{book}/enquire', [PublicResourcesController::class, 'enquireBook'])
Route::get('/short-courses/{record?}', fn (?string $record = null) => redirect(
Route::redirect('/saved-schools', '/saved');
Route::redirect('/guides', '/parent-guides');
Route::redirect('/about-taleem-finder', '/about');
Route::redirect('/find-my-school', '/directory');
Route::view('/for-schools', 'directory.for-schools')->name('directory.for-schools');
Route::get('/{topic}', [PublicResourcesController::class, 'policy'])
Route::get('/sitemap.xml', [PublicDirectoryController::class, 'sitemap'])->name('directory.sitemap');
Route::get('/local/school-preview/{school}/{page?}', SchoolPagePreviewController::class)
Route::view('/about', 'directory.about')->name('directory.about');
Route::view('/contact', 'directory.contact')->name('directory.contact');
Route::get('/signup', [InstituteClaimController::class, 'registration'])->name('directory.sign-up');
Route::post('/signup', [InstituteClaimController::class, 'register'])->middleware('throttle:6,1')->name('directory.sign-up.submit');
Route::get('/login', [InstituteClaimController::class, 'signInPage'])->name('directory.sign-in');
Route::post('/login', [InstituteClaimController::class, 'login'])->middleware('throttle:6,1')->name('directory.sign-in.submit');
Route::get('/dashboard', function (Request $request) {
Route::redirect('/add-school', '/for-schools');
Route::get('/institute/register', [InstituteClaimController::class, 'registration'])->name('institute.register');
Route::post('/institute/register', [InstituteClaimController::class, 'register'])->middleware('throttle:6,1');
Route::get('/institute/sign-in', [InstituteClaimController::class, 'signInPage'])->name('login');
Route::post('/institute/sign-in', [InstituteClaimController::class, 'login'])->middleware('throttle:6,1');
Route::get('/institute/new', [InstituteClaimController::class, 'submissions'])->middleware('auth')->name('institute.new');
Route::post('/institute/new', [InstituteClaimController::class, 'submit'])->middleware(['auth', 'throttle:6,1']);
Route::post('/institute/sign-out', [InstituteClaimController::class, 'logout'])->middleware('auth')->name('institute.logout');
Route::get('/institutions/{slug}/claim', [InstituteClaimController::class, 'show'])->name('institute.claim');
Route::post('/institutions/{slug}/claim', [InstituteClaimController::class, 'store'])->middleware(['auth', 'throttle:10,1']);
Route::get('/admin/claims', function (Request $request) {
Route::get('/admin/branches/{branch}/modules', function (Request $request, int $branch) {
$adminPage = function (Request $request, ?int $listing = null) {
Route::get('/admin/dashboard/{branch?}', AdminDashboardController::class)
Route::get('/admin/login', $adminPage)->name('admin.login');
Route::get('/admin/listings', $adminPage)->name('admin.listings');
Route::get('/admin/listings/create', $adminPage)->name('admin.listings.create');
Route::get('/admin/listings/{listing}', $adminPage)->whereNumber('listing')->name('admin.listings.show');
Route::get('/admin/listings/{listing}/edit', $adminPage)->whereNumber('listing')->name('admin.listings.edit');
Route::get('/admin/{kind}', function (Request $request, string $kind) {
Route::get('/admin/directory/{resource}', function (Request $request, string $resource) {
Route::get('/admin/platform/{resource}', function (Request $request, string $resource) {
Route::get('/admin/services/{resource}', function (Request $request, string $resource) {
Route::get('/admin/community/{resource}', function (Request $request, string $resource) {
Route::get('/admin/{screen}', $accessPage)->whereIn('screen', ['users', 'permissions', 'roles', 'members', 'operations']);
# api.php
Route::get('/health', HealthController::class);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:6,1');
Route::prefix('admin')->middleware(['auth:sanctum', RestrictPersonalAccessToken::class, 'can:moderateListings,'.School::class])->group(function () {
Route::get('community/{resource}/{record}/download', [AdminCommunityController::class, 'download'])
Route::prefix('community')->whereIn('resource', array_keys(AdminCommunityController::RESOURCES))->group(function () {
Route::get('{resource}', [AdminCommunityController::class, 'index']);
Route::get('{resource}/options', [AdminCommunityController::class, 'options']);
Route::post('{resource}', [AdminCommunityController::class, 'store']);
Route::patch('{resource}/{record}', [AdminCommunityController::class, 'update']);
Route::delete('{resource}/{record}', [AdminCommunityController::class, 'destroy']);
Route::prefix('services')->whereIn('resource', array_keys(AdminServicesController::RESOURCES))->group(function () {
Route::get('{resource}', [AdminServicesController::class, 'index']);
Route::get('{resource}/options', [AdminServicesController::class, 'options']);
Route::post('{resource}', [AdminServicesController::class, 'store']);
Route::patch('{resource}/{record}', [AdminServicesController::class, 'update']);
Route::delete('{resource}/{record}', [AdminServicesController::class, 'destroy']);
Route::prefix('platform')->whereIn('resource', array_keys(AdminPlatformController::RESOURCES))->group(function () {
Route::get('{resource}', [AdminPlatformController::class, 'index']);
Route::get('{resource}/options', [AdminPlatformController::class, 'options']);
Route::post('{resource}', [AdminPlatformController::class, 'store']);
Route::patch('{resource}/{record}', [AdminPlatformController::class, 'update']);
Route::delete('{resource}/{record}', [AdminPlatformController::class, 'destroy']);
Route::prefix('directory')->whereIn('resource', array_keys(AdminDirectoryController::RESOURCES))->group(function () {
Route::get('{resource}', [AdminDirectoryController::class, 'index']);
Route::get('{resource}/options', [AdminDirectoryController::class, 'options']);
Route::post('{resource}', [AdminDirectoryController::class, 'store']);
Route::patch('{resource}/{record}', [AdminDirectoryController::class, 'update'])->whereNumber('record');
Route::delete('{resource}/{record}', [AdminDirectoryController::class, 'destroy'])->whereNumber('record');
Route::get('directory/school-media/{record}/download', [AdminDirectoryController::class, 'downloadMedia'])->whereNumber('record');
Route::put('directory/school-groups/{group}/branches/{branch}', [AdminDirectoryController::class, 'attachBranch'])
Route::get('listings/{branch}/assignments/{assignment}', [AdminDirectoryController::class, 'assignments'])
Route::put('listings/{branch}/assignments/{assignment}', [AdminDirectoryController::class, 'syncAssignments'])
Route::get('branches/{branch}/modules', [AdminBranchModuleController::class, 'index']);
Route::patch('branches/{branch}/modules/{module}', [AdminBranchModuleController::class, 'update']);
Route::delete('branches/{branch}/modules/{module}', [AdminBranchModuleController::class, 'reset']);
Route::get('permissions', [UserController::class, 'permissionCatalogue']);
Route::get('claims', [InstituteClaimController::class, 'queue']);
Route::patch('claims/{claim}', [InstituteClaimController::class, 'review'])->whereNumber('claim');
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
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/notifications', [AdminPlatformController::class, 'inbox']);
Route::patch('/notifications/{record}', [AdminPlatformController::class, 'updateInbox']);
Route::delete('/notifications/{record}', [AdminPlatformController::class, 'deleteInbox']);
Route::get('/me', [AuthController::class, 'me'])->name('api.me');
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
Route::get('/schools/{school}/roles/{role}/assigned-users', [SchoolRoleController::class, 'assignedUsers'])->whereNumber('role');
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
Route::get('/schools/{school}/fee-years', [FeeInvoiceController::class, 'years']);
Route::get('/schools/{school}/fee-students', [FeeInvoiceController::class, 'students']);
Route::get('/schools/{school}/fee-invoices', [FeeInvoiceController::class, 'index']);
Route::post('/schools/{school}/fee-invoices', [FeeInvoiceController::class, 'store']);
Route::delete('/schools/{school}/fee-invoices/{feeInvoice}', [FeeInvoiceController::class, 'destroy']);
Route::post('/schools/{school}/fee-invoices/{feeInvoice}/payments', [FeeInvoiceController::class, 'recordPayment']);
Route::get('/schools/{school}/family-fee-receipts', [FamilyFeeReceiptController::class, 'index']);
Route::post('/schools/{school}/family-fee-receipts', [FamilyFeeReceiptController::class, 'store']);
Route::get('/schools/{school}/students/{student}/media/{kind}', [StudentController::class, 'media'])
->name('students.media');
Route::get('/schools/{school}/guardians/{guardian}/media/{kind}', [GuardianController::class, 'media'])
->name('guardians.media');
Route::get('/schools/{school}/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])
->name('expenses.attachment');
```

## Controllers

- `app/Http/Controllers/AdminDashboardController.php`
- `app/Http/Controllers/Api/AdminBranchModuleController.php`
- `app/Http/Controllers/Api/AdminCommunityController.php`
- `app/Http/Controllers/Api/AdminDirectoryController.php`
- `app/Http/Controllers/Api/AdminListingController.php`
- `app/Http/Controllers/Api/AdminLocationController.php`
- `app/Http/Controllers/Api/AdminPlatformController.php`
- `app/Http/Controllers/Api/AdminServicesController.php`
- `app/Http/Controllers/Api/AttendanceController.php`
- `app/Http/Controllers/Api/ExpenseController.php`
- `app/Http/Controllers/Api/FamilyFeeReceiptController.php`
- `app/Http/Controllers/Api/FeeDiscountController.php`
- `app/Http/Controllers/Api/FeeGroupController.php`
- `app/Http/Controllers/Api/FeeInvoiceController.php`
- `app/Http/Controllers/Api/FeeMasterController.php`
- `app/Http/Controllers/Api/FeeTypeController.php`
- `app/Http/Controllers/Api/GradeController.php`
- `app/Http/Controllers/Api/GuardianController.php`
- `app/Http/Controllers/Api/LegacyFeeTypeController.php`
- `app/Http/Controllers/Api/SchoolRoleController.php`
- `app/Http/Controllers/Api/SchoolSessionController.php`
- `app/Http/Controllers/Api/SectionController.php`
- `app/Http/Controllers/Api/StaffController.php`
- `app/Http/Controllers/Api/StaffLookupController.php`
- `app/Http/Controllers/Api/StudentController.php`
- `app/Http/Controllers/Api/StudentInquiryController.php`
- `app/Http/Controllers/Api/SubjectController.php`
- `app/Http/Controllers/Api/UserController.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/Controller.php`
- `app/Http/Controllers/HealthController.php`
- `app/Http/Controllers/InstituteClaimController.php`
- `app/Http/Controllers/PublicDirectoryController.php`
- `app/Http/Controllers/PublicResourcesController.php`
- `app/Http/Controllers/SchoolController.php`
- `app/Http/Controllers/SchoolPagePreviewController.php`
- `app/Http/Controllers/SchoolUserController.php`

## Models

- `app/Models/AttendanceRecord.php`
- `app/Models/Department.php`
- `app/Models/Designation.php`
- `app/Models/Enrollment.php`
- `app/Models/Expense.php`
- `app/Models/ExpenseHead.php`
- `app/Models/FamilyFeeReceipt.php`
- `app/Models/FeeDiscount.php`
- `app/Models/FeeGroup.php`
- `app/Models/FeeInvoice.php`
- `app/Models/FeeMaster.php`
- `app/Models/FeePayment.php`
- `app/Models/FeeType.php`
- `app/Models/Grade.php`
- `app/Models/Guardian.php`
- `app/Models/LeaveType.php`
- `app/Models/LegacyFeeType.php`
- `app/Models/LegacyStudentFeeSnapshot.php`
- `app/Models/School.php`
- `app/Models/SchoolSession.php`
- `app/Models/Section.php`
- `app/Models/Staff.php`
- `app/Models/StaffLeaveBalance.php`
- `app/Models/Student.php`
- `app/Models/StudentFeeCharge.php`
- `app/Models/StudentInquiry.php`
- `app/Models/StudentLifecycleEvent.php`
- `app/Models/Subject.php`
- `app/Models/User.php`

## app/Policies

- `app/Policies/SchoolPolicy.php`

## app/Services

- `app/Services/BranchModules.php`
- `app/Services/SchoolOrganisations.php`
- `app/Services/SchoolRoles.php`

## app/Console/Commands

- `app/Console/Commands/CreateSchoolUser.php`
- `app/Console/Commands/CreateSuperAdmin.php`
- `app/Console/Commands/ImportIfsSchoolData.php`

## app/Http/Middleware

- `app/Http/Middleware/RequireBranchModule.php`
- `app/Http/Middleware/RequireSchoolPermission.php`
- `app/Http/Middleware/ResolveSchoolPermissionTeam.php`
- `app/Http/Middleware/RestrictPersonalAccessToken.php`

## app/Http/Resources

- `app/Http/Resources/AdminListingResource.php`

## Migrations (file → tables created or altered)

| File | Tables |
|---|---|
| 0001_01_01_000000_create_users_table.php | password_reset_tokens, sessions, users |
| 0001_01_01_000001_create_cache_table.php | cache, cache_locks |
| 0001_01_01_000002_create_jobs_table.php | failed_jobs, job_batches, jobs |
| 2026_11_10_000003_create_schools_and_school_memberships.php | school_user, schools, users |
| 2026_11_11_000004_add_soft_deletes_to_schools_table.php | schools |
| 2026_11_11_015050_create_academic_structure_tables.php | grade_section, grades, sections |
| 2026_11_11_051538_create_subjects_table.php | grade_subject, subjects |
| 2026_11_11_053254_create_students_and_enrollments_tables.php | enrollments, students |
| 2026_11_11_060352_create_attendance_records_table.php | attendance_records, enrollments |
| 2026_11_11_061655_create_fee_types_table.php | fee_types |
| 2026_11_11_061656_create_fee_invoices_table.php | fee_invoices |
| 2026_11_11_061657_create_fee_payments_table.php | fee_payments |
| 2026_11_11_070000_create_school_sessions_table.php | school_sessions |
| 2026_11_11_071000_create_staff_management_tables.php | departments, designations, leave_types, staff, staff_leave_balances |
| 2026_11_11_074000_add_fee_setup_schema.php | fee_discounts, fee_group_grade, fee_groups, fee_masters, fee_types, legacy_fee_types, school_sessions |
| 2026_11_11_080000_add_monthly_fee_assignments.php | fee_masters, student_fee_charges |
| 2026_11_12_063454_add_erd_profile_fields_to_students_and_create_guardians_table.php | guardians, students |
| 2026_11_12_141500_add_document_paths_to_guardians_table.php | guardians |
| 2026_11_12_172800_create_expense_management_tables.php | expense_heads, expenses |
| 2026_11_12_180500_create_student_inquiries_table.php | student_inquiries |
| 2026_11_12_180600_create_student_lifecycle_events_table.php | student_lifecycle_events |
| 2026_11_13_095927_add_active_status_to_users_table.php | users |
| 2026_11_13_095928_create_permission_tables.php |  |
| 2026_11_13_095929_seed_platform_user_permissions.php |  |
| 2026_11_13_103819_add_teams_fields.php |  |
| 2026_11_13_103928_configure_school_scoped_roles_and_permissions.php | school_user |
| 2026_11_13_124343_add_source_admission_fields_to_students_and_guardians_tables.php | guardians, students |
| 2026_11_13_124344_create_family_fee_receipts_table.php | family_fee_receipts |
| 2026_11_13_124345_create_legacy_student_fee_snapshots_table.php | legacy_student_fee_snapshots |
| 2026_11_18_154243_add_directory_listing_fields_to_schools_table.php | schools |
| 2026_11_18_154808_add_directory_locations.php | areas, cities, schools |
| 2026_11_19_064652_create_listing_claims_table.php | listing_claims, school_group_user, school_groups, schools |
| 2026_11_19_065757_create_branch_module_catalogue.php | module_dependencies, modules, school_modules |
| 2026_11_19_094107_enforce_same_branch_lifecycle_events_and_expenses.php | expense_heads |
| 2026_11_19_094601_complete_v4_school_access_columns.php | school_user, schools, users |
| 2026_11_19_100921_enforce_branch_scope_on_student_fee_charges.php | fee_masters, student_fee_charges |
| 2026_11_19_101105_scope_fee_group_grades_to_branch.php | fee_group_grade |
| 2026_11_19_111233_create_v4_directory_catalogue.php | areas, courses, programs, school_, school_listing_fees, school_media, schools |
| 2026_11_19_125247_create_v4_platform_entitlements.php | demo_requests, listing_claims, notifications, otp_codes, plan_limits, plan_modules, plans, school_subscriptions, subscription_limits, subscription_modules |
| 2026_11_19_130537_create_v4_community_and_student_records.php | articles, events, listing_leads, reviews, saved_schools, school_stats_daily, student_fee_discounts, student_inquiries, student_transfers |
| 2026_11_19_132045_create_v4_book_exchange_and_study_resources.php | book_inquiries, books, study_resources |
| 2026_11_19_133657_create_v4_billing_websites_and_devices.php | device_tokens, personal_access_tokens, school_pages, school_site_settings, site_themes, subscription_invoice_lines, subscription_invoices, subscription_payments |
| 2026_11_19_170205_add_student_id_to_school_user_table.php | school_user |

## Factories and seeders

- `database/factories/DepartmentFactory.php`
- `database/factories/DesignationFactory.php`
- `database/factories/EnrollmentFactory.php`
- `database/factories/FeeInvoiceFactory.php`
- `database/factories/FeePaymentFactory.php`
- `database/factories/FeeTypeFactory.php`
- `database/factories/GradeFactory.php`
- `database/factories/LeaveTypeFactory.php`
- `database/factories/LegacyFeeTypeFactory.php`
- `database/factories/SchoolFactory.php`
- `database/factories/SchoolSessionFactory.php`
- `database/factories/SectionFactory.php`
- `database/factories/StaffFactory.php`
- `database/factories/StudentFactory.php`
- `database/factories/SubjectFactory.php`
- `database/factories/UserFactory.php`
- `database/seeders/BranchDemoSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/DirectoryDemoSeeder.php`
- `database/seeders/IfsDemoPagesSeeder.php`
- `database/seeders/PublicDemoSeeder.php`

## Tests (file → test methods)

- `tests/Feature/AcademicStructureTest.php` (7 tests)
- `tests/Feature/AdminAccessManagementTest.php` (5 tests)
- `tests/Feature/AdminBookExchangeTest.php` (2 tests)
- `tests/Feature/AdminCommunityTest.php` (4 tests)
- `tests/Feature/AdminDashboardTest.php` (3 tests)
- `tests/Feature/AdminDirectoryTest.php` (4 tests)
- `tests/Feature/AdminListingTest.php` (11 tests)
- `tests/Feature/AdminPlatformTest.php` (4 tests)
- `tests/Feature/AdminServicesTest.php` (3 tests)
- `tests/Feature/AttendanceManagementTest.php` (3 tests)
- `tests/Feature/AuthenticationTest.php` (6 tests)
- `tests/Feature/BranchModuleTest.php` (2 tests)
- `tests/Feature/BranchReferenceIntegrityTest.php` (4 tests)
- `tests/Feature/CreateSchoolUserCommandTest.php` (1 tests)
- `tests/Feature/CreateSuperAdminCommandTest.php` (1 tests)
- `tests/Feature/ExampleTest.php` (1 tests)
- `tests/Feature/FeeManagementTest.php` (4 tests)
- `tests/Feature/FeeStructureTest.php` (5 tests)
- `tests/Feature/HealthCheckTest.php` (3 tests)
- `tests/Feature/InstituteClaimTest.php` (4 tests)
- `tests/Feature/PublicDirectoryTest.php` (3 tests)
- `tests/Feature/PublicResourcesTest.php` (3 tests)
- `tests/Feature/SchoolAccessSchemaTest.php` (3 tests)
- `tests/Feature/SchoolManagementTest.php` (6 tests)
- `tests/Feature/SchoolPagePreviewTest.php` (2 tests)
- `tests/Feature/SchoolSessionManagementTest.php` (6 tests)
- `tests/Feature/StaffManagementTest.php` (8 tests)
- `tests/Feature/StudentEnrollmentTest.php` (6 tests)
- `tests/Feature/SubjectManagementTest.php` (5 tests)
- `tests/TestCase.php` (0 tests)
- `tests/Unit/ExampleTest.php` (1 tests)

## Frontend and views

- `resources/css/admin-dashboard.css`
- `resources/css/app.css`
- `resources/css/branch-workspace.css`
- `resources/css/data-tools.css`
- `resources/js/admin-api.js`
- `resources/js/admin.jsx`
- `resources/js/app.js`
- `resources/js/components/admin-layout.jsx`
- `resources/js/components/admin-navigation.jsx`
- `resources/js/components/admin-record.jsx`
- `resources/js/components/branch-picker.jsx`
- `resources/js/components/ui/button.jsx`
- `resources/js/components/ui/input.jsx`
- `resources/js/pages/Admin/Access.jsx`
- `resources/js/pages/Admin/BranchModules.jsx`
- `resources/js/pages/Admin/BranchWorkspace.jsx`
- `resources/js/pages/Admin/Claims.jsx`
- `resources/js/pages/Admin/Dashboard.jsx`
- `resources/js/pages/Admin/Directory.jsx`
- `resources/js/pages/Admin/Listings.jsx`
- `resources/js/pages/Admin/Locations.jsx`
- `resources/js/pages/Admin/branch/AcademicStructure.jsx`
- `resources/js/pages/Admin/branch/DataTools.jsx`
- `resources/js/pages/Admin/branch/ExpenseManagement.jsx`
- `resources/js/pages/Admin/branch/FeeManagement.jsx`
- `resources/js/pages/Admin/branch/FeeSetup.jsx`
- `resources/js/pages/Admin/branch/GuardianManagement.jsx`
- `resources/js/pages/Admin/branch/SchoolSessions.jsx`
- `resources/js/pages/Admin/branch/StaffManagement.jsx`
- `resources/js/pages/Admin/branch/StudentInquiries.jsx`
- `resources/js/pages/Admin/branch/StudentRoster.jsx`
- `resources/js/pages/Admin/branch/TableAction.jsx`
- `resources/js/pages/Admin/branch/TablePagination.jsx`
- `resources/js/pages/Admin/branch/api.js`
- `resources/js/pages/Admin/community-fields.js`
- `resources/js/pages/Admin/csv-records.js`
- `resources/js/pages/Admin/platform-fields.js`
- `resources/js/pages/Admin/service-fields.js`
- `resources/views/admin/app.blade.php`
- `resources/views/directory/about.blade.php`
- `resources/views/directory/card.blade.php`
- `resources/views/directory/collection.blade.php`
- `resources/views/directory/contact.blade.php`
- `resources/views/directory/dashboard.blade.php`
- `resources/views/directory/for-schools.blade.php`
- `resources/views/directory/home.blade.php`
- `resources/views/directory/icon.blade.php`
- `resources/views/directory/index.blade.php`
- `resources/views/directory/institute-account.blade.php`
- `resources/views/directory/institute-claim.blade.php`
- `resources/views/directory/institute-submission.blade.php`
- `resources/views/directory/layout.blade.php`
- `resources/views/directory/policy.blade.php`
- `resources/views/directory/profile.blade.php`
- `resources/views/directory/resource-detail.blade.php`
- `resources/views/directory/resources.blade.php`
- `resources/views/directory/search.blade.php`
- `resources/views/directory/sitemap.blade.php`
- `resources/views/school-page-preview.blade.php`
- `resources/views/welcome.blade.php`

## Known facts (verified by reading the code at c6ebc42)

- Test runner: PHPUnit 12; 119 test methods; no browser tests. CI runs PHPUnit on SQLite and MySQL 8.4 plus the frontend build.
- Admin: Inertia pages in `resources/js/pages/Admin/` (Dashboard, Listings, Locations, Directory, Access, Claims, BranchModules, BranchWorkspace and `branch/*` screens). Styles in `resources/css/admin-dashboard.css`. UI libraries NOT installed: no recharts, radix-ui, lucide-react, sonner or TanStack. The admin layout is `components/admin-layout.jsx`.
- There is no Inertia shared-props middleware; controllers pass props explicitly and call `Inertia::setRootView('admin.app')`.
- Dashboard today: `app/Http/Controllers/AdminDashboardController.php` (invokable, route `admin.dashboard`, `/admin/dashboard/{branch?}`) builds a `cards` map (label to value) and renders `Admin/Dashboard`. Tests: `tests/Feature/AdminDashboardTest.php` (3 tests).
- Module access: `app/Services/BranchModules.php` (`forBranch($schoolId)` returns slug, enabled, switched_on, has_override, dependencies; modules default to enabled when no switch row exists; `assertEnabled`). Middleware `RequireBranchModule` and `RequireSchoolPermission`.
- Module slugs in the catalogue: listing (core), academics, students, inquiries, guardians, attendance, staff, fees, expenses. There is no `reports` module yet.
- Permissions are named `<area>.view` and `<area>.manage` (users, roles, schools, academics, students, guardians, attendance, staff, fees, expenses); `reports.view` exists for the accountant role only. Roles: owner, group_admin, group_accountant, group_viewer (organisation level); admin/school_admin, teacher, account/accountant, student (branch level). Role helpers on `User`: `canEnterSchoolhouse`, `schoolAccessRole`, `permissionsForSchool`, `activeSchools`.
- Finance still uses the old cents-based invoices and payments (`amount_cents`) alongside the v4 fee setup. **Do not build on them.** Billing consolidation is a separate, later brief.
- Stack: Laravel 13, PHP 8.4, Sanctum, Spatie Permission, Inertia (`@inertiajs/react` 3.x), React 19, Vite, Tailwind 4.
