<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     *
     * Logic:
     *  - If user exists AND is approved → log in directly
     *  - If user exists AND is pending/rejected → show status message
     *  - If user does NOT exist → redirect to register with Google data pre-filled in session
     */
    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('[GoogleAuth] OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'Google sign-in was cancelled or failed. Please try again.');
        }

        $googleEmail   = strtolower(trim($googleUser->getEmail() ?? ''));
        $googleId      = $googleUser->getId();
        $googleName    = $googleUser->getName() ?? '';
        $googleAvatar  = $googleUser->getAvatar() ?? null;

        if (!$googleEmail) {
            return redirect()->route('login')
                ->with('error', 'Could not get your email from Google. Please try again.');
        }

        // ── 1. Try to find existing user by Google ID or by gmail/minsu email ──
        $user = User::where('google_id', $googleId)->first()
            ?? User::where('email', $googleEmail)->first()
            ?? User::where('notification_email', $googleEmail)->first();

        // ── 2. If user found ──
        if ($user) {
            // Update google_id if not set yet
            if (!$user->google_id) {
                $user->google_id = $googleId;
                if ($googleAvatar && !$user->profile_picture) {
                    $user->google_avatar = $googleAvatar;
                }
                $user->save();
            }

            // Always authenticate the user session first
            Auth::login($user, true);
            $request->session()->regenerate();

            // If account is still pending verification or onboarding
            if (!$user->isApproved() || $user->requiresOwnerOnboarding()) {
                return redirect()->route('account.status');
            }

            // Redirect approved user directly to their respective workspace/home
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            } elseif ($user->isCounselor()) {
                return redirect()->route('counselor.dashboard');
            } else {
                return redirect()->route('home');
            }
        }

        // ── 3. User NOT found → store Google data in session, redirect to register ──
        $request->session()->put('google_oauth', [
            'id'     => $googleId,
            'email'  => $googleEmail,
            'name'   => $googleName,
            'avatar' => $googleAvatar,
        ]);

        return redirect()->route('register')
            ->with('google_prefill', true)
            ->with('info', 'We found your Google account (' . $googleEmail . '). Complete the registration form below to apply for a MAGIREM AI student account. Your Gmail will be automatically verified.');
    }

    /**
     * Redirect user to their dashboard based on role
     */
    protected function redirectAfterLogin(User $user): \Illuminate\Http\RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->role === 'counselor') {
            return redirect()->route('counselor.dashboard');
        }
        return redirect()->route('student.wellbeing');
    }
}
