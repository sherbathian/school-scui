# Progress

Status values: `todo`, `doing`, `in review`, `done`, `blocked`. The builder updates its own row; the manager updates the order.
**Credits** = the usage Codex shows for the task (write what it shows; leave blank if none). Use it to see which tasks were expensive.

| Task | Name | Depends on | Status | PR | Tests (new / total) | Credits | Note |
|---|---|---|---|---|---|---|---|
| T00 | Baseline and pack check | none | todo | | | | |
| T01 | Schema gap command | T00 | todo | | | | |
| T02 | Admin UI kit, shell, sign-in | T00 | todo | | | | |
| T03 | Listings pages | T02 | todo | | | | |
| T04 | Cities and Areas pages | T03 | todo | | | | |
| T05 | Organisations, branches, owner access | T01 | todo | | | | |
| T06 | Plans, modules, entitlement tables | T05 | todo | | | | |
| T07 | ModuleAccess service | T06 | todo | | | | |
| T08 | Module enforcement on routes | T07 | todo | | | | |
| T09 | Platform-admin module switches | T07, T02 | todo | | | | |
| T10 | Claims data layer | T05 | todo | | | | |
| T11 | Public accounts | T02 | todo | | | | |
| T12 | Claims: submit and one-time code | T10, T11 | todo | | | | |
| T13 | Claims: admin review and approval | T12, T05 | todo | | | | |
| T14 | Plan limits and enforcement | T08 | todo | | | | |
| T15 | Audit trail | T09, T13 | todo | | | | |

## Backlog (briefs are written just in time, after the previous report)
- Billing consolidation: new invoice columns, switch code and tests, then retire `LegacyFeeType`, `LegacyStudentFeeSnapshot`, `FamilyFeeReceipt` and the school import command (see `docs/specs/spec-fees-billing.md`).
- `student_transfers` and branch transfer screen; owner reports across branches.
- Students, guardians, staff, attendance, fees and expenses dashboard pages in the admin pattern.
- Plans and add-on purchase screens; subscription invoices and payments.
- Marketing website (pricing from plans and modules, demo requests) and the institute Website module.
- Exams, timetable, notices, parent and student portal; mobile access.
