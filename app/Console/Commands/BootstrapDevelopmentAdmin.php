<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BootstrapDevelopmentAdmin extends Command
{
    protected $signature = 'magirem:bootstrap-admin';

    protected $description = 'Create the one-time administrator for an empty local MAGIREM development database.';

    public function handle(): int
    {
        $connection = DB::connection();
        $isTestHarness = app()->environment('testing') && $connection->getDriverName() === 'sqlite';
        $isTargetDevelopmentDatabase = app()->environment('local')
            && $connection->getDriverName() === 'mysql'
            && $connection->getDatabaseName() === 'emotional_aware_db';

        if (!$isTestHarness && !$isTargetDevelopmentDatabase) {
            $this->error('This bootstrap is limited to local emotional_aware_db or the isolated SQLite test harness.');
            return self::FAILURE;
        }
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'is_active')) {
            $this->error('The users table is missing required schema.');
            return self::FAILURE;
        }
        if (User::query()->exists()) {
            $this->error('Bootstrap refused: users table is not empty.');
            return self::FAILURE;
        }
        if (!$this->confirm('Create exactly one active administrator on this local database?')) {
            $this->warn('No administrator was created.');
            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $email = mb_strtolower(trim((string) $this->ask('Administrator email')));
        $password = (string) $this->secret('Administrator password (input hidden)');
        $passwordConfirmation = (string) $this->secret('Confirm administrator password (input hidden)');

        $validation = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:16'],
        ]);
        if ($validation->fails() || $password === '' || !hash_equals($password, $passwordConfirmation)) {
            $this->error('Invalid administrator details or password confirmation. No account was created.');
            return self::FAILURE;
        }

        $lockAcquired = false;
        try {
            if ($connection->getDriverName() === 'mysql') {
                $lockName = 'magirem_admin_bootstrap_' . $connection->getDatabaseName();
                $lockAcquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName])->acquired ?? 0) === 1;
                if (!$lockAcquired) {
                    $this->error('Could not acquire the one-time bootstrap lock. No account was created.');
                    return self::FAILURE;
                }
            }

            $userId = DB::transaction(function () use ($name, $email, $password): int {
                if (User::query()->lockForUpdate()->exists()) {
                    throw new \DomainException('Bootstrap refused because a user was created concurrently.');
                }

                $user = new User();
                $user->name = $name;
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->role = 'admin';
                $user->is_active = true;
                $user->remember_token = null;
                $user->save();

                $stored = User::query()->find($user->id);
                if (!$stored || $stored->role !== 'admin' || !$stored->is_active || !Hash::check($password, $stored->password)) {
                    throw new \RuntimeException('Administrator bootstrap verification failed.');
                }

                return (int) $stored->id;
            }, 1);

            $this->info('One local administrator was created and verified.');
            $this->line('Account ID: ' . $userId);
            $this->line('The password was neither displayed nor logged.');
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Bootstrap failed safely. No password was logged. Check the database and try only while users is empty.');
            return self::FAILURE;
        } finally {
            if ($lockAcquired && $connection->getDriverName() === 'mysql') {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', ['magirem_admin_bootstrap_' . $connection->getDatabaseName()]);
                } catch (Throwable) {
                    // A released connection also releases its named lock.
                }
            }
        }
    }
}
