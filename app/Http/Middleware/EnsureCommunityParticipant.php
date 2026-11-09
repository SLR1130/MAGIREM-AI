<?php

namespace App\Http\Middleware;

use App\Services\CommunityVerificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows only active, approved student/staff accounts into the shared Circle. */
class EnsureCommunityParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            app(CommunityVerificationService::class)->isEligibleParticipant($request->user()),
            403,
            'Community Circle is available to active, approved MinSU community accounts.'
        );

        return $next($request);
    }
}
