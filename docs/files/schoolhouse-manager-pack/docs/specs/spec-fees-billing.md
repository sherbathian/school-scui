# Spec: one fee flow (price list → charges → monthly invoice → payments)

Two billing systems existed side by side. They are joined into one flow. Dropping legacy_fee_types forces the invoice redesign.

## Rules
- Flow: fee_types → fee_groups (+fee_group_grade) → fee_masters (price list) → student_fee_charges (one per student per fee type per month) → fee_invoices (one per student per month) → fee_payments.
- Money is decimal(12,2) rupees everywhere. amount_cents is removed.
- fee_invoices: unique (school_id, invoice_no) and unique (school_id, student_id, billing_month).
- A discount is applied through student_fee_discounts; the charge records discount_amount.
- Every link includes school_id so one branch cannot use another branch's fee types, price lists, students or invoices.
- Retire in order: add new columns → switch code and tests → drop legacy_fee_types, legacy_student_fee_snapshots, family_fee_receipts and the import command.

## Tables

### `fee_types`  ·  Existing  ·  Built
Current fee catalog items.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID); Unique index | Existing |
| school_id | integer | no |  | Foreign key; Unique index → schools.id | Existing |
| title | varchar | no |  | Unique index | Existing |
| description | text | yes |  | — | Existing |
| created_at | datetime | yes |  | — | Existing |
| updated_at | datetime | yes |  | — | Existing |

### `fee_groups`  ·  Existing  ·  Built
Reusable fee group definitions.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID); Unique index | Existing |
| school_id | integer | no |  | Foreign key; Unique index → schools.id | Existing |
| title | varchar | no |  | Unique index | Existing |
| description | text | yes |  | — | Existing |
| allows_discount | tinyint(1) | no | '1' | — | Existing |
| created_at | datetime | yes |  | — | Existing |
| updated_at | datetime | yes |  | — | Existing |

### `fee_group_grade`  ·  Existing – change  ·  Built
Grades eligible for a fee group.
*Note:* school_id added so a fee group can only contain classes of its own branch. Same-branch rules tightened.
(0 unchanged existing columns not listed.)

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| fee_group_id | integer | no |  | Primary key; Foreign key → fee_groups.school_id + fee_groups.id | Changed |
| grade_id | integer | no |  | Primary key; Foreign key; Index → grades.school_id + grades.id | Changed |
| school_id | integer | no |  | Foreign key → schools.id | New |

Same-branch (composite) links: (school_id, grade_id) → grades(school_id, id); (school_id, fee_group_id) → fee_groups(school_id, id)

### `fee_masters`  ·  Existing  ·  Built
Fee group/type amount rules by session and billing month.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | Existing |
| school_id | integer | no |  | Foreign key → schools.id | Existing |
| school_session_id | integer | no |  | Foreign key; Unique index → school_sessions.school_id + school_sessions.id | Existing |
| fee_group_id | integer | no |  | Foreign key; Unique index → fee_groups.school_id + fee_groups.id | Existing |
| fee_type_id | integer | no |  | Foreign key; Unique index → fee_types.school_id + fee_types.id | Existing |
| due_date | date | no |  | — | Existing |
| amount | numeric | no |  | — | Existing |
| fine_type | varchar | no | 'none' | — | Existing |
| fine_value | numeric | no | '0' | — | Existing |
| created_at | datetime | yes |  | — | Existing |
| updated_at | datetime | yes |  | — | Existing |
| billing_month | varchar | no | '1970-01' | Unique index | Existing |

Same-branch (composite) links: (school_id, fee_type_id) → fee_types(school_id, id); (school_id, fee_group_id) → fee_groups(school_id, id); (school_id, school_session_id) → school_sessions(school_id, id)

### `student_fee_charges`  ·  Existing – change  ·  Built
Monthly fee amount assigned to an individual student.
*Note:* Each monthly charge is linked to the invoice it appears on and can carry a discount. Same-branch rules tightened.
(9 unchanged existing columns not listed.)

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| fee_master_id | integer | no |  | Foreign key; Unique index → fee_masters.school_id + fee_masters.id | Changed |
| school_session_id | integer | no |  | Foreign key; Index → school_sessions.school_id + school_sessions.id | Changed |
| fee_invoice_id | integer | yes |  | Foreign key → fee_invoices.school_id + fee_invoices.id | New |
| discount_amount | decimal(12,2) | no | '0' | — | New |

Same-branch (composite) links: (school_id, student_id) → students(school_id, id); (school_id, school_session_id) → school_sessions(school_id, id); (school_id, fee_master_id) → fee_masters(school_id, id); (school_id, fee_invoice_id) → fee_invoices(school_id, id)

### `student_fee_discounts`  ·  New  ·  P3 Engagement
Which discount (sibling, staff child, scholarship) applies to which student, and for how long.
*Note:* Without this table fee_discounts could be defined but never applied to anyone.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| school_id | integer | no |  | Foreign key; Index → schools.id | New |
| student_id | integer | no |  | Foreign key → students.school_id + students.id | New |
| fee_discount_id | integer | no |  | Foreign key → fee_discounts.school_id + fee_discounts.id | New |
| school_session_id | integer | yes |  | Foreign key → school_sessions.school_id + school_sessions.id | New |
| starts_on | date | yes |  | — | New |
| ends_on | date | yes |  | — | New |
| note | varchar(255) | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

Same-branch (composite) links: (school_id, student_id) → students(school_id, id); (school_id, fee_discount_id) → fee_discounts(school_id, id); (school_id, school_session_id) → school_sessions(school_id, id)

### `fee_invoices`  ·  Existing – change  ·  Built
Historical student-level fee invoices.
*Note:* Re-pointed after legacy_fee_types was dropped: one invoice per student per month, built from student_fee_charges. Money fields in rupees (decimal) instead of cents.
(7 unchanged existing columns not listed.)

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| fee_type_id | integer | no |  | Foreign key; Unique index → legacy_fee_types.school_id + legacy_fee_types.id | Removed |
| academic_year | varchar | no |  | Unique index; Index | Removed |
| description | varchar | no |  | — | Removed |
| amount_cents | integer | no |  | — | Removed |
| invoice_no | varchar(30) | no |  | Unique index (school_id, invoice_no) | New |
| school_session_id | integer | no |  | Foreign key → school_sessions.school_id + school_sessions.id | New |
| billing_month | varchar(7) | no |  | Unique index (school_id, student_id, billing_month) | New |
| subtotal | decimal(12,2) | no | '0' | — | New |
| discount_total | decimal(12,2) | no | '0' | — | New |
| fine_total | decimal(12,2) | no | '0' | — | New |
| total_amount | decimal(12,2) | no | '0' | — | New |
| paid_total | decimal(12,2) | no | '0' | — | New |

Same-branch (composite) links: (school_id, student_id) → students(school_id, id); (school_id, school_session_id) → school_sessions(school_id, id)

### `fee_payments`  ·  Existing – change  ·  Built
Payments recorded against historical invoices.
*Note:* Amount now in rupees (decimal) to match invoices; payment method added.
(8 unchanged existing columns not listed.)

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| amount_cents | integer | no |  | — | Removed |
| amount | decimal(12,2) | no |  | — | New |
| payment_method | varchar(20) | no | 'cash' | — | New |

Same-branch (composite) links: (school_id, fee_invoice_id) → fee_invoices(school_id, id)

## Tests to write first (from the Design Check sheet, DC = design check number)
- [ ] DC9 · Each branch has its own fee types, groups, price list, charges and invoices
- [ ] DC10 · A payment cannot be recorded in one branch against another branch's invoice
- [ ] DC11 · An invoice cannot be raised in one branch for another branch's student
- [ ] DC12 · A price list cannot use another branch's fee type
- [ ] DC13 · A charge cannot use another branch's price list
- [ ] DC14 · A fee group cannot contain another branch's classes
- [ ] DC15 · Only one invoice per student per month
- [ ] DC16 · A discount from another branch cannot be given to a student
- [ ] DC17 · An expense cannot use another branch's expense head
