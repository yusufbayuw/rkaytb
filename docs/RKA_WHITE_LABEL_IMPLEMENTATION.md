# RKA White-Label Platform — Implementation Inventory

## Non-negotiable product contract

- The two supplied documents are **format and regression references only**. They are never used to seed the organization, units, hierarchy, staff roles, chart of accounts, pricing, allocation methods, program templates, or content.
- New tables live under the rka_ namespace. Legacy models, tables, and resource routes remain in place. There is no automatic backfill or deletion.
- An organization is explicitly created by a pre-existing superadmin or admin-keuangan, and the creator receives an organization-specific owner membership. Other users must be added using existing accounts. Users do not inherit organization membership solely from a global Spatie role.
- Members can access only organizations in which their memberships are active. Question access is restricted further by question ownership, organization-level workflow roles, or valid scoped invitations.
- Financial plan allocation is not a journal posting. Imported XLSX journal lines remain in **staged** status until the reconciliation and posting policy is fully implemented.

## Implemented in the initial vertical slice

1. Organization membership, branding fields, configurable units, budget cycles and draft budget lines.
2. Configurable weighted/equal shared-cost allocation with deterministic cent rounding and recipient tenant validation.
3. Configurable question definitions (structured rules DSL), assignment, internal invitations, acceptance, multi-author answers and optimistic revision check.
4. Source-linked narrative synthesis, conflict blocking and independent review (a reviewer cannot verify a synthesis authored by the same user).
5. XLSX journal staging with explicit header mapping, schema validation, input hash idempotency, external journal-line keys and audit events.
6. Dynamic book section templates and independent editorial review. Published books freeze a snapshot. Initial DOCX/PDF/XLSX outputs derive from that snapshot.
7. Scoped Filament 2 workspace; no fixed unit, organization name, chapter outline, source account or allocation configuration.

## Manual first-run operations

1. Run migrations on a staging copy after obtaining a DB backup; verify legacy migrations and FK compatibility.
2. Sign in with an existing user holding Spatie role \`superadmin\` or \`admin-keuangan\`.
3. Navigate to **Rencana Kerja & Anggaran → Workspace RKA**. Create the organization with its own name.
4. Configure branding, units, memberships and an RKA cycle. Add institution-defined question banks and book sections. No example organization is created automatically.
5. Invite only existing authenticated users who are members of that organization.
6. For ledger import, use a custom XLSX mapped to header keys \`line_id\`, \`journal_no\`, \`date\` (yyyy-mm-dd), \`account_code\`, \`debit\`, \`credit\`. Optional keys: \`unit_code\`, \`posting_status\`, \`entry_kind\`, \`related_reference\`. Header mapping JSON can translate external column names. Identity values must be unique within source+organization. Use staged data for verification, not official reporting.

## Strictly unfinished — do not label production-ready

- Organization-wide administrator controls need dedicated configuration pages, branding/logo upload, revocable invitation management and granular permission evaluation for evidence files.
- Final approval policy engine, risk thresholds, SoD across all financial workflows, SLA escalation and delegated approvals are only **data model / design**, not operationally enforced for every action.
- Cost-pool configuration supports only equal/weighted calculation. Historical comparison, other driver algorithms, journal-aware elimination and reconciled KPI Budget are not completed.
- XLSX import stages normalized rows but does not implement the final posted-actual journal reconciliation, corrections, accounting close, authoritative ledger, or financial publication workflow.
- Book editor and exports are a working **text-first foundation**, not a faithful enterprise book layout: no advanced DOCX tables/charts/TOC, full evidence linking, or full style-preserving XLSX template import/export.
- The app has no independent browser UAT evidence; tests, full integration and security audit must be evaluated before merge/deploy.

## Legacy safety

- Do not remove \`rkas\`, monthly columns, or existing Filament Resources.
- Audit the current monthly display callback: it saves computed values during rendering, which can mutate records on a read.
- Audit \`User::canAccessFilament()\` returning true before widening app access.
- Treat legacy values stored as strings as a migration hazard; use decimal values and parity checks for new budget amounts.
- Never auto-enable the new module for every user or tenant by migrating arbitrary legacy \`unit_id\` data into memberships.

## Planned next workstreams

1. Tenant configuration UX, permission and evidence policies, audit access review, full SoD.
2. Budget baseline/revision approvals, mapping of existing budget rows and richer cost-pool drivers.
3. XLSX to reconciled actuals, journal pairing, reversal and consolidation policies, KPI profiles.
4. Content blocks with verified datasets, multi-contributor fact checks, section dependencies, workflow.
5. Professional DOCX/PDF/XLSX rendering parity, full end-to-end simulated unit/org regression, staging UAT.

Never claim this initial pull request implements the full business blueprint.
