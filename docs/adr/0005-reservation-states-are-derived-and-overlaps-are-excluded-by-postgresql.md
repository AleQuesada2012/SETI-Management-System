# Reservations store only confirmed or cancelled; PostgreSQL makes double booking impossible

The documents list "activa, cancelada, modificada, finalizada", the mockup shows "Confirmada/Pendiente", and there is no approval step. A reservation is stored as `confirmed` or `cancelled`. "Finished" and "in progress" are derived from its times, and "modified" from a version counter, so no scheduled job can leave them stale. Overlaps are prevented by an `EXCLUDE USING gist (space_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status = 'confirmed')` constraint (btree_gist). It holds even when two people book the same slot at the same instant; a check in PHP alone cannot guarantee that. Repeating bookings are separate rows that share a `series_id`, so each date can be moved or cancelled on its own.

## Consequences

"Eliminar reserva" in the interface cancels; the row stays for history (RF_50/RF_52). Reports read the derived status from `reporting.reservations`. Capacity and allowed-hours checks stay in Laravel.
