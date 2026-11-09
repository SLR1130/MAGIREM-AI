<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectInactiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'This account is not active.'], 401);
            }

            return redirect()->route('login')->with('error', 'This account is not active. Please contact support.');
        }

        return $next($request);
    }
}
