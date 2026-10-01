# Planning

Course: IC-6831 Proyecto de Ingeniería de Software, II Semestre 2026. Project manager: Alejandro Quesada
(@AleQuesada2012). This is the plan of record agreed in planning round 3 (2026-09-29). Per-requirement owner,
reviewer and sprint: [requirements/README.md](requirements/README.md), generated from `requirements/requirements.json`.
The reasons behind every decision: [decisions.md](decisions.md).

## Sprints

| Sprint | Delivery (Costa Rica time) | Content |
|---|---|---|
| Sprint 1 | Wed 2026-09-30 18:30 | The bootstrap (base platform) and the first screen of every module. |
| Sprint 2 | Wed 2026-10-14 18:30 | Sprint 1 carry-over first, then the **write side** others depend on: movements, file uploads, notifications, manager assignment, the report pipeline's first reports and statistics. |
| Sprint 3 | Wed 2026-10-28 18:30 | The **read side**: histories, consultations and filters, maintenance, the remaining reports and statistics, Power BI, AWS deployment, user manuals. |

- The team codes across both weeks of each sprint. Each person is planned at about 8 requirements per sprint; RFs
  that close inside another RF's PR (marked `*` below) and the pipeline checks do not count.
- **Carry-over:** at each delivery, every open issue of that sprint's milestone moves to the next sprint and is done
  first. Run `scripts/github/carry-over.sh AleQuesada2012/SETI-Management-System "Sprint 1" "Sprint 2"`. The sprint
  report states what carried over.
- At each delivery the PM tags `main` as `sprint-1`, `sprint-2`, `sprint-3`.

## Plan by person

`*` = closes inside another RF's PR (see its issue). Counts: Sprint 1 carry-over + Sprint 2 / Sprint 3.

| Person | Sprint 1 (carry-over) | Sprint 2 | Sprint 3 | Load |
|---|---|---|---|---|
| Alejandro · @AleQuesada2012 | 60, 63, 64 | 62, 68, 69, 70*, 71*, 72*, 75, 78, 80, 81, 84 | 61, 66, 67, 73, 76, 77, 82, 83 + AWS deployment | 11 / 8 |
| David · @DavidZamoraB | 01, 03, 04, 05, 07, 08, 14*, 15*, 16*, 17*, 18*, 19*, 23*, 24* | 06, 25 | 02, 09, 12, 13, 22, 28, 29, 30*, 31* | 8 / 7 |
| Diego · @Dizabu | 32, 33, 35, 36, 42, 44, 45 | 41 | 20, 21, 34, 37, 38, 39, 74 | 8 / 7 |
| Jose · @JoseHerrera21 | 46, 51, 53, 54, 55, 57 | 47, 48 | 10, 11, 26, 27, 49, 50, 52, 59, 65, 79 | 8 / 10 |
| Sebastián · @Rubiocai | 85, 90, 91, 94, 95, 100 | 40, 43 | 86, 87, 88, 92, 93, 96, 97, 98, 99, 101 + AWS deployment | 8 / 10 |

Delivered by the bootstrap (closed): RF_00_1, RF_00_2, RF_00_3, RF_00_4, RF_89. Duplicates RF_56 and RF_58 close with
RF_35 and RF_44. On 2026-09-30, Alejandro restored RF_60, RF_63 and RF_64 to his original Sprint 1 backlog; their planned carry-over to Sprint 2 is withdrawn. RF_63 remains the reference report for later definitions.

Two deliberate exceptions to the write/read principle:

- **RF_40/RF_43 are in Sprint 2**: without manager assignment an Administrador cannot act on any space in production
  (the ★ rule), so the system would be unusable.
- **Maintenance (RF_10, 11, 26, 27) is in Sprint 3**: Jose's Sprint 2 is full with the reservation core. The
  maintenance report and statistics are also Sprint 3 and can be built on demo data.

## Reports opening sequence (contract agreed 2026-09-29)

The PM/architect approved the [shared filter contract](../.ai/rules/reports.md#shared-filter-contract). This section
plans its implementation; it does not mark RFs delivered or reassign their owners or milestones.

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
| AWS deployment (`compose.prod.yaml` on one VM) | Sprint 3 task issue | Alejandro, Sebastián |

## Power BI (RF_61, RF_73)

- Only a handful of users see the Power BI dashboards; the client provides them Microsoft accounts with a Power BI
  license. They get a new permission, `dashboards.powerbi` (added with RF_61), through a role the Superusuario
  assigns. Everyone with `dashboards.view` keeps the in-app Tablero.
- The report is embedded with Power BI **secure embed** (an iframe; its URL lives in `.env`); viewers sign in with
  their licensed account. No Azure app registration.
- Alejandro builds the `.pbix` in Power BI Desktop (Windows) on the `reporting` views; backup: Sebastián.
- Data refresh: a half-day connectivity test at the start of Sprint 3, after the AWS deployment. Try a direct cloud
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
