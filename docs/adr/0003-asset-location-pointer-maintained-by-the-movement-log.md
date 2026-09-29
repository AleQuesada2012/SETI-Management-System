# An asset's current space is a cached pointer that only the movement log may change

`assets.current_space_id` stores where an asset is now. That makes "assets in this space" (the most frequent query, and the inventory list) a plain indexed foreign key and a normal Eloquent `belongsTo`. `asset_movements` (entry, transfer, exit; append-only) is the history behind RF_09, RF_25 and RF_67. This is deliberate, controlled redundancy: a trigger on `asset_movements` is the only writer of the pointer, and it rejects any other write to the column. The trigger also enforces the movement rules:
- a movement must start where the asset is, and history stays in chronological order;
- lost and deleted assets cannot move, and a decommissioned asset can only exit;
- nothing can move into a deleted space.

## Considered Options

- **Temporal placements table** (`asset_id, space_id, placed_at, removed_at`): no redundant column at all, but every listing must join the open placement, and entry, transfer and exit have to be inferred or stored on the rows.

## Consequences

To move an asset, insert a movement; never update the column (the database refuses). Registering an asset in a space is two inserts in one transaction: the asset, then its entry movement. The guard uses a transaction-local setting, so it prevents mistakes; it is not a security boundary against someone who can run arbitrary SQL.
