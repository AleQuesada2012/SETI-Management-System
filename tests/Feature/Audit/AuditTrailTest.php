<?php

use App\Enums\AuditAction;
use App\Enums\AuditChannel;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// RF_00_2 (changes) and RF_00_3 (creations): who, what, which element, when and from where (ADR-0002).

function auditEntry(AuditAction $action, Model $entity): AuditLog
{
    return AuditLog::query()
        ->where('action', $action)
        ->where('entity_type', $entity->getMorphClass())
        ->where('entity_id', $entity->getKey())
        ->sole();
}

test('a creation records the actor, the element and its values (RF_00_3)', function () {
    $actor = actingAsRole(SystemRole::Superusuario);

    $space = Space::factory()->create(['code' => 'LAB-110', 'name' => 'Laboratorio 110', 'capacity' => 30]);

    $entry = auditEntry(AuditAction::Created, $space);
    expect($entry->actor_id)->toBe($actor->id)
        ->and($entry->actor_label)->toBe($actor->name)
        ->and($entry->entity_label)->toBe('LAB-110 · Laboratorio 110')
        ->and($entry->new_values)->toMatchArray(['code' => 'LAB-110', 'name' => 'Laboratorio 110', 'capacity' => 30])
        ->and($entry->old_values)->toBeNull()
        ->and($entry->occurred_at)->not->toBeNull();
});

test('a creation of a user is recorded without secrets (RF_00_3)', function () {
    actingAsRole(SystemRole::Superusuario);

    $user = User::factory()->withTwoFactor()->create();

    $entry = auditEntry(AuditAction::Created, $user);
    expect($entry->new_values)->toHaveKeys(['name', 'email'])
        ->not->toHaveKeys(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
});

test('a change records only the changed fields, before and after (RF_00_2)', function () {
    actingAsRole(SystemRole::Superusuario);
    $space = Space::factory()->create(['capacity' => 20]);

    $space->update(['capacity' => 35]);

    $entry = auditEntry(AuditAction::Updated, $space);
    expect($entry->old_values)->toBe(['capacity' => 20])
        ->and($entry->new_values)->toBe(['capacity' => 35]);
});

test('saving without real changes writes no entry', function () {
    actingAsRole(SystemRole::Superusuario);
    $space = Space::factory()->create();

    $space->touch();

    expect(AuditLog::query()->where('action', AuditAction::Updated)->where('entity_id', $space->id)->exists())->toBeFalse();
});

test('a deletion keeps the last values', function () {
    actingAsRole(SystemRole::Superusuario);
    $space = Space::factory()->create(['code' => 'LAB-999']);

    $space->delete();

    expect(auditEntry(AuditAction::Deleted, $space)->old_values)->toMatchArray(['code' => 'LAB-999']);
});

test('the channel says how the change was made', function () {
    // Outside an HTTP request (artisan, tinker, seeders): cli.
    $brand = Brand::query()->create(['name' => 'Marca de consola']);
    expect(auditEntry(AuditAction::Created, $brand)->channel)->toBe(AuditChannel::Cli);

    // Inside a queued job: system, even though the job was dispatched from here.
    dispatch(fn () => Brand::query()->create(['name' => 'Marca de un job']));
    $fromJob = Brand::query()->where('name', 'Marca de un job')->sole();
    expect(auditEntry(AuditAction::Created, $fromJob)->channel)->toBe(AuditChannel::System);
});

test('entries written by one request share its request id', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $entry = AuditLog::query()->where('action', AuditAction::Login)->sole();
    expect($entry->request_id)->toBe($response->headers->get('X-Request-Id'))
        ->and($entry->channel)->toBe(AuditChannel::Web);
});

test('the audit trail cannot be changed or deleted, not even with SQL', function () {
    actingAsRole(SystemRole::Superusuario);
    Space::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['actor_label' => 'otra persona'])))
        ->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))
        ->toThrow(QueryException::class, 'append-only');
});
