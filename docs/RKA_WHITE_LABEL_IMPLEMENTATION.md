# RKA White-Label Platform — Implementation, Safety Gates and Rollout

## Product contract

The uploaded ADM RKA XLSX and 2027 RKA PDF are **example documents only**, not organization or accounting defaults. The application must never seed example organizations, fixed schools/departments, institution-specific unit names, job titles, account codes, cost-sharing percentages, academic categories, question banks or book chapters.

This branch adds a **new namespace of \`rka_*\` database tables**. Legacy tables and Filament Resources remain untouched. Legacy records are not migrated or copied automatically.

## Implemented and tested vertical slices

- Organization memberships, branding name/color/currency/locale/time zone, configurable units and annual cycles.
- Draft budget lines; independent reviewer approval and audit events.
- Equal / weighted shared-cost allocations; integer minor-unit rounding, recipient organization validation, independent approval. These are managerial plans, **not journal postings**.
- Versioned question definitions with a restricted rule DSL; question assignments include unit, question owner and **explicit independent reviewer**.
- Internal invitations, acceptance, cancellation/revocation, time-limited permissions, multiple answer revisions with optimistic revision checks.
- Scoped question listing and detail access: general editors/reviewers cannot enumerate all question content based on role alone.
- Private supporting-evidence upload and download, named independent review and verified-evidence requirement for evidence-bound synthesis; files are not public.
- XLSX import to **staging only** with configurable header mapping, file-hash idempotency, source/line overlap rejection, per-journal balance warnings and row validation. No financial actuals are posted from imports.
- Book sections, independent editorial review and export service skeleton. **Official publication is OFF by default** under \`RKA_ENABLE_OFFICIAL_PUBLICATION=false\`; do not enable until journal reconciliation, KPI Budget and closing are implemented and approved.
- Explicit superadmin-only **read-only legacy budget preview**, segregated from the new platform. It does not infer the historic budget year, copy records or automatically include their values in KPI calculations.
- Organization-configured **KPI Budget preview before Sharing Budget** based on approved budget-line categories. It excludes unapproved budgets, shared cost, reconciled actuals and eliminations; it is explicitly labeled NONFINAL.
- Replacement internal profile page after removing unavailable third-party \`ryangjchandler/filament-profile\` dependency; functionality includes name/email and password update.
- Automated SQLite/CI tests for access, financial approval, answer revisions, evidence, import staging, book-publication gates, allocation and preliminary KPI. A MySQL 8 CI matrix job is also configured.

## Safe first-run checklist

1. Back up the database before staging migrations. Never run \`migrate:fresh\` against production.
2. Install packages using the committed lock file, then run \`php artisan migrate --force\` **only in a controlled staging environment** after checking migrations and privileges.
3. Sign in with an existing authorized platform bootstrap account (Spatie \`superadmin\` or \`admin-keuangan\`), create a new organization with the user's own name, and assign existing accounts to it.
4. Create units, budget cycles and custom budget categories. Do **not** assume example document structures.
5. Create questions, explicitly assign independent reviewers and scoped contributors, and configure the evidence policy.
6. When importing XLSX journal files, provide \`line_id\`, \`journal_no\`, \`date\`, \`account_code\`, \`debit\`, \`credit\` columns, mapping any institution-specific headers. Data is staged and must not be treated as verified actuals.
7. Configure KPI category profile in a draft cycle; view the preview labeled "before Sharing Budget."
8. Keep \`RKA_ENABLE_OFFICIAL_PUBLICATION=false\` and verify the publication gate with a negative test.
9. Use superadmin legacy preview to compare historical data without modifying old records.
10. Perform database-specific schema tests, access-role UAT and complete business-flow tests before requesting a merge.

## Compliance and deliberate restrictions

- Both **organizations** and individual **question instances** have authorization controls.
- A reviewer must be assigned to the question, not merely possess the global reviewer role; reviewers cannot verify their own synthesis.
- A user may not accept another user's invitation, contribute after revocation, or use unverified evidence to approve evidence-required claims.
- Uploads use the local private disk, scoped storage paths and a controlled download endpoint. Production still needs malicious-file scanning, advanced data-retention rules, and granular document classification.
- No global budget-role privilege is granted automatically by creating a local organization membership.
- Legacy preview is **superadmin-only** because the original records do not have reliable organization IDs or budget-year references.
- Financial publication stays blocked. Only preview/test fixtures can enable the export snapshot code path under controlled test configuration.
- Staff and approver roles are currently **functional role values**; dynamic custom positions/delegation, risk/materiality workflow and SLA remain pending.

## Known gaps — DO NOT merge as completed RKA end-to-end or deploy to production

1. Full journal mapping, balanced posting/reversal processing, prevention of double counting at the ledger/consolidation layer, cost-pool source reconciliation, account/unit mapping, period closing, and authoritative actuals are **not implemented**. XLSX import is staging only.
2. Complete KPI Budget (with/without special funding categories, actuals, forecasts, after allocation, multi-period profiles, budget-vs-actual) is not implemented. The new calculator is **pre-sharing budget preview only**.
3. Full approval engine: role/position assignment, delegated authority, dynamic per-risk thresholds, unit disputes, SLA escalation, and SoD for every financial action require further implementation.
4. Book-content blocks, automatic data-linked tables/charts, professional DOCX layout with a TOC, document branding/logo, and PDF/XLSX report parity require further work.
5. Evidence content scanning, conflict resolution UI and full claim-level provenance are incomplete.
6. Legacy RKA adapters presently provide inspection **only**. Mapping legacy unit IDs, accounting periods, parent/child sums and string-valued monetary amounts to the new schema has not been approved or performed.
7. CI covers selected unit/feature tests; MySQL and complete browser UAT results must be checked before staging deployment. Passing CI alone does not prove production readiness.

## Rollout strategy

The project must use **expand → reconcile → switch**. Do not drop legacy columns/tables/resources. Verify parity on isolated staging data, create rollback plans, run authorization adversarial tests, test rendering of long documents, and keep the PR draft until critical accounting workflows are independently validated.

Known baseline risks to audit separately: legacy RKA table display may mutate records during formatting; global \`User::canAccessFilament()\` currently returns true. Neither should be changed casually without regression coverage for existing roles and pages.
