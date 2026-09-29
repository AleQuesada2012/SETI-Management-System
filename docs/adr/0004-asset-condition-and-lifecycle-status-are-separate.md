# Asset condition and lifecycle status are two separate attributes

The documents use "estado" for two different things: the physical condition shown as green or red (RF_05, RF_80) and the lifecycle (in maintenance, lost, decommissioned: RF_28, RF_29 and the mockup's "Mantenimiento" badge). We store them separately. `condition_id` points to an editable `asset_conditions` catalog whose rows carry a color group, which is the "color for a group of conditions". `status` is one of `in_service`, `in_maintenance`, `lost`, `decommissioned`. This allows combinations like "damaged and in maintenance", and keeps condition statistics from mixing with lifecycle counts.

## Consequences

The inventory badge shows the lifecycle status when an asset is not in service, and its condition color otherwise. Status changes are side effects of asset events and maintenance, applied by Laravel services in the same transaction.
