<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return app(RegistrationController::class)->show(request());
    }

    public function register(Request $request)
    {
        return app(RegistrationController::class)->submit($request);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Active status is part of the credential query so disabled users
        // cannot start a new session, including with a valid password.
        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if (!$user->isApproved() || $user->requiresOwnerOnboarding()) {
                return redirect()->route('account.status');
            }

            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            } elseif ($user->isCounselor()) {
                return redirect()->route('counselor.dashboard');
            } else {
                return redirect()->route('home');
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        
        // Clear remember token from database
        if ($user) {
            $user->remember_token = null;
            $user->save();
        }
        
        // Logout user
        Auth::logout();
        
        // Invalidate session
        $request->session()->invalidate();
        
        // Regenerate CSRF token
        $request->session()->regenerateToken();
        
        // Clear all session data
        $request->session()->flush();
        
        // Delete remember me cookie
        cookie()->queue(cookie()->forget('remember_web'));

        return redirect()->route('login')->with('success', 'You have been logged out successfully.');
    }
}
