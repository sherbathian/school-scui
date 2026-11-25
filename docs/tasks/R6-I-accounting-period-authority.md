# R6-I · Define accounting-period authority

## Outcome

Define the trusted source of a branch's accounting periods and business timezone before exposing the internal receipt posting and reversal services.

## Read (proposed)

- `docs/R6-F-receipt-posting-rules.md`
- `backend/app/Services/PostFamilyReceipt.php`
- `backend/app/Services/ReverseFamilyReceipt.php`
- Relevant branch and role definitions listed in `docs/REPO-MAP.md`

## Touch (proposed)

- `docs/R6-I-accounting-period-authority.md` (new design)
- `docs/PROGRESS.md`
- `docs/DECISIONS.md` (approved decisions only)

## Decisions to propose and verify

- Whether one branch accounting period can be open at a time, who can open and close periods, and how exceptions are handled.
- The branch business timezone and the date that controls posting and reversal. Use Asia/Karachi in synthetic IFS scenarios.
- Separate the date funds were received from the accounting posting date. Define handling for earlier receipts and reversals of receipts from closed periods.
- Specify how posting, reversal and period closure share a transaction lock, fail safely when period state is unavailable, and handle retries after closure.
- Define audit records for opening and closing a period and finance permission boundaries. Include manual scenarios for midnight, month-end, missing period, closed period, concurrent close and posting, and replay.
- Propose exact implementation files and tests for separate approval.

Documentation only. Frontend build: no.
