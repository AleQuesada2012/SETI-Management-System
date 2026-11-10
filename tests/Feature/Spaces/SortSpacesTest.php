<?php

// RF_37 · Ordenar espacios por fecha de modificación; RF_38 · Ordenar espacios por fecha de creación.

use App\Enums\SystemRole;
use App\Models\Space;
use App\Queries\Spaces\SpaceDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $at = fn (string $date) => CarbonImmutable::parse($date, 'America/Costa_Rica');

    // Created in one order, modified in another.
    $this->older = Space::factory()->create(['code' => 'SP-A', 'name' => 'Zeta']);
    $this->middle = Space::factory()->create(['code' => 'SP-B', 'name' => 'Alfa']);
    $this->newer = Space::factory()->create(['code' => 'SP-C', 'name' => 'Beta']);

    foreach ([[$this->older, '2026-01-05 08:00', '2026-10-20 15:45'], [$this->middle, '2026-03-10 09:00', '2026-02-01 07:05'], [$this->newer, '2026-06-15 10:00', '2026-08-30 22:10']] as [$space, $created, $updated]) {
        DB::table('spaces')->where('id', $space->id)->update(['created_at' => $at($created), 'updated_at' => $at($updated)]);
    }
});

/** @return list<string> */
function sortedCodes(?string $sortBy, string $direction = 'asc'): array
{
    return SpaceDirectory::query([], $sortBy, $direction)->whereIn('code', ['SP-A', 'SP-B', 'SP-C'])->pluck('code')->all();
}

test('spaces sort by creation date, ascending or descending', function () {
    expect(sortedCodes('created_at'))->toBe(['SP-A', 'SP-B', 'SP-C'])
        ->and(sortedCodes('created_at', 'desc'))->toBe(['SP-C', 'SP-B', 'SP-A']);
});

test('spaces sort by modification date, ascending or descending', function () {
    expect(sortedCodes('updated_at'))->toBe(['SP-B', 'SP-C', 'SP-A'])
        ->and(sortedCodes('updated_at', 'desc'))->toBe(['SP-A', 'SP-C', 'SP-B']);
});

test('without a valid sort column the list keeps its order by name', function () {
    expect(sortedCodes(null))->toBe(['SP-B', 'SP-C', 'SP-A'])
        ->and(sortedCodes('capacity'))->toBe(['SP-B', 'SP-C', 'SP-A']);
});

test('clicking a date header sorts ascending, and clicking it again switches to descending', function () {
    actingAsRole(SystemRole::Visualizador);

    Livewire::test('pages::spaces.index')
        ->call('sortByDate', 'created_at')
        ->assertSet('sortBy', 'created_at')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['SP-A', 'SP-B', 'SP-C'])
        ->call('sortByDate', 'created_at')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['SP-C', 'SP-B', 'SP-A'])
        ->call('sortByDate', 'updated_at')
        ->assertSet('sortBy', 'updated_at')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['SP-B', 'SP-C', 'SP-A'])
        ->call('sortByDate', 'capacity')
        ->assertSet('sortBy', 'updated_at');
});

test('the sorted header shows an arrow up when ascending and down when descending', function () {
    actingAsRole(SystemRole::Visualizador);

    /** The first icon path after the "Creado" header: Flux's chevron-up or chevron-down. */
    $arrow = function (string $html): string {
        preg_match('/data-test="sort-created".*?<path[^>]* d="([^"]+)"/s', $html, $match);

        return match (true) {
            str_starts_with($match[1] ?? '', 'M11.78 9.78') => 'up',
            str_starts_with($match[1] ?? '', 'M4.22 6.22') => 'down',
            default => 'none',
        };
    };

    $page = Livewire::test('pages::spaces.index')->call('sortByDate', 'created_at');
    expect($arrow($page->html()))->toBe('up');

    $page->call('sortByDate', 'created_at');
    expect($arrow($page->html()))->toBe('down');
});

test('creation dates show as DD/MM and modification dates as DD/MM/AA with a 24-hour time', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.index', ['orden' => 'created_at']))
        ->assertOk()
        ->assertSee('05/01')
        ->assertSee('20/10/26 15:45')
        ->assertSee('30/08/26 22:10');
});

test('the sort is kept in the URL', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.index', ['orden' => 'updated_at', 'sentido' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['SP-A', 'SP-C', 'SP-B']);
});
