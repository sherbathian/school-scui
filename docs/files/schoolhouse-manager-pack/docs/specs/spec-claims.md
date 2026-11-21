# Spec: add or claim an institution, verification

A verified institute gets a free public listing. Claiming an unclaimed listing proves who owns it. Verification gates everything.

## Rules
- Public access needs listing_status = published AND verified_at not null AND status active. This rule already exists for the public directory and must be reused, not copied.
- claim_status moves unclaimed → pending → claimed. A listing can have only one pending claim at a time.
- Claim methods: phone_otp, email_otp, document, manual. One-time codes are stored hashed, expire, and have limited attempts and request rates.
- Only a platform admin approves or rejects a claim. Approval is a transaction: two admins approving at the same time must produce one winner and one clear refusal.
- Approval makes the claimant an owner of the organisation (needs decision D1: organisation provisioning).
- A rejected claim shows its reason and can be resubmitted. Pending claims expire after a set time (default 14 days, config value).
- Claiming never changes publication: an unverified or suspended listing stays hidden whatever its claim status.

## Tables

### `listing_claims`  ·  New  ·  P2 Claim & plans
Requests by a person to claim (take over) an existing listing, with the review outcome.
*Note:* One verified claim turns claim_status on schools to 'claimed' and creates the school_user membership.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| school_id | integer | no |  | Foreign key; Index → schools.id | New |
| user_id | integer | no |  | Foreign key → users.id | New |
| claimant_name | varchar(150) | no |  | — | New |
| claimant_role | varchar(100) | yes |  | — | New |
| phone | varchar(30) | no |  | — | New |
| email | varchar(190) | yes |  | — | New |
| method | varchar(20) | no | 'phone_otp' | — | New |
| document_path | varchar(255) | yes |  | — | New |
| status | varchar(20) | no | 'pending' | Index | New |
| reviewed_by_user_id | integer | yes |  | Foreign key → users.id | New |
| reviewed_at | datetime | yes |  | — | New |
| review_note | text | yes |  | — | New |
| verified_at | datetime | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `otp_codes`  ·  New  ·  P2 Claim & plans
One-time codes sent by SMS, WhatsApp or email to verify a phone number or email.
*Note:* Store only a hash of the code, never the code itself.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| user_id | integer | yes |  | Foreign key → users.id | New |
| channel | varchar(10) | no |  | — | New |
| destination | varchar(190) | no |  | Index (destination, purpose) | New |
| purpose | varchar(30) | no |  | — | New |
| code_hash | varchar(255) | no |  | — | New |
| attempts | tinyint | no | '0' | — | New |
| expires_at | datetime | no |  | — | New |
| consumed_at | datetime | yes |  | — | New |
| ip_address | varchar(45) | yes |  | — | New |
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
