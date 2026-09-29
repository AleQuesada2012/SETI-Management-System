# Session authentication (Laravel Sanctum) instead of JWT

The non-functional requirements mention "JWT for logins and session handling", but the client is a responsive browser app served by Laravel, and RF_87/RF_88 require that removing a role or deactivating an account takes effect immediately. We use Laravel's session cookies (HttpOnly, SameSite, CSRF-protected; `sessions` table) through Sanctum, and keep Sanctum's hashed, revocable `personal_access_tokens` for any future non-browser client, because a stateless JWT cannot be revoked before it expires without re-adding exactly this server-side state, and it would live in JavaScript-readable storage.

## Considered Options

- **Short-lived JWT + refresh tokens stored in the database**: meets the NFR literally with the same revocation guarantees, but it means more code and more state, with no benefit for a browser-only client.
- **Stateless JWT with roles inside**: rejected; a deactivated user or a removed role keeps working until the token expires, violating RF_87/RF_88.

## Consequences

Passwords are still hashed (bcrypt via Laravel `Hash`). If the course later requires JWT literally, add a `refresh_tokens` table; nothing else in the schema changes. Permissions are always read from the database on each request, never from a token.
