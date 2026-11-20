<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * Feature tests run against PostgreSQL (`seti_testing`, phpunit.xml) inside a transaction per test
 * (RefreshDatabase). Never use DatabaseTruncation: the append-only tables refuse it by design.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** A verified, active user holding the given system roles. */
function userWithRole(SystemRole ...$roles): User
{
    return User::factory()->withRole(...$roles)->create();
}

/** Sign in as a new user holding the given system roles and return them. */
function actingAsRole(SystemRole ...$roles): User
{
    $user = userWithRole(...$roles);
    test()->actingAs($user);

    return $user;
}

/**
 * Call $method of the $component that $page (a rendered page's HTML) contains, through Livewire's real update
 * endpoint, as the browser does. Unlike Livewire::test, this runs the page route's middleware again
 * (SubstituteBindings, can:…), which is where a click can fail after the data changed in another tab.
 *
 * @param  list<mixed>  $params
 */
function livewireCall(string $page, string $component, string $method, array $params = []): TestResponse
{
    preg_match_all('/wire:snapshot="([^"]*)"/', $page, $matches);

    $snapshot = collect($matches[1])
        ->map(fn (string $encoded) => html_entity_decode($encoded, ENT_QUOTES))
        ->first(fn (string $json) => (json_decode($json, true)['memo']['name'] ?? null) === $component);

    expect($snapshot)->not->toBeNull("The page does not render the {$component} component.");

    return test()->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
        ]],
    ]);
}
