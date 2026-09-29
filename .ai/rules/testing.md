---
paths:
  - 'tests/**'
---

# Testing · platform

- **Pest 5 on PostgreSQL** (`seti_testing`, see `phpunit.xml`), inside the app container: `docker compose exec app php artisan test --compact`. The schema depends on PostgreSQL features, so SQLite is not an option.
- Feature tests use `RefreshDatabase` (set in `tests/Pest.php`) and start with catalogs, roles and permissions seeded (`TestCase::$seed`). `DatabaseTruncation` fails on append-only tables by design.
- Helpers in `tests/Pest.php`: `userWithRole(SystemRole::Administrador)`, `actingAsRole(...)`. Factories: `Space::factory()->managedBy($admin)`, `Asset::factory()->inSpace($space)`, `User::factory()->deactivated()|invited()`.
- **Definition of Done for tests.** Sprint 1: each requirement has a happy-path test and an unauthorized test (403 or `AuthorizationException`). From Sprint 2 also: validation errors, the audit entry it writes, and the database rule it relies on (e.g. an overlap is rejected with its Spanish message).
- Name tests by behaviour in Spanish-domain English: `test('an Administrador changes the assets of their spaces only', …)`. Put the requirement code in a comment at the top of the file.
- Test through the public surface: routes (`$this->get(route(...))`), Livewire (`Livewire::test('pages::assets.index')`), Actions (`app(RegisterAsset::class)->handle(...)`).
