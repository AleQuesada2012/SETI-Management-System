---
paths:
  - 'app/Reports/**'
  - 'app/Queries/Reports/**'
  - 'app/Actions/Reports/**'
  - 'app/Models/Reporting/**'
  - 'app/Policies/ReportGenerationPolicy.php'
  - 'resources/views/pages/reports/**'
  - 'resources/views/components/reports/**'
  - 'resources/views/reports/**'
  - 'resources/views/pages/dashboard.blade.php'
  - 'routes/modules/reports.php'
  - 'routes/modules/dashboard.php'
  - 'lang/es/reports.php'
  - 'lang/es/dashboard.php'
  - 'tests/Feature/Reports/**'
---

# Reports and dashboard (RF_00_4, RF_60–RF_84) · owner @AleQuesada2012 · reviewer @DavidZamoraB

**Who else works here (planning round 3):** @JoseHerrera21 writes the reservations and occupancy reports (RF_65,
RF_79) and @Dizabu the single-space report (RF_74); Alejandro reviews them. RF_63 is the first real definition and
the one to copy.

- **Read only `reporting.*` views** (ADR-0010) through `App\Models\Reporting\*` models. They are read-only; they never count or cache in extra tables.
- **A report** is a class in `app/Reports/Definitions` extending `App\Reports\ReportDefinition` (code, columns, filterKeys, describeFilters, query), registered in `App\Reports\ReportRegistry`. Its `query()` calls the module's Query (`AssetInventory`, `SpaceDirectory`, `UserDirectory`) with the same filter keys as the list page. `tests/Support/UsersTestReport.php` shows the shape.
- **Exporting** is already built: `App\Actions\Reports\ExportReport` stores the file, records `report_generations` and audits `generated`, all or nothing. Pages add `<livewire:reports.export-button report="code" :filters="$filters" />`; `pages/reports/show` previews any registered definition.
- **Filters are stored normalized**: `{}` means all records (RF_72) and the PDF says so.
- The audit report (RF_68) requires `reports.audit`: return it from `permission()`.
- Dashboard KPIs come from `reporting.dashboard_kpis`.
- **Charts** (RF_80–RF_83, dashboard) use one shared `<x-chart>` component built in RF_80 with Chart.js 4 (dependency approved in planning round 3). Series colors come from the `--color-chart-*` tokens in `resources/css/theme.css`, so charts follow light and dark mode.
- **Power BI** (RF_61, RF_73): a secure-embed iframe (URL in `.env`) shown only to users with the permission `dashboards.powerbi`, which RF_61 adds (enum case, seeder, role matrix; the Superusuario grants it). Power BI reads only the `reporting` views through the read-only `powerbi_reader` role (`database/sql/ops`). Refresh strategy: see `docs/PLANNING.md` § Power BI.

## Shared filter contract

Approved by Alejandro (@AleQuesada2012), PM and architect, on 2026-09-29. This is the implementation contract;
the current Query stubs and report normalization still need to implement it. Requirement text, ownership and
sprints remain in `docs/requirements/requirements.json`.

The first schemas cover inventory and spaces. Apply the common conventions to later reports, whose prescribed
criteria remain in their RFs; detail their schemas when implementing them.

### Ownership and reuse

- Each module owns one filter contract beside its Query: inventory with @DavidZamoraB; space search with @Dizabu
  and space filters with @JoseHerrera21. Lists, previews and exports reuse its supported keys, validation,
  normalization, option choices and predicates.
- Definitions delegate to the module contract and provide columns, Spanish labels and readable filter descriptions.
  Keep Query docblocks aligned with these schemas. Examples using flat keys such as `space_id` are superseded.
- Read `reporting.*` through the shared Queries. Export uses the same validated filters and queries current data,
  following the existing pipeline.

### Inventory schema

| Key | Input | `include_missing` allowed |
|---|---|---|
| `search` | Text | No |
| `brand` | `values`: brand IDs | Yes |
| `model` | `contains`: text | Yes |
| `category` | `values`: asset-category IDs (RF_24) | No |
| `condition` | `values`: physical-condition IDs | No |
| `condition_color` | `values`: `green`, `amber`, `red` | No |
| `lifecycle_status` | `values`: `in_service`, `in_maintenance`, `lost`, `decommissioned` | No |
| `space` | `values`: space IDs | Yes |
| `responsible_user` | `values`: user IDs | Yes |
| `acquired_on` | Optional `from` and `to` dates | Yes |

RF_63 displays physical condition and lifecycle status in separate columns. RF_08's green/red wording is clarified
to include all three approved physical-condition colors. The responsible-user filter also serves RF_30, which
closes with RF_08. `model` is a text criterion rather than a catalog.

Inventory search covers name, brand, model, generated code, asset tag and serial number. Internal numeric asset
IDs are filter references rather than UI search identifiers.

### Spaces schema

| Key | Input |
|---|---|
| `search` | Text |
| `space_type` | `values`: type IDs |
| `space_status` | `values`: status IDs |
| `building` | `values`: building IDs |

These attributes are required, so none supports `include_missing`. Capacity and reservability are outside this
first contract, despite the existing Query TODO. Building filtering implements the location intent documented
in `docs/Agent-DB.md` §7. Space search covers name, building, floor, location detail and space code.

### Users schema

Owned by @Rubiocai beside `App\Queries\Users\UserDirectory` (RF_90, RF_92, RF_95, RF_100, RF_101); the users report
(RF_69) reuses it.

| Key | Input |
|---|---|
| `search` | Text |
| `user_type` | `values`: `administrators`, `regular` |
| `role` | `values`: role IDs |
| `account_status` | `values`: `active`, `deactivated` |

None supports `include_missing`. Search covers name, email and user ID (`users.search_text`). `administrators`
are holders of Administrador without Superusuario; `regular` ("usuarios normales", RF_92) are users holding neither
Administrador nor Superusuario. Any holder of Superusuario is excluded from both types, including users with
multiple roles. With no user-type restriction, Superusuarios remain available, including through the role filter.
Roles are never soft-deleted, so every role is a choice.

### Audit schema

`App\Queries\Reports\AuditTrail` owns RF_68's `actor` (user ID `values`), `action` (AuditAction code `values`) and
`occurred_at` (`from`/`to` dates). Date bounds include entire Costa Rica calendar days. Actor choices include active
and deactivated users; the report preserves the original actor/entity labels and old/new value snapshots.
An empty actor selection also includes system records with no actor. No criterion supports `include_missing`.

### Input and matching

```json
{
  "search": "Lenovo",
  "brand": {"values": [12, 18], "include_missing": true},
  "model": {"contains": "ThinkPad"},
  "acquired_on": {"from": "2026-09-01", "to": "2026-09-30", "include_missing": true}
}
```

- Categorical `values` are arrays, including for one selection. Catalogs use IDs; enums use stable codes.
- Combine criteria with **AND** and alternatives inside one criterion with **OR**. A selected missing value is
  another OR alternative in that criterion: `Space A OR Sin espacio`, or `September OR Sin fecha de adquisición`.
- `include_missing` is a boolean available only on the nullable inventory criteria above. On its own, `true`
  selects only missing values. Omitted or empty criteria impose no restriction; an empty control never selects
  missing records implicitly.
- Trim text. Search and model matching are partial, case- and accent-insensitive. Treat `%`, `_` and backslash
  literally, following `TextSearch`; search matches any of its prescribed fields.
- Date bounds are inclusive and independently optional. Use `YYYY-MM-DD` in filters and `dd/mm/yyyy` in the UI.
  Acquisition dates are calendar dates. Later timestamp-based reports interpret selected days in Costa Rica time.

### Validation, normalization and choices

- Validate both top-level keys and criterion members before querying. Invalid shapes, unsupported keys,
  nonexistent IDs, unsupported enum codes, invalid dates and inverted ranges produce Spanish validation errors
  and block preview/export. This supersedes silently dropping unknown report filter keys.
- Normalize valid input by removing empty restrictions and duplicate selections. The normalized object drives
  queries, stored generation filters and readable descriptions; `{}` means no restrictions.
- A valid combination matching nothing returns an empty result with the RF's informative Spanish message.
- Existing reference IDs remain valid after catalog soft deletion or user deactivation. Offer current catalog
  entries plus removed entries referenced by the report's full dataset. Responsible-user choices include active
  and deactivated users. Clearly label removed/deactivated choices; a removed reference is distinct from missing.
- Keep option choices stable across filter changes, using the full report dataset and the policy above. Other
  criteria do not shrink choices or discard selected values. Preserve the authorization of the requesting surface.

## Report menu agreement

Approved in the same 2026-09-29 session for RF_60:

- Hide reports the user lacks permission to open, including future entries; enforce authorization on access too.
- Hide unavailable types, except types planned for future sprints, which appear as "Próximamente".
- Show the RF_60 informative message when a selected category has no available reports. Future entries do not
  count as available reports.
