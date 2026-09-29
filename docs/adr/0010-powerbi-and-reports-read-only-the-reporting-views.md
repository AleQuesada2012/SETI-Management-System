# PowerBI and exported reports read a `reporting` schema of views, never the base tables

Dashboards (RF_61, RF_73, RF_80–RF_83) and reports (RF_63–RF_79) read stable, denormalized views in the `reporting` schema. PowerBI connects through a read-only `powerbi_reader` role that cannot see base tables, so password hashes and tokens are out of reach. At this data size (about 1,300 assets and 22 spaces in the mockups) live queries are fast, so there are no summary tables or counters that could drift. The views are a contract that shields PowerBI from migrations. The in-app report module queries the same views, so the app and PowerBI always agree.

## Consequences

A migration that changes a column used by a view must drop and recreate that view in the same migration. If a query ever gets slow, turn that view into a materialized view refreshed by the scheduler; do not add counters to base tables.
