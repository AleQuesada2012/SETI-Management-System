<?php

use App\Enums\PermissionName;
use App\Enums\SystemRole;

// The seeded role → permission matrix (Agent-DB.md §5) keeps the promises of RF_89.

test('the Visualizador holds only read permissions', function () {
    foreach (SystemRole::Visualizador->permissions() as $permission) {
        expect($permission->isReadOnly())->toBeTrue("{$permission->value} is not read-only");
    }
});

test('the Superusuario holds every permission', function () {
    expect(SystemRole::Superusuario->permissions())->toBe(PermissionName::cases());
});

test('only the Superusuario manages users, roles and space managers', function () {
    $exclusive = [PermissionName::UsersCreate, PermissionName::UsersUpdate, PermissionName::UsersDeactivate,
        PermissionName::RolesAssign, PermissionName::RolesManage, PermissionName::SpacesAssignManagers];

    foreach (SystemRole::cases() as $role) {
        if ($role === SystemRole::Superusuario) {
            continue;
        }

        $held = array_filter($exclusive, fn (PermissionName $permission) => in_array($permission, $role->permissions(), true));

        expect($held)->toBe([], "{$role->value} holds a Superusuario-only permission");
    }
});

test('every permission has a Spanish description', function () {
    foreach (PermissionName::cases() as $permission) {
        expect($permission->description())->not->toBeEmpty();
    }
});
