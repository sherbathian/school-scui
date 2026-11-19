# Schoolhouse MySQL schema audit

19 November 2026 · baseline `c152ef1`

Compared the local MySQL 8.4.5 schema with the **Table Plan** and **Columns** sheets of `Schoolhouse-Database-Plan-v4.xlsx`. MySQL metadata was scoped to the `school_scui` database. The comparison covers table and selected column names. Type, index, foreign-key, default, and nullability parity across all tables still needs a separate review.

## Current coverage

| Measure | Count |
| --- | ---: |
| Local tables | 48 |
| Applied Laravel migrations | 31 |
| Tables in Database Plan v4 | 90 |
| Planned tables present by exact name | 45 |
| Planned tables absent by exact name | 45 |
| Local tables outside the v4 table list | 3 |

The three local-only tables are `family_fee_receipts`, `legacy_fee_types`, and `legacy_student_fee_snapshots`. Existing payment and invoice flows still reference the legacy schema. Plan an additive migration and reconcile balances and receipts before removing old tables or columns.

### Gaps that affect sequencing

- **Directory and locations.** `cities` has all 8 planned columns and `areas` has all 10. `schools` has 22 of 43 planned columns. Basic listing moderation and location fields are present. Organisation, claims, media, SEO, and richer profile fields are still to be added. Directory lookup tables for boards, education levels, and facilities are pending.
- **Organisation and entitlements.** `school_groups`, `school_group_user`, `listing_claims`, `school_modules`, and plan/subscription tables are absent. The workbook adds `school_user.is_primary_contact`, which is also pending. Resolve organisation membership and provisioning before adding dependent access rules. Platform-admin-only module switching is already confirmed.
- **Fees.** Current invoice and payment amounts are stored as integer cents. V4 also calls for invoice numbering, billing month, session-linked invoices, totals, and payment method. Keep student fees separate from platform subscriptions, and confirm the money representation before migrating historical amounts.
- **Editorial and later modules.** Articles, events, persisted saved schools, institute websites, device access, and subscription billing are planned. The current public shortlist uses browser sessions.

| Workbook phase | Missing tables | Names |
| --- | ---: | --- |
| P1 Directory | 12 | `boards`, `course_categories`, `courses`, `education_levels`, `facilities`, `programs`, `school_board`, `school_education_level`, `school_facility`, `school_groups`, `school_listing_fees`, `school_media` |
| P2 Claim & plans | 14 | `demo_requests`, `listing_claims`, `module_dependencies`, `modules`, `notifications`, `otp_codes`, `plan_limits`, `plan_modules`, `plans`, `school_group_user`, `school_modules`, `school_subscriptions`, `subscription_limits`, `subscription_modules` |
| P3 Engagement | 8 | `articles`, `events`, `listing_leads`, `reviews`, `saved_schools`, `school_stats_daily`, `student_fee_discounts`, `student_transfers` |
| P4 Community | 3 | `book_inquiries`, `books`, `study_resources` |
| P5 Websites & mobile | 5 | `device_tokens`, `personal_access_tokens`, `school_pages`, `school_site_settings`, `site_themes` |
| P6 Billing | 3 | `subscription_invoice_lines`, `subscription_invoices`, `subscription_payments` |

## Upgrade rehearsal

In a disposable database on the same MySQL server, applied the 29 migrations through the inherited school-management baseline. Created a synthetic branch, membership, student, legacy fee invoice, and payment. Applied the two listing and location migrations and compared the records before and after.

- The database advanced from 29 to 31 applied migrations.
- The school, membership, student, invoice, and payment retained their existing values.
- The school acquired a `draft` listing status, with verification, publication, city, and area unset.
- Running migration again left the migration ledger and representative records unchanged.

The rehearsal covered the current additive migrations from the inherited baseline. V4's remaining migrations, staging acceptance, and backup/restore verification are still pending.
