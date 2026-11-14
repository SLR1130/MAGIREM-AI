<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'emotional_aware_db') {
            throw new \LogicException('Demo seeding is disabled for emotional_aware_db. Use an isolated development or test database.');
        }

        // Seed coping strategies
        $this->call(CopingStrategiesSeeder::class);

        // Create Admin Account
        User::create([
            'name' => 'Admin Master',
            'email' => 'admin@mindcare.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
        ]);

        // Create Counselor Accounts
        User::create([
            'name' => 'Dr. Maria Santos',
            'email' => 'maria.santos@mindcare.com',
            'password' => bcrypt('counselor123'),
            'role' => 'counselor',
        ]);

        User::create([
            'name' => 'Dr. John Reyes',
            'email' => 'john.reyes@mindcare.com',
            'password' => bcrypt('counselor123'),
            'role' => 'counselor',
        ]);

        // Create Student Accounts
        User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.delacruz@student.msu.edu',
            'password' => bcrypt('student123'),
            'role' => 'student',
            'student_id' => '2024-00001',
            'year_level' => '3rd Year',
            'program' => 'BSIT',
        ]);

        User::create([
            'name' => 'Maria Clara',
            'email' => 'maria.clara@student.msu.edu',
            'password' => bcrypt('student123'),
            'role' => 'student',
            'student_id' => '2024-00002',
            'year_level' => '2nd Year',
            'program' => 'BSCS',
        ]);

        User::create([
            'name' => 'Pedro Santos',
            'email' => 'pedro.santos@student.msu.edu',
            'password' => bcrypt('student123'),
            'role' => 'student',
            'student_id' => '2024-00003',
            'year_level' => '4th Year',
            'program' => 'BSIT',
        ]);
    }
}
