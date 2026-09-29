# Files live on a Laravel Storage disk; the database keeps their metadata and FK-checked links

Several kinds of file are stored on a Laravel Storage disk: photos (RF_21, RF_41), documents (RF_20), damage evidence (RF_13) and exported reports (RF_70, RF_71, RF_84). PostgreSQL keeps one `files` row per file (disk, path, MIME type, size, SHA-256, image size, thumbnail). Owners are linked through explicit link tables with real foreign keys: `asset_files`, `space_photos`, `asset_event_evidence` and `report_generations.file_id`. The database enforces the MIME whitelist and size limits, and guarantees that no attachment points to a missing owner.

## Amendment (2026-09-28): the disk is the VM's own disk

The first version planned an S3-compatible store (MinIO) in production. Production is one AWS VM (ADR-0012), so the bytes stay on the private `local` disk, which in production is the `storage` Docker volume on the VM disk (`compose.prod.yaml`). The disk name is configurable (`FILES_DISK`, `config('filesystems.files_disk')`), so moving to S3 later changes configuration, not code or schema. Back up the `storage` volume together with the database.

## Considered Options

- **Bytes in PostgreSQL (`bytea`)**: one backup covers everything, but the database balloons, backups slow down and images cannot be served by the web server.
- **spatie/laravel-medialibrary (polymorphic `media` table)**: thumbnails and collections for free, but no referential integrity. That is why it is not used; do not "simplify" to it.
- **MinIO next to the app on the same VM**: an S3 API with no benefit over the local disk on a single machine, and one more service to run and back up.

## Consequences

Upload validation and thumbnail generation are our code. Deleting a `files` row cascades to its link row; the application removes the physical file after the transaction commits. Files are never public: they are served only through routes that authorize the viewer (e.g. `reports.generations.download`).
