---
paths:
  - 'app/Reports/**'
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
