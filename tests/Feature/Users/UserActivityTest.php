<?php

use App\Enums\AuditAction;
use App\Enums\AuditChannel;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * RF_99 Consultar historial de actividad de usuario: what a user did, with date, time and operation, by date range.
 */

/** An audit entry at a given Costa Rica time (audit_logs is append-only, so its time is set on insert). */
function activityOf(User $actor, AuditAction $action, string $at, ?string $entityType = null, ?string $entityLabel = null): AuditLog
{
    $log = new AuditLog;
    $log->forceFill([
        'occurred_at' => Carbon::parse($at, 'America/Costa_Rica'),
        'actor_id' => $actor->id,
        'actor_label' => $actor->name,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityType === null ? null : 1, // audit_logs_entity_check: both or neither
        'entity_label' => $entityLabel,
        'channel' => AuditChannel::Web,
        'request_id' => (string) Str::uuid(),
    ])->save();

    return $log;
}

test('the Superusuario opens a user\'s activity from their page and sees date, time and operation', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Lucía Campos', 'email' => 'lucia.campos@seti.test']);
    $other = User::factory()->create(['name' => 'Otra Persona', 'email' => 'otra.persona@seti.test']);
    activityOf($user, AuditAction::Login, '2026-10-20 14:35');
    activityOf($user, AuditAction::Created, '2026-10-21 09:05', 'space', 'LAB-REDES · Laboratorio de Redes');
    activityOf($other, AuditAction::Deleted, '2026-10-21 10:00', 'asset', 'A-9999 · Ajeno');

    $this->get(route('users.show', $user))->assertSee(route('users.activity', ['usuario' => $user->id]), escape: false);

    Livewire::withQueryParams(['usuario' => (string) $user->id])
        ->test('pages::users.activity')
        ->assertSee(__('users.activity.heading', ['user' => 'Lucía Campos']))
        ->assertSeeInOrder(['21/10/2026', '09:05', 'Creó', 'espacio', 'LAB-REDES · Laboratorio de Redes', '20/10/2026', '14:35', 'Inició sesión'])
        ->assertDontSee('A-9999 · Ajeno');
});

test('the activity is filtered by a date range of whole Costa Rica days', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->create(['email' => 'rango@seti.test']);
    activityOf($user, AuditAction::Login, '2026-10-10 08:00', 'user', 'Antes del rango');
    activityOf($user, AuditAction::Login, '2026-10-15 00:00', 'user', 'Primer instante del rango');
    activityOf($user, AuditAction::Login, '2026-10-20 23:59', 'user', 'Último minuto del rango');
    activityOf($user, AuditAction::Login, '2026-10-21 00:00', 'user', 'Después del rango');

    Livewire::test('pages::users.activity')
        ->set('user', (string) $user->id)
        ->set('from', '2026-10-15')
        ->set('to', '2026-10-20')
        ->assertSee('Primer instante del rango')
        ->assertSee('Último minuto del rango')
        ->assertDontSee('Antes del rango')
        ->assertDontSee('Después del rango')
        ->call('clearDates')
        ->assertSee('Antes del rango');
});

test('a user without activity, or without it in the range, says so', function () {
    actingAsRole(SystemRole::Superusuario);
    $quiet = User::factory()->create(['email' => 'quiet@seti.test']);
    $busy = User::factory()->create(['email' => 'busy@seti.test']);
    activityOf($busy, AuditAction::Login, '2026-10-10 08:00');

    Livewire::test('pages::users.activity')
        ->assertSee(__('users.activity.pick_user'))
        ->set('user', (string) $quiet->id)
        ->assertSee(__('users.activity.none'))
        ->set('user', (string) $busy->id)
        ->set('from', '2026-10-20')
        ->assertSee(__('users.activity.none_in_range'));
});

test('an inverted date range is reported instead of a list', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->create(['email' => 'invertido@seti.test']);

    Livewire::test('pages::users.activity')
        ->set('user', (string) $user->id)
        ->set('from', '2026-10-20')
        ->set('to', '2026-10-01')
        ->assertSee(__('users.activity.invalid'))
        ->assertSee(__('reports.filters.errors.range'));
});

test('the user select starts on a selectable empty option and lists deactivated users too', function () {
    actingAsRole(SystemRole::Superusuario);
    User::factory()->deactivated()->create(['name' => 'Jorge Ramírez', 'email' => 'jorge.ramirez@seti.test']);

    $html = Livewire::test('pages::users.activity')->html();

    expect($html)->toMatch('/<option[^>]*value=""/')
        ->not->toMatch('/<option[^>]*\bdisabled\b/')
        ->toContain(e(__('reports.filters.deactivated', ['name' => 'Jorge Ramírez'])));
});

test('the Reportes role may consult activity; roles without reports.audit may not', function () {
    actingAsRole(SystemRole::Reportes);
    $this->get(route('users.activity'))->assertOk();

    $admin = actingAsRole(SystemRole::Administrador);
    $this->get(route('users.activity'))->assertForbidden();
    $this->get(route('users.show', $admin))->assertOk()->assertDontSee(route('users.activity', ['usuario' => $admin->id]), escape: false);
});

test('the Reportes role reaches the activity from the reports menu and goes back to it', function () {
    actingAsRole(SystemRole::Reportes);

    $this->get(route('reports.index'))->assertOk()->assertSee(route('users.activity'), escape: false);
    $this->get(route('users.activity'))->assertOk()
        ->assertSee(__('users.activity.back_to_reports'))
        ->assertDontSee(__('users.activity.back'));
});

test('a user who sees reports but not the audit gets no link to the activity', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::ReportsView->value);
    $this->actingAs($user);

    $this->get(route('reports.index'))->assertOk()->assertDontSee(route('users.activity'), escape: false);
});
