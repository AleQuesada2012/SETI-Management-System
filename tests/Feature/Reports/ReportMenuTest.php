<?php

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\ReportType;
use App\Models\User;
use App\Reports\ReportRegistry;
use Livewire\Livewire;
use Tests\Support\UsersTestReport;

test('the report menu exposes four categories and registered reports', function () {
    actingAsRole(SystemRole::Reportes);
    app(ReportRegistry::class)->register(UsersTestReport::class);
    $this->get(route('reports.index'))->assertOk()->assertSee('Activos')->assertSee('Espacios')
        ->assertSee('Auditoría')->assertSee('Usuarios');
    Livewire::test('pages::reports.index')->set('category', 'users')
        ->assertSee(route('reports.show', 'users'), false)->assertDontSee(__('reports.index.empty'));
});

test('future entries are labelled upcoming and do not suppress the empty category message', function () {
    actingAsRole(SystemRole::Reportes);
    config(['reports.current_sprint' => 2]);
    $registry = app(ReportRegistry::class);
    ReportType::where('category', 'spaces')->get()->filter(fn (ReportType $type): bool => $registry->has($type->code))->each->delete();
    Livewire::test('pages::reports.index')->set('category', 'spaces')
        ->assertSee('Próximamente')->assertSee(__('reports.index.empty'))
        ->assertDontSee(route('reports.show', 'space_detail'), false);
    config(['reports.current_sprint' => 3]);
    Livewire::test('pages::reports.index')->set('category', 'spaces')->assertDontSee('Próximamente');
});

test('inaccessible audit reports are hidden and direct access is refused', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::ReportsView->value);
    $this->actingAs($user);
    Livewire::test('pages::reports.index')->set('category', 'audit')
        ->assertDontSee('Próximamente')->assertSee(__('reports.index.empty'));
    $this->get(route('reports.show', 'audit'))->assertForbidden();
    actingAsRole(SystemRole::Superusuario);
    Livewire::test('pages::reports.index')->set('category', 'audit')
        ->assertSee(route('reports.show', 'audit'), false)->assertDontSee('Próximamente');
});

test('users without reports access cannot open the menu or either report', function (string $code) {
    actingAsRole(SystemRole::Visualizador);
    $this->get(route('reports.index'))->assertForbidden();
    $this->get(route('reports.show', $code))->assertForbidden();
})->with(['assets_inventory', 'spaces']);
