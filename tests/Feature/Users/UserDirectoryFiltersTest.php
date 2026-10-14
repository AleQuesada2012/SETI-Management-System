<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Queries\Users\UserDirectory;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * The users filter contract (.ai/rules/reports.md § Users schema), shared by the users list (RF_90, RF_92, RF_95,
 * RF_101), the roles page (RF_100) and the users report (RF_69).
 */

test('valid filters are normalized: trimmed search, no duplicates, codes in order, role ids sorted', function () {
    $reportes = Role::findByName(SystemRole::Reportes->value)->id;
    $admin = Role::findByName(SystemRole::Administrador->value)->id;

    expect(UserDirectory::validate([
        'search' => '  vargas ',
        'user_type' => ['values' => ['regular', 'administrators', 'regular']],
        'role' => ['values' => [(string) $reportes, $admin, $reportes]],
        'account_status' => ['values' => []],
    ]))->toBe([
        'search' => 'vargas',
        'user_type' => ['values' => ['administrators', 'regular']],
        'role' => ['values' => collect([$reportes, $admin])->sort()->values()->all()],
    ]);
});

test('empty filters mean every user', function () {
    expect(UserDirectory::validate(['search' => '', 'user_type' => ['values' => []]]))->toBe([]);
});

test('broken filters are Spanish validation errors', function (array $filters, string $message) {
    expect(fn () => UserDirectory::validate($filters))->toThrow(ValidationException::class, $message);
})->with([
    'unknown key' => [['colour' => ['values' => ['red']]], 'El filtro «colour» no existe.'],
    'bad shape' => [['user_type' => 'administrators'], 'El filtro «Tipo de usuario» no tiene un formato válido.'],
    'unknown code' => [['user_type' => ['values' => ['owners']]], 'El filtro «Tipo de usuario» tiene una opción que no existe.'],
    'missing role' => [['role' => ['values' => [999_999]]], 'El filtro «Rol» tiene una opción que no existe.'],
    'unknown status' => [['account_status' => ['values' => ['banned']]], 'El filtro «Estado de cuenta» tiene una opción que no existe.'],
]);

test('an edited URL with broken filters shows the errors instead of the list', function () {
    actingAsRole(SystemRole::Superusuario);

    Livewire::withQueryParams(['filters' => ['user_type' => ['values' => ['owners']]]])
        ->test('pages::users.index')
        ->assertSee(__('users.index.invalid_filters'))
        ->assertSee('El filtro «Tipo de usuario» tiene una opción que no existe.')
        ->call('clearFilters')
        ->assertDontSee(__('users.index.invalid_filters'));
});
