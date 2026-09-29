# Notifications use Laravel's database channel with JSON snapshot payloads

In-app notifications are Laravel database notifications: reservation created, modified or cancelled; maintenance reminders; damage or loss reported. Each recipient gets one row in `notifications`. Its JSONB `data` snapshots the space, dates and actor, and carries the ids needed to link to the record. This is a deliberate exception to normalization, for three reasons: a notification must keep saying what was true when it was sent, notifications are not audited, and the built-in channel gives the inbox, read state and Reverb broadcasting with no custom code. Because only users are notified, we add a real foreign key from `notifiable_id` to `users` and fix `notifiable_type` to the morph alias `user`.

## Considered Options

- **Normalized `notifications` + `notification_recipients`** with a foreign key to the reservation: no duplicated payload, but the inbox, read state and broadcasting become custom code.

## Consequences

New notification types need no migration. Each notification class returns a short, stable `databaseType()` (for example `reservation.created`), so renaming a PHP class never breaks old rows. Read notifications older than 180 days are pruned.
