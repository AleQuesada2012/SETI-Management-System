# Laravel migrations are the source of truth for the schema; schema.sql is a frozen design reference

The data model was designed and tested as plain SQL (`docs/db/schema.sql`, `seed.sql`, `smoke-test.sql`). The first migrations reproduce that DDL verbatim as raw SQL, and the seeders port `seed.sql` as idempotent upserts. From then on every schema change is a new migration; `schema.sql` and `seed.sql` stay frozen as the record of the approved design, and `smoke-test.sql` keeps running in CI against a freshly migrated database, so the original 111 rules can never silently break.

## Considered Options

- **Keep editing schema.sql and load it with `DB::unprepared` in one migration**: a single readable file, but no incremental history, and production databases cannot be upgraded in place.
- **Maintain schema.sql and migrations in parallel**: two sources of truth that drift on the first busy week.
- **Translate the DDL into the Schema Builder**: idiomatic, but PostgreSQL features the model depends on (EXCLUDE constraints, generated columns with custom functions, partial unique indexes, triggers, views) would end up as raw SQL anyway, and the translation risks subtle differences.

## Consequences

A merged migration is never edited; fixes are new migrations. Constraint names are part of the application's contract (`lang/es/db.php`), so renaming one means updating its message. `docs/Agent-DB.md` explains the model and must be updated in the same PR as a migration that changes it.
