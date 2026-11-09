<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Seed non-account development reference data only. */
    public function run(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'emotional_aware_db') {
            throw new \LogicException('Demo seeding is disabled for emotional_aware_db. Use an isolated development or test database.');
        }

        $this->call(CopingStrategiesSeeder::class);
    }
}
