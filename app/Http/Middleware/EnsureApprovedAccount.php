<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->is_active) {
            abort(403);
        }

        if (!$user->isApproved()) {
            return redirect()->route('account.status');
        }

        if ($user->requiresOwnerOnboarding()) {
            return redirect()->route('account.status');
        }

        return $next($request);
    }
}
