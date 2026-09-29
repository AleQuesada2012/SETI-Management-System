---
paths:
  - 'app/Actions/Assets/**'
  - 'app/Queries/Assets/**'
  - 'app/Policies/AssetPolicy.php'
  - 'app/Policies/MaintenanceRecordPolicy.php'
  - 'app/Models/Asset*.php'
  - 'app/Models/MaintenanceRecord.php'
  - 'app/Models/Brand.php'
  - 'app/Models/Supplier.php'
  - 'resources/views/pages/assets/**'
  - 'resources/views/components/assets/**'
  - 'routes/modules/assets.php'
  - 'lang/es/assets.php'
  - 'tests/Feature/Assets/**'
---

# Assets (RF_01–RF_31) · owner @DavidZamoraB · reviewer @Dizabu

**Who works here (planning round 3).** David owns the module. Two slices are implemented by others, and David
reviews their PRs: maintenance (RF_10, RF_11, RF_26, RF_27) by @JoseHerrera21, and asset documents and photos
(RF_20, RF_21) by @Dizabu on the upload infrastructure built in RF_41. RF_14–RF_19, RF_23 and RF_24 are fields of the
asset form and close inside RF_03/RF_04; RF_30 is a custodian filter inside RF_08; RF_31 lives on the RF_12 page.

- **Location changes only through movements.** Place, transfer or remove an asset by inserting an `AssetMovement` (`entry`, `transfer`, `exit`); PostgreSQL updates `assets.current_space_id`. Setting that attribute throws (ADR-0003). Registering an asset (RF_03) = `Asset::create()` without a location + an `entry` movement, in one transaction.
- **Lifecycle status changes only as a side effect** of asset events (damage, loss, recovery, decommission) and maintenance (Agent-DB.md §8.4). Actions set it with `forceFill()`; it is not mass-assignable.
- **Condition ≠ lifecycle status** (ADR-0004): condition is physical (Buen estado, Dañado… with a color group); status is in service / in maintenance / lost / decommissioned.
- **Delete (RF_02) is a soft delete** for records entered by mistake. **Decommission (RF_29) is an `AssetEvent`**, keeps history forever, and is final.
- **Movements and events are append-only.** Correct a mistake with a new compensating row.
- **Space scope (★).** An Administrador acts only on assets in (or last in) the spaces they manage: `AssetPolicy` already encodes it; call `$this->authorize('update', $asset)` etc. in every action.
- **List, search, filters:** `App\Queries\Assets\AssetInventory` (RF_07, RF_08). The inventory report (RF_63) reuses it, so filter keys are shared with reports: keep them stable and documented in its docblock.
- Search uses `App\Queries\TextSearch` (accent-insensitive, trigram index).
- Files (photos RF_21, documents RF_20) go through `files` + `asset_files` (ADR-0007); bytes on `config('filesystems.files_disk')`.
