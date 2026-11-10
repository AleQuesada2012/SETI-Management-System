---
paths:
  - 'app/Support/Audit/**'
  - 'app/Listeners/**'
  - 'app/Http/Middleware/**'
  - 'app/Providers/FortifyServiceProvider.php'
  - 'app/Actions/Fortify/**'
  - 'resources/views/pages/auth/**'
  - 'resources/views/pages/settings/**'
  - 'lang/es/auth.php'
  - 'lang/es/passwords.php'
  - 'lang/es/security.php'
  - 'tests/Feature/Auth/**'
  - 'tests/Feature/Audit/**'
  - 'tests/Feature/Settings/**'
  - 'tests/Feature/Authorization/**'
---

# Security: sign-in, audit, authorization (RF_00_1–RF_00_3, RF_89) · owner @AleQuesada2012 · reviewer @DavidZamoraB

- **Audit trail (ADR-0002).** Models with `use Auditable;` record created/updated/deleted/restored automatically (with a morph alias in `AppServiceProvider`). Everything else (pivot changes, exports, logins, deactivations, file attach/detach) is explicit: `Audit::record(AuditAction::X, $entity, old: […], new: […])`. `audit_logs` is append-only in PostgreSQL.
- **Secrets never reach** audit values, notifications or logs: passwords, remember tokens, 2FA secrets, API tokens.
- **Authorization (RF_89)** has three layers, all required: `can:` middleware on the route, `$this->authorize()` in every Livewire action (Livewire requests bypass route middleware), and `@can` to hide what the user cannot use. `tests/Feature/Authorization/RouteAuthorizationTest.php` fails for a route without `can:`.
- **Check permissions through `App\Enums\PermissionName`** (`$user->can(PermissionName::AssetsView)`), never string literals. The Superusuario passes every Gate check (`Gate::before`).
- **Sessions**: database sessions, 120 minutes, encrypted; no "remember me" (the login drops the field); no self-registration; passwords ≥ 12 characters (+ breach check in production); 2FA optional. Anything that changes who may be signed in (deactivation, password change or reset) calls `User::endSessions()`.
- **Never tell a visitor whether an email has an account**: sign-in and "forgot password" answer the same, in text and in time, for registered and unregistered emails.
- A deactivated user is rejected at sign-in (`FortifyServiceProvider`) and logged out on the next request (`EnsureUserIsActive`).
