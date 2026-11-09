<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Keep the running local application's cached config untouched while
        // forcing PHPUnit to bootstrap from .env.testing and SQLite.
        $testingCache = dirname(__DIR__) . '/storage/framework/testing-config.php';
        if (is_file($testingCache)) {
            unlink($testingCache);
        }
        putenv('APP_CONFIG_CACHE=' . $testingCache);
        $_ENV['APP_CONFIG_CACHE'] = $testingCache;
        $_SERVER['APP_CONFIG_CACHE'] = $testingCache;

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($database === 'emotional_aware_db') {
            throw new LogicException('Tests may not run against emotional_aware_db; use the isolated SQLite test database.');
        }
    }
}
