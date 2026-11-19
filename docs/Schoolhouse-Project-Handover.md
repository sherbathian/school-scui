# Schoolhouse Project Handover

18 November 2026 · Working project name

Schoolhouse combines the Taleemfinder directory with branch-based institute management. Database Plan v4 governs conflicting scope and schema details. The planning and package guides provide the earlier product requirements and technical rationale. The complete source extracts follow the current decisions so readers can trace where each requirement came from.

## Current scope and decisions

- Build the directory fresh at taleemfinder.pk. Listings come from admin entry and institute submission. WordPress import, legacy IDs and redirect migration were dropped.
- Every verified school, college, university or institute receives a permanent free listing. Verification and publication are required before visibility. Platform management uses one common host. An entitled Website module can provide a platform subdomain. Customer-owned domains and the school_domains table were dropped.
- Branches have separate students, fee records and expenses. Owners can access branches in their organisation. Membership design still requires review. The workbook implements organisation entitlement plus branch switches and dependency checks. Turning access off or expiring it preserves records.
- Plans bundle modules and limits. Add-ons, trials and grants contribute entitlements. Student fee invoices and the platform’s subscription invoices serve different billing flows.
- Marketing and demo requests are in the database design. The separate marketing brand domain remains pending. The public directory and management share the backend and database.
- Use PHP 8.4+, Laravel 13 and React 19 as the baseline. Evaluate school-gpt for reuse. Confirm the real database engine and package resolution. Use test-driven development for every milestone.
- V4 reports 43 kept existing tables, 47 new tables, 90 total, 89 agreed and one table pending (school_group_user). It reports 56 of 56 database design checks passed. Implementation and production readiness require separate evidence.
- The older planning document specifies targets of 80 students and 15 staff. The v4 Free starter bundle and quota counting scope remain open. Treat proposed values and rules as configurable until agreed. The Open Decisions appendix supplies the full decisions register.
- The partner Word roadmap gives the gated implementation sequence and package choices.

## Source files

1. Schoolhouse Database Plan v4, the current database and business rules workbook.
2. Schoolhouse Project Planning Document v1.1, 17 November 2026.
3. Schoolhouse Package Selection Guide v1.1, 17 November 2026.

## Database Plan v4 reference

Workbook rows are transcribed below. Empty cells represent empty workbook values. The tabular extracts preserve the data and notes while workbook styling and formulas remain in the original file.

### Start Here

Schoolhouse database plan – version 4

Module-wise subscriptions, free verified listing, common domain and marketing site added; all checked on a test database.

| First column | Second column | Third column | Fourth column |
| --- | --- | --- | --- |
| At a glance | Value |  |  |
| Tables today (after your drops) | 43 |  |  |
|    of which change | 12 |  |  |
| New tables | 47 |  |  |
| Tables after the change | 90 |  |  |
| Tables dropped or removed (3 existing, 2 proposed) | 5 |  |  |
| Columns today (in kept tables) | 367 |  |  |
|    of which removed | 17 |  |  |
| New columns | 516 |  |  |
| Relationships changed to same-branch rules | 10 |  |  |
| New relationships | 89 |  |  |
| Design checks passed | 56 of 56 |  |  |
| Tables marked Agreed | 89 |  |  |
| Waiting for your check | 0 |  |  |
| Not reviewed yet | 1 |  |  |
| Review progress (Agreed) | 0.9888888888888889 |  |  |
| What to do next |  |  |  |
| 1. Table Plan | Rows marked &#x27;Updated – please check&#x27; (blue) changed since your review. Read the Reply column (M), then set Agreed, Needs change or Drop. |  |  |
| 2. Subscription Logic | The business rules: free listing, modules, plans, add-ons, branch switches, limits, billing and addresses, plus which tables are existing, modified or new. |  |  |
| 3. Design Check | How branches and module switches work, and the 56 test results. |  |  |
| 4. How Fees Work | Plain explanation of fee groups, price list, charges, invoices and payments, with an example. |  |  |
| 5. Open Decisions | Questions that still change the design, each with a recommendation. &#x27;Decided&#x27; rows are settled. |  |  |
| 6. Change Log | Every change in versions 3 and 4 and why. |  |  |
| Colour key | Green = new. Amber = changed. Red = column to be removed. Blue = please check. Yellow cells are yours. |  |  |
| Phases | P1 Directory · P2 Claim &amp; plans · P3 Engagement · P4 Community · P5 Websites &amp; mobile. &#x27;Built&#x27; = already in the app. |  |  |
| Data type¹ | New columns use MySQL-style types (for example varchar(150)). Existing rows keep the type names from your data dictionary. |  |  |

### Table Plan

Table plan – version 4

One row per table. Yellow columns are yours. 'Updated – please check' means I changed something since your review; read column M.

| Table | Area | Status | Phase | Existing columns | New columns | Removed columns | Columns after change | What it stores | What changes / why | Your decision | Your notes | Reply |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| school_user | School access | Existing – change | Built | 6 | 1 | 0 | 7 | Membership and school-level role context for a user. | One small addition; no change to roles. | Agreed |  |  |
| schools | School access | Existing – change | Built | 7 | 36 | 0 | 43 | School / tenant records that own school-specific data. | Existing rows: set listing_status=&#x27;draft&#x27;. management_enabled is removed: module switches now live in school_modules. &#x27;status&#x27; stays the on/off switch of the whole record; &#x27;listing_status&#x27; controls public visibility. | Agreed | legacy_wp_post_id no need column | Done: legacy_wp_post_id removed. Added branch_name, branch_code and is_main_branch: each branch is one row in this table, grouped under an organisation (school_groups). |
| users | School access | Existing – change | Built | 10 | 4 | 0 | 14 | People who can sign in to the application. | Enable public sign-up for owners and parents (currently disabled). Decide whether email stays required (see Open Decisions). | Agreed |  |  |
| areas | Directory lookups | New | P1 Directory | 0 | 10 | 0 | 10 | Sectors and localities inside a city (for example B-17, Bahria Town, Chaklala). | Keep one spelling per area (for example &#x27;DHA Phase 1&#x27;, not also &#x27;DHA Phase I&#x27;). | Agreed |  |  |
| boards | Directory lookups | New | P1 Directory | 0 | 8 | 0 | 8 | Examination boards and curricula (Federal Board, Punjab BISE, Cambridge, Montessori and so on). | Used for the board/curriculum filter. | Agreed |  |  |
| cities | Directory lookups | New | P1 Directory | 0 | 8 | 0 | 8 | Cities covered by the directory (for example Islamabad, Rawalpindi). | Lets you open the directory to new cities later without code changes. | Agreed |  |  |
| education_levels | Directory lookups | New | P1 Directory | 0 | 6 | 0 | 6 | Levels an institute offers (Playgroup, Primary, Matric, O-Level, FSc, BS and so on). | Powers the &#x27;grade level&#x27; filter and fee rows. | Agreed |  |  |
| facilities | Directory lookups | New | P1 Directory | 0 | 9 | 0 | 9 | Facilities (library, computer lab, transport) and academic focus (Islamic education, STEM, sports) used as filters. | One table with a &#x27;kind&#x27; field instead of two; the live site treats them as two filters. | Agreed |  |  |
| school_board | Public directory | New | P1 Directory | 0 | 2 | 0 | 2 | Links an institute to the boards/curricula it follows (many-to-many). |  | Agreed |  |  |
| school_education_level | Public directory | New | P1 Directory | 0 | 2 | 0 | 2 | Links an institute to the education levels it offers (many-to-many). |  | Agreed |  |  |
| school_facility | Public directory | New | P1 Directory | 0 | 2 | 0 | 2 | Links an institute to its facilities and academic focus (many-to-many). |  | Agreed |  |  |
| school_group_user | Public directory | New | P2 Claim &amp; plans | 0 | 6 | 0 | 6 | Who belongs to an organisation at owner level, and with what role. This is what lets the owner see every branch. | A person here may open every branch of the organisation. Branch staff are still linked only through school_user. | Not reviewed |  | New: this is what lets the owner see every branch. |
| school_groups | Public directory | New | P1 Directory | 0 | 9 | 0 | 9 | The organisation that owns one or more branches (a school, college, university or institute). Every institute that registers gets one. | Each branch is one row in schools, with its own students, staff, fees and accounts. The owner is linked in school_group_user and sees all branches. Also serves as the public brand (for example one school network with several campuses). | Agreed |  | Now the organisation that owns the branches. Added a status column. |
| school_listing_fees | Public directory | New | P1 Directory | 0 | 13 | 0 | 13 | Indicative public fees per level shown on the directory page. | Separate from fee_masters, which bills real students. Show &#x27;last confirmed&#x27; so parents can judge freshness. | Agreed |  |  |
| school_media | Public directory | New | P1 Directory | 0 | 12 | 0 | 12 | Gallery photos, brochures and video links shown on an institute&#x27;s public page. | Logo and cover live on schools for fast listing cards. Alternative: spatie/laravel-medialibrary (see Open Decisions). | Agreed |  |  |
| course_categories | Courses and programs | New | P1 Directory | 0 | 6 | 0 | 6 | Short-course categories (IT, languages, vocational, and so on). |  | Agreed |  |  |
| courses | Courses and programs | New | P1 Directory | 0 | 18 | 0 | 18 | Short courses such as Python, Typing and Shorthand, Graphic Designing. | school_id is optional so a course can be listed before its provider has a listing. | Agreed |  |  |
| programs | Courses and programs | New | P1 Directory | 0 | 14 | 0 | 14 | Degree and diploma programmes offered by colleges and universities (FSc, ICS, BS, MS). |  | Agreed |  |  |
| listing_claims | Claims and verification | New | P2 Claim &amp; plans | 0 | 16 | 0 | 16 | Requests by a person to claim (take over) an existing listing, with the review outcome. | One verified claim turns claim_status on schools to &#x27;claimed&#x27; and creates the school_user membership. | Agreed |  |  |
| otp_codes | Claims and verification | New | P2 Claim &amp; plans | 0 | 12 | 0 | 12 | One-time codes sent by SMS, WhatsApp or email to verify a phone number or email. | Store only a hash of the code, never the code itself. | Agreed |  |  |
| module_dependencies | Plans and limits | New | P2 Claim &amp; plans | 0 | 2 | 0 | 2 | Which module needs which other module (fees needs students; parent portal needs students). | Stops a branch from switching on a module whose foundation is off. | Agreed |  | New: a module that needs another (Fees needs Students) cannot work while that one is off. |
| modules | Plans and limits | New | P2 Claim &amp; plans | 0 | 11 | 0 | 11 | The catalogue of modules that can be switched on: students, fees, exams, SMS, institute website and so on. | One row per sellable or switchable feature. &#x27;is_core&#x27; marks what every verified institute always has (the free listing). &#x27;applies_to&#x27; limits a module to some institute types, for example university-only semester registration. | Agreed |  | New: the catalogue of switchable features. The free listing is a core module that every verified institute has and that cannot be switched off. |
| plan_limits | Plans and limits | New | P2 Claim &amp; plans | 0 | 6 | 0 | 6 | One row per limit per plan (students, staff, logins, storage, messages). | A table instead of fixed columns, so a new limit needs no database change. Empty value means unlimited. | Agreed |  |  |
| plan_modules | Plans and limits | New | P2 Claim &amp; plans | 0 | 3 | 0 | 3 | Which modules each plan includes. | This is the main place to change what a plan contains; no code change needed. | Agreed |  | New: which modules each plan includes. Changing a plan means changing rows here, not code. |
| plans | Plans and limits | New | P2 Claim &amp; plans | 0 | 10 | 0 | 10 | Subscription plans (Free, Standard, Professional, Enterprise). A plan is a bundle of modules plus limits. | Modules come from plan_modules and limits from plan_limits. The old features list inside this table is removed: it could not answer &#x27;which modules, for which branch&#x27;. | Agreed |  | Changed: the old &#x27;features&#x27; list is removed. A plan is now a bundle of modules (plan_modules) plus limits (plan_limits). |
| school_modules | Plans and limits | New | P2 Claim &amp; plans | 0 | 7 | 0 | 7 | The on/off switch of each module for each branch. | A branch can use a module only if it is switched on here and the organisation is entitled to it. The owner or a group manager can switch modules per branch. | Agreed |  | New: the on/off switch of each module for each branch. A module works only when the organisation is entitled to it AND the branch has switched it on. |
| school_subscriptions | Plans and limits | New | P2 Claim &amp; plans | 0 | 10 | 0 | 10 | Which plan each organisation is on and for how long. | The plan belongs to the organisation. Modules come from the plan plus add-ons, and each branch switches them on or off in school_modules. | Agreed |  | Changed: the plan belongs to the organisation, so limits count across all its branches. Added a grace period (paid modules become read-only before they close). |
| subscription_invoice_lines | Plans and limits | New | P6 Billing | 0 | 11 | 0 | 11 | The lines of a subscription invoice: the plan, and each add-on module per branch. | School and module are filled for add-on lines, so the owner can see exactly what each branch costs. | Agreed |  | New (later step): the plan once, plus each add-on per branch. |
| subscription_invoices | Plans and limits | New | P6 Billing | 0 | 13 | 0 | 13 | Invoices from the platform to an organisation for its plan and add-on modules. | Not to be confused with fee_invoices, which are fees charged by a school to its students. | Agreed |  | New (later step): what the platform bills an organisation, not to be confused with fee_invoices. |
| subscription_limits | Plans and limits | New | P2 Claim &amp; plans | 0 | 7 | 0 | 7 | Limits that replace a plan&#x27;s limits for one organisation (a special deal or promotion). | Example: this organisation gets 200 students instead of the plan&#x27;s 80. | Agreed |  | New: a special limit for one organisation (a deal or promotion) that replaces the plan&#x27;s limit. |
| subscription_modules | Plans and limits | New | P2 Claim &amp; plans | 0 | 10 | 0 | 10 | Modules an organisation has on top of its plan: paid add-ons, trials and manual grants. | Lets you sell one extra module (for example SMS), give a 30-day trial of a premium module, or grant a module to a partner, each with an end date. | Agreed |  | New: modules an organisation has on top of its plan: paid add-ons, trials, manual grants, each with an end date. |
| subscription_payments | Plans and limits | New | P6 Billing | 0 | 9 | 0 | 9 | Payments received from an organisation against a subscription invoice. |  | Agreed |  | New (later step): payments received against a subscription invoice. |
| demo_requests | Marketing site | New | P2 Claim &amp; plans | 0 | 16 | 0 | 16 | Requests for a demo or free trial sent from the marketing website. | The marketing site on its own domain uses this same database. A request can later be converted into a registered organisation. | Agreed |  | New: demo and free-trial requests from the marketing website, which shares this database. |
| school_pages | Institute websites | New | P5 Websites &amp; mobile | 0 | 13 | 0 | 13 | Editable pages of an institute&#x27;s own website (About, Admissions, Contact and custom pages). | Content is stored as structured blocks (JSON) and drawn by React, not stored as raw HTML. This avoids a whole class of script-injection problems. | Agreed |  |  |
| school_site_settings | Institute websites | New | P5 Websites &amp; mobile | 0 | 15 | 0 | 15 | Chosen theme, address and appearance settings of an institute&#x27;s own website (Website module). | One row per institute. The website lives at name.taleemfinder.pk (a subdomain of the common domain). No custom domains, no custom code or CSS. | Agreed |  | Changed: the website address is now a subdomain of the common domain (subdomain column). Own-domain handling removed. |
| site_themes | Institute websites | New | P5 Websites &amp; mobile | 0 | 11 | 0 | 11 | Website templates that institutes can choose. | Themes are built and reviewed by you and shipped with the application. Institutes never upload code. | Agreed |  |  |
| device_tokens | Mobile app | New | P5 Websites &amp; mobile | 0 | 8 | 0 | 8 | Push-notification address of each phone that has the app. | One user can have several devices. | Agreed |  |  |
| personal_access_tokens | Mobile app | New | P5 Websites &amp; mobile | 0 | 10 | 0 | 10 | Sign-in tokens for the mobile app (standard Laravel Sanctum table). | Created by Sanctum&#x27;s own migration. The web dashboard keeps using cookie sessions. | Agreed |  |  |
| events | Engagement | New | P3 Engagement | 0 | 15 | 0 | 15 | Open houses, entry tests, admission drives and education events. | school_id is optional so platform-wide events can exist. | Agreed |  |  |
| listing_leads | Engagement | New | P3 Engagement | 0 | 15 | 0 | 15 | Enquiries sent by parents from a public profile page to an institute. | Keep the consent flag and a retention rule (see Open Decisions). A lead can be converted into an internal student inquiry. | Agreed |  |  |
| reviews | Engagement | New | P3 Engagement | 0 | 15 | 0 | 15 | Parent, student and alumni ratings and comments about an institute. | Moderate before publishing. Consider launching without reviews at first. | Agreed |  |  |
| saved_schools | Engagement | New | P3 Engagement | 0 | 3 | 0 | 3 | A visitor&#x27;s saved list of institutes. | The compare list can stay in the browser; only saves need a table. | Agreed |  |  |
| school_stats_daily | Engagement | New | P3 Engagement | 0 | 9 | 0 | 9 | Daily counts of profile views, saves and leads per institute for the owner dashboard. | Aggregated per day to keep the table small. | Agreed |  |  |
| articles | Content | New | P3 Engagement | 0 | 14 | 0 | 14 | Parent guides and other editorial pages. | Replaces WordPress posts under /parent-guides/. No WordPress ID column (your decision). | Agreed | No need for legacy_wp_post_id | Done: legacy_wp_post_id removed. Added site (finder or marketing) so the marketing website can show its own blog from the same table. |
| book_inquiries | Community | New | P4 Community | 0 | 7 | 0 | 7 | Messages from people interested in a listed book. | Keeps phone numbers private until the owner accepts. | Agreed |  |  |
| books | Community | New | P4 Community | 0 | 18 | 0 | 18 | Used-book exchange: books offered for sale, swap or donation. |  | Agreed |  |  |
| study_resources | Community | New | P4 Community | 0 | 12 | 0 | 12 | Notes and study material shared by students. | Needs moderation and a copyright policy before launch. | Agreed |  |  |
| grade_section | Academics | Existing – keep | Built | 5 | 0 | 0 | 5 | Valid grade-to-section combinations. |  | Agreed |  |  |
| grade_subject | Academics | Existing – keep | Built | 5 | 0 | 0 | 5 | Subjects assigned to grade levels. |  | Agreed |  |  |
| grades | Academics | Existing – keep | Built | 6 | 0 | 0 | 6 | Grade or year-level lookup records. |  | Agreed |  |  |
| school_sessions | Academics | Existing – keep | Built | 10 | 0 | 0 | 10 | Academic sessions / years for a school. |  | Agreed |  |  |
| sections | Academics | Existing – keep | Built | 6 | 0 | 0 | 6 | Class section lookup records. |  | Agreed |  |  |
| subjects | Academics | Existing – keep | Built | 6 | 0 | 0 | 6 | School subject catalog. |  | Agreed |  |  |
| attendance_records | Students | Existing – keep | Built | 8 | 0 | 0 | 8 | Daily attendance status for an enrollment. |  | Agreed |  |  |
| enrollments | Students | Existing – keep | Built | 11 | 0 | 0 | 11 | A student’s class placement for an academic year. |  | Agreed |  |  |
| guardians | Students | Existing – keep | Built | 26 | 0 | 0 | 26 | Family and guardian contact details and uploaded documents. |  | Agreed |  |  |
| student_inquiries | Students | Existing – change | Built | 15 | 1 | 0 | 16 | Prospective student inquiries and admission pipeline records. | Links a public lead to the school&#x27;s private admission inquiry. Same-branch rules tightened. | Agreed |  | Same-branch rule tightened: this table can now only link to records of its own branch. |
| student_lifecycle_events | Students | Existing – change | Built | 8 | 0 | 0 | 8 | Student status timeline such as withdrawal or rejoining. | Same-branch rules tightened. | Agreed |  | Same-branch rule tightened: this table can now only link to records of its own branch. |
| student_transfers | Students | New | P3 Engagement | 0 | 10 | 0 | 10 | History of a student moving from one branch to another inside the same organisation. | The student gets a new record in the new branch (the old record is closed), so each branch keeps its own admission number, fees and balance. | Agreed |  | New: history of a student moving between branches of the same organisation. |
| students | Students | Existing – change | Built | 38 | 0 | 12 | 26 | Student identity, admission, contact, document, and source data. | 12 import-leftover and fee columns removed (your decision). Fees now live only in the fee tables. | Agreed | Need to delete following columns :<br>admitted_class<br>current_class_label<br>caste_or_tribe<br>fee_rate<br>fee_amount<br>fee_waiver<br>arrears_due<br>withdrawn_from_class<br>source_marks_obtained<br>source_marks_percentage<br>source_data<br>source_guardian_data | Done: the 12 columns you listed are shown in red as Removed. Fees now live only in the fee tables. |
| departments | Human resources | Existing – keep | Built | 5 | 0 | 0 | 5 | Staff department lookup records. |  | Agreed |  |  |
| designations | Human resources | Existing – keep | Built | 5 | 0 | 0 | 5 | Staff designation lookup records. |  | Agreed |  |  |
| leave_types | Human resources | Existing – keep | Built | 5 | 0 | 0 | 5 | Leave categories available to staff. |  | Agreed |  |  |
| staff | Human resources | Existing – change | Built | 31 | 0 | 0 | 31 | Staff profile and employment details. | Login link is now unique per branch (was unique overall), so one login can be staff in two branches. Same-branch rules tightened. | Agreed |  | Changed so one login can be staff in two branches (unique per branch instead of unique overall). |
| staff_leave_balances | Human resources | Existing – keep | Built | 8 | 0 | 0 | 8 | Allocated and used leave by staff, leave type, and session. |  | Agreed |  |  |
| fee_discounts | Fees and receipts | Existing – keep | Built | 8 | 0 | 0 | 8 | Percentage or fixed discounts for fee collection. |  | Agreed |  |  |
| fee_group_grade | Fees and receipts | Existing – change | Built | 2 | 1 | 0 | 3 | Grades eligible for a fee group. | school_id added so a fee group can only contain classes of its own branch. Same-branch rules tightened. | Agreed |  | school_id added so a fee group can only contain classes of its own branch. |
| fee_groups | Fees and receipts | Existing – keep | Built | 7 | 0 | 0 | 7 | Reusable fee group definitions. |  | Agreed | I am not sure how it will work,  | A fee group is a named bundle of classes that pay the same fees, for example &#x27;Primary (Class 1 to 5)&#x27;. The table fee_group_grade says which classes belong to it. Prices are then set per group in fee_masters. If every class pays differently, make one group per class. See the sheet &#x27;How Fees Work&#x27;. |
| fee_invoices | Fees and receipts | Existing – change | Built | 11 | 8 | 4 | 15 | Historical student-level fee invoices. | Re-pointed after legacy_fee_types was dropped: one invoice per student per month, built from student_fee_charges. Money fields in rupees (decimal) instead of cents. | Agreed |  | Because you dropped legacy_fee_types, invoices could no longer point at a fee type. They now work monthly: one invoice per student per month, built from that student&#x27;s charges. Please check. |
| fee_masters | Fees and receipts | Existing – keep | Built | 12 | 0 | 0 | 12 | Fee group/type amount rules by session and billing month. |  | Agreed |  |  |
| fee_payments | Fees and receipts | Existing – change | Built | 9 | 2 | 1 | 10 | Payments recorded against historical invoices. | Amount now in rupees (decimal) to match invoices; payment method added. | Agreed |  | Changed so amounts are in rupees (decimal) like the other fee tables, and a payment method was added. |
| fee_types | Fees and receipts | Existing – keep | Built | 6 | 0 | 0 | 6 | Current fee catalog items. |  | Agreed |  | Unchanged: the list of fee names (Tuition, Admission, Transport). Charges and price lists point at it. |
| student_fee_charges | Fees and receipts | Existing – change | Built | 11 | 2 | 0 | 13 | Monthly fee amount assigned to an individual student. | Each monthly charge is linked to the invoice it appears on and can carry a discount. Same-branch rules tightened. | Agreed | I am not sure how it will work,  | One row = what one student owes for one fee type in one month, for example Ali – Tuition – September 2026 – Rs 3,000. The system creates these automatically from the price list (fee_masters) for every student in the group&#x27;s classes. They are then collected onto the month&#x27;s invoice. I added fee_invoice_id and discount_amount so the charge can be traced to its invoice. See &#x27;How Fees Work&#x27;. |
| student_fee_discounts | Fees and receipts | New | P3 Engagement | 0 | 10 | 0 | 10 | Which discount (sibling, staff child, scholarship) applies to which student, and for how long. | Without this table fee_discounts could be defined but never applied to anyone. | Agreed |  | New: fee_discounts could be defined but never applied to a student; this table applies them. |
| expense_heads | Expenses | Existing – keep | Built | 6 | 0 | 0 | 6 | Expense categories. |  | Agreed |  |  |
| expenses | Expenses | Existing – change | Built | 11 | 0 | 0 | 11 | Dated expense entries, amounts, and optional attachment paths. | Same-branch rules tightened. | Agreed |  | Same-branch rule tightened: this table can now only link to records of its own branch. |
| model_has_permissions | Permissions | Existing – keep | Built | 4 | 0 | 0 | 4 | Polymorphic direct permission assignments to application models. |  | Agreed |  |  |
| model_has_roles | Permissions | Existing – keep | Built | 4 | 0 | 0 | 4 | Polymorphic assignments of roles to application models (normally users). |  | Agreed |  |  |
| permissions | Permissions | Existing – keep | Built | 5 | 0 | 0 | 5 | Individual abilities that can be assigned to roles or users. |  | Agreed |  |  |
| role_has_permissions | Permissions | Existing – keep | Built | 2 | 0 | 0 | 2 | Join table connecting roles to permissions. |  | Agreed |  |  |
| roles | Permissions | Existing – keep | Built | 6 | 0 | 0 | 6 | Named permission bundles; team_id scopes school roles. |  | Agreed |  |  |
| cache | Laravel infrastructure | Existing – keep | Built | 3 | 0 | 0 | 3 | Application cache values. |  | Agreed |  |  |
| cache_locks | Laravel infrastructure | Existing – keep | Built | 3 | 0 | 0 | 3 | Locks used by the application cache. |  | Agreed |  |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Built | 7 | 0 | 0 | 7 | Queue jobs that failed processing. |  | Agreed |  |  |
| job_batches | Laravel infrastructure | Existing – keep | Built | 10 | 0 | 0 | 10 | Metadata for grouped queue jobs. |  | Agreed |  |  |
| jobs | Laravel infrastructure | Existing – keep | Built | 7 | 0 | 0 | 7 | Queued background jobs waiting to run. |  | Agreed |  |  |
| migrations | Laravel infrastructure | Existing – keep | Built | 3 | 0 | 0 | 3 | Laravel migration execution history. |  | Agreed |  |  |
| notifications | Laravel infrastructure | New | P2 Claim &amp; plans | 0 | 8 | 0 | 8 | In-app notifications (claim approved, new lead, new review). Standard Laravel table. | Created by &#x27;php artisan notifications:table&#x27;. | Agreed |  |  |
| password_reset_tokens | Laravel infrastructure | Existing – keep | Built | 3 | 0 | 0 | 3 | Password reset tokens for login accounts. |  | Agreed |  |  |
| sessions | Laravel infrastructure | Existing – keep | Built | 6 | 0 | 0 | 6 | Web session state for signed-in users. |  | Agreed |  |  |

### Columns

Column-by-column plan – version 4

Green = new, amber = changed, red = to be removed. Filter 'Column status' to see only what changes.

| Table | Area | Table status | Column status | Column | Data type¹ | Nullable | Default | Key role | References | Plain-language meaning | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| school_user | School access | Existing – change | Existing | school_id | integer | No |  | Primary key; Foreign key | schools.id | School that owns this record; separates data between schools. |  |
| school_user | School access | Existing – change | Existing | user_id | integer | No |  | Primary key; Foreign key | users.id | Login account linked to this record. |  |
| school_user | School access | Existing – change | Existing | role | varchar | No | &#x27;teacher&#x27; | — |  | Role field for the membership and school-level role context for a user.. |  |
| school_user | School access | Existing – change | Existing | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the account or membership is enabled. |  |
| school_user | School access | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_user | School access | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_user | School access | Existing – change | New | is_primary_contact | tinyint(1) | No | &#x27;0&#x27; | — |  | Main contact for the institute (owner of the listing). |  |
| schools | School access | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| schools | School access | Existing – change | Existing | name | varchar | No |  | — |  | Name field for the school / tenant records that own school-specific data.. |  |
| schools | School access | Existing – change | Existing | slug | varchar | No |  | Unique index |  | Slug field for the school / tenant records that own school-specific data.. |  |
| schools | School access | Existing – change | Existing | status | varchar | No | &#x27;active&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| schools | School access | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| schools | School access | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| schools | School access | Existing – change | Existing | deleted_at | datetime | Yes |  | — |  | Soft-delete timestamp; a value means the record is archived/deleted. |  |
| schools | School access | Existing – change | New | institute_type | varchar(30) | No | &#x27;school&#x27; | Index |  | school, college, university or training_institute. |  |
| schools | School access | Existing – change | New | group_id | integer | Yes |  | Foreign key | school_groups.id | Network or brand this campus belongs to. |  |
| schools | School access | Existing – change | New | short_name | varchar(80) | Yes |  | — |  | Short display name for cards. |  |
| schools | School access | Existing – change | New | tagline | varchar(160) | Yes |  | — |  | One-line pitch. |  |
| schools | School access | Existing – change | New | description | text | Yes |  | — |  | About the institute, shown on the public page. |  |
| schools | School access | Existing – change | New | gender_type | varchar(15) | Yes |  | — |  | boys, girls or co_ed. |  |
| schools | School access | Existing – change | New | medium | varchar(30) | Yes |  | — |  | Language of instruction: english, urdu or both. |  |
| schools | School access | Existing – change | New | established_year | smallint | Yes |  | — |  | Year the institute was founded. |  |
| schools | School access | Existing – change | New | city_id | integer | Yes |  | Foreign key | cities.id | City of the campus (blank for management-only schools). |  |
| schools | School access | Existing – change | New | area_id | integer | Yes |  | Foreign key | areas.id | Sector or locality of the campus. |  |
| schools | School access | Existing – change | New | address_line | varchar(255) | Yes |  | — |  | Street address. |  |
| schools | School access | Existing – change | New | latitude | decimal(10,7) | Yes |  | — |  | Map latitude. |  |
| schools | School access | Existing – change | New | longitude | decimal(10,7) | Yes |  | — |  | Map longitude. |  |
| schools | School access | Existing – change | New | phone | varchar(30) | Yes |  | — |  | Public phone number. |  |
| schools | School access | Existing – change | New | whatsapp | varchar(30) | Yes |  | — |  | Public WhatsApp number. |  |
| schools | School access | Existing – change | New | email | varchar(190) | Yes |  | — |  | Public contact email. |  |
| schools | School access | Existing – change | New | website_url | varchar(255) | Yes |  | — |  | Institute website. |  |
| schools | School access | Existing – change | New | social_links | json | Yes |  | — |  | Facebook, Instagram, YouTube links. |  |
| schools | School access | Existing – change | New | logo_path | varchar(255) | Yes |  | — |  | Stored path of the logo. |  |
| schools | School access | Existing – change | New | cover_path | varchar(255) | Yes |  | — |  | Stored path of the cover photo. |  |
| schools | School access | Existing – change | New | fee_min | decimal(10,2) | Yes |  | Index |  | Lowest monthly fee, copied from school_listing_fees for sorting and filtering. |  |
| schools | School access | Existing – change | New | fee_max | decimal(10,2) | Yes |  | — |  | Highest monthly fee, copied from school_listing_fees. |  |
| schools | School access | Existing – change | New | rating_avg | decimal(3,2) | Yes |  | — |  | Average review rating, kept up to date by the application. |  |
| schools | School access | Existing – change | New | rating_count | integer | No | &#x27;0&#x27; | — |  | Number of approved reviews. |  |
| schools | School access | Existing – change | New | listing_status | varchar(20) | No | &#x27;draft&#x27; | Index |  | Public visibility: draft, pending_review, published or suspended. Only verified institutes can be published. |  |
| schools | School access | Existing – change | New | claim_status | varchar(20) | No | &#x27;unclaimed&#x27; | Index |  | unclaimed, pending or claimed. |  |
| schools | School access | Existing – change | New | source | varchar(20) | No | &#x27;admin_created&#x27; | — |  | How the listing was created: admin_created or self_submitted. |  |
| schools | School access | Existing – change | New | verified_at | datetime | Yes |  | — |  | When ownership or details were verified. |  |
| schools | School access | Existing – change | New | published_at | datetime | Yes |  | — |  | When the listing first went public. |  |
| schools | School access | Existing – change | New | is_featured | tinyint(1) | No | &#x27;0&#x27; | — |  | Show in featured spots. |  |
| schools | School access | Existing – change | New | featured_until | datetime | Yes |  | — |  | End of the featured period. |  |
| schools | School access | Existing – change | New | seo_title | varchar(160) | Yes |  | — |  | Custom search-engine title. |  |
| schools | School access | Existing – change | New | seo_description | varchar(300) | Yes |  | — |  | Custom search-engine description. |  |
| schools | School access | Existing – change | New | branch_name | varchar(100) | Yes |  | — |  | Name of this branch, for example Main Campus or Gulberg Branch. |  |
| schools | School access | Existing – change | New | branch_code | varchar(20) | Yes |  | Unique index (group_id, branch_code) |  | Short code used on receipts and student numbers, for example GUL. |  |
| schools | School access | Existing – change | New | is_main_branch | tinyint(1) | No | &#x27;0&#x27; | — |  | Marks the head office or main campus of the organisation. |  |
| users | School access | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| users | School access | Existing – change | Existing | name | varchar | No |  | — |  | Name field for the people who can sign in to the application.. |  |
| users | School access | Existing – change | Existing | email | varchar | No |  | Unique index |  | Email field for the people who can sign in to the application.. |  |
| users | School access | Existing – change | Existing | email_verified_at | datetime | Yes |  | — |  | Date and time the email address was verified. |  |
| users | School access | Existing – change | Existing | password | varchar | No |  | — |  | Hashed login password; this is not stored as readable text. |  |
| users | School access | Existing – change | Existing | remember_token | varchar | Yes |  | — |  | Token used to keep a login session remembered. |  |
| users | School access | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| users | School access | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| users | School access | Existing – change | Existing | is_super_admin | tinyint(1) | No | &#x27;0&#x27; | — |  | Whether the login has platform-level super-admin status. |  |
| users | School access | Existing – change | Existing | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the account or membership is enabled. |  |
| users | School access | Existing – change | New | phone | varchar(30) | Yes |  | Index |  | Mobile number; needed for OTP and parent/owner sign-up. |  |
| users | School access | Existing – change | New | phone_verified_at | datetime | Yes |  | — |  | When the phone number was verified. |  |
| users | School access | Existing – change | New | locale | varchar(5) | No | &#x27;en&#x27; | — |  | Preferred language (en or ur). |  |
| users | School access | Existing – change | New | last_login_at | datetime | Yes |  | — |  | Most recent successful sign-in. |  |
| areas | Directory lookups | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| areas | Directory lookups | New | New | city_id | integer | No |  | Foreign key | cities.id | City this area belongs to. |  |
| areas | Directory lookups | New | New | name | varchar(120) | No |  | — |  | Area, sector or locality name. |  |
| areas | Directory lookups | New | New | slug | varchar(120) | No |  | Unique index (city_id, slug) |  | URL-friendly name; unique within the city. |  |
| areas | Directory lookups | New | New | area_type | varchar(20) | No | &#x27;locality&#x27; | — |  | Kind of area: sector, locality or housing_scheme. |  |
| areas | Directory lookups | New | New | latitude | decimal(10,7) | Yes |  | — |  | Map latitude of the area centre. |  |
| areas | Directory lookups | New | New | longitude | decimal(10,7) | Yes |  | — |  | Map longitude of the area centre. |  |
| areas | Directory lookups | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the area appears in the directory. |  |
| areas | Directory lookups | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| areas | Directory lookups | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| boards | Directory lookups | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| boards | Directory lookups | New | New | name | varchar(100) | No |  | — |  | Board or curriculum name. |  |
| boards | Directory lookups | New | New | slug | varchar(100) | No |  | Unique index |  | URL-friendly name. |  |
| boards | Directory lookups | New | New | kind | varchar(30) | No | &#x27;board&#x27; | — |  | Type: board, curriculum or university_charter. |  |
| boards | Directory lookups | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether this option can be chosen. |  |
| boards | Directory lookups | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order in filters and forms. |  |
| boards | Directory lookups | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| boards | Directory lookups | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| cities | Directory lookups | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| cities | Directory lookups | New | New | name | varchar(100) | No |  | — |  | City name shown to visitors. |  |
| cities | Directory lookups | New | New | slug | varchar(100) | No |  | Unique index |  | URL-friendly name, for example islamabad. |  |
| cities | Directory lookups | New | New | province | varchar(100) | Yes |  | — |  | Province or territory of the city. |  |
| cities | Directory lookups | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the city appears in the directory. |  |
| cities | Directory lookups | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order in which cities are listed. |  |
| cities | Directory lookups | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| cities | Directory lookups | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| education_levels | Directory lookups | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| education_levels | Directory lookups | New | New | name | varchar(100) | No |  | — |  | Level name. |  |
| education_levels | Directory lookups | New | New | slug | varchar(100) | No |  | Unique index |  | URL-friendly name. |  |
| education_levels | Directory lookups | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order from youngest to oldest level. |  |
| education_levels | Directory lookups | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| education_levels | Directory lookups | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| facilities | Directory lookups | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| facilities | Directory lookups | New | New | name | varchar(100) | No |  | — |  | Facility or focus name. |  |
| facilities | Directory lookups | New | New | slug | varchar(100) | No |  | Unique index |  | URL-friendly name. |  |
| facilities | Directory lookups | New | New | kind | varchar(20) | No | &#x27;facility&#x27; | — |  | facility or focus. |  |
| facilities | Directory lookups | New | New | icon | varchar(60) | Yes |  | — |  | Icon name shown next to the label. |  |
| facilities | Directory lookups | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether this option can be chosen. |  |
| facilities | Directory lookups | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order in filters and forms. |  |
| facilities | Directory lookups | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| facilities | Directory lookups | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_board | Public directory | New | New | school_id | integer | No |  | Primary key; Foreign key | schools.id | Institute offering the board. |  |
| school_board | Public directory | New | New | board_id | integer | No |  | Primary key; Foreign key | boards.id | Board or curriculum followed. |  |
| school_education_level | Public directory | New | New | school_id | integer | No |  | Primary key; Foreign key | schools.id | Institute offering the level. |  |
| school_education_level | Public directory | New | New | education_level_id | integer | No |  | Primary key; Foreign key | education_levels.id | Level offered. |  |
| school_facility | Public directory | New | New | school_id | integer | No |  | Primary key; Foreign key | schools.id | Institute that has the facility. |  |
| school_facility | Public directory | New | New | facility_id | integer | No |  | Primary key; Foreign key | facilities.id | Facility or focus. |  |
| school_group_user | Public directory | New | New | school_group_id | integer | No |  | Primary key; Foreign key | school_groups.id | Organisation. |  |
| school_group_user | Public directory | New | New | user_id | integer | No |  | Primary key; Foreign key | users.id | Login account of the owner or group manager. |  |
| school_group_user | Public directory | New | New | role | varchar(30) | No | &#x27;owner&#x27; | — |  | owner, group_admin, group_accountant or group_viewer. |  |
| school_group_user | Public directory | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Switch off to remove access without deleting the record. |  |
| school_group_user | Public directory | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_group_user | Public directory | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_groups | Public directory | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_groups | Public directory | New | New | name | varchar(150) | No |  | — |  | Organisation or brand name. |  |
| school_groups | Public directory | New | New | slug | varchar(150) | No |  | Unique index |  | URL-friendly name. |  |
| school_groups | Public directory | New | New | description | text | Yes |  | — |  | About the organisation. |  |
| school_groups | Public directory | New | New | website_url | varchar(255) | Yes |  | — |  | Organisation website. |  |
| school_groups | Public directory | New | New | logo_path | varchar(255) | Yes |  | — |  | Stored path of the logo. |  |
| school_groups | Public directory | New | New | status | varchar(20) | No | &#x27;active&#x27; | — |  | active or suspended (suspending stops all branches). |  |
| school_groups | Public directory | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_groups | Public directory | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_listing_fees | Public directory | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_listing_fees | Public directory | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Institute that publishes this fee; separates data between schools. |  |
| school_listing_fees | Public directory | New | New | education_level_id | integer | Yes |  | Foreign key | education_levels.id | Level the fee applies to (optional). |  |
| school_listing_fees | Public directory | New | New | label | varchar(100) | Yes |  | — |  | Free-text label, for example &#x27;Grade 1 to 5&#x27;. |  |
| school_listing_fees | Public directory | New | New | monthly_fee | decimal(10,2) | Yes |  | — |  | Monthly tuition fee in PKR. |  |
| school_listing_fees | Public directory | New | New | admission_fee | decimal(10,2) | Yes |  | — |  | One-time admission fee in PKR. |  |
| school_listing_fees | Public directory | New | New | annual_charges | decimal(10,2) | Yes |  | — |  | Yearly charges in PKR. |  |
| school_listing_fees | Public directory | New | New | currency | char(3) | No | &#x27;PKR&#x27; | — |  | Currency code. |  |
| school_listing_fees | Public directory | New | New | effective_from | date | Yes |  | — |  | Date the fee took effect. |  |
| school_listing_fees | Public directory | New | New | last_confirmed_at | datetime | Yes |  | — |  | When the institute last confirmed this fee. |  |
| school_listing_fees | Public directory | New | New | notes | varchar(255) | Yes |  | — |  | Extra remarks, for example sibling discount. |  |
| school_listing_fees | Public directory | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_listing_fees | Public directory | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_media | Public directory | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_media | Public directory | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Institute that owns the media; separates data between schools. |  |
| school_media | Public directory | New | New | kind | varchar(20) | No | &#x27;photo&#x27; | — |  | photo, brochure or video. |  |
| school_media | Public directory | New | New | path | varchar(255) | Yes |  | — |  | Stored file path (blank for external video links). |  |
| school_media | Public directory | New | New | url | varchar(500) | Yes |  | — |  | External link, used for videos. |  |
| school_media | Public directory | New | New | caption | varchar(255) | Yes |  | — |  | Short caption. |  |
| school_media | Public directory | New | New | alt_text | varchar(255) | Yes |  | — |  | Text description for accessibility and SEO. |  |
| school_media | Public directory | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Display order. |  |
| school_media | Public directory | New | New | is_approved | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the item is visible publicly (allows moderation). |  |
| school_media | Public directory | New | New | uploaded_by_user_id | integer | Yes |  | Foreign key | users.id | Login account that uploaded the file. |  |
| school_media | Public directory | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_media | Public directory | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| course_categories | Courses and programs | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| course_categories | Courses and programs | New | New | name | varchar(100) | No |  | — |  | Category name. |  |
| course_categories | Courses and programs | New | New | slug | varchar(100) | No |  | Unique index |  | URL-friendly name. |  |
| course_categories | Courses and programs | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Display order. |  |
| course_categories | Courses and programs | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| course_categories | Courses and programs | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| courses | Courses and programs | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| courses | Courses and programs | New | New | school_id | integer | Yes |  | Foreign key | schools.id | Provider institute, if it has a listing. |  |
| courses | Courses and programs | New | New | provider_name | varchar(150) | Yes |  | — |  | Provider name when there is no linked institute. |  |
| courses | Courses and programs | New | New | city_id | integer | No |  | Foreign key | cities.id | City where the course runs. |  |
| courses | Courses and programs | New | New | area_id | integer | Yes |  | Foreign key | areas.id | Area where the course runs. |  |
| courses | Courses and programs | New | New | course_category_id | integer | Yes |  | Foreign key | course_categories.id | Category of the course. |  |
| courses | Courses and programs | New | New | title | varchar(150) | No |  | — |  | Course title. |  |
| courses | Courses and programs | New | New | slug | varchar(170) | No |  | Unique index |  | URL-friendly name. |  |
| courses | Courses and programs | New | New | summary | varchar(300) | Yes |  | — |  | One-line description for cards. |  |
| courses | Courses and programs | New | New | description | text | Yes |  | — |  | Full course details. |  |
| courses | Courses and programs | New | New | duration_value | smallint | Yes |  | — |  | Length of the course (number). |  |
| courses | Courses and programs | New | New | duration_unit | varchar(10) | Yes |  | — |  | days, weeks or months. |  |
| courses | Courses and programs | New | New | mode | varchar(15) | No | &#x27;onsite&#x27; | — |  | onsite, online or hybrid. |  |
| courses | Courses and programs | New | New | fee | decimal(10,2) | Yes |  | — |  | Course fee in PKR. |  |
| courses | Courses and programs | New | New | next_start_date | date | Yes |  | — |  | Date the next batch starts. |  |
| courses | Courses and programs | New | New | status | varchar(20) | No | &#x27;draft&#x27; | — |  | draft, published or archived. |  |
| courses | Courses and programs | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| courses | Courses and programs | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| programs | Courses and programs | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| programs | Courses and programs | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Institute offering the programme; separates data between schools. |  |
| programs | Courses and programs | New | New | title | varchar(150) | No |  | — |  | Programme name, for example BS Computer Science. |  |
| programs | Courses and programs | New | New | slug | varchar(150) | No |  | Unique index (school_id, slug) |  | URL-friendly name; unique within the institute. |  |
| programs | Courses and programs | New | New | level | varchar(30) | No | &#x27;bachelor&#x27; | — |  | intermediate, bachelor, master, phd, diploma or certificate. |  |
| programs | Courses and programs | New | New | field | varchar(100) | Yes |  | — |  | Subject area or department. |  |
| programs | Courses and programs | New | New | duration_months | smallint | Yes |  | — |  | Length of the programme in months. |  |
| programs | Courses and programs | New | New | fee_amount | decimal(10,2) | Yes |  | — |  | Fee in PKR for the period named in fee_period. |  |
| programs | Courses and programs | New | New | fee_period | varchar(20) | Yes |  | — |  | semester, year, month or total. |  |
| programs | Courses and programs | New | New | eligibility | text | Yes |  | — |  | Entry requirements. |  |
| programs | Courses and programs | New | New | admission_open | tinyint(1) | No | &#x27;0&#x27; | — |  | Whether admissions are currently open. |  |
| programs | Courses and programs | New | New | status | varchar(20) | No | &#x27;active&#x27; | — |  | active or archived. |  |
| programs | Courses and programs | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| programs | Courses and programs | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| listing_claims | Claims and verification | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| listing_claims | Claims and verification | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Listing being claimed. |  |
| listing_claims | Claims and verification | New | New | user_id | integer | No |  | Foreign key | users.id | Login account making the claim. |  |
| listing_claims | Claims and verification | New | New | claimant_name | varchar(150) | No |  | — |  | Full name of the person claiming. |  |
| listing_claims | Claims and verification | New | New | claimant_role | varchar(100) | Yes |  | — |  | Position, for example principal or owner. |  |
| listing_claims | Claims and verification | New | New | phone | varchar(30) | No |  | — |  | Contact phone used for verification. |  |
| listing_claims | Claims and verification | New | New | email | varchar(190) | Yes |  | — |  | Contact email used for verification. |  |
| listing_claims | Claims and verification | New | New | method | varchar(20) | No | &#x27;phone_otp&#x27; | — |  | email_otp, phone_otp, document or manual. |  |
| listing_claims | Claims and verification | New | New | document_path | varchar(255) | Yes |  | — |  | Stored proof document, when document verification is used. |  |
| listing_claims | Claims and verification | New | New | status | varchar(20) | No | &#x27;pending&#x27; | Index |  | pending, verified, rejected or withdrawn. |  |
| listing_claims | Claims and verification | New | New | reviewed_by_user_id | integer | Yes |  | Foreign key | users.id | Platform admin who reviewed the claim. |  |
| listing_claims | Claims and verification | New | New | reviewed_at | datetime | Yes |  | — |  | When the claim was reviewed. |  |
| listing_claims | Claims and verification | New | New | review_note | text | Yes |  | — |  | Reason or remarks from the reviewer. |  |
| listing_claims | Claims and verification | New | New | verified_at | datetime | Yes |  | — |  | When verification succeeded. |  |
| listing_claims | Claims and verification | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| listing_claims | Claims and verification | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| otp_codes | Claims and verification | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| otp_codes | Claims and verification | New | New | user_id | integer | Yes |  | Foreign key | users.id | Login account the code was issued for, if known. |  |
| otp_codes | Claims and verification | New | New | channel | varchar(10) | No |  | — |  | sms, whatsapp or email. |  |
| otp_codes | Claims and verification | New | New | destination | varchar(190) | No |  | Index (destination, purpose) |  | Phone number or email the code was sent to. |  |
| otp_codes | Claims and verification | New | New | purpose | varchar(30) | No |  | — |  | claim, phone_verify or login. |  |
| otp_codes | Claims and verification | New | New | code_hash | varchar(255) | No |  | — |  | Hashed one-time code. |  |
| otp_codes | Claims and verification | New | New | attempts | tinyint | No | &#x27;0&#x27; | — |  | Number of wrong attempts so far. |  |
| otp_codes | Claims and verification | New | New | expires_at | datetime | No |  | — |  | When the code stops working. |  |
| otp_codes | Claims and verification | New | New | consumed_at | datetime | Yes |  | — |  | When the code was used successfully. |  |
| otp_codes | Claims and verification | New | New | ip_address | varchar(45) | Yes |  | — |  | IP address that requested the code (abuse control). |  |
| otp_codes | Claims and verification | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| otp_codes | Claims and verification | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| module_dependencies | Plans and limits | New | New | module_id | integer | No |  | Primary key; Foreign key | modules.id | Module that has a requirement. |  |
| module_dependencies | Plans and limits | New | New | requires_module_id | integer | No |  | Primary key; Foreign key | modules.id | Module that must be on first. |  |
| modules | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| modules | Plans and limits | New | New | slug | varchar(50) | No |  | Unique index |  | Short code used in the application, for example fees or institute_website. |  |
| modules | Plans and limits | New | New | name | varchar(100) | No |  | — |  | Name shown to institutes. |  |
| modules | Plans and limits | New | New | description | varchar(255) | Yes |  | — |  | What the module covers. |  |
| modules | Plans and limits | New | New | module_group | varchar(30) | No | &#x27;management&#x27; | — |  | listing, management, website, messaging or app. |  |
| modules | Plans and limits | New | New | applies_to | json | Yes |  | — |  | Institute types the module is for (school, college, university, training_institute); blank means all. |  |
| modules | Plans and limits | New | New | is_core | tinyint(1) | No | &#x27;0&#x27; | — |  | Always available to a verified institute and cannot be switched off (the free listing). |  |
| modules | Plans and limits | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the module can be offered at all. |  |
| modules | Plans and limits | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order in menus and on the pricing page. |  |
| modules | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| modules | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| plan_limits | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| plan_limits | Plans and limits | New | New | plan_id | integer | No |  | Foreign key; Unique index (plan_id, limit_key) | plans.id | Plan the limit belongs to. |  |
| plan_limits | Plans and limits | New | New | limit_key | varchar(50) | No |  | — |  | Name of the limit: students, staff, logins, storage_mb or sms_per_month. |  |
| plan_limits | Plans and limits | New | New | limit_value | integer | Yes |  | — |  | Allowed amount; blank means unlimited. |  |
| plan_limits | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| plan_limits | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| plan_modules | Plans and limits | New | New | plan_id | integer | No |  | Primary key; Foreign key | plans.id | Plan. |  |
| plan_modules | Plans and limits | New | New | module_id | integer | No |  | Primary key; Foreign key | modules.id | Module included in the plan. |  |
| plan_modules | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| plans | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| plans | Plans and limits | New | New | name | varchar(100) | No |  | — |  | Plan name. |  |
| plans | Plans and limits | New | New | slug | varchar(100) | No |  | Unique index |  | Short code, for example free. |  |
| plans | Plans and limits | New | New | price_monthly | decimal(10,2) | No | &#x27;0&#x27; | — |  | Monthly price in PKR (0 for the Free plan). |  |
| plans | Plans and limits | New | New | is_default | tinyint(1) | No | &#x27;0&#x27; | — |  | Plan given to every newly registered institute (the Free plan). |  |
| plans | Plans and limits | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether the plan can be chosen. |  |
| plans | Plans and limits | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order shown on the pricing page. |  |
| plans | Plans and limits | New | New | description | varchar(255) | Yes |  | — |  | One-line description for the pricing page. |  |
| plans | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| plans | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_modules | Plans and limits | New | New | school_id | integer | No |  | Primary key; Foreign key | schools.id | Branch. |  |
| school_modules | Plans and limits | New | New | module_id | integer | No |  | Primary key; Foreign key | modules.id | Module. |  |
| school_modules | Plans and limits | New | New | is_enabled | tinyint(1) | No | &#x27;1&#x27; | — |  | 1 = switched on for this branch, 0 = switched off. |  |
| school_modules | Plans and limits | New | New | enabled_by_user_id | integer | Yes |  | Foreign key | users.id | Person who last changed the switch. |  |
| school_modules | Plans and limits | New | New | enabled_at | datetime | Yes |  | — |  | When the switch was last changed. |  |
| school_modules | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_modules | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_subscriptions | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_subscriptions | Plans and limits | New | New | school_group_id | integer | No |  | Foreign key; Index | school_groups.id | Organisation subscribed (covers all its branches). |  |
| school_subscriptions | Plans and limits | New | New | plan_id | integer | No |  | Foreign key | plans.id | Plan chosen. |  |
| school_subscriptions | Plans and limits | New | New | status | varchar(20) | No | &#x27;active&#x27; | — |  | trial, active, grace, expired or cancelled. |  |
| school_subscriptions | Plans and limits | New | New | starts_at | datetime | No |  | — |  | Subscription start. |  |
| school_subscriptions | Plans and limits | New | New | ends_at | datetime | Yes |  | — |  | Subscription end (blank if open-ended). |  |
| school_subscriptions | Plans and limits | New | New | grace_ends_at | datetime | Yes |  | — |  | End of the grace period after expiry; paid modules are read-only until then. |  |
| school_subscriptions | Plans and limits | New | New | payment_reference | varchar(100) | Yes |  | — |  | Receipt or transaction reference. |  |
| school_subscriptions | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_subscriptions | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| subscription_invoice_lines | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| subscription_invoice_lines | Plans and limits | New | New | subscription_invoice_id | integer | No |  | Foreign key; Index | subscription_invoices.id | Invoice the line belongs to. |  |
| subscription_invoice_lines | Plans and limits | New | New | school_id | integer | Yes |  | Foreign key | schools.id | Branch the line is for (blank for the plan line). |  |
| subscription_invoice_lines | Plans and limits | New | New | module_id | integer | Yes |  | Foreign key | modules.id | Module billed (blank for the plan line). |  |
| subscription_invoice_lines | Plans and limits | New | New | plan_id | integer | Yes |  | Foreign key | plans.id | Plan billed (for the plan line). |  |
| subscription_invoice_lines | Plans and limits | New | New | description | varchar(200) | No |  | — |  | Text shown on the invoice. |  |
| subscription_invoice_lines | Plans and limits | New | New | quantity | smallint | No | &#x27;1&#x27; | — |  | Number of units. |  |
| subscription_invoice_lines | Plans and limits | New | New | unit_price | decimal(10,2) | No | &#x27;0&#x27; | — |  | Price of one unit (PKR). |  |
| subscription_invoice_lines | Plans and limits | New | New | amount | decimal(12,2) | No | &#x27;0&#x27; | — |  | Line total (PKR). |  |
| subscription_invoice_lines | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| subscription_invoice_lines | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| subscription_invoices | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| subscription_invoices | Plans and limits | New | New | school_group_id | integer | No |  | Foreign key; Index | school_groups.id | Organisation being billed. |  |
| subscription_invoices | Plans and limits | New | New | invoice_no | varchar(30) | No |  | Unique index |  | Invoice number, for example SUB-2026-11-0007. |  |
| subscription_invoices | Plans and limits | New | New | period_start | date | No |  | — |  | First day billed. |  |
| subscription_invoices | Plans and limits | New | New | period_end | date | No |  | — |  | Last day billed. |  |
| subscription_invoices | Plans and limits | New | New | subtotal | decimal(12,2) | No | &#x27;0&#x27; | — |  | Sum of the lines (PKR). |  |
| subscription_invoices | Plans and limits | New | New | discount_total | decimal(12,2) | No | &#x27;0&#x27; | — |  | Discount (PKR). |  |
| subscription_invoices | Plans and limits | New | New | total_amount | decimal(12,2) | No | &#x27;0&#x27; | — |  | Amount payable (PKR). |  |
| subscription_invoices | Plans and limits | New | New | status | varchar(20) | No | &#x27;unpaid&#x27; | Index |  | draft, unpaid, paid or void. |  |
| subscription_invoices | Plans and limits | New | New | due_date | date | No |  | — |  | Payment due date. |  |
| subscription_invoices | Plans and limits | New | New | issued_at | datetime | Yes |  | — |  | When it was issued. |  |
| subscription_invoices | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| subscription_invoices | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| subscription_limits | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| subscription_limits | Plans and limits | New | New | school_subscription_id | integer | No |  | Foreign key; Unique index (school_subscription_id, limit_key) | school_subscriptions.id | Subscription (organisation) the exception applies to. |  |
| subscription_limits | Plans and limits | New | New | limit_key | varchar(50) | No |  | — |  | Which limit: students, staff, branches, logins, storage_mb or sms_per_month. |  |
| subscription_limits | Plans and limits | New | New | limit_value | integer | Yes |  | — |  | Allowed amount; blank means unlimited. |  |
| subscription_limits | Plans and limits | New | New | reason | varchar(255) | Yes |  | — |  | Why the exception was made. |  |
| subscription_limits | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| subscription_limits | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| subscription_modules | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| subscription_modules | Plans and limits | New | New | school_subscription_id | integer | No |  | Foreign key; Index | school_subscriptions.id | Subscription (organisation) receiving the module. |  |
| subscription_modules | Plans and limits | New | New | module_id | integer | No |  | Foreign key | modules.id | Module granted. |  |
| subscription_modules | Plans and limits | New | New | source | varchar(15) | No | &#x27;addon&#x27; | — |  | addon (paid), trial or manual_grant. |  |
| subscription_modules | Plans and limits | New | New | starts_at | datetime | No |  | — |  | When access begins. |  |
| subscription_modules | Plans and limits | New | New | ends_at | datetime | Yes |  | — |  | When access ends (blank = no end). |  |
| subscription_modules | Plans and limits | New | New | granted_by_user_id | integer | Yes |  | Foreign key | users.id | Platform admin who granted it, for trials and manual grants. |  |
| subscription_modules | Plans and limits | New | New | note | varchar(255) | Yes |  | — |  | Reason, for example &#x27;partner school&#x27; or &#x27;admissions season trial&#x27;. |  |
| subscription_modules | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| subscription_modules | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| subscription_payments | Plans and limits | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| subscription_payments | Plans and limits | New | New | subscription_invoice_id | integer | No |  | Foreign key; Index | subscription_invoices.id | Invoice paid. |  |
| subscription_payments | Plans and limits | New | New | amount | decimal(12,2) | No |  | — |  | Amount received (PKR). |  |
| subscription_payments | Plans and limits | New | New | paid_on | date | No |  | — |  | Date received. |  |
| subscription_payments | Plans and limits | New | New | method | varchar(20) | No | &#x27;bank_transfer&#x27; | — |  | bank_transfer, jazzcash, easypaisa, cash or other. |  |
| subscription_payments | Plans and limits | New | New | reference | varchar(100) | Yes |  | — |  | Transaction reference. |  |
| subscription_payments | Plans and limits | New | New | recorded_by_user_id | integer | Yes |  | Foreign key | users.id | Platform staff member who recorded it. |  |
| subscription_payments | Plans and limits | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| subscription_payments | Plans and limits | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| demo_requests | Marketing site | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| demo_requests | Marketing site | New | New | name | varchar(150) | No |  | — |  | Name of the person asking. |  |
| demo_requests | Marketing site | New | New | institute_name | varchar(150) | Yes |  | — |  | Name of their institute. |  |
| demo_requests | Marketing site | New | New | institute_type | varchar(30) | Yes |  | — |  | school, college, university or institute. |  |
| demo_requests | Marketing site | New | New | phone | varchar(30) | No |  | — |  | Contact phone. |  |
| demo_requests | Marketing site | New | New | email | varchar(190) | Yes |  | — |  | Contact email. |  |
| demo_requests | Marketing site | New | New | city | varchar(100) | Yes |  | — |  | City. |  |
| demo_requests | Marketing site | New | New | branches | smallint | Yes |  | — |  | How many branches they have. |  |
| demo_requests | Marketing site | New | New | students_estimate | integer | Yes |  | — |  | Roughly how many students. |  |
| demo_requests | Marketing site | New | New | message | text | Yes |  | — |  | Message. |  |
| demo_requests | Marketing site | New | New | source_site | varchar(30) | No | &#x27;marketing&#x27; | — |  | Website the request came from: marketing or finder. |  |
| demo_requests | Marketing site | New | New | status | varchar(20) | No | &#x27;new&#x27; | Index |  | new, contacted, trial, converted or lost. |  |
| demo_requests | Marketing site | New | New | assigned_to_user_id | integer | Yes |  | Foreign key | users.id | Team member following up. |  |
| demo_requests | Marketing site | New | New | school_group_id | integer | Yes |  | Foreign key | school_groups.id | Organisation created from this request. |  |
| demo_requests | Marketing site | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| demo_requests | Marketing site | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_pages | Institute websites | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_pages | Institute websites | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Institute that owns the page; separates data between schools. |  |
| school_pages | Institute websites | New | New | title | varchar(150) | No |  | — |  | Page title. |  |
| school_pages | Institute websites | New | New | slug | varchar(150) | No |  | Unique index (school_id, slug) |  | URL-friendly name; unique within the institute. |  |
| school_pages | Institute websites | New | New | page_type | varchar(20) | No | &#x27;custom&#x27; | — |  | home, about, admissions, contact or custom. |  |
| school_pages | Institute websites | New | New | content | longtext | Yes |  | — |  | Page content as structured blocks (JSON). |  |
| school_pages | Institute websites | New | New | is_published | tinyint(1) | No | &#x27;0&#x27; | — |  | Whether visitors can see the page. |  |
| school_pages | Institute websites | New | New | show_in_menu | tinyint(1) | No | &#x27;0&#x27; | — |  | Show the page in the navigation menu. |  |
| school_pages | Institute websites | New | New | sort_order | smallint | No | &#x27;0&#x27; | — |  | Order in the menu. |  |
| school_pages | Institute websites | New | New | seo_title | varchar(160) | Yes |  | — |  | Custom search-engine title. |  |
| school_pages | Institute websites | New | New | seo_description | varchar(300) | Yes |  | — |  | Custom search-engine description. |  |
| school_pages | Institute websites | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_pages | Institute websites | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_site_settings | Institute websites | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_site_settings | Institute websites | New | New | school_id | integer | No |  | Foreign key; Unique index | schools.id | Institute the settings belong to. |  |
| school_site_settings | Institute websites | New | New | site_theme_id | integer | No |  | Foreign key | site_themes.id | Theme in use. |  |
| school_site_settings | Institute websites | New | New | subdomain | varchar(63) | Yes |  | Unique index |  | Name before .taleemfinder.pk, for example abcschool. Reserved names (www, school, app, api, admin) are refused. |  |
| school_site_settings | Institute websites | New | New | site_enabled | tinyint(1) | No | &#x27;0&#x27; | — |  | Whether the institute&#x27;s own website is live (needs the Website module). |  |
| school_site_settings | Institute websites | New | New | primary_color | varchar(7) | Yes |  | — |  | Main brand colour, for example #1E2B4D. |  |
| school_site_settings | Institute websites | New | New | secondary_color | varchar(7) | Yes |  | — |  | Second brand colour. |  |
| school_site_settings | Institute websites | New | New | font_family | varchar(60) | Yes |  | — |  | Chosen font from the allowed list. |  |
| school_site_settings | Institute websites | New | New | homepage_sections | json | Yes |  | — |  | Which homepage sections are shown and in what order. |  |
| school_site_settings | Institute websites | New | New | menu_items | json | Yes |  | — |  | Navigation menu. |  |
| school_site_settings | Institute websites | New | New | footer_text | varchar(255) | Yes |  | — |  | Footer line. |  |
| school_site_settings | Institute websites | New | New | analytics_id | varchar(40) | Yes |  | — |  | Visitor-statistics ID, if the institute uses one. |  |
| school_site_settings | Institute websites | New | New | published_at | datetime | Yes |  | — |  | When the website first went live. |  |
| school_site_settings | Institute websites | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_site_settings | Institute websites | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| site_themes | Institute websites | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| site_themes | Institute websites | New | New | name | varchar(100) | No |  | — |  | Theme name. |  |
| site_themes | Institute websites | New | New | slug | varchar(100) | No |  | Unique index |  | Folder name of the theme in the application. |  |
| site_themes | Institute websites | New | New | description | varchar(255) | Yes |  | — |  | Short description shown when choosing. |  |
| site_themes | Institute websites | New | New | preview_path | varchar(255) | Yes |  | — |  | Preview image. |  |
| site_themes | Institute websites | New | New | version | varchar(20) | No | &#x27;1.0.0&#x27; | — |  | Theme version. |  |
| site_themes | Institute websites | New | New | is_premium | tinyint(1) | No | &#x27;0&#x27; | — |  | Only available on paid plans. |  |
| site_themes | Institute websites | New | New | is_active | tinyint(1) | No | &#x27;1&#x27; | — |  | Whether institutes can choose it. |  |
| site_themes | Institute websites | New | New | settings_schema | json | Yes |  | — |  | Which options (colours, sections) the theme lets institutes change. |  |
| site_themes | Institute websites | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| site_themes | Institute websites | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| device_tokens | Mobile app | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| device_tokens | Mobile app | New | New | user_id | integer | No |  | Foreign key | users.id | Login account that owns the device. |  |
| device_tokens | Mobile app | New | New | token | varchar(255) | No |  | Unique index |  | Push-notification token from Firebase Cloud Messaging. |  |
| device_tokens | Mobile app | New | New | platform | varchar(10) | No |  | — |  | android or ios. |  |
| device_tokens | Mobile app | New | New | app_version | varchar(20) | Yes |  | — |  | App version on the device. |  |
| device_tokens | Mobile app | New | New | last_seen_at | datetime | Yes |  | — |  | Last time the device contacted the server. |  |
| device_tokens | Mobile app | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| device_tokens | Mobile app | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| personal_access_tokens | Mobile app | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| personal_access_tokens | Mobile app | New | New | tokenable_type | varchar(255) | No |  | Index (tokenable_type, tokenable_id) |  | Kind of record the token belongs to (normally User). |  |
| personal_access_tokens | Mobile app | New | New | tokenable_id | integer | No |  | — |  | ID of the record the token belongs to. |  |
| personal_access_tokens | Mobile app | New | New | name | varchar(255) | No |  | — |  | Device or app name. |  |
| personal_access_tokens | Mobile app | New | New | token | varchar(64) | No |  | Unique index |  | Hashed token. |  |
| personal_access_tokens | Mobile app | New | New | abilities | text | Yes |  | — |  | What the token may do. |  |
| personal_access_tokens | Mobile app | New | New | last_used_at | datetime | Yes |  | — |  | Last time the token was used. |  |
| personal_access_tokens | Mobile app | New | New | expires_at | datetime | Yes |  | — |  | When the token stops working. |  |
| personal_access_tokens | Mobile app | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| personal_access_tokens | Mobile app | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| events | Engagement | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| events | Engagement | New | New | school_id | integer | Yes |  | Foreign key | schools.id | Institute hosting the event, if any. |  |
| events | Engagement | New | New | title | varchar(180) | No |  | — |  | Event title. |  |
| events | Engagement | New | New | slug | varchar(200) | No |  | Unique index |  | URL-friendly name. |  |
| events | Engagement | New | New | event_type | varchar(30) | No | &#x27;other&#x27; | — |  | open_house, entry_test, admission_drive, seminar or other. |  |
| events | Engagement | New | New | description | text | Yes |  | — |  | Event details. |  |
| events | Engagement | New | New | starts_at | datetime | No |  | Index |  | Start date and time. |  |
| events | Engagement | New | New | ends_at | datetime | Yes |  | — |  | End date and time. |  |
| events | Engagement | New | New | venue | varchar(200) | Yes |  | — |  | Where it takes place. |  |
| events | Engagement | New | New | city_id | integer | Yes |  | Foreign key | cities.id | City of the event. |  |
| events | Engagement | New | New | registration_url | varchar(500) | Yes |  | — |  | Link to register. |  |
| events | Engagement | New | New | status | varchar(20) | No | &#x27;draft&#x27; | — |  | draft, published or cancelled. |  |
| events | Engagement | New | New | created_by_user_id | integer | Yes |  | Foreign key | users.id | Login account that created the event. |  |
| events | Engagement | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| events | Engagement | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| listing_leads | Engagement | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| listing_leads | Engagement | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Institute receiving the enquiry. |  |
| listing_leads | Engagement | New | New | user_id | integer | Yes |  | Foreign key | users.id | Logged-in visitor who sent it, if any. |  |
| listing_leads | Engagement | New | New | parent_name | varchar(150) | No |  | — |  | Name of the person enquiring. |  |
| listing_leads | Engagement | New | New | phone | varchar(30) | No |  | — |  | Contact phone. |  |
| listing_leads | Engagement | New | New | email | varchar(190) | Yes |  | — |  | Contact email. |  |
| listing_leads | Engagement | New | New | grade_interested | varchar(60) | Yes |  | — |  | Class or programme the parent is asking about. |  |
| listing_leads | Engagement | New | New | message | text | Yes |  | — |  | Message from the parent. |  |
| listing_leads | Engagement | New | New | source | varchar(30) | No | &#x27;profile_page&#x27; | — |  | Where the enquiry came from. |  |
| listing_leads | Engagement | New | New | status | varchar(20) | No | &#x27;new&#x27; | Index |  | new, forwarded, contacted, closed or spam. |  |
| listing_leads | Engagement | New | New | student_inquiry_id | integer | Yes |  | Foreign key | student_inquiries.school_id + student_inquiries.id | Internal admission inquiry created from this lead. |  |
| listing_leads | Engagement | New | New | consent_given | tinyint(1) | No | &#x27;0&#x27; | — |  | Parent agreed to share contact details with the institute. |  |
| listing_leads | Engagement | New | New | ip_address | varchar(45) | Yes |  | — |  | IP address (spam control). |  |
| listing_leads | Engagement | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| listing_leads | Engagement | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| reviews | Engagement | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| reviews | Engagement | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Institute being reviewed. |  |
| reviews | Engagement | New | New | user_id | integer | No |  | Foreign key | users.id | Login account that wrote the review. |  |
| reviews | Engagement | New | New | rating | tinyint | No |  | — |  | Star rating from 1 to 5. |  |
| reviews | Engagement | New | New | title | varchar(150) | Yes |  | — |  | Short headline. |  |
| reviews | Engagement | New | New | body | text | No |  | — |  | Review text. |  |
| reviews | Engagement | New | New | relationship | varchar(30) | No | &#x27;parent&#x27; | — |  | parent, student or alumni. |  |
| reviews | Engagement | New | New | status | varchar(20) | No | &#x27;pending&#x27; | Index |  | pending, approved or rejected. |  |
| reviews | Engagement | New | New | moderated_by_user_id | integer | Yes |  | Foreign key | users.id | Platform admin who moderated. |  |
| reviews | Engagement | New | New | moderated_at | datetime | Yes |  | — |  | When it was moderated. |  |
| reviews | Engagement | New | New | school_reply | text | Yes |  | — |  | Public reply from the institute. |  |
| reviews | Engagement | New | New | replied_by_user_id | integer | Yes |  | Foreign key | users.id | Staff member who replied. |  |
| reviews | Engagement | New | New | school_replied_at | datetime | Yes |  | — |  | When the institute replied. |  |
| reviews | Engagement | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| reviews | Engagement | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| saved_schools | Engagement | New | New | user_id | integer | No |  | Primary key; Foreign key | users.id | Login account that saved the institute. |  |
| saved_schools | Engagement | New | New | school_id | integer | No |  | Primary key; Foreign key | schools.id | Institute saved. |  |
| saved_schools | Engagement | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_stats_daily | Engagement | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| school_stats_daily | Engagement | New | New | school_id | integer | No |  | Foreign key; Unique index (school_id, stat_date) | schools.id | Institute the counts belong to. |  |
| school_stats_daily | Engagement | New | New | stat_date | date | No |  | — |  | Day the counts cover. |  |
| school_stats_daily | Engagement | New | New | profile_views | integer | No | &#x27;0&#x27; | — |  | Public page views. |  |
| school_stats_daily | Engagement | New | New | saves | integer | No | &#x27;0&#x27; | — |  | Times saved by visitors. |  |
| school_stats_daily | Engagement | New | New | compare_adds | integer | No | &#x27;0&#x27; | — |  | Times added to compare. |  |
| school_stats_daily | Engagement | New | New | leads | integer | No | &#x27;0&#x27; | — |  | Enquiries received. |  |
| school_stats_daily | Engagement | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_stats_daily | Engagement | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| articles | Content | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| articles | Content | New | New | title | varchar(200) | No |  | — |  | Article title. |  |
| articles | Content | New | New | slug | varchar(220) | No |  | Unique index |  | URL-friendly name. |  |
| articles | Content | New | New | excerpt | varchar(300) | Yes |  | — |  | Short summary for lists and search results. |  |
| articles | Content | New | New | body | longtext | No |  | — |  | Article content. |  |
| articles | Content | New | New | cover_path | varchar(255) | Yes |  | — |  | Stored path of the cover image. |  |
| articles | Content | New | New | author_user_id | integer | Yes |  | Foreign key | users.id | Author&#x27;s login account. |  |
| articles | Content | New | New | status | varchar(20) | No | &#x27;draft&#x27; | Index |  | draft or published. |  |
| articles | Content | New | New | published_at | datetime | Yes |  | — |  | Publication date and time. |  |
| articles | Content | New | New | seo_title | varchar(160) | Yes |  | — |  | Custom search-engine title. |  |
| articles | Content | New | New | seo_description | varchar(300) | Yes |  | — |  | Custom search-engine description. |  |
| articles | Content | New | New | site | varchar(20) | No | &#x27;finder&#x27; | Index |  | Which website shows the article: finder (parent guides) or marketing (blog and news of the school management product). |  |
| articles | Content | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| articles | Content | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| book_inquiries | Community | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| book_inquiries | Community | New | New | book_id | integer | No |  | Foreign key; Index | books.id | Book asked about. |  |
| book_inquiries | Community | New | New | user_id | integer | No |  | Foreign key | users.id | Login account asking. |  |
| book_inquiries | Community | New | New | message | text | No |  | — |  | Message to the owner. |  |
| book_inquiries | Community | New | New | status | varchar(20) | No | &#x27;open&#x27; | — |  | open, accepted or declined. |  |
| book_inquiries | Community | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| book_inquiries | Community | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| books | Community | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| books | Community | New | New | user_id | integer | No |  | Foreign key | users.id | Login account offering the book. |  |
| books | Community | New | New | city_id | integer | No |  | Foreign key | cities.id | City where the book is available. |  |
| books | Community | New | New | area_id | integer | Yes |  | Foreign key | areas.id | Area where the book is available. |  |
| books | Community | New | New | title | varchar(200) | No |  | — |  | Book title. |  |
| books | Community | New | New | author | varchar(150) | Yes |  | — |  | Author. |  |
| books | Community | New | New | subject | varchar(80) | Yes |  | — |  | Subject, for example Physics. |  |
| books | Community | New | New | education_level_id | integer | Yes |  | Foreign key | education_levels.id | Class or level the book is for. |  |
| books | Community | New | New | board_id | integer | Yes |  | Foreign key | boards.id | Board or curriculum the book follows. |  |
| books | Community | New | New | condition | varchar(20) | No | &#x27;good&#x27; | — |  | new, good, fair or worn. |  |
| books | Community | New | New | listing_type | varchar(10) | No | &#x27;sell&#x27; | — |  | sell, swap or donate. |  |
| books | Community | New | New | price | decimal(10,2) | Yes |  | — |  | Asking price in PKR (sell only). |  |
| books | Community | New | New | description | text | Yes |  | — |  | Extra details. |  |
| books | Community | New | New | photo_path | varchar(255) | Yes |  | — |  | Stored path of the photo. |  |
| books | Community | New | New | status | varchar(20) | No | &#x27;available&#x27; | Index |  | available, reserved, given or removed. |  |
| books | Community | New | New | expires_at | datetime | Yes |  | — |  | When the listing is hidden automatically. |  |
| books | Community | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| books | Community | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| study_resources | Community | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| study_resources | Community | New | New | user_id | integer | No |  | Foreign key | users.id | Login account that shared the file. |  |
| study_resources | Community | New | New | school_id | integer | Yes |  | Foreign key | schools.id | Institute the resource relates to, if any. |  |
| study_resources | Community | New | New | title | varchar(200) | No |  | — |  | Resource title. |  |
| study_resources | Community | New | New | subject | varchar(80) | Yes |  | — |  | Subject. |  |
| study_resources | Community | New | New | education_level_id | integer | Yes |  | Foreign key | education_levels.id | Class or level. |  |
| study_resources | Community | New | New | description | text | Yes |  | — |  | What the file contains. |  |
| study_resources | Community | New | New | file_path | varchar(255) | No |  | — |  | Stored path of the uploaded file. |  |
| study_resources | Community | New | New | download_count | integer | No | &#x27;0&#x27; | — |  | Number of downloads. |  |
| study_resources | Community | New | New | status | varchar(20) | No | &#x27;pending&#x27; | Index |  | pending, approved or rejected. |  |
| study_resources | Community | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| study_resources | Community | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| grade_section | Academics | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | grades.school_id + grades.id | School that owns this record; separates data between schools. |  |
| grade_section | Academics | Existing – keep | Existing | grade_id | integer | No |  | Foreign key; Unique index; Index | grades.school_id + grades.id | Grade linked to this row. |  |
| grade_section | Academics | Existing – keep | Existing | section_id | integer | No |  | Foreign key; Unique index; Index | sections.school_id + sections.id | Section linked to this row. |  |
| grade_section | Academics | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| grade_section | Academics | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| grade_subject | Academics | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | grades.school_id + grades.id | School that owns this record; separates data between schools. |  |
| grade_subject | Academics | Existing – keep | Existing | grade_id | integer | No |  | Foreign key; Unique index | grades.school_id + grades.id | Grade linked to this row. |  |
| grade_subject | Academics | Existing – keep | Existing | subject_id | integer | No |  | Foreign key; Unique index; Index | subjects.school_id + subjects.id | Identifier referencing the related subject record. |  |
| grade_subject | Academics | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| grade_subject | Academics | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| grades | Academics | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| grades | Academics | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | schools.id | School that owns this record; separates data between schools. |  |
| grades | Academics | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the grade or year-level lookup records.. |  |
| grades | Academics | Existing – keep | Existing | status | varchar | No | &#x27;active&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| grades | Academics | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| grades | Academics | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| school_sessions | Academics | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| school_sessions | Academics | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| school_sessions | Academics | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the academic sessions / years for a school.. |  |
| school_sessions | Academics | Existing – keep | Existing | starts_on | date | No |  | — |  | First date of the academic session. |  |
| school_sessions | Academics | Existing – keep | Existing | ends_on | date | No |  | — |  | Last date of the academic session. |  |
| school_sessions | Academics | Existing – keep | Existing | is_current | tinyint(1) | No | &#x27;0&#x27; | — |  | Whether this academic session is the school’s current session. |  |
| school_sessions | Academics | Existing – keep | Existing | current_marker | integer | Yes |  | Unique index |  | Database marker used to keep one current academic year per school. |  |
| school_sessions | Academics | Existing – keep | Existing | status | varchar | No | &#x27;active&#x27; | — |  | Current state of the record (allowed values are defined by the application). |  |
| school_sessions | Academics | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| school_sessions | Academics | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| sections | Academics | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| sections | Academics | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | schools.id | School that owns this record; separates data between schools. |  |
| sections | Academics | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the class section lookup records.. |  |
| sections | Academics | Existing – keep | Existing | status | varchar | No | &#x27;active&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| sections | Academics | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| sections | Academics | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| subjects | Academics | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| subjects | Academics | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | schools.id | School that owns this record; separates data between schools. |  |
| subjects | Academics | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the school subject catalog.. |  |
| subjects | Academics | Existing – keep | Existing | status | varchar | No | &#x27;active&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| subjects | Academics | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| subjects | Academics | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| attendance_records | Students | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| attendance_records | Students | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | schools.id | School that owns this record; separates data between schools. |  |
| attendance_records | Students | Existing – keep | Existing | enrollment_id | integer | No |  | Foreign key; Unique index | enrollments.school_id + enrollments.id | Student enrollment linked to this attendance row. |  |
| attendance_records | Students | Existing – keep | Existing | attendance_date | date | No |  | Unique index; Index |  | Date attendance was recorded. |  |
| attendance_records | Students | Existing – keep | Existing | status | varchar | No |  | — |  | Current state of the record (allowed values are defined by the application). |  |
| attendance_records | Students | Existing – keep | Existing | marked_by_user_id | integer | Yes |  | Foreign key | users.id | User account that recorded attendance. |  |
| attendance_records | Students | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| attendance_records | Students | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| enrollments | Students | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| enrollments | Students | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | students.school_id + students.id | School that owns this record; separates data between schools. |  |
| enrollments | Students | Existing – keep | Existing | student_id | integer | No |  | Foreign key; Index | students.school_id + students.id | Student linked to this record. |  |
| enrollments | Students | Existing – keep | Existing | grade_id | integer | No |  | Foreign key | grade_section.school_id + grade_section.grade_id + grade_section.section_id | Grade linked to this row. |  |
| enrollments | Students | Existing – keep | Existing | section_id | integer | No |  | Foreign key | grade_section.school_id + grade_section.grade_id + grade_section.section_id | Section linked to this row. |  |
| enrollments | Students | Existing – keep | Existing | academic_year | varchar | No |  | — |  | Academic year associated with the record. |  |
| enrollments | Students | Existing – keep | Existing | status | varchar | No | &#x27;active&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| enrollments | Students | Existing – keep | Existing | enrolled_on | date | No |  | — |  | Date the student began this enrollment. |  |
| enrollments | Students | Existing – keep | Existing | ended_on | date | Yes |  | — |  | Date this enrollment ended, if applicable. |  |
| enrollments | Students | Existing – keep | Existing | created_at | datetime | Yes |  | Index |  | Date and time this row was created. |  |
| enrollments | Students | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| guardians | Students | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| guardians | Students | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| guardians | Students | Existing – keep | Existing | father_name | varchar | Yes |  | — |  | Father Name field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | father_phone | varchar | Yes |  | — |  | Father Phone field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | father_occupation | varchar | Yes |  | — |  | Father Occupation field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | father_cnic | varchar | Yes |  | Index |  | Father’s national identity number. |  |
| guardians | Students | Existing – keep | Existing | mother_name | varchar | Yes |  | — |  | Mother Name field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | mother_phone | varchar | Yes |  | — |  | Mother Phone field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | mother_occupation | varchar | Yes |  | — |  | Mother Occupation field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | mother_cnic | varchar | Yes |  | Index |  | Mother’s national identity number. |  |
| guardians | Students | Existing – keep | Existing | is_guardian_other | tinyint(1) | No | &#x27;0&#x27; | — |  | Whether a non-parent guardian is specified. |  |
| guardians | Students | Existing – keep | Existing | guardian_name | varchar | Yes |  | — |  | Guardian Name field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | guardian_relation | varchar | Yes |  | — |  | Guardian Relation field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | guardian_email | varchar | Yes |  | — |  | Guardian Email field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | guardian_phone | varchar | Yes |  | — |  | Guardian Phone field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | guardian_occupation | varchar | Yes |  | — |  | Guardian Occupation field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | guardian_cnic | varchar | Yes |  | Index |  | Guardian’s national identity number. |  |
| guardians | Students | Existing – keep | Existing | guardian_address | text | Yes |  | — |  | Guardian Address field for the family and guardian contact details and uploaded documents.. |  |
| guardians | Students | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| guardians | Students | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| guardians | Students | Existing – keep | Existing | deleted_at | datetime | Yes |  | — |  | Soft-delete timestamp; a value means the record is archived/deleted. |  |
| guardians | Students | Existing – keep | Existing | father_doc_path | varchar | Yes |  | — |  | Stored path for the father’s document. |  |
| guardians | Students | Existing – keep | Existing | mother_doc_path | varchar | Yes |  | — |  | Stored path for the mother’s document. |  |
| guardians | Students | Existing – keep | Existing | guardian_doc_path | varchar | Yes |  | — |  | Stored path for the guardian’s document. |  |
| guardians | Students | Existing – keep | Existing | source_parent_id | varchar | Yes |  | Unique index |  | Parent identifier from the imported source data. |  |
| guardians | Students | Existing – keep | Existing | source_data | text | Yes |  | — |  | Original spreadsheet/source fields preserved as JSON. |  |
| student_inquiries | Students | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| student_inquiries | Students | Existing – change | Existing | school_id | integer | No |  | Foreign key; Index | schools.id | School that owns this record; separates data between schools. |  |
| student_inquiries | Students | Existing – change | Existing | first_name | varchar | No |  | — |  | First Name field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Existing | last_name | varchar | No |  | — |  | Last Name field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Existing | guardian_name | varchar | Yes |  | — |  | Guardian Name field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Existing | guardian_phone | varchar | Yes |  | — |  | Guardian Phone field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Existing | guardian_email | varchar | Yes |  | — |  | Guardian Email field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Changed | requested_grade_id | integer | Yes |  | Foreign key | grades.school_id + grades.id | Grade requested by the prospective student. | Now checks the same branch (composite key). |
| student_inquiries | Students | Existing – change | Existing | inquiry_date | date | No |  | Index |  | Date the prospective student inquiry was received. |  |
| student_inquiries | Students | Existing – change | Existing | source | varchar | Yes |  | — |  | Source field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Existing | status | varchar | No | &#x27;new&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| student_inquiries | Students | Existing – change | Existing | notes | text | Yes |  | — |  | Notes field for the prospective student inquiries and admission pipeline records.. |  |
| student_inquiries | Students | Existing – change | Changed | student_id | integer | Yes |  | Foreign key | students.school_id + students.id | Student linked to this record. | Now checks the same branch (composite key). |
| student_inquiries | Students | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| student_inquiries | Students | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| student_inquiries | Students | Existing – change | New | listing_lead_id | integer | Yes |  | Foreign key | listing_leads.school_id + listing_leads.id | Public enquiry this inquiry was created from. |  |
| student_lifecycle_events | Students | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| student_lifecycle_events | Students | Existing – change | Existing | school_id | integer | No |  | Foreign key | schools.id | School that owns this record; separates data between schools. |  |
| student_lifecycle_events | Students | Existing – change | Changed | student_id | integer | No |  | Foreign key; Index | students.school_id + students.id | Student linked to this record. | Now checks the same branch (composite key). |
| student_lifecycle_events | Students | Existing – change | Existing | event_type | varchar | No |  | — |  | Kind of student lifecycle event. |  |
| student_lifecycle_events | Students | Existing – change | Existing | event_date | date | No |  | Index |  | Date the student lifecycle event occurred. |  |
| student_lifecycle_events | Students | Existing – change | Existing | reason | text | Yes |  | — |  | Reason field for the student status timeline such as withdrawal or rejoining.. |  |
| student_lifecycle_events | Students | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| student_lifecycle_events | Students | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| student_transfers | Students | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| student_transfers | Students | New | New | from_school_id | integer | No |  | Foreign key | schools.id | Branch the student left. |  |
| student_transfers | Students | New | New | to_school_id | integer | No |  | Foreign key | schools.id | Branch the student joined. |  |
| student_transfers | Students | New | New | from_student_id | integer | No |  | Foreign key | students.school_id + students.id | Student record in the old branch. |  |
| student_transfers | Students | New | New | to_student_id | integer | No |  | Foreign key | students.school_id + students.id | Student record in the new branch. |  |
| student_transfers | Students | New | New | transfer_date | date | No |  | — |  | Date of the transfer. |  |
| student_transfers | Students | New | New | reason | varchar(255) | Yes |  | — |  | Reason given. |  |
| student_transfers | Students | New | New | transferred_by_user_id | integer | Yes |  | Foreign key | users.id | Staff member who recorded the transfer. |  |
| student_transfers | Students | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| student_transfers | Students | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| students | Students | Existing – change | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| students | Students | Existing – change | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | schools.id | School that owns this record; separates data between schools. |  |
| students | Students | Existing – change | Existing | admission_number | varchar | No |  | Unique index |  | School admission identifier for the student. |  |
| students | Students | Existing – change | Existing | first_name | varchar | No |  | — |  | First Name field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | last_name | varchar | No |  | — |  | Last Name field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | status | varchar | No | &#x27;active&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| students | Students | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| students | Students | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| students | Students | Existing – change | Existing | guardian_id | integer | Yes |  | Foreign key | guardians.school_id + guardians.id | Guardian / family record linked to this row. |  |
| students | Students | Existing – change | Existing | gender | varchar | Yes |  | — |  | Gender field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | religion | varchar | Yes |  | — |  | Religion field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | email | varchar | Yes |  | — |  | Email field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | phone | varchar | Yes |  | — |  | Phone field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | address | text | Yes |  | — |  | Address field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | photo_path | varchar | Yes |  | — |  | Stored path for the uploaded photo. |  |
| students | Students | Existing – change | Existing | birth_date | date | Yes |  | — |  | Date for birth. |  |
| students | Students | Existing – change | Existing | b_form_number | varchar | Yes |  | Unique index |  | Student B-Form / identity document number. |  |
| students | Students | Existing – change | Existing | b_form_document_path | varchar | Yes |  | — |  | Stored path for the student B-Form document. |  |
| students | Students | Existing – change | Existing | admission_date | date | Yes |  | — |  | Date for admission. |  |
| students | Students | Existing – change | Existing | leave_date | date | Yes |  | — |  | Date for leave. |  |
| students | Students | Existing – change | Existing | previous_school_details | text | Yes |  | — |  | Previous School Details field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | note | text | Yes |  | — |  | Note field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | deleted_at | datetime | Yes |  | — |  | Soft-delete timestamp; a value means the record is archived/deleted. |  |
| students | Students | Existing – change | Existing | full_name | varchar | Yes |  | — |  | Full Name field for the student identity, admission, contact, document, and source data.. |  |
| students | Students | Existing – change | Existing | admission_type | varchar | Yes |  | — |  | Admission category from the source record. |  |
| students | Students | Existing – change | Existing | previous_admission_number | varchar | Yes |  | — |  | Admission number from the student’s previous record/school. |  |
| students | Students | Existing – change | Removed | admitted_class | varchar | Yes |  | — |  | Admitted Class field for the student identity, admission, contact, document, and source data.. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | current_class_label | varchar | Yes |  | — |  | Current Class Label field for the student identity, admission, contact, document, and source data.. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | caste_or_tribe | varchar | Yes |  | — |  | Caste Or Tribe field for the student identity, admission, contact, document, and source data.. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | fee_rate | numeric | Yes |  | — |  | Source-reported fee rate. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | fee_amount | numeric | Yes |  | — |  | Source-reported fee amount. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | fee_waiver | varchar | Yes |  | — |  | Source-reported fee waiver amount or value. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | arrears_due | numeric | Yes |  | — |  | Source-reported arrears balance due. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | withdrawn_from_class | varchar | Yes |  | — |  | Withdrawn From Class field for the student identity, admission, contact, document, and source data.. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | source_marks_obtained | numeric | Yes |  | — |  | Marks obtained as reported in the source record. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | source_marks_percentage | numeric | Yes |  | — |  | Marks percentage as reported in the source record. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | source_data | text | Yes |  | — |  | Original spreadsheet/source fields preserved as JSON. | Removed by you (import leftovers and fee fields). |
| students | Students | Existing – change | Removed | source_guardian_data | text | Yes |  | — |  | Original guardian fields preserved as JSON. | Removed by you (import leftovers and fee fields). |
| departments | Human resources | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| departments | Human resources | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| departments | Human resources | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the staff department lookup records.. |  |
| departments | Human resources | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| departments | Human resources | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| designations | Human resources | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| designations | Human resources | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| designations | Human resources | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the staff designation lookup records.. |  |
| designations | Human resources | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| designations | Human resources | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| leave_types | Human resources | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| leave_types | Human resources | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| leave_types | Human resources | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the leave categories available to staff.. |  |
| leave_types | Human resources | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| leave_types | Human resources | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| staff | Human resources | Existing – change | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| staff | Human resources | Existing – change | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| staff | Human resources | Existing – change | Changed | user_id | integer | Yes |  | Foreign key; Unique index (school_id, user_id) | users.id | Login account linked to this record. | Unique per branch instead of unique overall. |
| staff | Human resources | Existing – change | Changed | designation_id | integer | No |  | Foreign key | designations.school_id + designations.id | Identifier referencing the related designation record. | Now checks the same branch (composite key). |
| staff | Human resources | Existing – change | Changed | department_id | integer | No |  | Foreign key | departments.school_id + departments.id | Identifier referencing the related department record. | Now checks the same branch (composite key). |
| staff | Human resources | Existing – change | Existing | staff_no | varchar | No |  | Unique index |  | Staff No field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | first_name | varchar | No |  | — |  | First Name field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | last_name | varchar | No |  | — |  | Last Name field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | father_name | varchar | Yes |  | — |  | Father Name field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | mother_name | varchar | Yes |  | — |  | Mother Name field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | gender | varchar | No |  | — |  | Gender field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | marital_status | varchar | Yes |  | — |  | Marital Status field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | birth_date | date | No |  | — |  | Date for birth. |  |
| staff | Human resources | Existing – change | Existing | joining_date | date | No |  | — |  | Date for joining. |  |
| staff | Human resources | Existing – change | Existing | email | varchar | Yes |  | Unique index |  | Email field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | phone | varchar | Yes |  | — |  | Phone field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | emergency_contact_number | varchar | Yes |  | — |  | Emergency Contact Number field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | cnic | varchar | Yes |  | Unique index |  | Cnic field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | photo_path | varchar | Yes |  | — |  | Stored path for the uploaded photo. |  |
| staff | Human resources | Existing – change | Existing | address | text | Yes |  | — |  | Address field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | qualification | text | Yes |  | — |  | Qualification field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | work_experience | text | Yes |  | — |  | Work Experience field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | basic_salary | numeric | No | &#x27;0&#x27; | — |  | Basic Salary field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | bank_account_title | varchar | Yes |  | — |  | Bank Account Title field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | bank_name | varchar | Yes |  | — |  | Bank Name field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | bank_branch_name | varchar | Yes |  | — |  | Bank Branch Name field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | bank_account_number | text | Yes |  | — |  | Bank Account Number field for the staff profile and employment details.. |  |
| staff | Human resources | Existing – change | Existing | status | varchar | No | &#x27;active&#x27; | — |  | Current state of the record (allowed values are defined by the application). |  |
| staff | Human resources | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| staff | Human resources | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| staff | Human resources | Existing – change | Existing | deleted_at | datetime | Yes |  | — |  | Soft-delete timestamp; a value means the record is archived/deleted. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | staff_id | integer | No |  | Foreign key; Unique index | staff.id | Staff member linked to this row. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | leave_type_id | integer | No |  | Foreign key; Unique index | leave_types.id | Identifier referencing the related leave type record. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | school_session_id | integer | No |  | Foreign key; Unique index | school_sessions.id | Academic session linked to this row. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | allocated | numeric | No |  | — |  | Leave days allocated for this staff member. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | used | numeric | No | &#x27;0&#x27; | — |  | Leave days used by this staff member. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| staff_leave_balances | Human resources | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the percentage or fixed discounts for fee collection.. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | discount_type | varchar | No |  | — |  | Whether discount value is fixed or percentage-based. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | value | numeric | No |  | — |  | Value field for the percentage or fixed discounts for fee collection.. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | description | text | Yes |  | — |  | Description field for the percentage or fixed discounts for fee collection.. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| fee_discounts | Fees and receipts | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| fee_group_grade | Fees and receipts | Existing – change | Changed | fee_group_id | integer | No |  | Primary key; Foreign key | fee_groups.school_id + fee_groups.id | Fee group linked to this fee setup. | Now checks the same branch (composite key). |
| fee_group_grade | Fees and receipts | Existing – change | Changed | grade_id | integer | No |  | Primary key; Foreign key; Index | grades.school_id + grades.id | Grade linked to this row. | Now checks the same branch (composite key). |
| fee_group_grade | Fees and receipts | Existing – change | New | school_id | integer | No |  | Foreign key | schools.id | Branch; makes sure a fee group only contains classes of its own branch (fill from fee_groups when migrating). |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the reusable fee group definitions.. |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | description | text | Yes |  | — |  | Description field for the reusable fee group definitions.. |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | allows_discount | tinyint(1) | No | &#x27;1&#x27; | — |  | Yes/no flag: allows discount. |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| fee_groups | Fees and receipts | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| fee_invoices | Fees and receipts | Existing – change | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| fee_invoices | Fees and receipts | Existing – change | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | students.school_id + students.id | School that owns this record; separates data between schools. |  |
| fee_invoices | Fees and receipts | Existing – change | Existing | student_id | integer | No |  | Foreign key; Unique index | students.school_id + students.id | Student linked to this record. |  |
| fee_invoices | Fees and receipts | Existing – change | Removed | fee_type_id | integer | No |  | Foreign key; Unique index | legacy_fee_types.school_id + legacy_fee_types.id | Fee category linked to this fee setup or invoice. | Replaced by monthly invoice columns after legacy_fee_types was dropped. |
| fee_invoices | Fees and receipts | Existing – change | Removed | academic_year | varchar | No |  | Unique index; Index |  | Academic year associated with the record. | Replaced by monthly invoice columns after legacy_fee_types was dropped. |
| fee_invoices | Fees and receipts | Existing – change | Removed | description | varchar | No |  | — |  | Description field for the historical student-level fee invoices.. | Replaced by monthly invoice columns after legacy_fee_types was dropped. |
| fee_invoices | Fees and receipts | Existing – change | Removed | amount_cents | integer | No |  | — |  | Money amount stored in integer cents for historical data. | Replaced by monthly invoice columns after legacy_fee_types was dropped. |
| fee_invoices | Fees and receipts | Existing – change | Existing | due_date | date | Yes |  | — |  | Date for due. |  |
| fee_invoices | Fees and receipts | Existing – change | Existing | status | varchar | No | &#x27;open&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| fee_invoices | Fees and receipts | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| fee_invoices | Fees and receipts | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| fee_invoices | Fees and receipts | Existing – change | New | invoice_no | varchar(30) | No |  | Unique index (school_id, invoice_no) |  | Invoice number shown to parents, for example INV-2026-09-0042. |  |
| fee_invoices | Fees and receipts | Existing – change | New | school_session_id | integer | No |  | Foreign key | school_sessions.school_id + school_sessions.id | Academic session the invoice belongs to. |  |
| fee_invoices | Fees and receipts | Existing – change | New | billing_month | varchar(7) | No |  | Unique index (school_id, student_id, billing_month) |  | Month billed, for example 2026-09. One invoice per student per month. |  |
| fee_invoices | Fees and receipts | Existing – change | New | subtotal | decimal(12,2) | No | &#x27;0&#x27; | — |  | Sum of the charges before discount and fine (PKR). |  |
| fee_invoices | Fees and receipts | Existing – change | New | discount_total | decimal(12,2) | No | &#x27;0&#x27; | — |  | Total discount (PKR). |  |
| fee_invoices | Fees and receipts | Existing – change | New | fine_total | decimal(12,2) | No | &#x27;0&#x27; | — |  | Late fine added (PKR). |  |
| fee_invoices | Fees and receipts | Existing – change | New | total_amount | decimal(12,2) | No | &#x27;0&#x27; | — |  | Amount payable: subtotal minus discount plus fine (PKR). |  |
| fee_invoices | Fees and receipts | Existing – change | New | paid_total | decimal(12,2) | No | &#x27;0&#x27; | — |  | Total paid so far, kept up to date from fee_payments (PKR). |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | school_id | integer | No |  | Foreign key | schools.id | School that owns this record; separates data between schools. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | school_session_id | integer | No |  | Foreign key; Unique index | school_sessions.school_id + school_sessions.id | Academic session linked to this row. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | fee_group_id | integer | No |  | Foreign key; Unique index | fee_groups.school_id + fee_groups.id | Fee group linked to this fee setup. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | fee_type_id | integer | No |  | Foreign key; Unique index | fee_types.school_id + fee_types.id | Fee category linked to this fee setup or invoice. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | due_date | date | No |  | — |  | Date for due. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | amount | numeric | No |  | — |  | Monetary amount for the fee or expense record. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | fine_type | varchar | No | &#x27;none&#x27; | — |  | Whether the fine is none, fixed, or percentage-based. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | fine_value | numeric | No | &#x27;0&#x27; | — |  | Fine amount or rate, interpreted using fine_type. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| fee_masters | Fees and receipts | Existing – keep | Existing | billing_month | varchar | No | &#x27;1970-01&#x27; | Unique index |  | Month this fee rule, charge, or snapshot applies to (usually YYYY-MM). |  |
| fee_payments | Fees and receipts | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| fee_payments | Fees and receipts | Existing – change | Existing | school_id | integer | No |  | Foreign key; Index | fee_invoices.school_id + fee_invoices.id | School that owns this record; separates data between schools. |  |
| fee_payments | Fees and receipts | Existing – change | Existing | fee_invoice_id | integer | No |  | Foreign key | fee_invoices.school_id + fee_invoices.id | Invoice linked to this payment. |  |
| fee_payments | Fees and receipts | Existing – change | Removed | amount_cents | integer | No |  | — |  | Money amount stored in integer cents for historical data. | Replaced by amount (rupees). |
| fee_payments | Fees and receipts | Existing – change | Existing | paid_on | date | No |  | Index |  | Date for paid. |  |
| fee_payments | Fees and receipts | Existing – change | Existing | reference | varchar | Yes |  | — |  | Reference field for the payments recorded against historical invoices.. |  |
| fee_payments | Fees and receipts | Existing – change | Existing | recorded_by_user_id | integer | Yes |  | Foreign key | users.id | Identifier referencing the related recorded by user record. |  |
| fee_payments | Fees and receipts | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| fee_payments | Fees and receipts | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| fee_payments | Fees and receipts | Existing – change | New | amount | decimal(12,2) | No |  | — |  | Amount received (PKR). Replaces amount_cents so all money fields use the same unit. |  |
| fee_payments | Fees and receipts | Existing – change | New | payment_method | varchar(20) | No | &#x27;cash&#x27; | — |  | cash, bank_transfer, cheque, jazzcash, easypaisa or other. |  |
| fee_types | Fees and receipts | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID); Unique index |  | Unique identifier for this row. |  |
| fee_types | Fees and receipts | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| fee_types | Fees and receipts | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the current fee catalog items.. |  |
| fee_types | Fees and receipts | Existing – keep | Existing | description | text | Yes |  | — |  | Description field for the current fee catalog items.. |  |
| fee_types | Fees and receipts | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| fee_types | Fees and receipts | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | school_id | integer | No |  | Foreign key; Index | schools.id | School that owns this record; separates data between schools. |  |
| student_fee_charges | Fees and receipts | Existing – change | Changed | fee_master_id | integer | No |  | Foreign key; Unique index | fee_masters.school_id + fee_masters.id | Reusable fee rule that generated this student charge. | Now checks the same branch (composite key). |
| student_fee_charges | Fees and receipts | Existing – change | Changed | school_session_id | integer | No |  | Foreign key; Index | school_sessions.school_id + school_sessions.id | Academic session linked to this row. | Now checks the same branch (composite key). |
| student_fee_charges | Fees and receipts | Existing – change | Existing | student_id | integer | No |  | Foreign key; Unique index | students.school_id + students.id | Student linked to this record. |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | billing_month | varchar | No |  | Unique index; Index |  | Month this fee rule, charge, or snapshot applies to (usually YYYY-MM). |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | amount | numeric | No |  | — |  | Monetary amount for the fee or expense record. |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | due_date | date | No |  | — |  | Date for due. |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | status | varchar | No | &#x27;open&#x27; | Index |  | Current state of the record (allowed values are defined by the application). |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| student_fee_charges | Fees and receipts | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| student_fee_charges | Fees and receipts | Existing – change | New | fee_invoice_id | integer | Yes |  | Foreign key | fee_invoices.school_id + fee_invoices.id | Invoice this charge appears on (blank until the invoice is created). |  |
| student_fee_charges | Fees and receipts | Existing – change | New | discount_amount | decimal(12,2) | No | &#x27;0&#x27; | — |  | Discount given on this charge (PKR). |  |
| student_fee_discounts | Fees and receipts | New | New | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| student_fee_discounts | Fees and receipts | New | New | school_id | integer | No |  | Foreign key; Index | schools.id | Branch; separates data between branches. |  |
| student_fee_discounts | Fees and receipts | New | New | student_id | integer | No |  | Foreign key | students.school_id + students.id | Student who receives the discount. |  |
| student_fee_discounts | Fees and receipts | New | New | fee_discount_id | integer | No |  | Foreign key | fee_discounts.school_id + fee_discounts.id | Discount rule applied. |  |
| student_fee_discounts | Fees and receipts | New | New | school_session_id | integer | Yes |  | Foreign key | school_sessions.school_id + school_sessions.id | Session the discount is valid for (blank = all). |  |
| student_fee_discounts | Fees and receipts | New | New | starts_on | date | Yes |  | — |  | First day the discount applies. |  |
| student_fee_discounts | Fees and receipts | New | New | ends_on | date | Yes |  | — |  | Last day the discount applies. |  |
| student_fee_discounts | Fees and receipts | New | New | note | varchar(255) | Yes |  | — |  | Reason or approval note. |  |
| student_fee_discounts | Fees and receipts | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| student_fee_discounts | Fees and receipts | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| expense_heads | Expenses | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| expense_heads | Expenses | Existing – keep | Existing | school_id | integer | No |  | Foreign key; Unique index | schools.id | School that owns this record; separates data between schools. |  |
| expense_heads | Expenses | Existing – keep | Existing | title | varchar | No |  | Unique index |  | Title field for the expense categories.. |  |
| expense_heads | Expenses | Existing – keep | Existing | description | text | Yes |  | — |  | Description field for the expense categories.. |  |
| expense_heads | Expenses | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| expense_heads | Expenses | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| expenses | Expenses | Existing – change | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| expenses | Expenses | Existing – change | Existing | school_id | integer | No |  | Foreign key; Unique index; Index | schools.id | School that owns this record; separates data between schools. |  |
| expenses | Expenses | Existing – change | Changed | expense_head_id | integer | No |  | Foreign key | expense_heads.school_id + expense_heads.id | Expense category linked to this expense. | Now checks the same branch (composite key). |
| expenses | Expenses | Existing – change | Existing | name | varchar | No |  | — |  | Name field for the dated expense entries, amounts, and optional attachment paths.. |  |
| expenses | Expenses | Existing – change | Existing | description | text | Yes |  | — |  | Description field for the dated expense entries, amounts, and optional attachment paths.. |  |
| expenses | Expenses | Existing – change | Existing | invoice_number | varchar | Yes |  | Unique index |  | Invoice Number field for the dated expense entries, amounts, and optional attachment paths.. |  |
| expenses | Expenses | Existing – change | Existing | expense_date | date | No |  | Index |  | Date for expense. |  |
| expenses | Expenses | Existing – change | Existing | amount | numeric | No |  | — |  | Monetary amount for the fee or expense record. |  |
| expenses | Expenses | Existing – change | Existing | attachment_path | varchar | Yes |  | — |  | Stored file path for the attachment. |  |
| expenses | Expenses | Existing – change | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| expenses | Expenses | Existing – change | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| model_has_permissions | Permissions | Existing – keep | Existing | permission_id | integer | No |  | Primary key; Foreign key | permissions.id | Permission record linked through this assignment. |  |
| model_has_permissions | Permissions | Existing – keep | Existing | model_type | varchar | No |  | Primary key; Index |  | Polymorphic model class name for the linked account/record. |  |
| model_has_permissions | Permissions | Existing – keep | Existing | model_id | integer | No |  | Primary key; Index |  | ID of the polymorphic model record. |  |
| model_has_permissions | Permissions | Existing – keep | Existing | team_id | integer | No |  | Primary key; Index |  | Permission team scope; in this application, a school ID for school roles. |  |
| model_has_roles | Permissions | Existing – keep | Existing | role_id | integer | No |  | Primary key; Foreign key | roles.id | Role record linked through this assignment. |  |
| model_has_roles | Permissions | Existing – keep | Existing | model_type | varchar | No |  | Primary key; Index |  | Polymorphic model class name for the linked account/record. |  |
| model_has_roles | Permissions | Existing – keep | Existing | model_id | integer | No |  | Primary key; Index |  | ID of the polymorphic model record. |  |
| model_has_roles | Permissions | Existing – keep | Existing | team_id | integer | No |  | Primary key; Index |  | Permission team scope; in this application, a school ID for school roles. |  |
| permissions | Permissions | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| permissions | Permissions | Existing – keep | Existing | name | varchar | No |  | Unique index |  | Name field for the individual abilities that can be assigned to roles or users.. |  |
| permissions | Permissions | Existing – keep | Existing | guard_name | varchar | No |  | Unique index |  | Guard Name field for the individual abilities that can be assigned to roles or users.. |  |
| permissions | Permissions | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| permissions | Permissions | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| role_has_permissions | Permissions | Existing – keep | Existing | permission_id | integer | No |  | Primary key; Foreign key | permissions.id | Permission record linked through this assignment. |  |
| role_has_permissions | Permissions | Existing – keep | Existing | role_id | integer | No |  | Primary key; Foreign key | roles.id | Role record linked through this assignment. |  |
| roles | Permissions | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| roles | Permissions | Existing – keep | Existing | team_id | integer | Yes |  | Unique index; Index |  | Permission team scope; in this application, a school ID for school roles. |  |
| roles | Permissions | Existing – keep | Existing | name | varchar | No |  | Unique index |  | Name field for the named permission bundles; team_id scopes school roles.. |  |
| roles | Permissions | Existing – keep | Existing | guard_name | varchar | No |  | Unique index |  | Guard Name field for the named permission bundles; team_id scopes school roles.. |  |
| roles | Permissions | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| roles | Permissions | Existing – keep | Existing | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| cache | Laravel infrastructure | Existing – keep | Existing | key | varchar | No |  | Primary key |  | Key field for the application cache values.. |  |
| cache | Laravel infrastructure | Existing – keep | Existing | value | text | No |  | — |  | Value field for the application cache values.. |  |
| cache | Laravel infrastructure | Existing – keep | Existing | expiration | integer | No |  | Index |  | Expiration field for the application cache values.. |  |
| cache_locks | Laravel infrastructure | Existing – keep | Existing | key | varchar | No |  | Primary key |  | Key field for the locks used by the application cache.. |  |
| cache_locks | Laravel infrastructure | Existing – keep | Existing | owner | varchar | No |  | — |  | Owner field for the locks used by the application cache.. |  |
| cache_locks | Laravel infrastructure | Existing – keep | Existing | expiration | integer | No |  | Index |  | Expiration field for the locks used by the application cache.. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | uuid | varchar | No |  | Unique index |  | Uuid field for the queue jobs that failed processing.. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | connection | varchar | No |  | Index |  | Connection field for the queue jobs that failed processing.. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | queue | varchar | No |  | Index |  | Queue field for the queue jobs that failed processing.. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | payload | text | No |  | — |  | Payload field for the queue jobs that failed processing.. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | exception | text | No |  | — |  | Exception field for the queue jobs that failed processing.. |  |
| failed_jobs | Laravel infrastructure | Existing – keep | Existing | failed_at | datetime | No | CURRENT_TIMESTAMP | Index |  | Date and time for failed. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | id | varchar | No |  | Primary key |  | Unique identifier for this row. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | name | varchar | No |  | — |  | Name field for the metadata for grouped queue jobs.. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | total_jobs | integer | No |  | — |  | Total Jobs field for the metadata for grouped queue jobs.. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | pending_jobs | integer | No |  | — |  | Pending Jobs field for the metadata for grouped queue jobs.. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | failed_jobs | integer | No |  | — |  | Failed Jobs field for the metadata for grouped queue jobs.. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | failed_job_ids | text | No |  | — |  | Failed Job Ids field for the metadata for grouped queue jobs.. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | options | text | Yes |  | — |  | Options field for the metadata for grouped queue jobs.. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | cancelled_at | integer | Yes |  | — |  | Date and time for cancelled. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | created_at | integer | No |  | — |  | Date and time this row was created. |  |
| job_batches | Laravel infrastructure | Existing – keep | Existing | finished_at | integer | Yes |  | — |  | Date and time for finished. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | queue | varchar | No |  | Index |  | Queue field for the queued background jobs waiting to run.. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | payload | text | No |  | — |  | Payload field for the queued background jobs waiting to run.. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | attempts | integer | No |  | — |  | Attempts field for the queued background jobs waiting to run.. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | reserved_at | integer | Yes |  | — |  | Date and time for reserved. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | available_at | integer | No |  | — |  | Date and time for available. |  |
| jobs | Laravel infrastructure | Existing – keep | Existing | created_at | integer | No |  | — |  | Date and time this row was created. |  |
| migrations | Laravel infrastructure | Existing – keep | Existing | id | integer | No |  | Primary key (auto ID) |  | Unique identifier for this row. |  |
| migrations | Laravel infrastructure | Existing – keep | Existing | migration | varchar | No |  | — |  | Migration field for the laravel migration execution history.. |  |
| migrations | Laravel infrastructure | Existing – keep | Existing | batch | integer | No |  | — |  | Batch field for the laravel migration execution history.. |  |
| notifications | Laravel infrastructure | New | New | id | char(36) | No |  | Primary key |  | Unique notification ID (UUID). |  |
| notifications | Laravel infrastructure | New | New | type | varchar(255) | No |  | — |  | Notification class name. |  |
| notifications | Laravel infrastructure | New | New | notifiable_type | varchar(255) | No |  | Index (notifiable_type, notifiable_id) |  | Kind of record notified (normally User). |  |
| notifications | Laravel infrastructure | New | New | notifiable_id | integer | No |  | — |  | ID of the record notified. |  |
| notifications | Laravel infrastructure | New | New | data | text | No |  | — |  | Notification content as JSON. |  |
| notifications | Laravel infrastructure | New | New | read_at | datetime | Yes |  | — |  | When the notification was read. |  |
| notifications | Laravel infrastructure | New | New | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| notifications | Laravel infrastructure | New | New | updated_at | datetime | Yes |  | — |  | Date and time this row was last updated. |  |
| password_reset_tokens | Laravel infrastructure | Existing – keep | Existing | email | varchar | No |  | Primary key |  | Email field for the password reset tokens for login accounts.. |  |
| password_reset_tokens | Laravel infrastructure | Existing – keep | Existing | token | varchar | No |  | — |  | Token field for the password reset tokens for login accounts.. |  |
| password_reset_tokens | Laravel infrastructure | Existing – keep | Existing | created_at | datetime | Yes |  | — |  | Date and time this row was created. |  |
| sessions | Laravel infrastructure | Existing – keep | Existing | id | varchar | No |  | Primary key |  | Unique identifier for this row. |  |
| sessions | Laravel infrastructure | Existing – keep | Existing | user_id | integer | Yes |  | Index |  | Login account linked to this record. |  |
| sessions | Laravel infrastructure | Existing – keep | Existing | ip_address | varchar | Yes |  | — |  | Ip Address field for the web session state for signed-in users.. |  |
| sessions | Laravel infrastructure | Existing – keep | Existing | user_agent | text | Yes |  | — |  | User Agent field for the web session state for signed-in users.. |  |
| sessions | Laravel infrastructure | Existing – keep | Existing | payload | text | No |  | — |  | Payload field for the web session state for signed-in users.. |  |
| sessions | Laravel infrastructure | Existing – keep | Existing | last_activity | integer | No |  | Index |  | Last Activity field for the web session state for signed-in users.. |  |

### Relationships

Foreign-key relationships – version 4

Status 'Changed' = an existing link tightened to a same-branch rule. 'New' = added.

| From table | From column(s) | To table | To column(s) | On delete | On update | Status | Note |
| --- | --- | --- | --- | --- | --- | --- | --- |
| areas | city_id | cities | id | cascade | no action | New |  |
| articles | author_user_id | users | id | set null | no action | New |  |
| attendance_records | marked_by_user_id | users | id | set null | no action | Existing |  |
| attendance_records | school_id | schools | id | cascade | no action | Existing |  |
| attendance_records | school_id, enrollment_id | enrollments | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| book_inquiries | book_id | books | id | cascade | no action | New |  |
| book_inquiries | user_id | users | id | cascade | no action | New |  |
| books | area_id | areas | id | set null | no action | New |  |
| books | board_id | boards | id | set null | no action | New |  |
| books | city_id | cities | id | restrict | no action | New |  |
| books | education_level_id | education_levels | id | set null | no action | New |  |
| books | user_id | users | id | cascade | no action | New |  |
| courses | area_id | areas | id | set null | no action | New |  |
| courses | city_id | cities | id | restrict | no action | New |  |
| courses | course_category_id | course_categories | id | set null | no action | New |  |
| courses | school_id | schools | id | set null | no action | New |  |
| demo_requests | assigned_to_user_id | users | id | set null | no action | New |  |
| demo_requests | school_group_id | school_groups | id | set null | no action | New |  |
| departments | school_id | schools | id | restrict | no action | Existing |  |
| designations | school_id | schools | id | restrict | no action | Existing |  |
| device_tokens | user_id | users | id | cascade | no action | New |  |
| enrollments | school_id, grade_id, section_id | grade_section | school_id, grade_id, section_id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| enrollments | school_id, student_id | students | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| events | city_id | cities | id | set null | no action | New |  |
| events | created_by_user_id | users | id | set null | no action | New |  |
| events | school_id | schools | id | cascade | no action | New |  |
| expense_heads | school_id | schools | id | cascade | no action | Existing |  |
| expenses | school_id | schools | id | cascade | no action | Existing |  |
| expenses | school_id, expense_head_id | expense_heads | school_id, id | restrict | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| fee_discounts | school_id | schools | id | restrict | no action | Existing |  |
| fee_group_grade | school_id | schools | id | cascade | no action | New |  |
| fee_group_grade | school_id, fee_group_id | fee_groups | school_id, id | cascade | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| fee_group_grade | school_id, grade_id | grades | school_id, id | restrict | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| fee_groups | school_id | schools | id | restrict | no action | Existing |  |
| fee_invoices | school_id, school_session_id | school_sessions | school_id, id | restrict | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| fee_invoices | school_id, student_id | students | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| fee_masters | school_id | schools | id | restrict | no action | Existing |  |
| fee_masters | school_id, fee_group_id | fee_groups | school_id, id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| fee_masters | school_id, fee_type_id | fee_types | school_id, id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| fee_masters | school_id, school_session_id | school_sessions | school_id, id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| fee_payments | recorded_by_user_id | users | id | set null | no action | Existing |  |
| fee_payments | school_id, fee_invoice_id | fee_invoices | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| fee_types | school_id | schools | id | restrict | no action | Existing |  |
| grade_section | school_id, grade_id | grades | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| grade_section | school_id, section_id | sections | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| grade_subject | school_id, grade_id | grades | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| grade_subject | school_id, subject_id | subjects | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| grades | school_id | schools | id | cascade | no action | Existing |  |
| guardians | school_id | schools | id | cascade | no action | Existing |  |
| leave_types | school_id | schools | id | restrict | no action | Existing |  |
| listing_claims | reviewed_by_user_id | users | id | set null | no action | New |  |
| listing_claims | school_id | schools | id | cascade | no action | New |  |
| listing_claims | user_id | users | id | cascade | no action | New |  |
| listing_leads | school_id | schools | id | cascade | no action | New |  |
| listing_leads | school_id, student_inquiry_id | student_inquiries | school_id, id | set null | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| listing_leads | user_id | users | id | set null | no action | New |  |
| model_has_permissions | permission_id | permissions | id | cascade | no action | Existing |  |
| model_has_roles | role_id | roles | id | cascade | no action | Existing |  |
| module_dependencies | module_id | modules | id | cascade | no action | New |  |
| module_dependencies | requires_module_id | modules | id | cascade | no action | New |  |
| otp_codes | user_id | users | id | cascade | no action | New |  |
| plan_limits | plan_id | plans | id | cascade | no action | New |  |
| plan_modules | module_id | modules | id | cascade | no action | New |  |
| plan_modules | plan_id | plans | id | cascade | no action | New |  |
| programs | school_id | schools | id | cascade | no action | New |  |
| reviews | moderated_by_user_id | users | id | set null | no action | New |  |
| reviews | replied_by_user_id | users | id | set null | no action | New |  |
| reviews | school_id | schools | id | cascade | no action | New |  |
| reviews | user_id | users | id | cascade | no action | New |  |
| role_has_permissions | permission_id | permissions | id | cascade | no action | Existing |  |
| role_has_permissions | role_id | roles | id | cascade | no action | Existing |  |
| saved_schools | school_id | schools | id | cascade | no action | New |  |
| saved_schools | user_id | users | id | cascade | no action | New |  |
| school_board | board_id | boards | id | cascade | no action | New |  |
| school_board | school_id | schools | id | cascade | no action | New |  |
| school_education_level | education_level_id | education_levels | id | cascade | no action | New |  |
| school_education_level | school_id | schools | id | cascade | no action | New |  |
| school_facility | facility_id | facilities | id | cascade | no action | New |  |
| school_facility | school_id | schools | id | cascade | no action | New |  |
| school_group_user | school_group_id | school_groups | id | cascade | no action | New |  |
| school_group_user | user_id | users | id | cascade | no action | New |  |
| school_listing_fees | education_level_id | education_levels | id | set null | no action | New |  |
| school_listing_fees | school_id | schools | id | cascade | no action | New |  |
| school_media | school_id | schools | id | cascade | no action | New |  |
| school_media | uploaded_by_user_id | users | id | set null | no action | New |  |
| school_modules | enabled_by_user_id | users | id | set null | no action | New |  |
| school_modules | module_id | modules | id | cascade | no action | New |  |
| school_modules | school_id | schools | id | cascade | no action | New |  |
| school_pages | school_id | schools | id | cascade | no action | New |  |
| school_sessions | school_id | schools | id | restrict | no action | Existing |  |
| school_site_settings | school_id | schools | id | cascade | no action | New |  |
| school_site_settings | site_theme_id | site_themes | id | restrict | no action | New |  |
| school_stats_daily | school_id | schools | id | cascade | no action | New |  |
| school_subscriptions | plan_id | plans | id | restrict | no action | New |  |
| school_subscriptions | school_group_id | school_groups | id | cascade | no action | New |  |
| school_user | school_id | schools | id | cascade | no action | Existing |  |
| school_user | user_id | users | id | cascade | no action | Existing |  |
| schools | area_id | areas | id | set null | no action | New |  |
| schools | city_id | cities | id | restrict | no action | New |  |
| schools | group_id | school_groups | id | set null | no action | New |  |
| sections | school_id | schools | id | cascade | no action | Existing |  |
| staff | school_id | schools | id | restrict | no action | Existing |  |
| staff | school_id, department_id | departments | school_id, id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| staff | school_id, department_id | departments | school_id, id | restrict | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| staff | school_id, designation_id | designations | school_id, id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| staff | school_id, designation_id | designations | school_id, id | restrict | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| staff | user_id | users | id | restrict | no action | Existing |  |
| staff_leave_balances | leave_type_id | leave_types | id | restrict | no action | Existing |  |
| staff_leave_balances | school_session_id | school_sessions | id | restrict | no action | Existing |  |
| staff_leave_balances | staff_id | staff | id | cascade | no action | Existing |  |
| student_fee_charges | school_id | schools | id | restrict | no action | Existing |  |
| student_fee_charges | school_id, fee_invoice_id | fee_invoices | school_id, id | set null | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_fee_charges | school_id, fee_master_id | fee_masters | school_id, id | restrict | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_fee_charges | school_id, school_session_id | school_sessions | school_id, id | restrict | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_fee_charges | school_id, student_id | students | school_id, id | cascade | no action | Existing | Composite relationship (often enforces same-school ownership). |
| student_fee_discounts | school_id | schools | id | cascade | no action | New |  |
| student_fee_discounts | school_id, fee_discount_id | fee_discounts | school_id, id | cascade | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_fee_discounts | school_id, school_session_id | school_sessions | school_id, id | restrict | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_fee_discounts | school_id, student_id | students | school_id, id | cascade | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_inquiries | school_id | schools | id | cascade | no action | Existing |  |
| student_inquiries | school_id, listing_lead_id | listing_leads | school_id, id | set null | no action | New | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_inquiries | school_id, requested_grade_id | grades | school_id, id | set null | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_inquiries | school_id, student_id | students | school_id, id | set null | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_lifecycle_events | school_id | schools | id | cascade | no action | Existing |  |
| student_lifecycle_events | school_id, student_id | students | school_id, id | cascade | no action | Changed | Same-branch rule: the link includes school_id, so a record cannot point at another branch&#x27;s record. Parent needs a unique index on (school_id, id). |
| student_transfers | from_school_id | schools | id | restrict | no action | New |  |
| student_transfers | from_school_id, from_student_id | students | school_id, id | restrict | no action | New | Student must belong to the branch named in from_school_id. |
| student_transfers | to_school_id | schools | id | restrict | no action | New |  |
| student_transfers | to_school_id, to_student_id | students | school_id, id | restrict | no action | New | Student must belong to the branch named in to_school_id. |
| student_transfers | transferred_by_user_id | users | id | set null | no action | New |  |
| students | school_id | schools | id | cascade | no action | Existing |  |
| students | school_id, guardian_id | guardians | school_id, id | restrict | no action | Existing | Composite relationship (often enforces same-school ownership). |
| study_resources | education_level_id | education_levels | id | set null | no action | New |  |
| study_resources | school_id | schools | id | set null | no action | New |  |
| study_resources | user_id | users | id | cascade | no action | New |  |
| subjects | school_id | schools | id | cascade | no action | Existing |  |
| subscription_invoice_lines | module_id | modules | id | set null | no action | New |  |
| subscription_invoice_lines | plan_id | plans | id | set null | no action | New |  |
| subscription_invoice_lines | school_id | schools | id | set null | no action | New |  |
| subscription_invoice_lines | subscription_invoice_id | subscription_invoices | id | cascade | no action | New |  |
| subscription_invoices | school_group_id | school_groups | id | restrict | no action | New |  |
| subscription_limits | school_subscription_id | school_subscriptions | id | cascade | no action | New |  |
| subscription_modules | granted_by_user_id | users | id | set null | no action | New |  |
| subscription_modules | module_id | modules | id | restrict | no action | New |  |
| subscription_modules | school_subscription_id | school_subscriptions | id | cascade | no action | New |  |
| subscription_payments | recorded_by_user_id | users | id | set null | no action | New |  |
| subscription_payments | subscription_invoice_id | subscription_invoices | id | restrict | no action | New |  |

### Open Decisions

Decisions that shape the design

Write your decision in the yellow column. 'Decided' rows are settled by your feedback or requirements.

| # | Decision | Options | Recommendation | Tables affected | Your decision | Status |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | One table or two for listing and tenant? | (a) Extend schools so a listing and a managed branch are the same row. (b) A separate institutes table for the directory, linked to schools when management starts. | (a). Tested in the Design Check sheet: every check passed with this design. | schools |  | Open |
| 2 | How to handle multi-branch organisations | One organisation with many branches, each branch a full set of its own records. | Each branch is one row in schools, grouped by school_groups. Owner access through school_group_user. | school_groups, schools, school_group_user | Your requirement: one school, many branches, own students and accounts, owner sees all. | Decided |
| 3 | Can parents sign up with phone only? | Keep users.email required, or make it optional and allow phone + OTP login. | Allow phone-only for parents (email optional but unique when present). Owners and staff keep email. | users, otp_codes |  | Open |
| 4 | Owner role | (a) A role at branch level. (b) A role at organisation level. | (b): owner and group roles live in school_group_user, so the owner sees every branch, including branches added later. | school_group_user, school_user |  | Open |
| 5 | Image storage | (a) Custom school_media table. (b) spatie/laravel-medialibrary. | (b) if you want automatic thumbnails; (a) is simpler for launch. | school_media, schools |  | Open |
| 6 | Public fee versus billing fee | Show fees from school_listing_fees only, or read them from fee_masters when the school uses management. | Keep them separate at first. Public fees are indicative and carry a &#x27;last confirmed&#x27; date. | school_listing_fees, fee_masters |  | Open |
| 7 | Launch with reviews? | Launch with reviews, launch later, or ratings only. | Later (P3). Needs moderation and a takedown policy. | reviews |  | Open |
| 8 | Courses and programmes | Keep separate courses and programs tables, or merge into one. | Keep separate. | courses, programs |  | Open |
| 9 | Legacy fee tables | Keep or drop legacy_fee_types and legacy_student_fee_snapshots. | Drop. | legacy_fee_types, legacy_student_fee_snapshots | You dropped both in the Table Plan. | Decided |
| 10 | Parent leads: consent and retention | How long to keep leads and whether the school sees the phone number immediately. | Require consent_given, show contact details only after consent, delete or anonymise after a set period. | listing_leads |  | Open |
| 11 | Importing old taleemfinder.pk data | Import the old WordPress listings, or start fresh. | Start fresh: taleemfinder.pk is a new website. No import, no old IDs, no redirect rules. Listings come from admin entry and from institutes registering themselves. | schools, areas | Your decision: no need to record import. | Decided |
| 12 | Urdu content | (a) Extra columns such as name_ur. (b) A translations table. (c) English only at launch. | (c) at launch. | schools, articles |  | Open |
| 13 | Free listing and verification | Which institutes appear publicly and for free. | Every verified institute gets a free public listing for ever. Unverified institutes are not published. The listing is a core module that cannot be switched off. | schools, listing_claims, modules | Your requirement: free listing for all verified institutes. | Decided |
| 14 | Module-wise subscriptions | One plan switches everything, or each module can be enabled or disabled for each school (branch). | Module by module, per branch: a plan is a bundle of modules, add-ons and trials sit on top, and each branch switches modules on or off. | modules, plan_modules, subscription_modules, school_modules | Your requirement: enable and disable each module for each school. | Decided |
| 15 | Own domain or subdomain for school management | (a) Every school gets its own address. (b) Everyone uses the common domain. | (b): management runs on the common domain (for example school.taleemfinder.pk). Only the Website module adds a name.taleemfinder.pk address. Customer-owned domains are not offered. | school_site_settings | Your decision: not needed. | Decided |
| 16 | Does the Free starter plan include any management modules? | (a) Listing only; management only for subscribers. (b) A small starter bundle (for example Students, Attendance, Staff, up to 80 students, 15 staff, 1 branch). | (b) as lead generation: institutes start free and grow into paid modules. You can empty the bundle at any time by changing plan_modules. | plans, plan_modules, plan_limits |  | Open |
| 17 | Pricing model | (a) Plan price per organisation per month plus add-on modules per branch per month. (b) A price per student per month. | (a): simple to explain and matches the module switches. (b) is common among competitors; it can be added later as a limit-based price. | plans, modules, subscription_invoices |  | Open |
| 18 | Grace period after a subscription ends | None, or a short read-only period before paid modules close. | 14 days read-only (grace_ends_at). The free listing never stops and no data is deleted. | school_subscriptions |  | Open |
| 19 | Who may switch modules on or off for a branch | (a) Platform admin only. (b) Also the organisation owner or group manager, within what the plan covers. | (b). A branch admin can only use what is switched on. | school_modules, school_group_user |  | Open |
| 20 | Organisation for every institute | Create an organisation only for multi-branch institutes, or automatically for every institute that is verified. | Automatically. Adding a second branch later is then just adding a row, and the plan and owner are already at organisation level. | school_groups, school_group_user |  | Open |
| 21 | Plan limits across branches | Count students and staff per branch, or across the whole organisation. | Across the organisation, so one school cannot split into many free branches. | plan_limits, subscription_limits |  | Open |
| 22 | How a student transfer between branches works | (a) New record in the new branch plus a transfer record. (b) Move the same record. | (a): each branch keeps its own admission number, invoices and balance. (b) would break invoices and attendance history. | student_transfers, students |  | Open |
| 23 | What &#x27;account&#x27; means | (a) Fee collection and expenses per branch (what exists now). (b) Also cash and bank accounts with balances and transfers between branches. | (a) for the first release. If you need cash books and inter-branch transfers, we add a ledger design as a separate step. | fee_payments, expenses |  | Open |
| 24 | Money unit | Whole rupees as decimal(12,2), or integer paisa (&#x27;cents&#x27;). | Decimal rupees everywhere, so invoices, payments, charges, expenses and subscriptions use one unit. | fee_invoices, fee_payments |  | Open |
| 25 | Import leftovers in guardians | guardians.source_parent_id and guardians.source_data look like the student import columns you removed. | Remove them too, for consistency. | guardians |  | Open |
| 26 | Marketing website and brand domain | Serve the school management marketing site from a separate brand domain on the same application and database, or from a section of taleemfinder.pk. | A separate brand domain (see the Project Planning Document, section 12.4) so the product has its own identity, with pricing and plans read from the same database. | demo_requests, articles, plans, modules |  | Open |
| 27 | Institute website per branch or per organisation | Each branch has its own website, or the organisation has one site listing all branches. | Per branch for now (as designed); allow an organisation-level site later. | school_site_settings |  | Open |

### Subscription Logic

Subscription logic – plans, modules and branches

The business rules the database was built to support. Everything here was tested (see Design Check).

| First column | Second column | Third column | Fourth column |
| --- | --- | --- | --- |
| 1. Principles |  |  |  |
| Rule | What it means |  |  |
| Free listing for every verified institute | A verified school, college, university or institute is published in the directory free of charge, for ever. The listing is a core module: it never depends on a subscription and cannot be switched off. |  |  |
| Verification comes first | An unverified institute is not published and gets no modules. Verification is done through a claim (code, document or manual check). |  |  |
| Everything else is a module | Students, Attendance, Fees, Expenses, Exams, Parent portal, SMS and WhatsApp, Institute website and so on. Each can be sold, trialled, switched on or off on its own. |  |  |
| A plan is a bundle | A plan lists modules (plan_modules) and limits (plan_limits). Changing what a plan contains means changing rows, not code. |  |  |
| Two layers decide access | Layer 1, entitlement: what the organisation has (its plan, plus add-ons, trials and grants). Layer 2, switch: what each branch has turned on. A module works only when both are true, and when the modules it depends on work too. |  |  |
| One common address | Schools use the management system at the common domain (for example school.taleemfinder.pk). No school needs its own domain. Only the Website module adds a public address, name.taleemfinder.pk. |  |  |
| Data is never deleted | Switching a module off, downgrading or letting a subscription end hides or locks the module. The data stays and returns when the module is switched back on. |  |  |
| 2. Who can do what |  |  |  |
| Person | Can |  |  |
| Platform administrator | Create modules and plans, set limits, grant trials and special limits, verify institutes, override anything. |  |  |
| Owner or group manager of an organisation | Choose the plan, buy add-ons, and switch modules on or off for each branch, within what the organisation is entitled to. |  |  |
| Branch administrator and staff | Use the modules that are switched on for their branch. They cannot buy or switch. |  |  |
| Parent or student | Use the portal and app when the Parent portal module is on for the branch. |  |  |
| 3. The access rule (all must be true) |  |  |  |
| Step | Check | Result if it fails |  |
| 1 | The institute is verified. | No access to anything, not even the listing. |  |
| 2 | The module is active and applies to this institute type (for example a university-only module is closed to schools). | No access. |  |
| 3 | If the module is core (the free listing): allowed. | Full access. |  |
| 4 | The organisation has a subscription that is active, in trial, or inside its grace period. | After the grace period: no access (the listing stays). |  |
| 5 | The organisation is entitled to the module through its plan, or through an add-on, trial or grant that has not ended. | No access. |  |
| 6 | The branch has switched the module on. | No access in that branch; other branches are unaffected. |  |
| 7 | Every module it depends on also passes steps 4 to 6. | No access (Fees needs Students; Parent portal needs Students). |  |
| Outcome | All passed during the subscription: full access. All passed during the grace period: read-only. | — |  |
| 4. Life of an institute |  |  |  |
| Stage | What happens |  |  |
| Register and verify | The institute (or you) creates the listing; the owner claims it and is verified. The listing is published free. |  |  |
| Starter plan | A newly verified organisation is placed on the default plan (the Free starter). Which modules it includes is up to you; it can be empty. |  |  |
| Upgrade | The owner picks a plan. The organisation&#x27;s entitlement changes immediately; branch switches are not rewritten, so an owner&#x27;s earlier choices are kept. |  |  |
| Add-on or trial | A row in subscription_modules gives one module with an end date, for the organisation. Branches then switch it on. |  |  |
| Expiry | Status moves to grace: paid modules become read-only for the grace period, then close. The listing stays. Data is kept. |  |  |
| Downgrade | Modules outside the new plan close; data stays; switching back or upgrading restores them. |  |  |
| 5. Limits |  |  |  |
| Limit | How it works |  |  |
| Which limits | students, staff, branches, logins, storage_mb, sms_per_month (a new limit needs no database change). |  |  |
| Where they live | plan_limits per plan. subscription_limits replaces a plan&#x27;s limit for one organisation (a deal or promotion). |  |  |
| What is counted | Active students and staff across all branches of the organisation, so one school cannot split into many free branches. |  |  |
| At the limit | Adding is blocked with an upgrade message. Viewing and export keep working. |  |  |
| 6. Billing the organisation |  |  |  |
| Item | How it works |  |  |
| Invoice | One subscription invoice per organisation per period: the plan once, plus each add-on module per branch (subscription_invoices and subscription_invoice_lines). |  |  |
| Payment | Recorded against the invoice (subscription_payments). An unpaid invoice leads to the grace period. |  |  |
| Not the same as fees | fee_invoices are what a school charges its students. subscription_invoices are what the platform charges the organisation. |  |  |
| 7. Addresses |  |  |  |
| Address | Used for |  |  |
| taleemfinder.pk | The public directory (School Finder). |  |  |
| school.taleemfinder.pk (example) | The common login and management dashboard for every institute. |  |  |
| name.taleemfinder.pk | An institute&#x27;s own website, only with the Website module. Reserved names are refused: www, school, app, api, admin. |  |  |
| A separate brand domain | Marketing website for the school management system, on the same application and database (pricing and plans are read from the plans and modules tables). |  |  |
| 8. Example: Bright Future Education, two branches |  |  |  |
| Module | Entitled through | Main Campus | Gulberg Branch |
| Free listing | Core (verified) | On | On |
| Students | Standard plan | On | On |
| Fees | Standard plan | On | Off (owner switched it off) |
| Parent portal | Standard plan | On | On only while Students stays on |
| SMS and WhatsApp | Add-on until 10 Nov 2027 | On | Off (not switched on) |
| Institute website | 30-day trial | On until the trial ends | Off |
| 9. Existing, modified or new |  |  |  |
| Table | Status | What it does here |  |
| schools | Existing in the app – modified | Branch columns added; management_enabled not needed; source values simplified. |  |
| school_user | Existing in the app – unchanged | Branch staff links. |  |
| users | Existing in the app – modified | Phone, language, last login. |  |
| school_groups | Proposed earlier – modified | The organisation; status added. |  |
| school_group_user | Proposed earlier – unchanged | Owner access across branches. |  |
| listing_claims | Proposed earlier – unchanged | Verification of ownership. |  |
| plans | Proposed earlier – modified | Features list removed; description added. |  |
| plan_limits | Proposed earlier – unchanged | Limits per plan. |  |
| school_subscriptions | Proposed earlier – modified | Grace period; organisation level. |  |
| school_site_settings | Proposed earlier – modified | Subdomain column; own-domain handling removed. |  |
| articles | Proposed earlier – modified | site column added. |  |
| school_domains | Proposed earlier – removed | Own domains are not offered. |  |
| modules | New | Catalogue of switchable modules. |  |
| module_dependencies | New | Which module needs which. |  |
| plan_modules | New | Modules in each plan. |  |
| subscription_modules | New | Add-ons, trials, grants. |  |
| subscription_limits | New | Special limits for one organisation. |  |
| school_modules | New | On/off switch per branch. |  |
| subscription_invoices, subscription_invoice_lines, subscription_payments | New (later step) | Billing the organisation. |  |
| demo_requests | New | Marketing website enquiries. |  |

### Design Check

Design check – branches and module-wise subscriptions

Requirements tested: each branch has its own students and accounts; the owner sees every branch; modules can be switched on or off per branch; free listing for every verified institute.

| First column | Second column | Third column | Fourth column |
| --- | --- | --- | --- |
| A. How branches work in the design |  |  |
| Concept | Where it lives | How it works |
| Organisation | school_groups | The school, college, university or institute as a whole. Every institute that registers gets one. |
| Branch | schools (group_id, branch_name, branch_code, is_main_branch) | Each branch is a full institute record. It has its own students, staff, classes, fees and expenses. |
| Own students and accounts | every management table carries school_id | Students, invoices, payments, charges, expenses and staff all belong to one branch. Composite keys stop a record pointing at another branch&#x27;s record (tests 8, 10 to 17, 28). |
| Owner sees everything | school_group_user | A person linked here (role owner or group manager) may open every branch of the organisation, including branches added later. |
| Branch staff see their branch only | school_user | Accountants, teachers and admins are linked to one or more branches and see only those. |
| Free listing | schools (listing_status, verified_at) | Every verified institute is published free of charge for ever. The listing never depends on a subscription and cannot be switched off. |
| Modules and plans | modules, plan_modules, subscription_modules, school_modules | A plan bundles modules. The organisation is entitled to the plan&#x27;s modules plus any add-ons or trials. Each branch then switches modules on or off. A module works only when both are true (sheet Subscription Logic). |
| Plan and limits | school_subscriptions, plan_limits, subscription_limits | Limits (for example 80 students, 15 staff, branches) count across all branches of the organisation. A special deal can replace a plan&#x27;s limit for one organisation. |
| Moving a student | student_transfers | A transfer opens a new record in the new branch and closes the old one; history and old balances stay with the old branch. |
| B. Access rule the application applies |  |  |
| A user may open branch X when (1) they have a school_user link to X, or (2) they have an active school_group_user link to the organisation that X belongs to. Reports for the owner run over the list of branches this rule returns; a branch accountant gets the same reports over a list of one. |  |  |
| C. Test results (56 checks on a test database built from this workbook) |  |  |
| Check | What was tested | Result / detail |
| 1 Access | Owner of the organisation sees both branches | Pass: owner → branches [1, 2] |
| 2 Access | Branch accountant sees only their own branch | Pass: accountant → [1] |
| 3 Access | Person with no link sees nothing | Pass: outsider → none |
| 4 Access | Teacher linked to two branches sees both, but not the organisation as owner | Pass: teacher → [1, 2] |
| 5 Students | Each branch has its own students (3 + 2) | Pass: Main: 3 students, Gulberg: 2 students |
| 6 Students | The same admission number can exist in two branches | Pass: ADM-001 used in both Main and Gulberg |
| 7 Students | The same admission number inside one branch is refused | Pass: UNIQUE constraint failed: students.school_id, students.admission_number |
| 8 Students | A branch cannot enrol another branch&#x27;s student | Pass: FOREIGN KEY constraint failed |
| 9 Fees | Each branch has its own fee types, groups, price list, charges and invoices | Pass: Main: Rs 3,000 × 3 students; Gulberg: Rs 4,000 × 2 students |
| 10 Fees | A payment cannot be recorded in one branch against another branch&#x27;s invoice | Pass: FOREIGN KEY constraint failed |
| 11 Fees | An invoice cannot be raised in one branch for another branch&#x27;s student | Pass: FOREIGN KEY constraint failed |
| 12 Fees | A price list cannot use another branch&#x27;s fee type | Pass: FOREIGN KEY constraint failed |
| 13 Fees | A charge cannot use another branch&#x27;s price list | Pass: FOREIGN KEY constraint failed |
| 14 Fees | A fee group cannot contain another branch&#x27;s classes | Pass: FOREIGN KEY constraint failed |
| 15 Fees | Only one invoice per student per month | Pass: UNIQUE constraint failed: fee_invoices.school_id, fee_invoices.student_id, fee_invoices.billing_month |
| 16 Fees | A discount from another branch cannot be given to a student | Pass: FOREIGN KEY constraint failed |
| 17 Accounts | An expense cannot use another branch&#x27;s expense head | Pass: FOREIGN KEY constraint failed |
| 18 Owner view | Owner&#x27;s totals across branches: billed, collected, expenses, students | Pass: billed 17,000; collected 8,500; expenses 2,100; students 5; net cash 6,400 |
| 19 Branch view | Branch accountant&#x27;s totals show only their branch | Pass: Main only: billed 9,000; collected 4,500; expenses 1,200; students 3 |
| 20 Owner view | One query gives a per-branch comparison for the owner | Pass: Main Campus: 3 students, collected 4,500, spent 1,200; Gulberg Branch: 2 students, collected 4,000, spent 900 |
| 21 Transfer | A student moves from Main to Gulberg: old record closed, new record opened, history kept | Pass: 1 transfer recorded; old record status &#x27;transferred&#x27;; Main keeps the student&#x27;s Sept invoice (1); Gulberg starts with 0 invoices |
| 22 Transfer | A transfer cannot claim a student who is not in the &#x27;from&#x27; branch | Pass: FOREIGN KEY constraint failed |
| 23 Transfer | Organisation-wide active student count is unchanged by a transfer (5 before, 5 after; the moved student is not counted twice) | Pass: active students after transfer: Main 2, Gulberg 3, total 5 |
| 24 Plan limit | Free-plan limit is counted across all branches of the organisation | Pass: limit 80 students; in use 5 across both branches; room left 75 |
| 25 Plan limit | Staff limit (15) is available in the same way | Pass: limit 15 staff for the organisation |
| 26 Staff | One teacher can work in two branches with the same login | Pass: Same login linked as staff in Main and Gulberg |
| 27 Staff | The same login cannot be staff twice inside one branch | Pass: UNIQUE constraint failed: staff.school_id, staff.user_id |
| 28 Staff | A branch cannot use another branch&#x27;s designation | Pass: FOREIGN KEY constraint failed |
| 29 Modules | Verified branch on the Free plan: free listing and starter modules work | Pass: listing full, students full, attendance full |
| 30 Modules | Modules outside the plan are refused (fees on the Free plan) | Pass: fees → none: not part of the Free plan |
| 31 Modules | An unverified institute gets nothing, not even the listing | Pass: unverified school → none for every module |
| 32 Modules | After upgrading to Standard, fees works in the branch where it is switched on | Pass: Main: fees full |
| 33 Modules | The same module stays off in a branch where the owner switched it off | Pass: Gulberg: fees none (switched off) |
| 34 Modules | Switching on a module the organisation has not bought gives no access | Pass: website modules are not in Standard |
| 35 Modules | ...even if the switch is turned on by mistake | Pass: switch on, not entitled → none |
| 36 Modules | A paid add-on (SMS) works for the organisation until its end date | Pass: SMS now: full; after 10 Nov 2027: none |
| 37 Modules | A 30-day trial of a premium module works, then stops by itself | Pass: during trial: full; after trial: none |
| 38 Modules | A module whose foundation is off does not work (parent portal needs students) | Pass: Main: portal full; Gulberg with Students off: portal none |
| 39 Modules | A university-only module is not available to a school | Pass: type &#x27;school&#x27; → none |
| 40 Modules | A special limit for one organisation replaces the plan&#x27;s limit | Pass: plan limit 500; this organisation 800 |
| 41 Modules | The number of branches is limited by the plan (Standard allows 3) | Pass: 2 branches in use of 3; one more may be added, a fourth may not |
| 42 Modules | After expiry, paid modules are read-only during the grace period | Pass: grace: fees read_only, students read_only, listing full |
| 43 Modules | After the grace period paid modules stop but the free listing stays | Pass: expired: fees none, listing full; no data deleted |
| 44 Modules | Data is kept when a subscription ends | Pass: Main still holds 3 students and its invoices |
| 45 Modules | The owner sees which modules each branch has: Main and Gulberg differ | Pass: Main {&#x27;students&#x27;: &#x27;full&#x27;, &#x27;fees&#x27;: &#x27;full&#x27;, &#x27;parent_portal&#x27;: &#x27;full&#x27;, &#x27;sms&#x27;: &#x27;full&#x27;}; Gulberg {&#x27;students&#x27;: &#x27;full&#x27;, &#x27;fees&#x27;: &#x27;none&#x27;, &#x27;parent_portal&#x27;: &#x27;full&#x27;, &#x27;sms&#x27;: &#x27;none&#x27;} |
| 46 Modules | A branch cannot have two switches for the same module | Pass: UNIQUE constraint failed: school_modules.school_id, school_modules.module_id |
| 47 Modules | A plan cannot list the same module twice | Pass: UNIQUE constraint failed: plan_modules.plan_id, plan_modules.module_id |
| 48 Modules | A dependency must point at a real module | Pass: FOREIGN KEY constraint failed |
| 49 Modules | Only one special limit per limit name per organisation | Pass: UNIQUE constraint failed: subscription_limits.school_subscription_id, subscription_limits.limit_key |
| 50 Website | An institute website needs the Website module, and its subdomain on the common domain must be unique | Pass: brightfuture.taleemfinder.pk can belong to one institute only; UNIQUE constraint failed: school_site_settings.subdomain |
| 51 Marketing site | The marketing website&#x27;s pricing page can be drawn from the same database (plans and their modules) | Pass: Free: 4 modules; Standard: 8 modules; Professional: 10 modules |
| 52 Plans | Exactly one plan is the default given to a newly verified organisation | Pass: the Free starter plan |
| 53 Marketing site | A demo request from the marketing website is stored and later linked to the organisation it became | Pass: demo request → converted → linked to the organisation |
| 54 Billing | A subscription invoice lists the plan once and each add-on per branch; payments reduce the balance | Pass: invoice Rs 5,500 (plan 5,000 + SMS for Main 500); paid 3,000; balance 2,500 |
| 55 Structure | Foreign-key check over all data inserted | Pass: no broken links |
| 56 Structure | Every relationship points at an existing column with a unique key | Pass: 147 relationships checked across 90 tables |
| D. Existing links tightened so one branch cannot point at another branch&#x27;s record |  |  |
| In the version you reviewed these ten links did not check the branch, so a mistake in the application could have attached, for example, one branch&#x27;s expense head to another branch&#x27;s expense. They now include school_id. |  |  |
| Table | Column | Now points at |
| expenses | expense_head_id | expense_heads (school_id + id) |
| fee_group_grade | grade_id | grades (school_id + id) |
| fee_group_grade | fee_group_id | fee_groups (school_id + id) |
| staff | department_id | departments (school_id + id) |
| staff | designation_id | designations (school_id + id) |
| student_fee_charges | school_session_id | school_sessions (school_id + id) |
| student_fee_charges | fee_master_id | fee_masters (school_id + id) |
| student_inquiries | student_id | students (school_id + id) |
| student_inquiries | requested_grade_id | grades (school_id + id) |
| student_lifecycle_events | student_id | students (school_id + id) |
| E. What this test does and does not prove |  |  |
| Built: a test database with all 90 tables and 147 relationships taken from this workbook, then filled with one organisation, two branches, five students, fees, payments, expenses, plans, modules, add-ons, trials and subscription invoices. |  |  |
| Proves: the table design, keys and relationships are consistent and enforce branch separation, and the owner and branch queries return correct totals. |  |  |
| Does not prove: application code, permissions in the screens, performance with large data, or behaviour on MariaDB/MySQL. The same rules use standard foreign keys and unique indexes, but repeat the test on your real database when the migrations are written. |  |  |

### How Fees Work

How fees work – from price list to payment

An example for one student, Ali, in Class 3 at the Main branch, for September 2026.

| Step | Table(s) | What happens | Example |
| --- | --- | --- | --- |
| 1 | fee_types | List the kinds of fee the branch charges. | Tuition, Admission, Transport |
| 2 | fee_groups, fee_group_grade | Bundle classes that pay the same fees into a fee group. | Group &#x27;Primary&#x27; = Class 1 to Class 5 |
| 3 | fee_masters | The price list: for a group, a fee type, a session and a month, set the amount, due date and late fine. | Primary · Tuition · 2026-09 · Rs 3,000 · due 10 Sep · fine Rs 100 after the due date |
| 4 | fee_discounts, student_fee_discounts | Define discounts, then assign them to students. | Sibling discount 10%, assigned to Ali |
| 5 | student_fee_charges | The system creates one charge per student from the price list for every student in the group&#x27;s classes. | Ali · Tuition · 2026-09 · Rs 3,000 · discount Rs 300 |
| 6 | fee_invoices | The month&#x27;s charges are collected on one invoice per student per month. | INV-2026-09-0042 · subtotal 3,000 · discount 300 · total 2,700 |
| 7 | fee_payments | Each payment is recorded against the invoice; the invoice keeps a running paid total. | Rs 1,000 cash on 5 Sep → paid 1,000, balance 1,700 (partly paid) |
| 8 | reports | Collections, defaulters and totals per branch and for the whole organisation are queries over invoices and payments. | Owner sees Main and Gulberg side by side |
| What was wrong before: the old design had two billing systems side by side. Payments could only attach to the old invoices (tied to the old legacy_fee_types), while the new fee groups and price list produced charges that had no invoice and no way to record payment. Step 6 and 7 above join them into one flow. |  |  |  |

### Change Log

What changed – versions 3 and 4

Version 4: your latest feedback (no import, module-wise subscriptions, common domain, marketing site). Version 3: earlier feedback and the multi-branch requirement.

| Version | Item | Type | Detail | Reason |
| --- | --- | --- | --- | --- |
| v4 | Importing taleemfinder.pk data | Dropped from the plan | No import step, no imported-listing source value, no old IDs, no redirect rules. The area note about cleaning duplicates after import is replaced by a plain naming rule. | Your decision: taleemfinder.pk is a new website. |
| v4 | schools.source | Meaning changed | Values are now admin_created or self_submitted only. | No import. |
| v4 | schools.management_enabled | Column removed | Replaced by the module switches in school_modules. | Module-wise subscriptions. |
| v4 | school_domains | Table removed | Customer-owned domains are not offered. Management runs on the common domain (for example school.taleemfinder.pk). | Your decision: not needed. |
| v4 | school_site_settings.subdomain | Column added | The Website module gives name.taleemfinder.pk, unique across the platform. | Replaces school_domains. |
| v4 | modules, module_dependencies, plan_modules | Tables added | The catalogue of modules, what each module needs, and which modules each plan includes. | Module-wise subscriptions. |
| v4 | subscription_modules, subscription_limits | Tables added | Add-ons, trials and manual grants with end dates; special limits for one organisation. | Module-wise subscriptions. |
| v4 | school_modules | Table added | On/off switch of each module for each branch. | You can enable and disable each module for each school. |
| v4 | plans.features | Column removed | Replaced by plan_modules and plan_limits. | A single list could not say which module, for which branch. |
| v4 | school_subscriptions | Changed | Added grace_ends_at and the statuses trial, active, grace, expired, cancelled. | Read-only grace period after expiry. |
| v4 | subscription_invoices, subscription_invoice_lines, subscription_payments | Tables added (later step) | What the platform bills an organisation: the plan once and each add-on per branch. | Charging for plans and add-ons. |
| v4 | demo_requests | Table added | Demo and free-trial requests from the marketing website. | Separate brand domain on the same database. |
| v4 | articles.site | Column added | finder or marketing, so the marketing website has its own blog. | Marketing website. |
| v3 | redirects | Table dropped | Not created. | Your decision. |
| v3 | family_fee_receipts | Table dropped | One receipt covering several children of a family is no longer a table. | Your decision. |
| v3 | legacy_fee_types | Table dropped | Removed. | Your decision. This forced the invoice redesign below. |
| v3 | legacy_student_fee_snapshots | Table dropped | Removed. | Your decision. |
| v3 | schools.legacy_wp_post_id, articles.legacy_wp_post_id | Columns removed | Never added. | Your decision. |
| v3 | schools.listing_claims | Stray row removed | A row named listing_claims had appeared under schools in your copy; it is not a real column. | Looked like an accidental edit. |
| v3 | students (12 columns) | Columns removed | admitted_class, current_class_label, caste_or_tribe, fee_rate, fee_amount, fee_waiver, arrears_due, withdrawn_from_class, source_marks_obtained, source_marks_percentage, source_data, source_guardian_data. | Your decision. |
| v3 | fee_invoices | Redesigned | Removed fee_type_id, academic_year, description, amount_cents. Added invoice_no, school_session_id, billing_month, subtotal, discount_total, fine_total, total_amount, paid_total. | Needed because legacy_fee_types was dropped; joins the two billing systems. |
| v3 | fee_payments, student_fee_charges, student_fee_discounts, fee_group_grade | Changed or added | Rupees (decimal) everywhere, payment method, charge-to-invoice link, discounts applied to students, branch check on fee groups. | One money unit and one billing flow. |
| v3 | schools, school_groups, school_group_user, student_transfers | Branch design | branch_name, branch_code, is_main_branch; organisation; owner access across branches; history of student moves. | Multi-branch requirement. |
| v3 | staff.user_id | Key changed | Unique per branch instead of unique overall. | A teacher can work in two branches. |
| v3 | 10 existing links | Keys tightened | Now include school_id. | One branch cannot point at another branch&#x27;s records. |

## Project Planning Document v1.1 source extract

The following text preserves the source wording and table content for reference. Resolve any conflict using the current scope above and the Database Plan v4 decisions.

### Page 1

```text
SCHOOLHOUSE
Listing and management platform for schools, colleges, universities and
institutes


Project planning document
Features, free and paid plans, development environment and requirements
Version 1.1 · 17 November 2026




“Schoolhouse” is the working name used in the database dictionary. Replace it with the final brand name when it is
chosen.
```

### Page 2

```text
Schoolhouse · Project planning document


Contents
1. Purpose of this document
2. Vision and goals
3. Who uses the platform
4. SCUI: the four types of institute
5. Feature 1 – Public listing (free)
6. Feature 2 – Registration and onboarding
7. Feature 3 – Management system
8. Feature 4 – Free plan and limits
9. Feature 5 – Own domain and own template (paid)
10. Feature 6 – Mobile application
11. Platform administration
12. Technical architecture and front-end plan
13. Development environment
14. Requirements
15. Roadmap
16. Database changes needed
17. Risks and how to reduce them
18. Decisions needed from you
19. Next steps
Appendix – Glossary and source material




Version 1.1 · 17 November 2026                      Page 2 of 20
```

### Page 3

```text
Schoolhouse · Project planning document


1. Purpose of this document
This document explains, point by point, what the Schoolhouse platform will do, how the free and paid
parts fit together, what you need to build it, and what has to be decided next. It is written so that you
can read it yourself, share it with a developer or a partner, and use it as the reference while the
database and the first version are built.
It builds on three things that already exist:
   • taleemfinder.pk – the live school finder, currently built in WordPress, covering Islamabad and
     Rawalpindi.
   • school-gpt and school-ai – two Laravel + React versions of a school management system.
     school-gpt is further along (students, attendance, staff, fees, expenses) and is the recommended
     base.
   • Schoolhouse-Database-Plan.xlsx – the table-by-table database plan (version 2: 46 existing
     tables, 36 proposed new tables).

  How to read this document
  Where a number or rule is your decision it is marked “set by you”. Where it is my suggestion it is
  marked “proposed” and listed again in section 18 so you can confirm or change it.



  What changed in version 1.1
  Versions: PHP 8.4 or newer, Laravel 13 and React 19 are now the fixed baseline (version 1.0 said PHP
  8.3).
  Front-end plan: sections 12.2 and 12.3 explain how the School Finder, the institute websites and the
  management dashboard share one backend and one database, with suggested tools.
  Packages: section 13.6 and the separate Package Selection Guide list the safe, free packages to use.
  Database: version 2 of the workbook adds the plan, domain, theme, page and mobile tables (section 16).




2. Vision and goals
Schoolhouse is one platform with two faces. Parents and students use the public side to find and
compare institutes. Institutes use the management side to run their daily work. The free listing brings
institutes in; the management tools keep them; the paid features pay for the platform.

 Goal                                 What it means in practice

 Free listing for every SCUI          Any school, college, university or institute can appear in the directory at no
                                      cost, for as long as it likes.

 Useful free management               A registered institute can run its basics free, within clear limits (for example
                                      80 students and 15 staff).

 Paid growth features                 Higher limits, the institute’s own domain, its own template and a branded
                                      mobile app are paid upgrades.

 One database, many                   All institutes share one system and one database; each sees only its own
 institutes                           data.

 Build on what exists                 Move taleemfinder.pk from WordPress to Laravel + React and merge it with
                                      the school management code instead of starting again.




Version 1.1 · 17 November 2026                                                                               Page 3 of 20
```

### Page 4

```text
Schoolhouse · Project planning document


3. Who uses the platform
 Role                            What they do                                                 Where they work

 Visitor, parent, student        Search, filter and compare institutes; save favourites;      Public website, mobile
                                 send an enquiry; later read and write reviews.               app

 Institute owner /               Registers or claims the listing, edits the public profile,   Institute dashboard
 administrator                   sets up the institute, manages staff and students, sees
                                 reports.

 Teacher / staff                 Marks attendance, enters marks, views class lists,           Dashboard, mobile app
                                 applies for leave.

 Accountant                      Creates fee invoices, records payments, issues               Dashboard
                                 receipts, records expenses.

 Parent / student (portal)       Views attendance, fee dues, results and notices for          Mobile app, web portal
                                 their own child.

 Platform administrator          Approves listings and claims, manages plans and limits,      Admin area
 (you)                           moderates content, supports institutes.




4. SCUI: the four types of institute
SCUI stands for Schools, Colleges, Universities and Institutes. They all need a listing and a
management system, but they organise their work differently. The platform handles this with one engine
that changes its labels and modules according to the institute type.

 Type                    How it is organised                         Special management needs

 School                  Grades (classes), sections, subjects,       Daily attendance, monthly fees, guardians,
                         academic session.                           report cards. Already largely built in school-gpt.

 College                 Programmes (FSc, ICS, FA, ICom,             Programme-wise admission, board registration
                         BS), years, subjects.                       numbers, annual fee plans, exam results.

 University              Faculties, departments, degree              Semester registration, course enrolment, credit-
                         programmes, semesters, courses and          hour fees, GPA and transcripts. Most complex;
                         credit hours.                               build last.

 Institute /             Short courses, batches, timings,            Course and batch enrolment, per-course fees
 academy                 instructors.                                and instalments, certificates.


How it works. Each institute record has an institute type. That field decides which words the
dashboard uses (for example “Class” or “Semester” or “Batch”) and which modules are switched on.
The core pieces – people, attendance, fees, staff, permissions – are shared by all four types, so they
are built once.

  Recommendation
  Build and prove the school workflow first, then add college and institute (small changes), and university
  last. Universities are a separate level of complexity and should not delay the first launch.




Version 1.1 · 17 November 2026                                                                              Page 4 of 20
```

### Page 5

```text
Schoolhouse · Project planning document


5. Feature 1 – Public listing (free)
The public listing is the front door of the platform and stays free for every SCUI. It replaces and extends
taleemfinder.pk.

What visitors can do
   • Search and filter by city, area or sector, institute type, board or curriculum, level, fee range,
     facilities and academic focus.
   • Open a profile page with description, photos, location on a map, contact details, fees,
     programmes or courses, facilities and events.
   • Compare several institutes side by side and save a favourites list.
   • Send an enquiry to an institute (with the parent’s consent to share contact details).
   • Later: read and write reviews, browse events and open houses, use the used-book exchange.

How a listing gets created
 Route                           Description

 Imported                        The 114 institutes on taleemfinder.pk are imported with their existing addresses
                                 (URLs) so search rankings are kept.

 Added by you                    You or a team member adds an institute from the admin area.

 Self-registered                 An institute registers itself and creates its own listing.



Claiming and verification
An imported listing starts as unclaimed. The real owner can claim it. To stop fake claims, ownership is
verified with a one-time code sent to the phone or email on file, a supporting document, or a manual
check by you. Once verified, the claimant becomes the institute’s administrator and can edit the listing
and switch on management.

Listing status
Each listing has a publication status (draft, pending review, published, suspended) and a claim
status (unclaimed, pending, claimed). Only published listings are visible to the public. Every listing also
shows when its fees were last confirmed, so parents can judge how current the information is.

Search visibility (SEO)
The directory lives on search traffic. Public pages are therefore rendered on the server so search
engines can read them, old WordPress addresses are redirected permanently to the new ones, and
every page has its own title and description.


6. Feature 2 – Registration and onboarding
Registration turns a listing into a working institute account. The path is short and the first useful result
arrives quickly.
  1. Create an account. The person signs up with email or phone and confirms it with a one-time
     code.
  2. Choose the institute type. School, college, university or institute.
  3. Create or claim the listing. Search for the institute; if it exists, claim it; if not, add it.
  4. Verify. Phone or email code, document, or manual approval, depending on the listing.
  5. Switch on management. The institute is placed on the Free plan automatically.

Version 1.1 · 17 November 2026                                                                            Page 5 of 20
```

### Page 6

```text
Schoolhouse · Project planning document

  6. Setup wizard. Academic session, classes or programmes, subjects, first staff and first students
     (including bulk import from Excel).
Why a wizard matters. Most institutes will leave if they face an empty system. The wizard, a ready-
made starter structure for each institute type, and Excel import are what make the free plan convincing.


7. Feature 3 – Management system
The management system lets an institute run its internal work. The table shows each module, what it
does, and where the existing code stands. “Built” is based on the school-gpt repository and its database
dictionary.

 Module                           What it does                                                Status

 Academic structure               Sessions, grades or programmes, sections, subjects, and     Built (school-level)
                                  links between them.

 Students and guardians           Student records, guardian details, enrolment into a class   Built
                                  and session, lifecycle events (admitted, promoted, left).

 Admission inquiries              Track inquiries from first contact to admission.            Built

 Attendance                       Daily attendance by class, with history.                    Built

 Staff and leave                  Staff records, departments, designations, leave types and   Built
                                  balances.

 Fees and receipts                Fee types, groups, discounts, invoices, payments, family    Built
                                  receipts.

 Expenses                         Expense heads and expense records.                          Built

 Exams and results                Exam schedules, marks entry, result sheets, report cards.   To build

 Timetable                        Class timetables and teacher allocation.                    To build

 Notices and messaging            Notices to parents and staff; SMS, WhatsApp or push         To build
                                  delivery.

 Parent and student portal        Read-only view of attendance, fees, results and notices.    To build

 Reports and dashboards           Summaries, defaulters list, fee collection, attendance      To confirm
                                  statistics, Excel and PDF export.

 College, university,             Programme, semester, course and batch handling as           To build
 institute modules                described in section 4.

 Later options                    Payroll, transport, hostel, library, inventory.             Future


Same-school safety. Every record belongs to one institute and the system refuses to link records
across institutes. This is already tested in school-gpt and must stay that way: one institute must never
see another’s data.
Before real institutes use it, the existing hardening list should be completed: stricter integrity rules
between tables, cross-origin settings limited to the real website, and a password-reset flow.


8. Feature 4 – Free plan and limits
Registered institutes receive limited management features free of charge. Limits keep the free plan
sustainable and give institutes a reason to upgrade when they grow.


Version 1.1 · 17 November 2026                                                                           Page 6 of 20
```

### Page 7

```text
Schoolhouse · Project planning document


Proposed free plan
 Item                                                    Free plan                               Source

 Public listing                                          Included, with no time limit            Set by you

 Active students                                         Up to 80                                Set by you

 Staff records                                           Up to 15                                Set by you

 Administrator and staff logins                          Up to 5                                 Proposed

 Core modules (attendance, fees, receipts,               Included                                Proposed
 expenses, inquiries)

 Exams and results                                       Basic                                   Proposed

 Reports                                                 Basic, with Excel and PDF export        Proposed

 File storage                                            1 GB                                    Proposed

 SMS and WhatsApp messages                               Not included; paid per message          Proposed

 Free subdomain (name.yourplatform.pk)                   To decide                               Open

 Own domain and own template                             Paid                                    Set by you

 Branded mobile app                                      Paid (the shared app is free)           Proposed



How limits are enforced
   • Each institute is linked to a plan. Each plan has named limits (students, staff, logins, storage).
   • Before adding a student or staff member the system compares the current count with the plan
     limit.
   • The system warns at 80% and 90% of a limit and shows a clear upgrade message at 100%.
   • At the limit, adding is blocked. Existing data is never deleted or hidden, and viewing and
     exporting always keep working.
   • Only active students and staff count. Archiving a student who has left frees a place.
   • If a paid institute downgrades, it keeps all data but cannot add new records until it is under the
     free limit.

Paid plan structure
 Plan                            Students    Staff         Own domain and           Mobile app            Price
                                                           template

 Free                            80          15            No                       Shared app            Free

 Standard                        To be set   To be set     Optional add-on          Shared app            To be set

 Professional                    To be set   To be set     Included                 Branded app           To be set
                                                                                    optional

 Enterprise (large               Custom      Custom        Included, custom         Branded app           Quote
 schools, universities)                                    design



  Decision needed
  Pricing model: flat tiers (simple to explain) or per student per month (fairer for very different sizes). Flat
  tiers with a student cap are the easier start.




Version 1.1 · 17 November 2026                                                                                Page 7 of 20
```

### Page 8

```text
Schoolhouse · Project planning document


9. Feature 5 – Own domain and own template (paid)
A paying institute can run its public website on its own address (for example
www.exampleschool.edu.pk) with its own look, while the data still lives in the platform’s shared
database. Visitors see the institute’s website; the institute sees nothing but its own data.

How the institute gets its own domain
  1. The institute upgrades and enters its domain name in the dashboard.
  2. The platform shows the DNS record to add (normally a CNAME pointing to the platform).
  3. The platform checks the DNS record automatically and marks the domain as verified.
  4. A security certificate (HTTPS) is issued automatically.
  5. Visitors to that address now see the institute’s website. The platform recognises the institute from
     the web address and loads only that institute’s data.

How templates work
“Own template” should mean a choice of platform-built templates that the institute customises, not
free-form code upload.

 Level                           What the institute gets                                               Plan

 Template and settings           Choose from a set of ready templates; change logo, colours,           Paid
                                 fonts, homepage sections and menu; edit pages such as About,
                                 Admissions, Gallery and Contact.

 Custom design                   A template designed and built for the institute by you or a           Enterprise /
                                 partner.                                                              paid service


Why not allow custom code upload? Custom code running on a shared server is a security risk: one
institute’s code could try to reach another institute’s data. Templates are therefore reviewed and
installed by you.

What the institute’s website shows
Home, about, programmes or classes, admissions and fees, gallery, events, notices, contact form with
map, and a login button for staff and parents. Content comes from the same listing data, so updating it
once updates the directory and the institute’s own site.

Search engine handling
The directory page and the institute’s own site would otherwise compete as duplicate content. Each
page declares a preferred (canonical) address, and the directory profile links to the institute’s own site.

Support effort
DNS mistakes are the most common support issue. A built-in domain checker that explains in plain
language what is wrong reduces support work greatly.


10. Feature 6 – Mobile application
Who it is for
 Audience                                 Main functions

 Parents and students                     Attendance, fee dues and receipts, results, notices, events; find and
                                          compare institutes.



Version 1.1 · 17 November 2026                                                                                Page 8 of 20
```

### Page 9

```text
Schoolhouse · Project planning document


 Audience                                 Main functions

 Teachers and staff                       Mark attendance (even with a weak connection), view class lists, apply for
                                          leave, read notices.

 Owners and administrators                Quick dashboard: attendance today, fees collected, defaulters, inquiries.



Technology options
 Option                           Advantages                                        Drawbacks

 Installable web app (PWA)        No store approval; reuses the web code;           Weaker push notifications and
                                  fastest to launch.                                device features, especially on
                                                                                    iPhone.

 React Native with Expo           One codebase for Android and iPhone;              Needs store accounts, release
 (recommended)                    uses the same JavaScript and React skills         management and some native
                                  as the web dashboard; good push                   knowledge.
                                  notification support.

 Flutter                          Strong performance, one codebase.                 A different language and toolset to
                                                                                    learn and maintain.



Recommended approach
   • Make the web dashboard fully mobile-friendly first; offer it as an installable web app during early
     phases.
   • Build one shared React Native (Expo) app for parents, students and staff that shows the screens
     for the person’s role and institute.
   • Offer a branded app (the institute’s own name and icon in the stores) only as a premium option,
     because each one needs its own store listing and upkeep.

What the backend must provide
   • Token-based sign-in for mobile devices. The web dashboard uses cookie sessions; the app
     needs separate access tokens.
   • A versioned API (for example /api/v1) so old app versions keep working after updates.
   • Push notifications through Firebase Cloud Messaging, with a table that stores each device.
   • Offline attendance: save marks on the phone and send them when the connection returns.
Google Play and Apple require developer accounts with their own fees; check current prices before
budgeting.


11. Platform administration
You, and later your team, need an admin area to run the platform itself.
   • Approve or reject new listings and ownership claims.
   • Moderate reviews, uploads and shared study material.
   • Create plans, set limits and move institutes between plans.
   • Import and clean directory data; merge duplicate areas and institutes.
   • See usage per institute: students, staff, storage, last activity.
   • Manage domains and templates for paying institutes.
   • Switch features on and off per institute or for everyone.
   • Read audit records: who changed what, and when.

Version 1.1 · 17 November 2026                                                                              Page 9 of 20
```

### Page 10

```text
Schoolhouse · Project planning document


12. Technical architecture and front-end plan

12.1 Architecture
The recommended design is a modular monolith: one Laravel application with clearly separated
modules, rather than many small services. It is far easier for a small team to build, deploy and keep
running.

 Layer                           Technology                             Role

 Public website                  Laravel with Inertia.js and React,     Directory and institute sites; readable by
                                 server-side rendering                  search engines.

 Institute dashboard             React single-page application          Daily management work behind login.

 Mobile app                      React Native (Expo)                    Parent, student and staff app.

 Institute websites              Same application; a template (theme)   Own-domain websites for paying
                                 per institute                          institutes; see 12.2.

 API and business                Laravel 13 (PHP 8.4 or newer)          One set of rules used by the website,
 rules                                                                  dashboard and app.

 Database                        MySQL or MariaDB                       One shared database; every private table
                                                                        carries the institute’s ID.

 Cache, queues,                  Redis                                  Speed, background jobs (SMS, email,
 sessions                                                               imports), rate limiting.

 Search                          Meilisearch (via Laravel Scout)        Fast filtered search across institutes and
                                                                        courses.

 File storage                    S3-compatible object storage           Photos, documents, uploads; scales
                                                                        independently of the server.

 Notifications                   Email, SMS or WhatsApp provider,       Reach parents and staff.
                                 Firebase push



Multi-tenancy (many institutes, one system)
   • Shared database, shared tables. Each private record carries the institute’s ID. This is the
     existing school-gpt approach and the simplest to operate.
   • Host-based recognition. A middleware reads the web address, finds the institute (including
     custom domains) and sets the current institute for the whole request.
   • Roles per institute. The existing permission package assigns roles within one institute, so a
     person can be an administrator in one place and a parent in another.
   • Plan checks in one place. A single service answers “may this institute add another student?” so
     limits are applied consistently.

Security principles
   • Test tenant isolation automatically for every new module.
   • Store only hashed passwords and hashed one-time codes.
   • Limit login and code requests to resist abuse and spam sign-ups.
   • Keep uploaded files outside the public web folder and check file types.
   • Keep an audit trail of important changes (fees, marks, deletions).




Version 1.1 · 17 November 2026                                                                           Page 10 of 20
```

### Page 11

```text
Schoolhouse · Project planning document


12.2 One backend, three front-end applications
The School Finder, each institute’s own website and the management dashboard all read and write the
same database through the same Laravel rules. They differ in who uses them and in how their pages
are produced.

 Application                Who uses it               How pages are produced            Main tools

 School Finder              Parents, students and     Server-rendered React (Inertia    Inertia, React 19, Tailwind
 (public directory)         search engines            with server-side rendering), so   CSS, Leaflet, TanStack
                                                      every page can be read by         Query, React Hook Form
                                                      search engines.                   with Zod

 Institute websites         Visitors to an            The same server-rendered          The Finder’s tools plus
                            institute’s own           application; a template (theme)   theme components, colour
                            website                   chosen by the institute decides   settings and Tiptap content
                                                      the look.                         blocks

 Management                 Institute staff, owners   React single-page application     React Router, TanStack
 dashboard                  and the platform          behind login, working through     Query and Table, React
                            administrator             the JSON API.                     Hook Form with Zod,
                                                                                        Recharts, shadcn/ui

 Mobile app (Step 6)        Parents, students and     React Native (Expo) using the     Expo, TanStack Query, the
                            staff                     same API.                         shared API types



How the three fit together
   • The Finder and the institute websites are one application. A middleware reads the web
     address. The platform’s own address shows the Finder; an institute’s address (free subdomain or
     own domain) shows that institute’s website.
   • The dashboard is a separate bundle in the same project, served from its own address (for
     example app.yourplatform.pk) and signed in with cookie sessions.
   • One shared design system. A single folder of components (buttons, forms, tables, cards),
     colours and fonts is used by all three, so a change is made once.
   • Same data rules. The Finder and institute websites show only published, public data. The
     dashboard shows only the signed-in institute’s own data.

How an institute’s own website is produced
   • A visitor arrives at a web address. The application looks it up in the school_domains table to find
     the institute.
   • It loads that institute’s settings (theme, colours, menu) from school_site_settings.
   • The theme, a set of React components shipped with the application and reviewed by you, draws
     the pages from the institute’s public listing data and its school_pages content.
   • The finished page is cached, so busy periods such as admissions do not slow the server.
   • Each address has its own page titles, canonical links and sitemap, so search engines treat it as
     the institute’s own site.

Custom domains and HTTPS
The server needs a security certificate for every institute domain. The safe pattern is to let a front web
server such as Caddy request certificates on demand, but only after asking the application first. The
application answers yes only for domains that exist in school_domains and have been verified. This
stops anyone from pointing a random domain at your server to trigger certificate requests.




Version 1.1 · 17 November 2026                                                                          Page 11 of 20
```

### Page 12

```text
Schoolhouse · Project planning document


Templates and page content
Templates are part of the application code and are installed by you. An institute chooses one and
changes colours, fonts, menu, sections and page text through settings, but cannot upload code. Page
text is written in a rich-text editor (Tiptap) and stored as structured blocks (JSON), then drawn by React.
Because no raw HTML is stored, there is no HTML sanitiser to maintain and no script injection through
page content.

Suggested project layout
   • One Laravel project with two front-end entry points: a server-rendered one for the Finder and
     institute websites, and a client-rendered one for the dashboard.
   • Folders inside resources/js: finder (public pages), sites (one folder per theme), dashboard
     (management app) and ui (shared components and design tokens).
   • A separate mobile folder for the Expo app, sharing the generated API types.

12.3 Front-end tools (suggested)
All tools below are free, open source and were checked on npm on 17 November 2026 for licence and
recent updates. Use TypeScript for new front-end code: it catches mistakes before release and lets the
front end share types with the API.

 Purpose                         Suggested tool                         Why

 Page framework                  Inertia.js with React 19 and server-   One project, no separate API for public pages,
                                 side rendering                         readable by search engines.

 Build tool                      Vite                                   Already used in both repositories.

 Styling                         Tailwind CSS 4                         Fast to build; colour variables make per-
                                                                        institute themes simple.

 Components and                  shadcn/ui (copied into your project,   You own the code, accessible by default, easy
 icons                           built on Radix UI) with lucide-react   to restyle.

 Data loading                    TanStack Query                         Caching and loading states for the dashboard
                                                                        and Finder filters.

 Data tables                     TanStack Table                         Sorting, filtering and paging of long student
                                                                        and fee lists.

 Forms                           React Hook Form with Zod               Fast forms with clear rules. The server still
                                                                        validates everything.

 Charts                          Recharts                               Attendance and fee dashboards.

 Rich text                       Tiptap                                 Editor for pages and notices; stores structured
                                                                        content instead of raw HTML.

 Maps                            Leaflet with OpenStreetMap, used       Free. Avoid react-leaflet 5, which has a non-
                                 directly                               standard licence.

 Dates and messages              date-fns; Sonner for pop-up            Small and reliable.
                                 messages

 API documentation               Scramble (Laravel package) with        Documentation and TypeScript types
 and types                       openapi-typescript                     generated from the API itself, so front end and
                                                                        back end cannot drift apart.

 Urdu and right-to-left          i18next with the Noto Nastaliq Urdu    Only if Urdu is needed.
                                 font (open font licence)




Version 1.1 · 17 November 2026                                                                               Page 12 of 20
```

### Page 13

```text
Schoolhouse · Project planning document


 Purpose                         Suggested tool                       Why

 Testing                         Vitest, React Testing Library,       Unit, component and end-to-end checks.
                                 Playwright; MSW for API mocks
                                 (optional)

 Shared component                Storybook (optional)                 Build and review shared components on their
 workshop                                                             own.

 Installable web app             vite-plugin-pwa (optional)           Lets the dashboard install on a phone before
                                                                      the app exists.

 Mobile                          Expo with expo-router and expo-      Planned for Step 6.
                                 notifications

 Page speed                      Lighthouse (in Chrome) and           Check Finder pages on slow mobile networks.
                                 Google PageSpeed Insights

 Design drafts                   Figma (free plan)                    Agree the look of the Finder, themes and
                                                                      dashboard before coding.




13. Development environment

13.1 Software on your computer
 Item                        Recommended                              Why

 PHP                         8.4 or newer                             Required by many of the recommended
                                                                      packages (the Spatie set); it also satisfies
                                                                      school-gpt, which needs 8.3 or newer.

 Composer                    Version 2                                PHP package manager.

 Node.js                     22 LTS or 24 LTS                         Builds the React front end. Node 20 reached
                                                                      end of life in 2026; upgrade the server as well.

 Database                    MariaDB or MySQL, the same as            Avoids surprises between laptop and server.
                             production                               Check which one production runs (see section
                                                                      16 note).

 Redis                       Version 7 or newer                       Cache, queues and sessions.

 Local tooling               Laravel Herd (Windows, macOS) or         Quick, repeatable setup of PHP, database
                             Docker with Laravel Sail                 and Redis.

 Editor                      VS Code or PhpStorm                      PHP and React support, debugging.

 Git and GitHub              Latest Git; private repositories         Version control, issues, automated checks.

 API and database            Bruno or Postman; DBeaver or             Test the API and inspect data.
 tools                       TablePlus

 Mail testing                Mailpit                                  Catches emails during development.

 Mobile                      Expo, Android Studio emulator, a         Needed once the app phase starts.
                             real phone



Quality tools
 Purpose                                    Tool

 Backend tests                              PHPUnit or Pest (school-gpt uses PHPUnit; school-ai uses Pest)


Version 1.1 · 17 November 2026                                                                            Page 13 of 20
```

### Page 14

```text
Schoolhouse · Project planning document


 Purpose                                  Tool

 Front-end tests                          Vitest and React Testing Library

 End-to-end tests                         Playwright for sign-up, claim, fee and attendance flows

 Code style and checks                    Laravel Pint, Larastan, ESLint, Prettier

 Automated checks on every                GitHub Actions running tests and style checks
 change



13.2 Computer requirements
 Item                            Minimum                               Comfortable

 Memory                          8 GB                                  16 GB or more (Docker, database, editor and
                                                                       emulator together)

 Storage                         256 GB SSD                            512 GB SSD

 Processor                       Quad-core, 64-bit                     Recent 6- or 8-core

 Operating system                Windows 11 with WSL 2,                Same
                                 macOS, or Ubuntu

 Internet                        Stable broadband                      Stable broadband and a backup connection



13.3 Three environments
 Environment               Purpose                                            Notes

 Local                     Daily development on your computer.                Test data only; never real student
                                                                              records.

 Staging                   A copy of production for testing releases          Small server, same software versions,
                           and for showing progress.                          password-protected.

 Production                The live platform.                                 Backups, monitoring and security
                                                                              hardening switched on.


Working method. Keep a stable main branch, build each feature on its own branch, run automated
tests on every change, and release to staging first. Deploy by script (for example through GitHub
Actions) so that releases are repeatable and quick to undo.

13.4 Production server
Your Linode notes already describe a suitable base: Ubuntu 24.04 LTS, Nginx, a MariaDB database,
Node.js, Composer, Certbot and fail2ban. Upgrade PHP-FPM from 8.3 to 8.4 or newer, because many
recommended packages require it. For this platform add the following.

 Need                                Recommendation

 Queue workers                       Run background jobs under Supervisor or systemd; schedule the Laravel
                                     scheduler with cron.

 Cache and sessions                  Install Redis.

 Web server                          Nginx with PHP-FPM and OPcache enabled; HTTP/2 and compression.




Version 1.1 · 17 November 2026                                                                              Page 14 of 20
```

### Page 15

```text
Schoolhouse · Project planning document


 Need                                Recommendation

 HTTPS for many domains              Wildcard certificate for platform subdomains. For customers’ own domains use
                                     automatic certificates, for example Caddy with on-demand certificates in front
                                     of the application, or an automated Certbot job.

 Firewall and hardening              UFW, fail2ban, SSH keys only, automatic security updates, and review of
                                     unknown services on the server.

 Backups                             Server snapshots plus a nightly database dump copied to separate storage.
                                     Test a restore regularly.

 Monitoring                          Uptime monitor, error tracking (for example Sentry), log rotation, disk and
                                     memory alerts.

 CDN and DNS                         Cloudflare in front of the site for caching, protection and DNS control.

 Starting size                       Begin with a mid-sized plan (about 4 GB memory) and move the database to
                                     its own server when load grows.



13.5 Outside services
 Need                                Options

 SMS and WhatsApp                    A Pakistani SMS gateway for bulk messages; WhatsApp Business API for
                                     richer notices. Plan to charge institutes per message.

 Email                               Amazon SES, Postmark or Brevo.

 Online fee payment (later)          JazzCash, Easypaisa or bank integrations; add only after the core system is
                                     stable.

 Maps                                OpenStreetMap with Leaflet (free) or Google Maps.

 Push notifications                  Firebase Cloud Messaging.

 File storage                        S3-compatible object storage.

 Domains                             A .pk registrar for the platform name and for selling domains if you choose to.



13.6 Packages
Use only free packages with permissive licences (MIT, BSD or Apache-2.0) from trusted maintainers,
add them step by step, and keep each one behind your own class so it can be replaced. The full list, the
risks and the install order are in the separate Package Selection Guide.

 Need                                     Packages

 Sign-in and roles                        Laravel Sanctum, Laravel Fortify, spatie/laravel-permission

 Public pages and search                  Inertia, Laravel Scout with Meilisearch

 Files and images                         spatie/laravel-medialibrary, Flysystem S3 adapter

 Search-engine basics                     spatie/laravel-sluggable, spatie/laravel-sitemap

 Operations                               spatie/laravel-backup, spatie/laravel-health

 Audit trail                              spatie/laravel-activitylog

 Imports and documents                    Laravel Excel, DomPDF

 API documentation                        Scramble


Version 1.1 · 17 November 2026                                                                            Page 15 of 20
```

### Page 16

```text
Schoolhouse · Project planning document




14. Requirements

14.1 Functional requirements
Priority: Must = needed for launch, Should = soon after, Could = later.

 Requirement                                                                                       Priority

 Public directory with search, filters, profiles, compare and saved list                           Must

 Import of the existing taleemfinder.pk data and redirect of old addresses                         Must

 Institute registration, claiming and verification                                                 Must

 Free plan with enforced limits (80 students, 15 staff, other limits)                              Must

 School management: students, attendance, staff, fees, expenses                                    Must

 Excel import of students and staff                                                                Must

 Platform admin area (approvals, plans, users)                                                     Must

 Parent enquiries sent to institutes                                                               Should

 Exams, results and report cards                                                                   Should

 Notices and SMS or WhatsApp delivery                                                              Should

 Parent and student portal                                                                         Should

 Mobile app (shared)                                                                               Should

 Own domain and templates (paid)                                                                   Should

 College and institute modules                                                                     Should

 Reviews and events                                                                                Could

 University module                                                                                 Could

 Branded mobile apps, online payments, book exchange, study notes                                  Could



14.2 Non-functional requirements
 Area                            Requirement

 Security                        Strict separation of institute data, tested automatically; hashed passwords; rate
                                 limits; audit trail.

 Privacy                         Student data concerns children. Collect the minimum, restrict access by role, ask
                                 consent before sharing parent contact details, and support deletion on request.
                                 Check the data protection rules that apply in Pakistan before launch.

 Performance                     Directory pages load quickly on mobile networks; search results return in about a
                                 second; heavy work runs in the background.

 Availability and                Daily backups with tested restores; monitoring and alerts.
 backups

 Usability                       Mobile-first design; simple language; Urdu with right-to-left layout when decided;
                                 accessible colours and text sizes.



Version 1.1 · 17 November 2026                                                                              Page 16 of 20
```

### Page 17

```text
Schoolhouse · Project planning document


 Area                            Requirement

 Scalability                     Handle thousands of institutes and tens of thousands of students by adding cache,
                                 search and separate database capacity.

 Maintainability                 Automated tests, written documentation, consistent code style, small modules.




15. Roadmap
The steps below are in the recommended order. Timing depends on how much time you and any
helpers can give, so no dates are fixed here.

 Step       Focus                                    Result

 0          Finalise the database                    Every table and column agreed in the workbook (version 2
                                                     already includes the plan, domain, theme and app tables).

 1          Foundation and directory                 Laravel + React project set up from school-gpt; directory pages,
                                                     search and the taleemfinder.pk import with redirects.

 2          Registration, claiming, plans            Institutes can register or claim a listing, are placed on the Free
                                                     plan, and limits are enforced.

 3          Management for schools                   Hardened school module, setup wizard, Excel import, parent
                                                     enquiries, basic reports.

 4          Exams, notices, portal                   Results, timetable, notices and the parent and student portal.

 5          Own domain and templates                 Domain verification, automatic HTTPS, templates and site
                                                     settings for paying institutes.

 6          Mobile app                               Token sign-in, versioned API, push notifications, shared Expo
                                                     app.

 7          More institute types and                 College, institute and university modules; reviews, events, book
            community                                exchange.




16. Database changes needed
Version 2 of the workbook Schoolhouse-Database-Plan.xlsx now includes the tables that the free plan,
institute websites and mobile app need, in addition to the directory, claiming and community tables. It
lists 82 tables in total: the 46 that exist today and 36 proposed new ones.

 Table                                    Purpose                                            Step and status

 plans                                    Plan definitions: Free, Standard and so on.        Step 2 · moved up from
                                                                                             “later”

 plan_limits                              One row per limit per plan (students, staff,       Step 2 · added in version 2
                                          logins, storage).

 school_subscriptions                     Which plan each institute is on, and for how       Step 2 · moved up from
                                          long.                                              “later”

 school_domains                           Institute domains, verification and certificate    Step 5 · added in version 2
                                          status.

 site_themes                              Templates available to institutes.                 Step 5 · added in version 2




Version 1.1 · 17 November 2026                                                                               Page 17 of 20
```

### Page 18

```text
Schoolhouse · Project planning document


 Table                                    Purpose                                          Step and status

 school_site_settings                     Chosen template, colours, menu and               Step 5 · added in version 2
                                          homepage settings per institute.

 school_pages                             Editable pages of the institute’s own website,   Step 5 · added in version 2
                                          stored as structured blocks.

 personal_access_tokens                   Sign-in tokens for mobile devices (standard      Step 6 · added in version 2
                                          Laravel Sanctum table).

 device_tokens                            Push notification address of each phone.         Step 6 · added in version 2

 Academic tables for college,             Faculties, programmes, semesters, courses,       Step 7 · still to be
 university, institute                    batches.                                         designed; names must not
                                                                                           clash with the directory’s
                                                                                           courses and programs
                                                                                           tables



  Check the database engine
  Your data dictionary states that the application uses MySQL locally and in production, while your Linode
  notes list MariaDB. Confirm which engine runs where, and use the same engine in every environment.




17. Risks and how to reduce them
 Risk                            Why it matters                           How to reduce it

 Scope grows too large           Management, directory, domains           Follow the step order; ship small modules;
                                 and mobile are each a large              postpone university and community
                                 product.                                 features.

 Fake or duplicate               A false owner could edit a real          Verification codes, documents and manual
 claims                          institute’s listing.                     review for doubtful cases.

 Free plan abuse                 People could create many accounts        Tie accounts to verified phone numbers;
                                 to avoid limits.                         review unusual patterns.

 Student data privacy            Records concern children and are         Minimum data, role-based access, audit
                                 sensitive.                               trail, consent, secure backups.

 Loss of search ranking          A careless migration can remove          Keep addresses, redirect every changed
                                 years of search visibility.              one, test before switching.

 Custom domain                   DNS mistakes cause many support          Built-in domain checker and clear
 support load                    requests.                                instructions; automatic certificates.

 Mobile app effort               Store accounts, reviews and              Start with the web app; one shared app;
                                 updates take ongoing time.               branded apps only as a paid service.

 Single-developer                Illness or time pressure can stall the   Documentation, automated tests and clean
 dependency                      project.                                 version control so another developer can
                                                                          step in.




18. Decisions needed from you
  1. Confirm the free limits: 80 students and 15 staff are set; confirm the proposed 5 logins, 1 GB
     storage and basic reports.


Version 1.1 · 17 November 2026                                                                              Page 18 of 20
```

### Page 19

```text
Schoolhouse · Project planning document

  2. Decide whether a free subdomain (for example name.yourplatform.pk) is included in the Free
     plan.
  3. Choose the pricing model: flat tiers or per student per month.
  4. Confirm the order of institute types: school first, then college and institute, then university.
  5. Choose the mobile route: installable web app first, then one shared React Native app.
  6. Confirm the database engine (MariaDB or MySQL) for every environment.
  7. Decide whether Urdu is needed at launch.
  8. Decide when reviews are introduced and how they are moderated.
  9. Choose the final platform name and domain.


19. Next steps
  1. Review the Table Plan sheet in the workbook and mark each table Agreed, Needs change or
     Drop.
  2. Review the new plan, domain, theme and app tables added in version 2 of the workbook (section
     16).
  3. Prepare the local environment from section 13 and confirm school-gpt runs on it.
  4. Set up a small staging server with the same software as production.
  5. Write the database migrations for the agreed Step 1 tables and the taleemfinder.pk import script.


Appendix – Glossary and source material
 Term                                     Meaning

 SCUI                                     Schools, colleges, universities and institutes.

 Tenant                                   One institute using the shared system; its data is kept separate from every
                                          other institute.

 Listing                                  The public profile of an institute in the directory.

 Claim                                    A request by the real owner to take control of an existing listing.

 Plan                                     A package of limits and features, such as Free or Standard.

 SSR (server-side rendering)              Pages are built on the server so search engines can read them.

 PWA                                      A website that can be installed on a phone like an app.

 DNS, CNAME                               The address book of the internet; a CNAME record points a domain to
                                          another address.

 OTP                                      One-time password: a short code sent by SMS, WhatsApp or email.

 Modular monolith                         One application organised into clearly separated modules.

 Theme (template)                         A ready-made look for an institute’s own website, built by you and chosen
                                          by the institute.

 Inertia                                  A Laravel tool that lets React draw the pages while Laravel stays in charge
                                          of data and routing.

 Design system                            One shared set of components, colours and fonts used by every front-end
                                          application.




Version 1.1 · 17 November 2026                                                                                  Page 19 of 20
```

### Page 20

```text
Schoolhouse · Project planning document

Source material: taleemfinder.pk (live site); github.com/sherbathian/school-gpt and
github.com/sherbathian/school-ai (reviewed 17 November 2026); Schoolhouse-Database-Data-
Dictionary.xlsx (46 tables, 408 columns); Schoolhouse-Database-Plan.xlsx (version 2); Schoolhouse-
Package-Selection-Guide.docx.




Version 1.1 · 17 November 2026                                                           Page 20 of 20
```


## Package Selection Guide v1.1 source extract

The following text preserves the source wording and table content for reference. Resolve any conflict using the current scope above and the Database Plan v4 decisions.

### Page 1

```text
SCHOOLHOUSE
Package selection and dependency security guide
Which Laravel and front-end packages to use, why they are needed, and how to keep the
application safe and easy to extend


Version 1.1 · 17 November 2026




Companion to the Schoolhouse Project Planning Document. Package facts were checked on 17 November 2026 from
each package’s public repository or registry. No package was installed or tested.
```

### Page 2

```text
Schoolhouse · Package selection and dependency security guide


1. Summary
You asked for free, reliable Laravel packages that are necessary, do not create security problems, and
leave the application easy to extend. This guide gives the short list, the reasons, the risks of each
package, and the rules that keep the list safe over time.

 Group                           Count      Meaning

 Core packages                   14         Install when the matching step of the roadmap starts. Each one saves
                                            substantial work or handles a security-sensitive job better than home-
                                            made code.

 Optional packages               10         Useful in particular situations. Decide when you reach that need, not
                                            before.

 Development tools               5          Used while building and testing. Never active on the live server (except
                                            where stated).

 Build yourself                  5          Small, specific pieces where no package fits your rules, or where owning
                                            the code is safer.

 Avoid                           4          Packages or choices that add risk, cost or lock-in for this project.



  Four points to know first
  1. PHP 8.4. Most Spatie packages on the list (activity log, backup, sluggable, sitemap, translatable, query
  builder, health) now require PHP 8.4. Use PHP 8.4 or newer on your computer and on the server. The
  Project Planning Document (version 1.1) now uses the same baseline: PHP 8.4 or newer, Laravel 13 and
  React 19.
  2. Laravel 13. Every core package listed declares support for Laravel 12 and 13. The activity log
  package asks for Laravel 13.32 or newer, so update the framework first.
  3. One correction. In my earlier message I said to skip Laravel Horizon because its main branch did not
  list Laravel 13. That was wrong: its current 5.x line supports Laravel 12 and 13. Horizon is now listed as
  optional (it needs Redis).
  4. One licence trap. The React wrapper for Leaflet maps (react-leaflet 5) uses the Hippocratic licence,
  which is not a standard open-source licence. Use Leaflet itself, which is BSD-licensed.




2. How packages were chosen
Every package had to pass the same tests. A package that fails any test is either rejected or marked
optional with a reason.

 Test                                    What was checked

 Free and permissive licence             MIT, BSD or Apache-2.0 only, so you can run a paid service without licence
                                         conditions.

 Active maintenance                      Recent commits and releases in the public repository (checked 17
                                         November 2026).

 Works with your stack                   Declares support for Laravel 13 and a PHP version you will run.

 Trusted maintainer                      Official Laravel packages, Spatie, or a long-established project with a large
                                         user base.

 Small attack surface                    Does one job; does not execute uploaded code; does not need broad
                                         system access.




Version 1.1 · 17 November 2026                                                                                Page 2 of 10
```

### Page 3

```text
Schoolhouse · Package selection and dependency security guide


 Test                                   What was checked

 Easy to replace                        Can be hidden behind your own class, so a future swap changes one file,
                                        not the whole application.

 Genuinely needed                       Saves real effort or handles a risky job (passwords, files, backups).
                                        Otherwise Laravel’s own features are used.


Rule of thumb: use Laravel’s built-in features first (policies, queues, scheduler, cache, validation, rate
limiting, storage). Add a package only when it clearly beats writing and maintaining the code yourself.


3. Core packages
Risk shows how much damage a flaw in the package could do in your setup: Low, Medium or High.
“Step” refers to the roadmap in the planning document.

 Package                  What it does for             Needed?        Risk        Hardening and notes
                          Schoolhouse

 laravel/sanctum          Sign-in for the web          Yes ·          Low         Official. Keep cookie settings strict;
                          dashboard (cookies)          already in                 set short expiry on mobile tokens;
                          and the mobile app           school-gpt                 allow only your own domains for
                          (tokens).                                               cookie sign-in. Step 1.

 laravel/fortify          Password reset, email        Yes ·          Low         Official and security-reviewed.
                          verification and two-        school-gpt                 Safer than writing reset logic
                          factor sign-in (back end     has no                     yourself. Enable rate limits; turn on
                          only; you keep your          password                   two-factor for platform admins.
                          own React screens).          reset                      Step 2.

 spatie/laravel-          Roles and permissions        Yes ·          Medium      Wrong permissions mean data
 permission               per institute.               already in                 leaks, so test every role. Keep the
                                                       school-gpt                 per-institute setting on. Step 1.

 inertiajs/inertia-       Server-rendered React        Yes            Low         Official adapter; large user base.
 laravel (and             pages so search                                         Never put secrets in page data,
 @inertiajs/react)        engines can read the                                    because Inertia sends it to the
                          directory.                                              browser. Step 1.

 laravel/scout (with      Fast filtered search         Yes            Low         Official. Index only public fields.
 meilisearch/meilis       across institutes and                                   Keep the Meilisearch key private
 earch-php)               courses.                                                and run Meilisearch on a private
                                                                                  network. Step 1.

 spatie/laravel-          Photos, logos and            Yes            Medium      Uploads are a classic attack route.
 medialibrary             documents with                                          Allow only listed file types, set size
                          automatic thumbnails.                                   limits, store outside the public
                                                                                  folder, and keep image libraries
                                                                                  updated. Step 1.

 spatie/laravel-          Readable, unique             Yes            Low         Tiny and focused. Keep old slugs
 sluggable                address names such                                      in the redirects table when a name
                          as                                                      changes. Step 1.
                          /schools/islamabad/b-1
                          7/.

 spatie/laravel-          XML sitemaps for             Yes            Low         Include only published listings so
 sitemap                  search engines.                                         drafts never leak. Step 1.

 spatie/laravel-          Scheduled backups of         Yes            Medium      Backups hold all student data.
 backup                   the database and files.                                 Encrypt archives, store them off
                                                                                  the server, protect the storage
                                                                                  keys, and test a restore. Step 1.


Version 1.1 · 17 November 2026                                                                               Page 3 of 10
```

### Page 4

```text
Schoolhouse · Package selection and dependency security guide


 Package                  What it does for             Needed?           Risk       Hardening and notes
                          Schoolhouse

 spatie/laravel-          Checks and alerts for        Yes               Low        Keep the status page behind a
 health                   disk, database, queue                                     secret or login. Step 1.
                          and scheduler
                          problems.

 league/flysystem-        Lets Laravel store files     Yes, when         Low        Official Laravel filesystem adapter.
 aws-s3-v3                on S3-compatible             you move                     Use keys limited to one bucket.
                          storage instead of the       storage off                  Step 1 or 2.
                          server disk.                 the server

 spatie/laravel-          Audit trail: who             Yes               Medium     Never log passwords, one-time
 activitylog              changed fees, marks,                                      codes or tokens. Restrict who can
                          student records and                                       read the log. Needs PHP 8.4 and
                          when.                                                     Laravel 13.32+. Step 2.

 maatwebsite/excel        Excel and CSV import         Yes               Medium     Reads uploaded files, so validate
                          of students and staff;                                    type and size and process in the
                          report export.                                            queue. When exporting, neutralise
                                                                                    cells that start with = + - @ so
                                                                                    spreadsheets cannot run injected
                                                                                    formulas. Step 3.

 barryvdh/laravel-        PDF fee receipts,            Yes               Medium     Has a history of vulnerabilities
 dompdf                   vouchers and report                                       when it fetches remote content.
                          cards.                                                    Keep remote fetching switched off,
                                                                                    never render HTML typed by
                                                                                    users, and update promptly. Last
                                                                                    repository activity is July 2026, so
                                                                                    watch it. Step 3.




4. Optional packages
Decide on these when the need is real. Each one adds code you must keep updated.

 Package                     Use                                Risk       When and how

 spatie/laravel-             Finds the institute from the       Medium     Choose at Step 5 (custom domains). Works
 multitenancy                web address and keeps                         with one shared database. You can instead
                             queued jobs aware of the                      write about 100 lines of your own
                             current institute.                            middleware; choose the package if you
                                                                           want tenant-aware queued jobs without
                                                                           extra work.

 laravel/pennant             Switch features on or off          Low        Handy for paid features and gradual
                             per institute or plan.                        releases. Do not use it for plan limits (80
                                                                           students, 15 staff); that is your own service.

 spatie/laravel-             Store the same field in            Low        Only if Urdu is needed at launch. Adds
 translatable                English and Urdu.                             JSON columns; plan the search impact first.

 spatie/laravel-query-       Filter and sort lists through      Low        Useful at Step 6. Allow only a listed set of
 builder                     the API for the mobile app.                   filters so no one can query private columns.

 laravel/socialite           “Sign in with Google” for          Low        Official. Still verify the phone number for
                             parents.                                      claims. Requires a Google developer setup.

 laravel/horizon             Dashboard and tuning for           Medium     Official; supports Laravel 12 and 13; needs
                             queue workers.                                Redis. Protect the dashboard route. Plain
                                                                           queues with Supervisor also work.




Version 1.1 · 17 November 2026                                                                                 Page 4 of 10
```

### Page 5

```text
Schoolhouse · Package selection and dependency security guide


 Package                     Use                                Risk       When and how

 sentry/sentry-laravel       Reports errors from the            Low        The package is MIT; the Sentry service has
                             website, dashboard and                        a free tier with limits. Remove personal data
                             app.                                          before sending.

 dedoc/scramble              Writes the API                     Low        MIT; supports Laravel 10 to 13; active in
                             documentation                                 November 2026. Keep the documentation
                             automatically from your                       page private or behind login in production.
                             code, so a TypeScript                         Useful from Step 3 onward.
                             client can be generated
                             from it.

 filament/filament           Ready-made admin panel             Medium     Can save weeks, but it brings its own
                             for the platform                              Livewire stack beside React. Confirm
                             administrator area                            Laravel 13 support for the version you
                             (approvals, plans,                            install before adopting.
                             moderation).

 lab404/laravel-             Let you view an institute          High       This is an account-takeover feature by
 impersonate                 exactly as its administrator                  design. Allow only platform admins, log
                             sees it, for support.                         every use, show a visible banner, and never
                                                                           allow it on other admins. Last repository
                                                                           activity was March 2026.




5. Development tools
 Package                           Purpose                                       Safety note

 laravel/pint                      Automatic code style.                         Development only.

 larastan/larastan                 Finds bugs by reading the code (static        Development only.
                                   analysis).

 pestphp/pest or PHPUnit           Automated tests. school-gpt uses              Development only. Pick one and use
                                   PHPUnit; school-ai uses Pest. Pest 5          it for the whole project.
                                   needs PHP 8.4.

 laravel/pail                      Reads the application log live in the         Development only.
                                   terminal.

 laravel/telescope                 Shows requests, queries and jobs while        Local only. It records request data,
                                   you develop.                                  so leaving it on a live server exposes
                                                                                 private information. Install as a
                                                                                 development dependency and keep it
                                                                                 disabled in production.




6. Build yourself
 Item                                Why not a package                           What to do

 Plan limits (80 students, 15        Your rules are specific, and a small        One PlanLimits service that answers
 staff, logins, storage)             service is easier to audit than a           “may this institute add another?” and
                                     general package.                            is called from every create action.

 Claim and verification flow,        Security-critical and tied to your own      Hash codes, expire them, limit
 one-time codes                      rules and SMS provider.                     attempts and requests, log outcomes.

 Domain verification and             Done at the server (DNS checks,             A job that checks DNS, plus Caddy or
 certificate handling                automatic HTTPS), not in Laravel.           an automated Certbot job.


Version 1.1 · 17 November 2026                                                                               Page 5 of 10
```

### Page 6

```text
Schoolhouse · Package selection and dependency security guide


 Item                                  Why not a package                          What to do

 API versioning for the                A routing convention, not a package.       Routes under /api/v1; add v2 beside it
 mobile app                                                                       when needed.

 Tenant isolation tests                Only you know which records must           An automated test for every new
                                       never cross institutes.                    module that logs in as institute A and
                                                                                  tries to read institute B.




7. Avoid
 Choice                                     Reason

 stancl/tenancy                             Well made, but its normal mode gives every institute its own database. That
                                            is much harder to run, back up and report across, and your design already
                                            uses one shared database safely. It also needs PHP 8.4.

 react-leaflet 5 (Hippocratic               A non-standard licence with usage conditions. Use Leaflet directly (BSD-2-
 licence)                                   Clause) or another map library with a standard licence.

 Unmaintained or unknown                    Anything with no release in about a year, a single unknown maintainer, or
 packages                                   no tests. Write the small piece yourself instead.

 Packages that install or run               A theme or plugin system that lets institutes upload PHP or JavaScript lets
 uploaded code                              one institute attack all of them. Keep templates reviewed and installed by
                                            you.




8. Front-end and mobile packages
The same tests apply to JavaScript packages. Dates are the last time each package changed on the
npm registry (17 November 2026).

 Package                         Use                          Licence      Last           Note
                                                                           updated

 react, react-dom                User interface.              MIT          Nov 2026       Core of the dashboard and
                                                                                          public pages.

 react-router                    Page navigation in the       MIT          Oct 2026       school-ai uses it; school-gpt
                                 dashboard.                                               has none. Use one router
                                                                                          only in the dashboard.

 @inertiajs/react                Connects Laravel to          MIT          Nov 2026       For the server-rendered
                                 React pages.                                             public side.

 @tanstack/react-query           Loads and caches data        MIT          Nov 2026       Removes a lot of hand-
                                 from the API.                                            written loading code.

 react-hook-form with            Forms and validation.        MIT          Nov 2026 /     Always validate again on the
 zod                                                                       Oct 2026       server; browser checks are
                                                                                          only convenience.

 vite                            Build tool.                  MIT          Nov 2026       Already used by both
                                                                                          repositories.

 tailwindcss                     Styling.                     MIT          Nov 2026       Optional; use your existing
                                                                                          style approach if you prefer.




Version 1.1 · 17 November 2026                                                                                 Page 6 of 10
```

### Page 7

```text
Schoolhouse · Package selection and dependency security guide


 Package                         Use                       Licence     Last       Note
                                                                       updated

 @tanstack/react-table           Sorting, filtering and    MIT         Oct 2026   For student, fee and
                                 paging of long lists.                            attendance lists in the
                                                                                  dashboard.

 recharts                        Charts.                   MIT         Nov 2026   Attendance and fee
                                                                                  dashboards.

 @tiptap/react                   Rich-text editor for      MIT         Nov 2026   Stores structured content, so
                                 pages and notices.                               no raw HTML needs to be
                                                                                  stored or cleaned.

 radix-ui, lucide-react,         Accessible building       MIT / ISC   Aug–Nov    shadcn/ui is copied into your
 tailwind-merge,                 blocks, icons and pop-                2026       project rather than installed
 sonner                          up messages (the base                            as a package.
                                 of shadcn/ui).

 date-fns                        Date handling.            MIT         May 2026   Small and stable.

 openapi-typescript              Creates TypeScript        MIT         Jun 2026   Pair with Scramble so front
                                 types from the API                               end and API stay in step.
                                 documentation.

 storybook, msw, vite-           Component workshop,       MIT         Oct–Nov    All optional.
 plugin-pwa                      API mocks for tests,                  2026
                                 installable web app.

 leaflet                         Maps                      BSD-2-      Nov 2026   Use directly, not through
                                 (OpenStreetMap).          Clause                 react-leaflet 5.

 i18next                         Urdu and English text,    MIT         Nov 2026   Only if Urdu is needed.
                                 right-to-left support.

 expo                            Mobile app (React         MIT         Nov 2026   Planned for Step 6.
                                 Native).

 vitest, @testing-               Unit, component and       MIT /       Aug–Nov    Development only.
 library/react,                  end-to-end tests.         Apache-2.   2026
 @playwright/test                                          0

 eslint, prettier                Code checks and           MIT         Nov 2026   Development only.
                                 formatting.

 axios or the built-in           Calls to the API.         MIT         Nov 2026   Fewer dependencies is safer:
 fetch                                                                            a small wrapper around fetch
                                                                                  is enough. If you keep axios,
                                                                                  update it promptly.




9. Keeping dependencies safe over time
Choosing good packages is the first half. The second half is a routine that stops a safe list from
becoming an unsafe one.

Automatic checks
   • Run composer audit and npm audit on every change in GitHub Actions and once a week; fail
     the build on known vulnerabilities.
   • Switch on GitHub Dependabot and security alerts (free) so you are told about fixes.
   • Commit composer.lock and package-lock.json. Install on servers with composer install --no-
     dev --optimize-autoloader and npm ci; never run composer update on the live server.


Version 1.1 · 17 November 2026                                                                          Page 7 of 10
```

### Page 8

```text
Schoolhouse · Package selection and dependency security guide


Version rules
   • Use normal version ranges (for example ^8.5). Never use dev-main, dev-master or *.
   • Apply security fixes within days. Apply routine updates monthly and major upgrades quarterly,
     each behind the automated tests.
   • For non-urgent upgrades, wait about two weeks after a new release before adopting it, so a bad
     or hijacked release is found by others first.

Supply-chain protection
   • List allowed Composer plugins explicitly (allow-plugins) and review any package that runs install
     scripts; use npm ci --ignore-scripts where the build allows.
   • Turn on two-factor sign-in for your GitHub, npm and hosting accounts; use tokens limited to one
     repository.
   • Remove packages you no longer use. Every package is attack surface.
   • Check licences with composer licenses and a licence checker for npm. Accept MIT, BSD and
     Apache-2.0; stop and review anything else.

Settings that must be right in production
   • Application debug mode off; Telescope disabled; error pages that show no details.
   • PDF remote fetching off; upload types and sizes limited; backup archives encrypted and stored off
     the server.
   • Search, Redis and database reachable only from the server’s private network.


10. Keeping the application easy to extend
A package should never be woven through your whole code. These rules keep every package
replaceable.
   • Wrap each package behind your own class. For example ImportService (Excel), PdfRenderer
     (PDF), MediaStorage (media library), AuditLogger (activity log), PlanLimits. The rest of the code
     calls your class. Replacing a package then touches one file.
   • Use Laravel first. Policies, gates, queues, the scheduler, validation, rate limiting and the cache
     are built in and need no package.
   • Publish and commit package configuration. Changes are then reviewed like code. Never edit
     files inside the vendor folder.
   • Extend through events and hooks, not by copying package code.
   • Organise by feature (listings, claims, students, fees, domains), each with its own tests, so new
     institute types are added as modules.
   • Keep core tables standard. Use ordinary migrations. Let packages own only the tables they are
     meant to own (permissions, media, activity log).
   • Use feature switches for risky or paid features so they can be turned off quickly.
   • Keep the total small. Aim for fewer than about 25 direct production packages across Laravel and
     JavaScript combined.


11. Installation order
Install packages when their step begins, not all at once. Use PHP 8.4. Commands are shown for
reference; Composer will pick the release that fits your Laravel version.



Version 1.1 · 17 November 2026                                                                 Page 8 of 10
```

### Page 9

```text
Schoolhouse · Package selection and dependency security guide


 Step                                Install

 1 · Foundation and directory        composer require inertiajs/inertia-laravel laravel/scout meilisearch/meilisearch-
                                     php http-interop/http-factory-guzzle spatie/laravel-medialibrary spatie/laravel-
                                     sluggable spatie/laravel-sitemap spatie/laravel-backup spatie/laravel-health
                                     league/flysystem-aws-s3-v3
                                     composer require --dev laravel/pint larastan/larastan laravel/pail

 2 · Registration, claiming,         composer require laravel/fortify spatie/laravel-activitylog
 plans                               Optional: laravel/pennant

 3 · School management               composer require maatwebsite/excel barryvdh/laravel-dompdf

 5 · Own domains and                 Optional: spatie/laravel-multitenancy (or your own host-resolving middleware)
 templates

 6 · Mobile app                      Optional: spatie/laravel-query-builder
                                     Already available: laravel/sanctum (token mode)

 Whenever needed                     laravel/socialite, spatie/laravel-translatable, sentry/sentry-laravel,
                                     laravel/horizon, filament/filament

 Development only                    composer require --dev laravel/telescope (keep disabled in production); Pest
                                     or PHPUnit




12. Checklist before adding any new package
  1. Is there a Laravel feature that already does this?
  2. Is the licence MIT, BSD or Apache-2.0?
  3. Was there a release or commit in the last year, and is it from a known maintainer or vendor?
  4. Does it declare support for your Laravel and PHP versions?
  5. Does it handle uploads, passwords, files, network requests or run code? If yes, read its security
     notes and test it with hostile input.
  6. Can it be wrapped behind your own class so it can be replaced?
  7. Does it add database tables, and if so, can it respect the institute ID?
  8. Have you run composer audit or npm audit after installing it?
  9. Is the reason for adding it written down in the project notes?


Appendix – Verification data
Taken from each package’s public repository on 17 November 2026. “Laravel” and “PHP” are the
constraints in the package’s current composer.json. This is a snapshot, not a test: run Composer to
confirm for the exact versions you install.

 Package                                    Last repository        Licence      Laravel                   PHP
                                            activity

 laravel/sanctum                            Nov 2026               MIT          ^12.0 | ^13.0             ^8.2

 laravel/fortify                            Nov 2026               MIT          ^12.0 | ^13.0             ^8.2

 spatie/laravel-permission                  Nov 2026               MIT          ^12.0 | ^13.0             ^8.3

 inertiajs/inertia-laravel                  Nov 2026               MIT          ^11.35 | ^12.0 |          ^8.2.0
                                                                                ^13.0



Version 1.1 · 17 November 2026                                                                                   Page 9 of 10
```

### Page 10

```text
Schoolhouse · Package selection and dependency security guide


 Package                                    Last repository      Licence     Laravel                  PHP
                                            activity

 laravel/scout                              Nov 2026             MIT         ^12.0 | ^13.0            ^8.2

 meilisearch/meilisearch-php                Nov 2026             MIT         see note                 ^8.1

 spatie/laravel-medialibrary                Nov 2026             MIT         ^12.0 | ^13.0            ^8.2

 spatie/laravel-sluggable                   Nov 2026             MIT         ^13.0                    ^8.4

 spatie/laravel-sitemap                     Nov 2026             MIT         ^13.0                    ^8.4

 spatie/laravel-backup                      Nov 2026             MIT         ^12.40 | ^13.0           ^8.4

 spatie/laravel-health                      Nov 2026             MIT         ^13.0                    ^8.4

 league/flysystem-aws-s3-v3                 Nov 2026             MIT         see note                 ^8.0.2

 spatie/laravel-activitylog                 Nov 2026             MIT         ^13.32                   ^8.4

 maatwebsite/excel                          Nov 2026             MIT         ^12.0 | | ^13.0          ^8.3

 barryvdh/laravel-dompdf                    Jul 2026             MIT         ^9 | ^10 | ^11 | ^12 |   ^8.1
                                                                             ^13

 spatie/laravel-multitenancy                Nov 2026             MIT         ^12.0 | ^13.0            ^8.3

 laravel/pennant                            Nov 2026             MIT         ^12.0 | ^13.0            ^8.2

 spatie/laravel-translatable                Nov 2026             MIT         ^12.0 | ^13.0            ^8.4

 spatie/laravel-query-builder               Nov 2026             MIT         ^12.0 | ^13.0            ^8.4

 laravel/socialite                          Nov 2026             MIT         ^12.0 | ^13.0            ^8.2

 laravel/horizon                            Nov 2026             MIT         ^12.0 | ^13.0            ^8.2

 sentry/sentry-laravel                      Nov 2026             MIT         ^6 to ^11, ^12.0 |       ^7.2 | ^8.0
                                                                             ^13.0

 lab404/laravel-impersonate                 Mar 2026             MIT         ^6 to ^11, ^12.0 |       ^7.2 | ^8.0
                                                                             ^13.0

 dedoc/scramble                             Nov 2026             MIT         ^10.0 | ^11.0 | ^12.0    ^8.1
                                                                             | ^13.0

 stancl/tenancy                             Nov 2026             MIT         ^12.0 | ^13.0            ^8.4


Notes: constraints are read from each package’s current branch, so older releases may support older versions. “See
note” marks libraries that do not depend on Laravel directly (meilisearch-php and the S3 adapter).

Sources: public GitHub repositories of the packages listed; the npm registry (versions, licences, last-published
dates); github.com/sherbathian/school-gpt and school-ai composer files; the Schoolhouse Project Planning
Document.




Version 1.1 · 17 November 2026                                                                           Page 10 of 10
```

