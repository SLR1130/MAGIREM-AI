<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SecureAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        // Verify session integrity
        $user = Auth::user();
        
        // Check if user still exists in database
        if (!$user || !$user->exists) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Session expired. Please login again.');
        }

        // Re-check the persisted flag on every protected request so an
        // already-authenticated session is revoked immediately after disable.
        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'This account is not active. Please contact support.');
        }

        // Regenerate session ID periodically for security
        if (!$request->session()->has('last_regenerated')) {
            $request->session()->put('last_regenerated', time());
        } else {
            $lastRegenerated = $request->session()->get('last_regenerated');
            // Regenerate every 30 minutes
            if (time() - $lastRegenerated > 1800) {
                $request->session()->regenerate();
                $request->session()->put('last_regenerated', time());
            }
        }

        return $next($request);
    }
}
