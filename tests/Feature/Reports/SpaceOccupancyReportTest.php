<?php

// RF_79 · Generar reporte de ocupación de espacios.

use App\Actions\Reports\ExportReport;
use App\Enums\ReportFormat;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Space;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;

/** Confirmed reservations of $space, one per start time, each lasting $minutes. */
function occupancyReportReservations(Space $space, int $minutes, string ...$startsAt): void
{
    foreach ($startsAt as $start) {
        $start = Carbon::parse($start);
        Reservation::factory()->between($start, $start->copy()->addMinutes($minutes))->create(['space_id' => $space->id]);
    }
}

beforeEach(function () {
    $this->author = actingAsRole(SystemRole::Reportes);
    $this->october = ['date' => ['from' => '2026-10-01', 'to' => '2026-10-31']];

    $this->busiest = Space::factory()->create(['code' => 'LAB-01', 'name' => 'Laboratorio de redes']);
    $this->middle = Space::factory()->create(['code' => 'AUL-02', 'name' => 'Aula 2']);
    $this->quietest = Space::factory()->create(['code' => 'SAL-03', 'name' => 'Sala de reuniones']);
    occupancyReportReservations($this->busiest, 90, '2026-10-05 08:00', '2026-10-06 08:00', '2026-10-07 08:00');
    occupancyReportReservations($this->middle, 120, '2026-10-05 08:00', '2026-10-06 08:00');
    occupancyReportReservations($this->quietest, 45, '2026-10-05 08:00');
    occupancyReportReservations(Space::factory()->create(['name' => 'Sala de noviembre']), 60, '2026-11-03 08:00');
});

test('the preview lists each space with its reservations in the period, from the most used to the least', function () {
    $this->get(route('reports.show', ['report' => 'occupancy', 'filters' => $this->october]))
        ->assertOk()
        ->assertSee('Período: Desde 01/10/2026 · Hasta 31/10/2026')
        ->assertSeeInOrder([
            'LAB-01 · Laboratorio de redes', '3', '4 h 30 min',
            'AUL-02 · Aula 2', '2', '4 h',
            'SAL-03 · Sala de reuniones', '1', '45 min',
        ])
        ->assertDontSee('Sala de noviembre');
});

test('the export has the same rows in the same order, in Excel and PDF', function () {
    Storage::fake(config('filesystems.files_disk'));

    $generation = app(ExportReport::class)->handle($this->author, 'occupancy', ReportFormat::Xlsx, $this->october);
    $rows = SimpleExcelReader::create(Storage::disk($generation->file->disk)->path($generation->file->path))->getRows()->all();

    expect($generation->row_count)->toBe(3)
        ->and($generation->filters)->toBe($this->october)
        ->and(array_column($rows, 'Espacio'))->toBe(['LAB-01 · Laboratorio de redes', 'AUL-02 · Aula 2', 'SAL-03 · Sala de reuniones'])
        ->and(array_map('intval', array_column($rows, 'Reservas')))->toBe([3, 2, 1])
        ->and(array_column($rows, 'Tiempo reservado'))->toBe(['4 h 30 min', '4 h', '45 min']);

    $pdf = app(ExportReport::class)->handle($this->author, 'occupancy', ReportFormat::Pdf, $this->october);
    expect(Storage::disk($pdf->file->disk)->get($pdf->file->path))->toStartWith('%PDF');
});

test('choosing a month gives a monthly report, and several months add up', function () {
    Storage::fake(config('filesystems.files_disk'));
    $october = ['month' => ['values' => ['2026-10']]];

    $this->get(route('reports.show', ['report' => 'occupancy', 'filters' => $october]))
        ->assertOk()
        ->assertSee('Mes: Octubre de 2026')
        ->assertSeeInOrder(['LAB-01 · Laboratorio de redes', 'AUL-02 · Aula 2', 'SAL-03 · Sala de reuniones'])
        ->assertDontSee('Sala de noviembre');

    $generation = app(ExportReport::class)->handle($this->author, 'occupancy', ReportFormat::Xlsx, $october);
    expect($generation->row_count)->toBe(3)->and($generation->filters)->toBe($october);

    $this->get(route('reports.show', ['report' => 'occupancy', 'filters' => ['month' => ['values' => ['2026-11', '2026-10']]]]))
        ->assertSee('Mes: Octubre de 2026 · Noviembre de 2026')
        ->assertSee('Sala de noviembre');
});

test('the month choices are the current month and every month with reservations, newest first', function () {
    Carbon::setTestNow('2027-01-15 10:00');

    $this->get(route('reports.show', 'occupancy'))
        ->assertSeeInOrder(['Enero de 2027', 'Noviembre de 2026', 'Octubre de 2026'])
        ->assertDontSee('Diciembre de 2026');
});

test('a month that does not exist is rejected', function () {
    expect(fn () => app(ExportReport::class)->handle($this->author, 'occupancy', ReportFormat::Pdf, ['month' => ['values' => ['2026-13']]]))
        ->toThrow(ValidationException::class, 'El mes seleccionado no es válido.');
});

test('without a period the report covers every reservation', function () {
    $this->get(route('reports.show', 'occupancy'))
        ->assertOk()
        ->assertSee(__('reports.document.all_records'))
        ->assertSee('Sala de noviembre');
});

test('a final date before the initial one is rejected in the preview and the export', function () {
    $inverted = ['date' => ['from' => '2026-10-31', 'to' => '2026-10-01']];

    $this->get(route('reports.show', ['report' => 'occupancy', 'filters' => $inverted]))
        ->assertOk()
        ->assertSee('La fecha final no puede ser anterior a la fecha inicial.')
        ->assertDontSee('Laboratorio de redes');

    expect(fn () => app(ExportReport::class)->handle($this->author, 'occupancy', ReportFormat::Pdf, $inverted))
        ->toThrow(ValidationException::class);
});

test('a period without reservations says so', function () {
    $this->get(route('reports.show', ['report' => 'occupancy', 'filters' => ['date' => ['from' => '2026-12-01', 'to' => '2026-12-31']]]))
        ->assertOk()
        ->assertSee(__('reports.document.empty'));
});

test('roles without reports.view cannot open the occupancy report', function () {
    $this->actingAs(userWithRole(SystemRole::Visualizador))
        ->get(route('reports.show', 'occupancy'))
        ->assertForbidden();
});
