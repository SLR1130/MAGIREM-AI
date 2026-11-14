<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RestoreUserAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Restore old user accounts
        
        // leialido10@gmail.com
        User::firstOrCreate(
            ['email' => 'leialido10@gmail.com'],
            [
                'name' => 'Leialido User',
                'password' => bcrypt('admin123'),
                'role' => 'student',
                'is_active' => true,
            ]
        );

        // rain@gmail.com
        User::firstOrCreate(
            ['email' => 'rain@gmail.com'],
            [
                'name' => 'Rain User',
                'password' => bcrypt('123456789'),
                'role' => 'student',
                'is_active' => true,
            ]
        );

        echo "\n✅ Restored user accounts:\n";
        echo "  - leialido10@gmail.com (password: admin123)\n";
        echo "  - rain@gmail.com (password: 123456789)\n\n";
    }
}
