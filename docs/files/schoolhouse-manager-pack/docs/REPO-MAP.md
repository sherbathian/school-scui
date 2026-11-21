# Repository map (generated 19 Nov 2026 from `main` at c152ef1)

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
Route::get('/{collection}', [PublicDirectoryController::class, 'collection'])
Route::post('/{collection}/{slug}', [PublicDirectoryController::class, 'toggle'])
Route::get('/sitemap.xml', [PublicDirectoryController::class, 'sitemap'])->name('directory.sitemap');
Route::view('/about', 'directory.about')->name('directory.about');
Route::view('/contact', 'directory.contact')->name('directory.contact');
$adminPage = function (Request $request, ?int $listing = null) {
Route::get('/admin/login', $adminPage)->name('admin.login');
Route::get('/admin/listings', $adminPage)->name('admin.listings');
Route::get('/admin/listings/create', $adminPage)->name('admin.listings.create');
Route::get('/admin/listings/{listing}/edit', $adminPage)->whereNumber('listing')->name('admin.listings.edit');
Route::get('/admin/{kind}', function (Request $request, string $kind) {
# api.php
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
Route::get('/schools/{school}/students/{student}/media/{kind}', [StudentController::class, 'media'])
->name('students.media');
Route::get('/schools/{school}/guardians/{guardian}/media/{kind}', [GuardianController::class, 'media'])
->name('guardians.media');
Route::get('/schools/{school}/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])
->name('expenses.attachment');
```

## Controllers

- `app/Http/Controllers/Api/AdminListingController.php`
- `app/Http/Controllers/Api/AdminLocationController.php`
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
- `app/Http/Controllers/PublicDirectoryController.php`
- `app/Http/Controllers/SchoolController.php`
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

- `app/Services/SchoolRoles.php`

## app/Console/Commands

- `app/Console/Commands/CreateSchoolUser.php`
- `app/Console/Commands/CreateSuperAdmin.php`
- `app/Console/Commands/ImportIfsSchoolData.php`

## app/Http/Middleware

- `app/Http/Middleware/RequireSchoolPermission.php`
- `app/Http/Middleware/ResolveSchoolPermissionTeam.php`

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
- `database/seeders/DatabaseSeeder.php`

## Tests (file → test methods)

- `tests/Feature/AcademicStructureTest.php` (7): test_school_admin_can_create_grades_and_assign_sections, test_duplicate_grade_and_section_titles_are_rejected_within_one_school, test_section_assignment_rejects_another_schools_section, test_database_rejects_a_grade_section_link_with_mismatched_school_id, test_grade_section_assignments_can_be_changed_and_archived_without_losing_the_link_history, test_teacher_can_read_but_cannot_change_academic_structure, test_archiving_a_section_preserves_its_existing_grade_assignment
- `tests/Feature/AdminListingTest.php` (11): test_admin_creates_and_edits_a_draft_on_the_school_record, test_verification_is_required_before_publication_and_suspension_preserves_history, test_listing_actions_require_platform_admin_access, test_archived_and_management_suspended_schools_cannot_be_published, test_admin_workspace_is_available_only_to_active_platform_admins, test_admin_sign_in_rejects_school_members_and_starts_a_platform_session, test_admin_manages_locations_and_filters_listings_by_city_and_area, test_dashboard_counts_and_filters_the_verification_queue, test_listing_search_respects_status_and_matches_slug, test_listing_editor_pages_require_platform_access_and_load_the_requested_record, test_platform_admin_can_list_edit_and_delete_locations
- `tests/Feature/AttendanceManagementTest.php` (3): test_school_admin_can_record_and_update_attendance_for_a_full_class, test_teacher_can_record_attendance_but_unassigned_user_cannot, test_attendance_rejects_incomplete_class_lists_foreign_enrollments_and_invalid_statuses
- `tests/Feature/AuthenticationTest.php` (6): test_guests_cannot_read_their_profile_or_school_list, test_user_can_sign_in_read_their_school_memberships_and_sign_out, test_invalid_credentials_receive_a_generic_error, test_school_members_can_only_read_their_own_schools, test_school_admins_can_manage_a_school_but_teachers_cannot, test_super_admin_can_list_and_manage_schools_without_a_membership
- `tests/Feature/CreateSchoolUserCommandTest.php` (1): test_standard_school_user_can_be_provisioned_without_a_membership
- `tests/Feature/CreateSuperAdminCommandTest.php` (1): test_super_admin_can_be_provisioned_from_the_console_without_printing_the_password
- `tests/Feature/ExampleTest.php` (1): test_the_application_returns_a_successful_response
- `tests/Feature/FeeManagementTest.php` (3): test_school_admin_can_create_a_fee_invoice_and_record_partial_payments, test_overpayment_is_rejected_and_an_unpaid_invoice_can_be_voided, test_fee_records_are_school_admin_only_and_invoice_requires_matching_enrollment_year
- `tests/Feature/FeeStructureTest.php` (5): test_admin_can_create_school_wide_fee_types_and_discounts, test_fee_type_catalog_is_school_scoped_and_teachers_cannot_access_it, test_admin_can_assign_fee_group_to_school_grades_and_foreign_grade_is_rejected, test_fee_master_requires_same_school_references_and_decimal_values, test_fee_master_is_unique_per_group_type_session_and_month_and_protects_used_groups
- `tests/Feature/HealthCheckTest.php` (3): test_health_endpoint_reports_a_working_database_connection, test_health_endpoint_hides_database_errors_when_the_connection_fails, getPdo
- `tests/Feature/PublicDirectoryTest.php` (2): test_only_active_verified_published_listings_are_discoverable, test_directory_filters_by_city_area_keyword_and_type
- `tests/Feature/SchoolManagementTest.php` (6): test_super_admin_can_create_update_and_archive_schools, test_school_admin_can_manage_teacher_memberships_but_not_grant_admin_role, test_global_admin_can_assign_school_admin_and_teacher_accounts, test_school_admin_cannot_manage_other_schools_or_school_admin_memberships, test_non_super_admin_cannot_create_or_archive_schools, test_users_without_a_school_account_cannot_be_added
- `tests/Feature/SchoolSessionManagementTest.php` (6): test_school_admin_can_create_sessions_and_first_session_becomes_current, test_session_dates_must_be_ordered_and_must_not_overlap, test_current_session_switch_is_atomic_and_exactly_one_remains_current, test_current_session_cannot_be_unset_or_archived_until_another_is_current, test_teachers_can_view_but_cannot_manage_sessions, test_school_members_cannot_read_another_schools_sessions
- `tests/Feature/StaffManagementTest.php` (8): test_admin_can_create_school_scoped_lookup_values_and_a_staff_profile, test_cross_school_lookup_and_unlinked_user_ids_are_rejected, test_staff_login_is_optional_but_can_only_be_linked_once, test_sensitive_staff_fields_are_encrypted_and_hidden_from_teachers, test_staff_lookup_names_are_unique_per_school_and_in_use_items_cannot_be_deleted, test_teacher_can_read_staff_but_cannot_change_staff_or_lookups, test_leave_balance_is_per_staff_leave_type_and_session_and_rejects_invalid_values, test_staff_and_leave_balance_routes_are_scoped_to_school
- `tests/Feature/StudentEnrollmentTest.php` (6): test_school_admin_can_create_a_student_and_enroll_them_in_an_assigned_class, test_student_admission_saves_erd_profile_and_parent_guardian_details, test_enrollment_requires_a_section_assigned_to_an_active_grade_in_the_same_school, test_moving_a_student_closes_the_previous_enrollment_and_keeps_history, test_admission_numbers_are_unique_per_school_and_teacher_access_is_read_only, test_archived_students_can_be_restored_and_roster_can_filter_by_academic_year
- `tests/Feature/SubjectManagementTest.php` (5): test_school_admin_can_create_subjects_assigned_to_grades_and_update_assignments, test_subject_assignment_rejects_foreign_school_and_inactive_grades, test_database_rejects_grade_subject_link_with_mismatched_school_id, test_teacher_can_view_subjects_but_cannot_create_or_archive_them, test_duplicate_subject_titles_are_school_scoped_and_archive_preserves_assignments
- `tests/TestCase.php` (0): 
- `tests/Unit/ExampleTest.php` (1): test_that_true_is_true

## Frontend and views

- `resources/css/admin-dashboard.css`
- `resources/css/app.css`
- `resources/js/admin-api.js`
- `resources/js/admin.jsx`
- `resources/js/app.js`
- `resources/js/components/admin-layout.jsx`
- `resources/js/components/ui/button.jsx`
- `resources/js/components/ui/input.jsx`
- `resources/js/pages/Admin/Listings.jsx`
- `resources/js/pages/Admin/Locations.jsx`
- `resources/views/admin/app.blade.php`
- `resources/views/directory/about.blade.php`
- `resources/views/directory/card.blade.php`
- `resources/views/directory/collection.blade.php`
- `resources/views/directory/contact.blade.php`
- `resources/views/directory/home.blade.php`
- `resources/views/directory/index.blade.php`
- `resources/views/directory/layout.blade.php`
- `resources/views/directory/profile.blade.php`
- `resources/views/directory/search.blade.php`
- `resources/views/directory/sitemap.blade.php`
- `resources/views/welcome.blade.php`

## Known facts (verified by reading the code)

- Test runner: PHPUnit 12. No Pest, no browser tests yet. 74 test methods at the baseline.
- Admin: Inertia entry `resources/js/admin.jsx`; one big page `Admin/Listings.jsx` (sign-in, list, create, edit) and `Admin/Locations.jsx`; calls the JSON API through `admin-api.js`; styles in `css/admin-dashboard.css`; UI components only `Button` and `Input`; JavaScript (JSX), not TypeScript; no Wayfinder.
- Admin routes are closures in `routes/web.php` (`$adminPage`), not controllers.
- There is no Inertia shared-props middleware yet (no `HandleInertiaRequests`): flash messages and the signed-in user are not shared to pages. Task T02 adds it.
- Legacy still present and scheduled for removal (decisions already made): `LegacyFeeTypeController`, `LegacyFeeType`, `LegacyStudentFeeSnapshot`, `FamilyFeeReceipt` and the school import command.
- Stack: Laravel 13, PHP 8.4, Sanctum, Spatie Permission 8.5, Inertia (`inertia-laravel` 3.7, `@inertiajs/react` 3.11), React 19, Vite 8, Tailwind 4.
