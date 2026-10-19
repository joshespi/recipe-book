<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The containers pass the real database settings as environment variables. phpunit.xml
        // forces SQLite, but if that ever stops working RefreshDatabase would wipe the real data.
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Refusing to run tests: the test database is not in-memory SQLite.');
        }
    }
}
