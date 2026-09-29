<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test starts with the reference data every environment has: catalogs, report types, roles and
     * permissions (DatabaseSeeder; the demo data only loads in `local`). RefreshDatabase wraps each test in a
     * transaction, so this runs once per test run.
     */
    protected bool $seed = true;
}
