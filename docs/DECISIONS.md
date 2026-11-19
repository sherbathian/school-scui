# Schoolhouse decisions

| Date recorded | Decision | Options considered | Decided by |
| --- | --- | --- | --- |
| 19 Nov 2026 | The workbook supplied under `docs/updated` is the canonical Database Plan v4. The repository copy is `docs/Schoolhouse-Database-Plan-v4.xlsx`. | Earlier local v4 copy or updated v4 copy. | Tahir, confirmed during repository sync |
| 19 Nov 2026 | Each listing and managed branch share one `schools` row. | Shared row or separate directory and management rows. | Tahir |
| 19 Nov 2026 | MySQL is the database target for development and integration testing. Confirm the production engine before staging. | MySQL or PostgreSQL for implementation. | Tahir |
| 19 Nov 2026 | Each verified institute gets an organisation record. | Every verified institute or only multi-branch institutes. | Tahir |
| 19 Nov 2026 | Super Admin approves claims. An approved claimant gets organisation owner access across its branches. | Organisation owner access or access to the claimed branch only. | Tahir |
| 19 Nov 2026 | Only platform admins change branch module switches. | Platform admins only, or platform admins and authorised organisation owners. | Tahir |
| 19 Nov 2026 | Admin workspaces share Inertia, React 19, Tailwind CSS, and shadcn/ui. | Shared role-based admin or separate frontend stacks. | Tahir |

Open decisions include parent phone sign-up, media storage, the public fee source, free-plan modules and quotas, grace period, pricing, the marketing hostname, and the production database engine. Confirm only the decisions needed for the next increment.
