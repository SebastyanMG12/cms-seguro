<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Debe ejecutarse ANTES de que la aplicación arranque y migre.
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');

        if (! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                'Las pruebas solo pueden ejecutarse contra una base cuyo nombre termine en _test.'
            );
        }

        parent::setUp();
    }
}
