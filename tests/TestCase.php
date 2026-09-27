<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? null) !== 'medgrid_test') {
            throw new RuntimeException('Tests may only run against the medgrid_test database.');
        }
        parent::setUp();
        if (config('database.connections.pgsql.database') !== 'medgrid_test') {
            throw new RuntimeException('Resolved test database is not medgrid_test.');
        }
    }
}
