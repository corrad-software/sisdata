<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests use RefreshDatabase, which wipes the database. Refuse to run
     * against anything that isn't a dedicated *_test database — the shared dev
     * DB was wiped twice by a run pointed at it (e.g. via a cached config).
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_contains($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against database [{$database}]: it must contain \"_test\".");
        }

        return $app;
    }
}
