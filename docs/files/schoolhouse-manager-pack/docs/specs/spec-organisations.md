# Spec: organisations, branches and owner access

One organisation owns one or more branches. Each branch is one `schools` row with its own students, staff and accounts. The owner sees every branch.

## Rules
- An organisation is a `school_groups` row. A branch is a `schools` row whose `group_id` points at it.
- `school_group_user` links a user to an organisation with a role (owner, group_admin, group_accountant, group_viewer). An active link lets the user open EVERY branch of that organisation, including branches added later.
- `school_user` links branch staff to one or more branches; they see only those branches.
- Access rule: a user may open branch X when (1) a `school_user` link to X is active, OR (2) an active `school_group_user` link exists to the organisation X belongs to.
- `branch_code` is unique inside one organisation.
- Records of one branch never point at another branch: links include `school_id` (composite keys).
- Moving a student between branches opens a new student record in the new branch and writes `student_transfers`; the old record is closed.

## Tables

### `school_groups`  ·  New  ·  P1 Directory
The organisation that owns one or more branches (a school, college, university or institute). Every institute that registers gets one.
*Note:* Each branch is one row in schools, with its own students, staff, fees and accounts. The owner is linked in school_group_user and sees all branches. Also serves as the public brand (for example one school network with several campuses).

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| name | varchar(150) | no |  | — | New |
| slug | varchar(150) | no |  | Unique index | New |
| description | text | yes |  | — | New |
| website_url | varchar(255) | yes |  | — | New |
| logo_path | varchar(255) | yes |  | — | New |
| status | varchar(20) | no | 'active' | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `school_group_user`  ·  New  ·  P2 Claim & plans
Who belongs to an organisation at owner level, and with what role. This is what lets the owner see every branch.
*Note:* A person here may open every branch of the organisation. Branch staff are still linked only through school_user.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| school_group_id | integer | no |  | Primary key; Foreign key → school_groups.id | New |
| user_id | integer | no |  | Primary key; Foreign key → users.id | New |
| role | varchar(30) | no | 'owner' | — | New |
| is_active | tinyint(1) | no | '1' | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `schools`  ·  Existing – change  ·  Built
School / tenant records that own school-specific data.
*Note:* Existing rows: set listing_status='draft'. management_enabled is removed: module switches now live in school_modules. 'status' stays the on/off switch of the whole record; 'listing_status' controls public visibility.
(7 unchanged existing columns not listed.)

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| institute_type | varchar(30) | no | 'school' | Index | New |
| group_id | integer | yes |  | Foreign key → school_groups.id | New |
| short_name | varchar(80) | yes |  | — | New |
| tagline | varchar(160) | yes |  | — | New |
| description | text | yes |  | — | New |
| gender_type | varchar(15) | yes |  | — | New |
| medium | varchar(30) | yes |  | — | New |
| established_year | smallint | yes |  | — | New |
| city_id | integer | yes |  | Foreign key → cities.id | New |
| area_id | integer | yes |  | Foreign key → areas.id | New |
| address_line | varchar(255) | yes |  | — | New |
| latitude | decimal(10,7) | yes |  | — | New |
| longitude | decimal(10,7) | yes |  | — | New |
| phone | varchar(30) | yes |  | — | New |
| whatsapp | varchar(30) | yes |  | — | New |
| email | varchar(190) | yes |  | — | New |
| website_url | varchar(255) | yes |  | — | New |
| social_links | json | yes |  | — | New |
| logo_path | varchar(255) | yes |  | — | New |
| cover_path | varchar(255) | yes |  | — | New |
| fee_min | decimal(10,2) | yes |  | Index | New |
| fee_max | decimal(10,2) | yes |  | — | New |
| rating_avg | decimal(3,2) | yes |  | — | New |
| rating_count | integer | no | '0' | — | New |
| listing_status | varchar(20) | no | 'draft' | Index | New |
| claim_status | varchar(20) | no | 'unclaimed' | Index | New |
| source | varchar(20) | no | 'admin_created' | — | New |
| verified_at | datetime | yes |  | — | New |
| published_at | datetime | yes |  | — | New |
| is_featured | tinyint(1) | no | '0' | — | New |
| featured_until | datetime | yes |  | — | New |
| seo_title | varchar(160) | yes |  | — | New |
| seo_description | varchar(300) | yes |  | — | New |
| branch_name | varchar(100) | yes |  | — | New |
| branch_code | varchar(20) | yes |  | Unique index (group_id, branch_code) | New |
| is_main_branch | tinyint(1) | no | '0' | — | New |

### `student_transfers`  ·  New  ·  P3 Engagement
History of a student moving from one branch to another inside the same organisation.
*Note:* The student gets a new record in the new branch (the old record is closed), so each branch keeps its own admission number, fees and balance.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| from_school_id | integer | no |  | Foreign key → schools.id | New |
| to_school_id | integer | no |  | Foreign key → schools.id | New |
| from_student_id | integer | no |  | Foreign key → students.school_id + students.id | New |
| to_student_id | integer | no |  | Foreign key → students.school_id + students.id | New |
| transfer_date | date | no |  | — | New |
| reason | varchar(255) | yes |  | — | New |
| transferred_by_user_id | integer | yes |  | Foreign key → users.id | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

Same-branch (composite) links: (from_school_id, from_student_id) → students(school_id, id); (to_school_id, to_student_id) → students(school_id, id)

## Tests to write first (from the Design Check sheet, DC = design check number)
- [ ] DC1 · Owner of the organisation sees both branches
- [ ] DC2 · Branch accountant sees only their own branch
- [ ] DC3 · Person with no link sees nothing
- [ ] DC4 · Teacher linked to two branches sees both, but not the organisation as owner
