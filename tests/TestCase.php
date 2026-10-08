<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests rebuild the database from scratch. They must only ever do that to
     * the throwaway in-memory one: if anything (Docker, a shell variable) points
     * them at a real database, stop before a single table is touched.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Refusing to run tests against the real database [{$connection}: {$database}]. They only run on in-memory SQLite (see phpunit.xml).");
        }

        return parent::setUpTraits();
    }
}
