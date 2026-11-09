<?php

namespace App\Http\Middleware;

use App\Services\CommunityVerificationService;
use App\Services\PrivacyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCommunityConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $eligibility = app(CommunityVerificationService::class);
        $eligibility->requireEligibleParticipant($user);

        // Students need their current, purpose-specific consent for writes.
        // Approved counselors and administrators participate under their
        // authorized staff account; they do not inherit student consent data.
        if ($user->isStudent() && !app(PrivacyService::class)->canUseCommunity($user->id)) {
            abort(403, 'Community participation is disabled in your privacy settings.');
        }

        return $next($request);
    }
}
