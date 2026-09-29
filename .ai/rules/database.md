---
paths:
  - 'database/**'
  - 'app/Enums/**'
  - 'docs/db/**'
---

# Database: migrations, seeders, enums · platform (@AleQuesada2012, @Rubiocai)

- **Migrations are the source of truth** (ADR-0013). `docs/db/schema.sql` and `docs/db/seed.sql` are frozen references of the original design; `docs/db/smoke-test.sql` must still pass on a migrated database.
- **A merged migration is never edited.** Change the schema with a new migration; a person decides when an exception is needed.
- Constraints, exclusion constraints, triggers and views are raw SQL (`DB::statement` / `DB::unprepared`), like the existing migrations. A migration that alters a column used by a `reporting` view drops and recreates that view.
- **A new table** needs: PK, every FK indexed, `timestamptz` timestamps, a `CHECK` plus a PHP enum in `app/Enums` for any code-driven set, audit coverage (`Auditable` + morph alias), a `reporting` view if it is reported, and tests for its rules (Agent-DB.md §16 rule 10).
- **Constraint names are an API**: `lang/es/db.php` maps each name to a Spanish message through `App\Support\Database\ConstraintViolation`. Name new constraints `<table>_<rule>` and add the message.
- Seeders are idempotent (`upsert` on `code`/`name`). `DemoSeeder` runs only in `local`.
- Keep in sync in the same PR: migration ↔ enum ↔ `lang/es/db.php` ↔ `docs/Agent-DB.md` ↔ `CONTEXT.md`.
