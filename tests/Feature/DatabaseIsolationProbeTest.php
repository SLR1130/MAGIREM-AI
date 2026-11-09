<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationProbeTest extends TestCase
{
    public function test_phpunit_uses_testing_environment_and_in_memory_sqlite(): void
    {
        $connection = DB::connection();

        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
    }
}
