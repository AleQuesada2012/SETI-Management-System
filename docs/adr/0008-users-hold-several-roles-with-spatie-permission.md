# Users hold several roles (spatie/laravel-permission) instead of the single role in the documents

The documents and mockup show one role per user (RF_87 "cambiar su rol actual"). The team wants Discord-style roles instead: named bundles of permissions, several per user, whose permissions add up. We use spatie/laravel-permission and add a real foreign key from `model_has_roles.model_id` to `users`, since only users hold roles. We seed five protected system roles (Superusuario, Administrador, Reportes, Visualizador, Reservante) and let the Superusuario create custom roles from a fixed permission catalog. The new Reservante role resolves the conflict between RF_46 (any user books) and RF_89 (Visualizador is read-only): a professor can be Visualizador + Reservante.

## Consequences

RF_87 "modify role" becomes "replace one role with another". Laravel enforces two rules: a user always keeps at least one role, and the last active Superusuario can neither lose that role nor be deactivated. After seeding permissions with SQL, run `php artisan permission:cache-reset`.
