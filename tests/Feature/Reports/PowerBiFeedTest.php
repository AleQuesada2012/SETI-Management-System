<?php

// RF_73 (and RF_61) · the CSV feed Power BI Service refreshes from, and the data status on the dashboards page.

use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Space;
use App\Models\User;
use App\Reports\PowerBiFeed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

const FEED_PASSWORD = 'feed-password-for-tests';

beforeEach(function () {
    config([
        'reports.powerbi_feed.username' => 'powerbi',
        'reports.powerbi_feed.password' => FEED_PASSWORD,
        'reports.powerbi_embed_url' => 'https://app.powerbi.com/reportEmbed?reportId=test&autoAuth=true&ctid=tenant',
    ]);
});

function pullFeed(string $dataset, ?string $password = FEED_PASSWORD): TestResponse
{
    $headers = $password === null ? [] : ['Authorization' => 'Basic '.base64_encode('powerbi:'.$password)];

    return test()->get('/powerbi/'.$dataset.'.csv', $headers);
}

/**
 * @return list<array<string, string>>
 */
function feedRows(TestResponse $response): array
{
    $lines = array_map(fn (string $line) => str_getcsv($line, escape: ''), preg_split('/\R/', trim($response->streamedContent())));
    $header = array_shift($lines);

    return array_map(fn (array $values) => array_combine($header, $values), $lines);
}

test('every dataset is served as CSV with the columns the semantic model types', function (string $dataset) {
    $response = pullFeed($dataset)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $header = str_getcsv(strtok($response->streamedContent(), "\n"), escape: '');

    expect($header)->toBe(array_keys(PowerBiFeed::DATASETS[$dataset]['columns']));
})->with(array_keys(PowerBiFeed::DATASETS));

test('the assets dataset carries current data without emails or free-text notes', function () {
    $responsible = User::factory()->create(['name' => 'Ana Responsable', 'email' => 'ana.responsable@itcr.ac.cr']);
    $space = Space::factory()->create(['name' => 'Laboratorio de Redes']);
    $asset = Asset::factory()->inSpace($space)->create([
        'responsible_user_id' => $responsible->id,
        'observations' => 'Nota interna que no sale del sistema',
    ]);

    $response = pullFeed('assets')->assertOk();
    $row = collect(feedRows($response))->firstWhere('id', (string) $asset->id);

    expect($row['space_name'])->toBe('Laboratorio de Redes')
        ->and($row['responsible_name'])->toBe('Ana Responsable')
        ->and($row['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
    expect($response->streamedContent())
        ->not->toContain('ana.responsable@itcr.ac.cr')
        ->not->toContain('Nota interna');
});

test('timestamps are Costa Rica local time, booleans true or false and missing values empty', function () {
    $user = User::factory()->invited()->create(['name' => 'Invitada Nueva']);
    DB::table('users')->where('id', $user->id)->update(['created_at' => DB::raw("'2026-11-10 20:30:00+00'::timestamptz")]);

    $row = collect(feedRows(pullFeed('users')))->firstWhere('id', (string) $user->id);

    expect($row['created_at'])->toBe('2026-11-10 14:30:00')
        ->and($row['invitation_pending'])->toBe('true')
        ->and($row['last_login_at'])->toBe('');
});

test('the feed rejects a missing or wrong credential and serves nothing', function (?string $password) {
    User::factory()->create(['name' => 'No Debe Salir']);

    $response = pullFeed('users', $password)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="SETI Power BI", charset="UTF-8"');

    expect($response->getContent())->not->toContain('No Debe Salir');
})->with([null, 'wrong-password']);

test('the base address lists the datasets, so Power BI Service can test the credential', function (string $path) {
    Cache::forget(PowerBiFeed::LAST_PULLED_KEY);

    $response = $this->get($path, ['Authorization' => 'Basic '.base64_encode('powerbi:'.FEED_PASSWORD)])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect(explode("\n", trim($response->getContent())))
        ->toBe(array_map(fn (string $dataset) => $dataset.'.csv', array_keys(PowerBiFeed::DATASETS)))
        ->and(PowerBiFeed::lastPulledAt())->toBeNull();
})->with(['/powerbi/', '/powerbi']);

test('the base address rejects a missing or wrong credential', function (?string $password) {
    $headers = $password === null ? [] : ['Authorization' => 'Basic '.base64_encode('powerbi:'.$password)];

    $this->get('/powerbi/', $headers)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="SETI Power BI", charset="UTF-8"');
})->with([null, 'wrong-password']);

test('without a configured password the feed does not exist', function () {
    config(['reports.powerbi_feed.password' => null]);

    pullFeed('assets')->assertNotFound();
    $this->get('/powerbi/', ['Authorization' => 'Basic '.base64_encode('powerbi:')])->assertNotFound();
});

test('an unknown dataset or a view outside the feed is not served', function (string $dataset) {
    pullFeed($dataset)->assertNotFound();
})->with(['audit_logs', 'space_managers', 'nonexistent']);

test('the feed throttles repeated attempts', function () {
    foreach (range(1, 60) as $attempt) {
        pullFeed('user_roles', 'wrong-password')->assertUnauthorized();
    }

    pullFeed('user_roles')->assertTooManyRequests();
});

test('the feed starts no session and sets no cookie', function () {
    pullFeed('user_roles')->assertOk()->assertCookieMissing(config('session.cookie'));
});

test('the dashboards page shows when Power BI last read the data', function () {
    $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 15));
    pullFeed('assets')->streamedContent();
    $this->travel(2)->hours();

    actingAsPowerBiViewer()->get(route('reports.powerbi'))
        ->assertOk()
        ->assertSee(__('reports.powerbi.last_updated', ['date' => '11/11/2026 09:15']))
        ->assertDontSee(__('reports.powerbi.never_updated'));
});

test('the dashboards page warns when the data could not be updated for too long', function () {
    config(['reports.powerbi_feed.stale_after_hours' => 13]);
    $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
    pullFeed('assets')->streamedContent();
    $this->travel(14)->hours();

    actingAsPowerBiViewer()->get(route('reports.powerbi'))
        ->assertSee(__('reports.powerbi.stale', ['date' => '11/11/2026 07:00']));
});

test('the dashboards page says when Power BI has never read the data', function () {
    Cache::forget(PowerBiFeed::LAST_PULLED_KEY);

    actingAsPowerBiViewer()->get(route('reports.powerbi'))
        ->assertSee(__('reports.powerbi.never_updated'));
});

test('a credential that fails to authenticate does not count as a refresh', function () {
    Cache::forget(PowerBiFeed::LAST_PULLED_KEY);

    pullFeed('assets', 'wrong-password')->assertUnauthorized();

    expect(PowerBiFeed::lastPulledAt())->toBeNull();
});

test('with the feed off the page shows no data status', function () {
    config(['reports.powerbi_feed.password' => null]);

    actingAsPowerBiViewer()->get(route('reports.powerbi'))
        ->assertOk()
        ->assertDontSee(__('reports.powerbi.never_updated'))
        ->assertDontSee('Última actualización de los datos');
});

function actingAsPowerBiViewer(): TestCase
{
    $user = userWithRole(SystemRole::Visualizador);
    $user->givePermissionTo(PermissionName::DashboardsPowerbi->value);

    return test()->actingAs($user);
}
