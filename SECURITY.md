# Security policy

## Reporting a vulnerability

Do not open a public issue. Write privately to the platform owners (@AleQuesada2012, @Rubiocai) or use GitHub's
*Report a vulnerability* (Security → Advisories) on this repository. Include what you found, how to reproduce it and
what data it exposes. We answer within two working days.

## What the application guarantees

- **Authentication** (RF_00_1): email + password, passwords hashed with bcrypt, at least 12 characters (and checked
  against known breaches in production); optional two-factor authentication; sign-in throttled to 5 attempts per
  minute per email and IP; no self-registration (accounts are created by the Superusuario and activated by an emailed,
  single-use invitation link).
- **Sessions**: server-side (database), encrypted, HttpOnly, `SameSite=Lax`, `Secure` in production, 120 minutes.
  Deactivating an account ends its sessions immediately.
- **Authorization** (RF_89): every route and every Livewire action checks a policy or permission; the test suite
  fails when a route has no authorization check.
- **Audit trail** (RF_00_2, RF_00_3, RF_00_4): who did what, to which element, when and from where, in an append-only
  table PostgreSQL refuses to change. Secrets never reach it.
- **Files** are private: served only through authorized routes, never by public URL; type and size are checked by
  the application and the database.
- **Transport**: HTTPS only in production (Caddy, automatic certificates, HSTS); security headers on every response.
- **Secrets** live only in `.env` files that are never committed; CI runs gitleaks and `composer audit`.

## Supported versions

Only the latest `main` (deployed at each `sprint-N` tag) receives fixes.
