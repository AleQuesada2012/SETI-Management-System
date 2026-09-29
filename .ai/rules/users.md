---
paths:
  - 'app/Actions/Users/**'
  - 'app/Queries/Users/**'
  - 'app/Policies/UserPolicy.php'
  - 'app/Policies/RolePolicy.php'
  - 'app/Models/User.php'
  - 'app/Models/Role.php'
  - 'app/Models/Permission.php'
  - 'resources/views/pages/users/**'
  - 'resources/views/pages/roles/**'
  - 'routes/modules/users.php'
  - 'lang/es/users.php'
  - 'tests/Feature/Users/**'
---

# Users and roles (RF_85–RF_101) · owner @Rubiocai · reviewer @AleQuesada2012

**Sebastián's work outside this module (planning round 3):** manager assignment, RF_40 and RF_43, in Sprint 2
(read `.ai/rules/spaces.md`; @Dizabu reviews). Only holders of the Administrador role can be assigned; assigning and
removing are audited with `Audit::record(AuditAction::ManagerAssigned|ManagerRemoved, …)`; call
`$user->refreshManagedSpaces()` afterwards. RF_96/RF_97 then read the same `space_managers` data.

- **Users are never deleted.** Deactivation (RF_88) sets `deactivated_at`/`deactivated_by` and deletes the user's `sessions` rows; reactivation (RF_98) clears them. Both audited (`Deactivated`, `Reactivated`).
- **Registering (RF_91)** creates the user from name, email and role(s) with `password = NULL`, then sends the invitation: a password-broker link (`Password::broker()->sendResetLink`) that sets the password and `email_verified_at`. Emails are stored lowercase.
- **Invariants no one may break**, enforced in `app/Actions/Users` (policies cannot, the Superusuario bypasses them):
  - a user always holds at least one role;
  - the last active Superusuario keeps that role and stays active;
  - system roles (`roles.is_system`) are never renamed or deleted.
- **Role changes** (RF_85–RF_87) are pivot changes: audit them with `Audit::record(AuditAction::RoleAssigned|RoleRemoved, $user, new: ['role' => …])`, then `app(PermissionRegistrar::class)->forgetCachedPermissions()`. RF_87 swaps roles in one transaction. Removing the Administrador role also removes the user's space-manager assignments.
- **List, search, filters:** `App\Queries\Users\UserDirectory` over `reporting.users` (never exposes secrets). The users report (RF_69) reuses it.
- Role descriptions (RF_100) come from `roles.description` / `permissions.description`, seeded from `App\Enums\SystemRole` and `App\Enums\PermissionName`.
