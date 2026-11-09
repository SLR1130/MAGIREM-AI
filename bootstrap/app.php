<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\RejectInactiveAccount::class]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'secure.auth' => \App\Http\Middleware\SecureAuth::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'community.consent' => \App\Http\Middleware\EnsureCommunityConsent::class,
            'community.participant' => \App\Http\Middleware\EnsureCommunityParticipant::class,
            'account.approved' => \App\Http\Middleware\EnsureApprovedAccount::class,
            'account.verification.reviewer' => \App\Http\Middleware\EnsureAccountVerificationReviewer::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Daily aggregation at 23:59
        $schedule->command('emotion:aggregate-daily')->dailyAt('23:59');
        
        // Pattern detection at 00:15 (after aggregation)
        $schedule->command('emotion:detect-patterns')->dailyAt('00:15');

        $schedule->command('account-verification:purge-expired-documents')
            ->dailyAt('02:15')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
