# Planning

Course: IC-6831 Proyecto de Ingeniería de Software, II Semestre 2026. Project manager: Alejandro Quesada
(@AleQuesada2012). The planning round 3 baseline (2026-09-29) was provisionally revised by the PM on 2026-10-06
after an interview about remaining work. The Sprint 2 planning meeting has not taken place; this records the PM's
accepted provisional plan, not a team meeting decision. Tracking: [issue #146](https://github.com/AleQuesada2012/SETI-Management-System/issues/146).
Per-requirement owner, reviewer and sprint: [requirements/README.md](requirements/README.md), generated from
`requirements/requirements.json`. The dated decisions are in [decisions.md](decisions.md).

## Sprints

| Sprint | Delivery (Costa Rica time) | Content |
|---|---|---|
| Sprint 1 | Wed 2026-09-30 18:30 | The bootstrap (base platform) and the first screen of every module. |
| Sprint 2 | Wed 2026-10-14 18:30 | Finish existing PRs, then enabling uploads, notifications and manager assignment; asset deletion/components, asset attachments, reservation changes, assignment consultations, user filter completion, reports and first statistics. Prepare AWS/Power BI prerequisites. |
| Sprint 3 | Wed 2026-10-28 18:30 | Remaining events, histories, maintenance, roles/accounts, reports and statistics; AWS deployment and Power BI. Finish features by October 25; protect October 26–28 for integration, fixes, manuals and delivery. |

- Balance **effort remaining from October 6**, with RF counts as a secondary check. The old eight-RF target and
  strict write/read split are superseded by work packages, implementation reuse and dependency order.
- Assume roughly equal availability of one working day per person per week, assisted by coding agents. Tests,
  documentation, integration and reviews consume that availability; reserve short review windows throughout
  the week so a completed prerequisite does not wait for someone's next full implementation session.
- **Outstanding Sprint 1 work:** complete it first and count its remaining integration against current capacity.
  This revision preserves the Sprint 1 milestone of RF_63/RF_64 and RF_91/RF_100 and records their unfinished
  status; it does not run the retrospective carry-over script or rewrite the original delivery backlog.
  The general carry-over process at later deliveries remains in `scripts/github/carry-over.sh`.
- At each delivery the PM tags `main` as `sprint-1`, `sprint-2`, `sprint-3`.

## Plan by person

Snapshot: GitHub issues and visible PRs on 2026-10-06. Numbers below are RF suffixes and list **remaining open
requirements**, not previously completed scope. `*` marks RF_31 closing with RF_12 and RF_70/71/72 requiring
acceptance verification of the existing export pipeline. Owners and reviewers are unchanged.

| Person | Outstanding Sprint 1, finish first | Sprint 2 | Sprint 3 |
|---|---|---|---|
| Alejandro · @AleQuesada2012 | 63, 64 | 62, 68, 69, 70*, 71*, 72*, 75, 78, 80, 81, 84 + AWS/Power BI preparation | 61, 66, 67, 73, 76, 77, 82, 83 + most AWS implementation and Power BI connectivity test |
| David · @DavidZamoraB | — | 02, 06, 12, 25, 31* | 09, 13, 22, 28, 29 |
| Diego · @Dizabu | — | 20, 21, 41 | 34, 37, 38, 39, 74 |
| Jose · @JoseHerrera21 | — | 47, 48, 49, 50 | 10, 11, 26, 27, 52, 59, 65, 79 |
| Sebastián · @Rubiocai | 91, 100 | 40, 43, 92, 96, 97, 101 | 86, 87, 88, 93, 98, 99; AWS support as needed |

The table covers all 61 RF issues still open in Sprint 2/3 at this snapshot, plus four unfinished Sprint 1 RFs.
RF_60 is closed; RF_30 is already closed with RF_08 and retains its historical Sprint 3 metadata rather than being
counted as remaining work. Bootstrap requirements RF_00_1–RF_00_4/RF_89 and completed duplicates RF_56/RF_58
are likewise excluded. Previously delivered scope remains available in issue history and requirement metadata.

### Milestone changes accepted on 2026-10-06

These eleven RFs move **Sprint 3 → Sprint 2**; no other requirement milestone changes.

| Person | RFs | GitHub issues | Reason |
|---|---|---|---|
| David | RF_02, RF_12, RF_31 | #11, #21, #40 | Deletion/components have existing model and authorization foundations; RF_12/31 are one package. |
| Diego | RF_20, RF_21 | #29, #30 | Adapt RF_41's upload mechanism to asset documents/photos in the same sprint. |
| Jose | RF_49, RF_50 | #58, #59 | Complete modification/cancellation after the Sprint 2 notification foundation. |
| Sebastián | RF_92, RF_96, RF_97, RF_101 | #101, #105, #106, #110 | Complete partly implemented filters and consult the manager assignments delivered by RF_40/43. |

### Provisional remaining-effort estimate

Scale: small = 1, medium = 2, large = 4, very large = 6 relative planning units. These are estimates from acceptance
criteria, existing code and visible PRs, not measured hours or a guarantee of delivery. Each package includes
implementation, relevant tests, Spanish UI/manual evidence and author follow-up. Reviewing other people's PRs
requires additional capacity and is not included in these implementation totals. Unpublished teammate work is
not visible in this snapshot and may reduce the estimates.

| Person | Before: S2 / S3 | Revised: S2 / S3 | Total |
|---|---|---|---|
| Alejandro | 19 / 28 | 21 / 26 | 47 |
| David | 2 / 16 | 8 / 10 | 18 |
| Diego | 6 / 16 | 10 / 12 | 22 |
| Jose | 6 / 24 | 12 / 18 | 30 |
| Sebastián | 8 / 16 | 12 / 12 | 24 |

The revised Sprint 2 totals include Alejandro's two units for integrating RF_63/64 and Sebastián's four units for
integrating RF_91/100. They are existing implementations with review/conflict work remaining, not fresh builds.
David's RF_06/25 is also an existing PR (#143), budgeted as a two-unit integration/documentation package.

Package basis, with no repeated infrastructure cost:

- **Alejandro:** report integration (2); RF_62/70/71/72 acceptance completion (2); audit report RF_68 (2); users
  report RF_69 (2); warranties RF_75 (2); custodians RF_78 (1); shared chart/state statistics RF_80 (4); category
  statistics RF_81 (2); generation history RF_84 (2). Remaining report/statistics RF_66/67/76/77/82/83 are two units
  each; Power BI RF_61 (6), RF_73 (4), AWS task #111 (4), connectivity test #112 (2). One AWS unit and one RF_61
  readiness unit move into Sprint 2 as preparation; their delivery milestones remain Sprint 3.
- **David:** movement PR integration (2), deletion RF_02 (2), components RF_12/31 (4), shared loss/decommission
  mechanics RF_22/28/29 (4), incremental damage/evidence RF_13 (2), unified asset history RF_09 (4).
- **Diego:** uploads/space photos RF_41 (6), asset attachment adaptation RF_20/21 (4), space deletion RF_34 (2),
  sorting RF_37/38 (2), unified space history RF_39 (4), single-space report RF_74 (4).
- **Jose:** notifications RF_47/48 (6), modification RF_49 (4), cancellation RF_50 (2), reservation history RF_52
  (2), reservation report RF_65 (2), occupancy consultation RF_59 (2), occupancy report RF_79 (2), shared maintenance/
  repair core RF_10/11/26 (6), scheduled maintenance/reminders RF_27 (4).
- **Sebastián:** outstanding user PR integration (4), manager assignment RF_40/43 (4), assignment consultations
  RF_96/97 (2), filter acceptance completion RF_92/101 (2), role removal/swap RF_86/87 (4), account deactivation/
  reactivation RF_88/98 (4), user editing RF_93 (2), activity history RF_99 (2).

The original load table counted anticipated Sprint 1 carry-over, most of which is now complete. This revision
balances each person's work across the remaining time, not equal totals between people: unchanged ownership and
mostly Alejandro-led AWS leave Alejandro with the highest estimated total and significant additional review work.
Coding agents speed implementation but do not remove human acceptance, review or integration work. Revisit the
estimates against actual progress; the existing cut order remains the contingency if delivery runs late.

### Execution and dependency order

1. **Finish existing PRs first.** Integrate the reference inventory/spaces reports (#140/#141), user registration/
   role consultation (#136/#137) and movements (#143), including conflicts, review feedback and manual evidence.
   Preserve the dependent report integration order; an approved PR is not a delivered requirement until merged.
2. **Deliver enabling mechanisms early in Sprint 2.** RF_41 supplies uploads for RF_20/21 and damage evidence.
   RF_47/48 supply notifications for RF_49/50 and later damage/loss/maintenance work. RF_40/43 supply manager
   assignment used by recipient scoping, administrator space actions and RF_96/97. RF_80 supplies shared charts.
3. **Complete the added Sprint 2 packages.** Adapt uploads only after their shared mechanism is ready; complete
   reservation changes/cancellation with their notifications; verify the existing user filters and expose assignment
   consultations. David can build deletion/components against existing models and policies without waiting for
   uploads. Keep module-owner and platform reviews moving in the short review windows.
4. **Prepare external prerequisites during Sprint 2.** Confirm Windows/Power BI Desktop access, licensed viewer
   accounts, AWS access, domain readiness and production configuration/backup prerequisites. This is preparation
   for task #111 and RF_61, not a Sprint 2 deployment commitment or a provider/account change authorization.
5. **Start Sprint 3 with AWS and connectivity.** Alejandro does most deployment work; Sebastián remains support/
   backup under the existing shared responsibility. Run task #112 after deployment so RF_73's connection/gateway
   outcome is known early. Continue remaining reports, histories, maintenance and role/account work in parallel.
   Histories/reports can be developed with representative fixtures; final integrated checks must cover actual
   writer effects. They need not wait for every write UI before development begins.
6. **Complete features by October 25.** October 26–28 are reserved for integrated checks, fixes, manual polishing
   and demonstration preparation. Keep the October 28 18:30 delivery and the existing cut order.

Requirement criteria and domain semantics are unchanged. For example, RF_74's precise report layout and RF_27's
reminder window still need their normal implementation clarification; estimates do not settle those decisions.

## Reports opening sequence (contract agreed 2026-09-29)

The PM/architect approved the [shared filter contract](../.ai/rules/reports.md#shared-filter-contract). This section
preserves the original opening agreement; use the current remaining-work table and live issues/PRs above for status.
It does not mark RFs delivered or reassign their owners or milestones.

1. **Inventory contract — David (RF_07, RF_08, including RF_30).** Implement validation, normalization, choices and
   filtering beside `app/Queries/Assets/AssetInventory.php`; align the list/search surface and its `lang/es/assets.php`
   errors. Cover the prescribed search fields, every criterion, combined selections, missing values, historical
   references, date boundaries, invalid input, empty results and clearing filters. David owns this module.
2. **Spaces contract — Diego and Jose (RF_42, RF_53, RF_54).** Coordinate the contract beside
   `app/Queries/Spaces/SpaceDirectory.php`; Diego supplies search and Jose filters/list integration in
   `resources/views/pages/spaces/index.blade.php`. Errors belong in `lang/es/spaces.php`. Cover search, type/status/
   building filters, AND/OR combinations, historical references, invalid input, empty results and clearing.
3. **Reference report — Alejandro, David reviews (RF_63, #72).** Add the inventory definition in
   `app/Reports/Definitions`, register it and delegate filters to the inventory contract. Display the required
   identification/name/brand/model/space/responsible/acquisition columns plus separate condition and lifecycle
   status. Reuse `pages/reports/show`, the export button and `ExportReport`. Update the existing normalization
   surface so invalid/unknown filters block both preview and export. Cover columns, preview, unauthorized access,
   validation and filter parity across preview/PDF/XLSX, including generation metadata and the export audit entry.
4. **Spaces report — Alejandro, David reviews (RF_64, #73).** Add and register the spaces definition, delegating to
   the spaces contract. Render name, composed location, capacity, type, status and managers (responsable). Verify
   current data, all shared filters, preview/export consistency, unauthorized access, validation and audited export.
5. **Menu — Alejandro, David reviews (RF_60, #69).** Replace `pages/reports/index`'s placeholder; add Spanish strings
   in `lang/es/reports.php`. Cover four categories, category selection, empty-category messages, available links,
   hidden inaccessible entries and permitted future-sprint entries. Availability needs runtime access to planned
   sprint and permission metadata before definitions exist; `report_types` currently has neither. Implement that
   metadata seam with platform review, keeping the approved role/permission matrix.

Reports reuse the existing `reports.view`/`reports.export` authorization, plus `reports.audit` for the audit type,
and the current routes. Existing reporting views expose the needed filter fields; this plan needs no dependency
addition or change to a merged migration. In-report filter controls remain RF_62 (#71), consuming the same contract.

Before each feature PR: follow `.ai/workflow.md`, run affected Pest tests and `docker compose exec app composer test`,
check the Spanish UI at 360 px, and add the Sprint 2 manual note and screenshot. Query changes require their module
owners' coordination/review; report integration consumes their contracts. Only completed RFs belong in `Closes`
lines. Recording this agreement does not close #69, #72 or #73.

## Cut order

If Sprint 3 runs late, these are dropped in this order and reported as not delivered (never marked done):

1. RF_99 user activity history (the audit report RF_68 covers it for the Superusuario)
2. RF_79 occupancy report (RF_59 shows the same data on screen)
3. RF_83 reservation statistics
4. RF_37/RF_38 sorting
5. RF_73 automated refresh, falling back to a manually refreshed Power BI report

## Shared infrastructure

The first requirement that needs a piece builds it, as a PR a platform owner reviews; later requirements reuse it.
Nobody builds infrastructure ahead of its first use.

| Infrastructure | Built in | By |
|---|---|---|
| Notifications: database notifications, bell in the top bar, Reverb live updates (ADR-0006) | RF_47/RF_48 | Jose |
| File uploads: validation, thumbnails for phones, private storage, authorized download (ADR-0007) | RF_41 | Diego |
| `<x-chart>` with Chart.js 4, colored from `resources/css/theme.css` | RF_80 | Alejandro |
| Color palettes (light and dark tokens) | the bootstrap | done |
| AWS deployment (`compose.prod.yaml` on one VM) | Sprint 3 task #111; prerequisite preparation in Sprint 2 | Mostly Alejandro; Sebastián supports |

## Power BI (RF_61, RF_73)

- Only a handful of users see the Power BI dashboards; the client provides them Microsoft accounts with a Power BI
  license. They get a new permission, `dashboards.powerbi` (added with RF_61), through a role the Superusuario
  assigns. Everyone with `dashboards.view` keeps the in-app Tablero.
- The report is embedded with Power BI **secure embed** (an iframe; its URL lives in `.env`); viewers sign in with
  their licensed account. No Azure app registration.
- Alejandro builds the `.pbix` in Power BI Desktop (Windows) on the `reporting` views; backup: Sebastián.
- Access/readiness preparation starts in Sprint 2; deployment and the half-day connectivity test remain early
  Sprint 3, with the test after AWS deployment. Try a direct cloud
  connection to PostgreSQL (TLS, the read-only `powerbi_reader` login, firewall rules) first; if Power BI requires a
  gateway, install an on-premises data gateway on a SETI PC; the safety net is a manually refreshed report. Power BI
  Pro allows 8 scheduled refreshes per day.

## Reassignments (planning round 3, 2026-09-29)

Recorded for the professor and the Sprint 1 report. Reason: balance the load (it was David 31, Alejandro 25,
Sebastián 16, Diego 14, Jose 12 open requirements) and keep each mechanism in one person's hands.

| Requirements | From → To | Why |
|---|---|---|
| RF_20, RF_21 asset documents and photos | David → Diego | One file-upload mechanism, shared with RF_41 space photos |
| RF_10, RF_11, RF_26, RF_27 maintenance | David → Jose | Time-based scheduling, like reservations; David keeps inventory, movements and events |
| RF_40, RF_43 assign/remove lab managers | Diego → Sebastián | Same data as RF_96/RF_97; removing the Administrador role must remove assignments (RF_86/RF_87) |
| RF_65, RF_79 reservations and occupancy reports | Alejandro → Jose | Same queries as RF_52 and RF_59 |
| RF_74 single-space report | Alejandro → Diego | The space page (RF_35) exported |

When someone works in another person's module, that module's owner reviews the PR (CONTRIBUTING.md). The issue
keeps the label of the module whose code it touches.
