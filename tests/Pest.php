<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
