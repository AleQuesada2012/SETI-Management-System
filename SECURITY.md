# Security policy

## Reporting a vulnerability

Do not open a public issue. Write privately to the platform owners (@AleQuesada2012, @Rubiocai) or use GitHub's
*Report a vulnerability* (Security → Advisories) on this repository. Include what you found, how to reproduce it and
what data it exposes. We answer within two working days.

## What the application guarantees

- **Authentication** (RF_00_1): email + password, passwords hashed with bcrypt, at least 12 characters (and checked
  against known breaches in production); two-factor authentication, required for the Superusuario and the
  Administradores before they can use the application and optional for the other roles; sign-in throttled to 5 attempts per
  minute per email and IP; "forgot password" throttled to 5 requests per minute per IP and answering the same, in
  text and in time, whether or not the email is registered; no self-registration (accounts are created by the Superusuario and activated by an emailed,
  single-use invitation link; the first Superusuario of a fresh database is created the same way from the server's
  console, `php artisan users:create-first-superuser`, which refuses once one exists).
- **Sessions**: server-side (database), encrypted, HttpOnly, `SameSite=Lax`, `Secure` in production; they end after
  120 minutes idle and 9 hours after sign-in in any case. A confirmed password stays confirmed for 120 minutes.
  Deactivating an account ends its sessions immediately; changing the password ends the user's other sessions and
  resetting it ends all of them, and so does a change of the account's email. There is no "remember me" cookie, even for a crafted request.
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
