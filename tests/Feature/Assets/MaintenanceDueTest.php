<?php

// RF_27 · The daily reminder of scheduled maintenance (maintenance.due, Agent-DB.md §10): once when it comes within
// NotifyDueMaintenance::DAYS_AHEAD days and once when its day has passed, to the managers of the asset's space.

use App\Actions\Assets\NotifyDueMaintenance;
use App\Actions\Assets\ScheduleMaintenance;
use App\Actions\Assets\UpdateScheduledMaintenance;
use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\Space;
use App\Notifications\Maintenance\MaintenanceDue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-11-02 07:00');
    $this->manager = userWithRole(SystemRole::Administrador);
    $this->superuser = userWithRole(SystemRole::Superusuario);
    $this->space = Space::factory()->managedBy($this->manager)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create(['name' => 'Proyector Epson']);
});

afterEach(fn () => Carbon::setTestNow());

function scheduleFor(Asset $asset, string $date, string $description = 'Limpieza del filtro de aire.'): MaintenanceRecord
{
    return app(ScheduleMaintenance::class)->handle(test()->superuser, $asset, [
        'kind' => 'preventive', 'scheduled_for' => $date, 'description' => $description,
    ]);
}

test('maintenance due within three days reminds the managers of the asset\'s space once', function () {
    $due = scheduleFor($this->asset, '2026-11-05');
    scheduleFor($this->asset, '2026-11-06', 'Todavía lejos');

    expect(app(NotifyDueMaintenance::class)->handle())->toBe(1)
        ->and(app(NotifyDueMaintenance::class)->handle())->toBe(0);

    $notification = $this->manager->notifications()->sole();
    expect($notification->type)->toBe('maintenance.due')
        ->and($notification->data)->toMatchArray([
            'maintenance_id' => $due->id,
            'asset' => ['id' => $this->asset->id, 'code' => $this->asset->code, 'name' => 'Proyector Epson'],
            'kind' => 'preventive',
            'scheduled_for' => '2026-11-05',
            'stage' => 'upcoming',
        ])
        ->and($this->superuser->notifications()->count())->toBe(0);
});

test('an overdue maintenance is reminded once more, and a new planned day starts over', function () {
    $record = scheduleFor($this->asset, '2026-11-03');
    app(NotifyDueMaintenance::class)->handle();

    Carbon::setTestNow('2026-11-04 07:00');
    expect(app(NotifyDueMaintenance::class)->handle())->toBe(1)
        ->and(app(NotifyDueMaintenance::class)->handle())->toBe(0)
        ->and($this->manager->notifications()->pluck('data')->pluck('stage')->sort()->values()->all())->toBe(['overdue', 'upcoming']);

    app(UpdateScheduledMaintenance::class)->handle($this->superuser, $record, [
        'kind' => 'preventive', 'scheduled_for' => '2026-11-06', 'description' => $record->description,
    ]);
    expect(app(NotifyDueMaintenance::class)->handle())->toBe(1)
        ->and($this->manager->notifications()->count())->toBe(3);
});

test('without active managers the Superusuarios are reminded', function () {
    $orphan = Asset::factory()->inSpace(Space::factory()->create())->create();
    $deactivatedManager = userWithRole(SystemRole::Administrador);
    $deactivatedManager->managedSpaces()->attach($orphan->current_space_id);
    $deactivatedManager->forceFill(['deactivated_at' => now()])->save();
    scheduleFor($orphan, '2026-11-04');

    app(NotifyDueMaintenance::class)->handle();

    expect($this->superuser->notifications()->count())->toBe(1)
        ->and($deactivatedManager->notifications()->count())->toBe(0)
        ->and($this->manager->notifications()->count())->toBe(0);
});

test('cancelled, completed or lost-asset maintenance is not reminded', function () {
    $cancelled = scheduleFor($this->asset, '2026-11-04');
    $cancelled->forceFill(['status' => 'cancelled'])->save();
    $lost = Asset::factory()->inSpace($this->space)->create();
    scheduleFor($lost, '2026-11-04');
    $lost->forceFill(['status' => AssetStatus::Lost])->save();

    Notification::fake();
    expect(app(NotifyDueMaintenance::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

test('the daily command sends the reminders and is scheduled', function () {
    scheduleFor($this->asset, '2026-11-03');

    $this->artisan('maintenance:notify-due')->expectsOutput('Recordatorios enviados: 1')->assertSuccessful();

    $this->artisan('schedule:list')->expectsOutputToContain('maintenance:notify-due');
});

test('the manager reads the reminder in the bell and it opens the asset', function () {
    scheduleFor($this->asset, '2026-11-03');
    Carbon::setTestNow('2026-11-04 07:00');
    app(NotifyDueMaintenance::class)->handle();
    $this->actingAs($this->manager);

    Livewire::test('notifications.bell')
        ->assertSee("Mantenimiento vencido: {$this->asset->code}")
        ->assertSee('Preventivo de Proyector Epson programado para el 03/11/2026 sigue sin registrarse: Limpieza del filtro de aire.')
        ->call('open', $this->manager->notifications()->sole()->id)
        ->assertRedirect(route('assets.show', $this->asset));

    expect(MaintenanceDue::inboxMessage(['stage' => 'upcoming', 'kind' => 'corrective', 'asset' => ['id' => 1, 'code' => 'A-1', 'name' => 'PC'], 'scheduled_for' => '2026-11-05', 'description' => 'X']))
        ->toBe([
            'title' => 'Mantenimiento próximo: A-1',
            'body' => 'Correctivo de PC programado para el 05/11/2026: X',
            'url' => route('assets.show', 1),
        ]);
});
