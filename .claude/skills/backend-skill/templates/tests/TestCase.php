<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // RefreshDatabase rebuilds the isolated test database schema from the
    // migrations and runs DatabaseSeeder before every test, so roles,
    // permissions, and reference data exist in each test. The database the
    // suite uses is defined by phpunit.xml (see references/testing.md §4.1).
    public $seed = true;
}
