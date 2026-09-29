# The audit trail is written by the application into one append-only table

Every create, update, delete and restore, every report export, every role or space-manager change and every authentication event is written by Laravel into `audit_logs` in the same transaction as the change. Each entry records the actor, channel (web/api/cli/system), IP, user agent, request id and the old/new values of the changed fields. A trigger rejects UPDATE, DELETE and TRUNCATE on the table. We capture in the application rather than with PostgreSQL row triggers because only the application sees non-row events (logins, exports) and knows the actor without per-transaction session variables. It also matches the agreed scope: changes made with direct SQL are deliberately not audited.

## Considered Options

- **Generic PostgreSQL audit triggers**: capture even direct SQL, but need `SET LOCAL app.user_id` in every transaction, break subtly with pooled connections and cannot see logins or downloads.
- **Per-table history tables**: easy point-in-time queries, but every migration must change two tables, and cross-entity reports (RF_68, RF_99) need UNIONs.

## Consequences

Eloquent `attach`/`detach`/`sync` fire no model events, so role and space-manager changes must be logged explicitly by the services that make them. Automated tests must use transactions (`RefreshDatabase`), not truncation. `actor_label` and `entity_label` are snapshots, so the audit report shows what was true at the time (RF_68).
