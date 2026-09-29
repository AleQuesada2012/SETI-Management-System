---
paths:
  - 'app/Actions/Reservations/**'
  - 'app/Policies/ReservationPolicy.php'
  - 'app/Models/Reservation.php'
  - 'app/Notifications/**'
  - 'resources/views/pages/reservations/**'
  - 'resources/views/components/reservations/**'
  - 'resources/views/pages/spaces/index.blade.php'
  - 'routes/modules/reservations.php'
  - 'lang/es/reservations.php'
  - 'tests/Feature/Reservations/**'
---

# Reservations (RF_46–RF_59) · owner @JoseHerrera21 · reviewer @Rubiocai

**Jose's work outside this module (planning round 3):** maintenance (RF_10, RF_11, RF_26, RF_27) in the assets module
(read `.ai/rules/assets.md`; @DavidZamoraB reviews), and the reservations and occupancy reports (RF_65, RF_79) as
report definitions (read `.ai/rules/reports.md`; @AleQuesada2012 reviews). Maintenance changes the asset's lifecycle
status as a side effect (Agent-DB.md §8.4), in the same transaction.

- **Time is `[start, end)`.** Compare with `Reservation::overlapping()`, which mirrors the `reservations_no_overlap` exclusion constraint. Back-to-back reservations (10:00–11:00 and 11:00–12:00) are valid.
- **The database is the final judge of overlaps** (ADR-0005): validate for a friendly message, then catch the constraint violation on insert (`App\Support\Database\ConstraintViolation` maps it to Spanish). A pre-check alone loses under concurrency.
- **Only `confirmed` and `cancelled` are stored.** In progress / finished are derived from time (`effectiveStatus()`). "Eliminar reserva" in the UI = cancel (RF_50); the row stays.
- **Modifying (RF_49)** bumps `version` (optimistic locking: update `WHERE version = :read_version`); `version > 1` is the "modificada" label.
- **Series** (`series_id`) are independent reservations created together; each date changes or cancels on its own.
- **Who may change a reservation:** `ReservationPolicy` (own with `manage_own`; any in managed spaces with `manage_any`, ★). Create only in reservable spaces (`Space::isReservable()`, checked in the Action).
- **Notifications (RF_47, RF_48)** use Laravel database notifications with JSON snapshots (ADR-0006), sent after commit to the space's managers and the Superusuario. RF_47 builds the shared notification infrastructure (the bell in the top bar, Reverb live updates) as a platform-reviewed PR; it ships together with RF_46.
- **Your components embedded in the space page:** `resources/views/components/reservations/space-reservations.blade.php` (RF_57) and `availability-check.blade.php` (RF_55). Keep their public `Space $space` property: `pages/spaces/show` passes it.
- **The spaces list page** (`pages/spaces/index`, RF_53, RF_54) is yours; @Dizabu adds search and "Añadir espacio" to it through `SpaceDirectory`.
