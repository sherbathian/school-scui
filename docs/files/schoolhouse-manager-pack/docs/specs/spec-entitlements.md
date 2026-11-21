# Spec: plans, modules, add-ons, limits and branch switches

The free listing is a core module. Everything else is a module sold through plans, add-ons or trials and switched on per branch by a platform admin.

## Rules
- Two layers: ENTITLEMENT (organisation): `plan_modules` of the current plan plus `subscription_modules` (add-ons, trials, grants) that have not ended. SWITCH (branch): `school_modules.is_enabled`. Both must be true.
- Only platform admins write `school_modules`. Owners and branch admins cannot (403, tested through direct requests).
- Data is never deleted when a module is switched off, downgraded or expired.
- Limits come from `plan_limits`; `subscription_limits` replaces a plan limit for one organisation. Limits count across all branches.
- Seed catalogue slugs: directory_listing (core), students, staff, attendance, fees, expenses, exams, parent_portal, sms, institute_website, university_academics (applies to universities only).
- Dependencies (verified): attendance, fees, exams and parent_portal each need students.
- Starter plan (default, editable seed): students, staff, attendance; limits 80 students, 15 staff, 1 branch (decision D3).

## Tables

### `modules`  ·  New  ·  P2 Claim & plans
The catalogue of modules that can be switched on: students, fees, exams, SMS, institute website and so on.
*Note:* One row per sellable or switchable feature. 'is_core' marks what every verified institute always has (the free listing). 'applies_to' limits a module to some institute types, for example university-only semester registration.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| slug | varchar(50) | no |  | Unique index | New |
| name | varchar(100) | no |  | — | New |
| description | varchar(255) | yes |  | — | New |
| module_group | varchar(30) | no | 'management' | — | New |
| applies_to | json | yes |  | — | New |
| is_core | tinyint(1) | no | '0' | — | New |
| is_active | tinyint(1) | no | '1' | — | New |
| sort_order | smallint | no | '0' | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `module_dependencies`  ·  New  ·  P2 Claim & plans
Which module needs which other module (fees needs students; parent portal needs students).
*Note:* Stops a branch from switching on a module whose foundation is off.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| module_id | integer | no |  | Primary key; Foreign key → modules.id | New |
| requires_module_id | integer | no |  | Primary key; Foreign key → modules.id | New |

### `plans`  ·  New  ·  P2 Claim & plans
Subscription plans (Free, Standard, Professional, Enterprise). A plan is a bundle of modules plus limits.
*Note:* Modules come from plan_modules and limits from plan_limits. The old features list inside this table is removed: it could not answer 'which modules, for which branch'.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| name | varchar(100) | no |  | — | New |
| slug | varchar(100) | no |  | Unique index | New |
| price_monthly | decimal(10,2) | no | '0' | — | New |
| is_default | tinyint(1) | no | '0' | — | New |
| is_active | tinyint(1) | no | '1' | — | New |
| sort_order | smallint | no | '0' | — | New |
| description | varchar(255) | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `plan_modules`  ·  New  ·  P2 Claim & plans
Which modules each plan includes.
*Note:* This is the main place to change what a plan contains; no code change needed.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| plan_id | integer | no |  | Primary key; Foreign key → plans.id | New |
| module_id | integer | no |  | Primary key; Foreign key → modules.id | New |
| created_at | datetime | yes |  | — | New |

### `plan_limits`  ·  New  ·  P2 Claim & plans
One row per limit per plan (students, staff, logins, storage, messages).
*Note:* A table instead of fixed columns, so a new limit needs no database change. Empty value means unlimited.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| plan_id | integer | no |  | Foreign key; Unique index (plan_id, limit_key) → plans.id | New |
| limit_key | varchar(50) | no |  | — | New |
| limit_value | integer | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `school_subscriptions`  ·  New  ·  P2 Claim & plans
Which plan each organisation is on and for how long.
*Note:* The plan belongs to the organisation. Modules come from the plan plus add-ons, and each branch switches them on or off in school_modules.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| school_group_id | integer | no |  | Foreign key; Index → school_groups.id | New |
| plan_id | integer | no |  | Foreign key → plans.id | New |
| status | varchar(20) | no | 'active' | — | New |
| starts_at | datetime | no |  | — | New |
| ends_at | datetime | yes |  | — | New |
| grace_ends_at | datetime | yes |  | — | New |
| payment_reference | varchar(100) | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `subscription_modules`  ·  New  ·  P2 Claim & plans
Modules an organisation has on top of its plan: paid add-ons, trials and manual grants.
*Note:* Lets you sell one extra module (for example SMS), give a 30-day trial of a premium module, or grant a module to a partner, each with an end date.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| school_subscription_id | integer | no |  | Foreign key; Index → school_subscriptions.id | New |
| module_id | integer | no |  | Foreign key → modules.id | New |
| source | varchar(15) | no | 'addon' | — | New |
| starts_at | datetime | no |  | — | New |
| ends_at | datetime | yes |  | — | New |
| granted_by_user_id | integer | yes |  | Foreign key → users.id | New |
| note | varchar(255) | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `subscription_limits`  ·  New  ·  P2 Claim & plans
Limits that replace a plan's limits for one organisation (a special deal or promotion).
*Note:* Example: this organisation gets 200 students instead of the plan's 80.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| id | integer | no |  | Primary key (auto ID) | New |
| school_subscription_id | integer | no |  | Foreign key; Unique index (school_subscription_id, limit_key) → school_subscriptions.id | New |
| limit_key | varchar(50) | no |  | — | New |
| limit_value | integer | yes |  | — | New |
| reason | varchar(255) | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

### `school_modules`  ·  New  ·  P2 Claim & plans
The on/off switch of each module for each branch.
*Note:* A branch can use a module only if it is switched on here and the organisation is entitled to it. The owner or a group manager can switch modules per branch.

| Column | Type | Null | Default | Key / link | Change |
|---|---|---|---|---|---|
| school_id | integer | no |  | Primary key; Foreign key → schools.id | New |
| module_id | integer | no |  | Primary key; Foreign key → modules.id | New |
| is_enabled | tinyint(1) | no | '1' | — | New |
| enabled_by_user_id | integer | yes |  | Foreign key → users.id | New |
| enabled_at | datetime | yes |  | — | New |
| created_at | datetime | yes |  | — | New |
| updated_at | datetime | yes |  | — | New |

## Tests to write first (from the Design Check sheet, DC = design check number)
- [ ] DC29 · Verified branch on the Free plan: free listing and starter modules work
- [ ] DC30 · Modules outside the plan are refused (fees on the Free plan)
- [ ] DC31 · An unverified institute gets nothing, not even the listing
- [ ] DC32 · After upgrading to Standard, fees works in the branch where it is switched on
- [ ] DC33 · The same module stays off in a branch where the owner switched it off
- [ ] DC34 · Switching on a module the organisation has not bought gives no access
- [ ] DC35 · ...even if the switch is turned on by mistake
- [ ] DC36 · A paid add-on (SMS) works for the organisation until its end date
- [ ] DC37 · A 30-day trial of a premium module works, then stops by itself
- [ ] DC38 · A module whose foundation is off does not work (parent portal needs students)
- [ ] DC39 · A university-only module is not available to a school
- [ ] DC40 · A special limit for one organisation replaces the plan's limit
- [ ] DC41 · The number of branches is limited by the plan (Standard allows 3)
- [ ] DC42 · After expiry, paid modules are read-only during the grace period
- [ ] DC43 · After the grace period paid modules stop but the free listing stays
- [ ] DC44 · Data is kept when a subscription ends
- [ ] DC45 · The owner sees which modules each branch has: Main and Gulberg differ
- [ ] DC46 · A branch cannot have two switches for the same module
- [ ] DC47 · A plan cannot list the same module twice
- [ ] DC48 · A dependency must point at a real module
- [ ] DC49 · Only one special limit per limit name per organisation

## Access decision (the algorithm the design check verified)

Inputs: branch (a `schools` row), module slug, time `at` (default now). Output: `full`, `read_only` or `none`, with a reason.

1. Module missing or inactive → none.
2. If the module is core (directory_listing): full when the branch is verified; otherwise none.
3. Branch not verified → none.
4. Module applies only to some institute types (`applies_to`) and the branch type is not among them → none.
5. Find the organisation's current subscription (status active, trial or grace). Active/trial → mode full. Grace (grace_ends_at in the future) → mode read_only. Otherwise → none.
6. Entitled? The module is in `plan_modules` of the plan, OR in `subscription_modules` with starts_at <= at and (ends_at is null or ends_at >= at). Otherwise → none.
7. Branch switch: `school_modules` row exists with is_enabled = true. Otherwise → none.
8. Dependencies: every module in `module_dependencies` for this module must itself pass steps 1–7. Otherwise → none.
9. Result: the mode from step 5 (full or read_only).

Never delete data in any path. Switching, downgrade and expiry only change the answer.
