<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                if (!$user || !$user->is_active) {
                    Auth::guard($guard)->logout();
                    if ($request->hasSession()) {
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    }
                    continue;
                }

                if (!$user->isApproved() || $user->requiresOwnerOnboarding()) {
                    return redirect()->route('account.status');
                }
                
                // Redirect authenticated users to their dashboard
                if ($user->isAdmin()) {
                    return redirect()->route('admin.dashboard');
                } elseif ($user->isCounselor()) {
                    return redirect()->route('counselor.dashboard');
                } else {
                    return redirect()->route('student.insights');
                }
            }
        }

        return $next($request);
    }
}
