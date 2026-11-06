<?php

// RF_61 · the embedded Power BI dashboards.

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

const POWERBI_TEST_URL = 'https://app.powerbi.com/reportEmbed?reportId=test&autoAuth=true&ctid=tenant';

function userWithPowerBi(): User
{
    $user = userWithRole(SystemRole::Visualizador);
    $user->givePermissionTo(PermissionName::DashboardsPowerbi->value);

    return $user;
}

test('a user with the permission sees the dashboards embedded in the page', function () {
    config(['reports.powerbi_embed_url' => POWERBI_TEST_URL]);

    $this->actingAs(userWithPowerBi())->get(route('reports.powerbi'))
        ->assertOk()
        ->assertSee(__('reports.powerbi.title'))
        ->assertSee('<iframe', false)
        ->assertSee(e(POWERBI_TEST_URL), false)
        ->assertSee(__('reports.powerbi.refresh'))
        ->assertDontSee(__('reports.powerbi.not_configured'));
});

test('without an embed URL the page says the dashboards are not configured', function () {
    config(['reports.powerbi_embed_url' => null]);

    $this->actingAs(userWithPowerBi())->get(route('reports.powerbi'))
        ->assertOk()
        ->assertSee(__('reports.powerbi.not_configured'))
        ->assertDontSee('<iframe', false);
});

test('only the Superusuario holds the permission among the system roles', function (SystemRole $role, int $status) {
    config(['reports.powerbi_embed_url' => POWERBI_TEST_URL]);

    $this->actingAs(userWithRole($role))->get(route('reports.powerbi'))->assertStatus($status);
})->with([
    'Superusuario' => [SystemRole::Superusuario, 200],
    'Administrador' => [SystemRole::Administrador, 403],
    'Reportes' => [SystemRole::Reportes, 403],
    'Visualizador' => [SystemRole::Visualizador, 403],
    'Reservante' => [SystemRole::Reservante, 403],
]);

test('the menu links to the dashboards only for users who may open them', function () {
    config(['reports.powerbi_embed_url' => POWERBI_TEST_URL]);

    $this->actingAs(userWithPowerBi())->get(route('reports.powerbi'))
        ->assertSee(__('app.nav.powerbi'));

    $this->actingAs(userWithRole(SystemRole::Reportes))->get(route('reports.index'))
        ->assertDontSee(__('app.nav.powerbi'));
});

test('a user who holds only this permission starts on the dashboards', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::DashboardsPowerbi->value);

    $this->actingAs($user)->get(route('start'))->assertRedirect(route('reports.powerbi'));
});

test('seeding again adds the permission without removing grants made later', function () {
    $user = userWithPowerBi();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($user->fresh()->can(PermissionName::DashboardsPowerbi))->toBeTrue();
});
