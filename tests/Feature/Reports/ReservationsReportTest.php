<?php

// RF_65 · Generar reporte de reservaciones.

use App\Actions\Reports\ExportReport;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Reports\Definitions\ReservationsReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;

/** PostgreSQL derives the effective status from its own clock: 2020 is finished and 2030 is upcoming. */
function reportReservation(Space $space, string $startsAt, string $title, array $attributes = []): Reservation
{
    $start = Carbon::parse($startsAt);

    return Reservation::factory()
        ->between($start, $start->copy()->addHours(2))
        ->create(['space_id' => $space->id, 'title' => $title, ...$attributes]);
}

test('the report lists every state of the period with space, date, time, reason, attendees, responsible and status', function () {
    $author = actingAsRole(SystemRole::Reportes);
    Storage::fake(config('filesystems.files_disk'));
    $space = Space::factory()->create(['code' => 'LAB-07', 'name' => 'Laboratorio de redes']);
    $ana = User::factory()->create(['name' => 'Ana Rojas']);

    reportReservation($space, '2030-06-03 08:00', 'Tutoría activa', ['responsible_id' => $ana->id, 'attendees' => 12]);
    $cancelled = reportReservation($space, '2030-06-04 08:00', 'Charla suspendida');
    $cancelled->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $cancelled->responsible_id])->save();
    reportReservation($space, '2030-06-05 08:00', 'Reunión movida')->forceFill(['version' => 2])->save();
    reportReservation($space, '2030-07-01 08:00', 'Fuera del período');
    reportReservation($space, '2020-06-01 08:00', 'Clase finalizada');

    $period = ['date' => ['from' => '2030-06-01', 'to' => '2030-06-30']];

    $this->get(route('reports.show', ['report' => 'reservations', 'filters' => $period]))->assertOk()
        ->assertSeeInOrder(['LAB-07 · Laboratorio de redes', '03/06/2030', '08:00–10:00', 'Tutoría activa', '12', 'Ana Rojas', 'Próxima'])
        ->assertSeeInOrder(['Charla suspendida', 'Cancelada'])
        ->assertSeeInOrder(['Reunión movida', 'Próxima · Modificada'])
        ->assertDontSee('Fuera del período');

    $generation = app(ExportReport::class)->handle($author, 'reservations', ReportFormat::Xlsx, $period);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();

    expect(array_column($rows, 'Motivo'))->toBe(['Tutoría activa', 'Charla suspendida', 'Reunión movida'])
        ->and($rows[0])->toMatchArray(['Espacio' => 'LAB-07 · Laboratorio de redes', 'Fecha' => '03/06/2030', 'Horario' => '08:00–10:00',
            'Responsable' => 'Ana Rojas', 'Estado' => 'Próxima'])
        ->and((int) $rows[0]['Cantidad de personas'])->toBe(12);

    $pdf = app(ExportReport::class)->handle($author, 'reservations', ReportFormat::Pdf, ['date' => ['from' => '2020-06-01', 'to' => '2020-06-01']]);
    expect(Storage::disk($pdf->file->disk)->get($pdf->file->path))->toStartWith('%PDF');
});

test('the report can be filtered by status', function () {
    actingAsRole(SystemRole::Reportes);
    $space = Space::factory()->create();
    reportReservation($space, '2030-06-03 08:00', 'Tutoría activa');
    reportReservation($space, '2020-06-01 08:00', 'Clase finalizada');

    $this->get(route('reports.show', ['report' => 'reservations', 'filters' => ['status' => ['values' => ['finished']]]]))->assertOk()
        ->assertSee('Clase finalizada')
        ->assertDontSee('Tutoría activa');
});

test('the applied filters are printed in Spanish', function () {
    expect(app(ReservationsReport::class)->describeFilters([
        'status' => ['values' => ['cancelled', 'modified']],
        'date' => ['from' => '2030-06-01', 'to' => '2030-06-30'],
    ]))->toBe([
        'Estado de la reserva' => 'Cancelada · Modificada',
        'Fecha de la reserva' => 'Desde 01/06/2030 · Hasta 30/06/2030',
    ]);
});

test('invalid filters block the export and a period without reservations says so', function () {
    $author = actingAsRole(SystemRole::Reportes);

    expect(fn () => app(ExportReport::class)->handle($author, 'reservations', ReportFormat::Pdf, ['date' => ['from' => '2030-06-30', 'to' => '2030-06-01']]))
        ->toThrow(ValidationException::class, __('reservations.validation.to_after_from'))
        ->and(fn () => app(ExportReport::class)->handle($author, 'reservations', ReportFormat::Pdf, ['space' => ['values' => [1]]]))
        ->toThrow(ValidationException::class);

    $this->get(route('reports.show', ['report' => 'reservations', 'filters' => ['date' => ['from' => '2031-01-01', 'to' => '2031-01-31']]]))
        ->assertOk()->assertSee(__('reports.document.empty'));
});

test('a user without reports.view cannot open the reservations report', function () {
    actingAsRole(SystemRole::Reservante);

    $this->get(route('reports.show', 'reservations'))->assertForbidden();
});
