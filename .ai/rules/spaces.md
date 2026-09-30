---
paths:
  - 'app/Actions/Spaces/**'
  - 'app/Queries/Spaces/**'
  - 'app/Policies/SpacePolicy.php'
  - 'app/Models/Space.php'
  - 'app/Models/SpaceType.php'
  - 'app/Models/SpaceStatus.php'
  - 'app/Models/Building.php'
  - 'resources/views/pages/spaces/**'
  - 'resources/views/components/spaces/**'
  - 'resources/views/pages/reservations/index.blade.php'
  - 'routes/modules/spaces.php'
  - 'lang/es/spaces.php'
  - 'tests/Feature/Spaces/**'
---

# Spaces (RF_32–RF_45) · owner @Dizabu · reviewer @JoseHerrera21

- **Shared pages.** The spaces list (`pages/spaces/index`) belongs to @JoseHerrera21 (RF_53, RF_54); you add the search (RF_42) and "Añadir espacio" (RF_32) there, through `App\Queries\Spaces\SpaceDirectory`. The space page (`pages/spaces/show`) is yours and embeds Jose's `<livewire:reservations.space-reservations>` and `<livewire:reservations.availability-check>`; keep those tags and their `:space` binding. The reservations calendar (`pages/reservations/index`, RF_45) is yours.
- **Shared filter contract.** Before implementing `SpaceDirectory`, RF_42 or RF_54, read the [shared filter contract](reports.md#shared-filter-contract), approved by the PM/architect on 2026-09-29. Coordinate search with @Dizabu and filters with @JoseHerrera21; the same validation, normalization and option choices serve RF_64.
- **Who else works here (planning round 3).** Manager assignment (RF_40, RF_43) is implemented by @Rubiocai in Sprint 2; you review it. You build the shared file-upload infrastructure in RF_41 (validation, thumbnails for phones, private storage, authorized download route) and reuse it for asset documents and photos (RF_20, RF_21, reviewed by David). The single-space report (RF_74) is yours, as a report definition reviewed by @AleQuesada2012.
- **Space managers** (`space_managers`, ADR-0009) are the "responsable" / "administrador asignado". Only holders of the Administrador role can be assigned; assigning and removing are pivot changes, audited with `Audit::record(AuditAction::ManagerAssigned|ManagerRemoved, …)`. An Administrador who creates a space (RF_32) becomes its first manager in the same transaction.
- **Space scope (★).** An Administrador changes only the spaces they manage (`SpacePolicy`). Assigning managers (RF_40, RF_43) is Superusuario-only.
- **"Ocupado" is derived** from a confirmed reservation happening now (`reporting.spaces.is_occupied`), never stored as a status.
- **Reservable** = type is reservable AND status allows reservations AND not deleted (`Space::isReservable()`).
- **Delete (RF_34) is a soft delete**, refused while the space holds in-service/in-maintenance assets or has confirmed reservations that have not ended. Code and name become free for reuse.
- **Status changes (RF_44, RF_58)** that affect confirmed future reservations warn the user first.
- `code` and `name` are unique among non-deleted spaces, case-insensitively: let the database decide and show `lang/es/db.php` messages (`ConstraintViolation`).
