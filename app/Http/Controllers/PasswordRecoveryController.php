<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountVerificationEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    private const REQUEST_MESSAGE = 'If an eligible account exists, a reset link will be sent only to its verified personal Gmail. This page does not confirm whether an account exists.';

    public function __construct(private readonly AccountVerificationEmailService $emails) {}

    public function requestForm(): View
    {
        return view('auth.password.request');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@minsu\.edu\.ph$/i'],
        ], [
            'email.regex' => 'Enter the MinSU institutional email used for login.',
        ]);
        $email = mb_strtolower(trim($validated['email']));
        // Keep the database's canonical spelling for older records whose
        // institutional email may not have been stored in lowercase.
        $brokerEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->value('email') ?: $email;
        $broker = Password::broker();

        // Laravel owns secure token creation, hashing, expiry, and per-address
        // throttling. The callback diverts delivery to the already-verified
        // personal Gmail and returns the same public response for all outcomes.
        $broker->sendResetLink(['email' => $brokerEmail], function (User $candidate, string $token) use ($broker): string {
            $user = User::query()->whereKey($candidate->getAuthIdentifier())->first();
            if (!$this->eligibleForRecovery($user) || !$this->emails->queuePasswordReset($user, $token)) {
                if ($user) {
                    $broker->deleteToken($user);
                }
            }

            return Password::RESET_LINK_SENT;
        });

        return back()->with('status', self::REQUEST_MESSAGE);
    }

    public function resetForm(): \Illuminate\Http\Response
    {
        return response()
            ->view('auth.password.reset')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@minsu\.edu\.ph$/i'],
            'password' => ['required', 'string', 'max:128', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ], [
            'email.regex' => 'The reset request is invalid or expired. Request a new link to continue.',
            'password.min' => 'Use a password with at least 12 characters.',
            'password.mixed' => 'Use uppercase and lowercase letters in your password.',
            'password.numbers' => 'Include at least one number in your password.',
            'password.symbols' => 'Include at least one symbol in your password.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $emailInput = mb_strtolower(trim($validated['email']));
        $email = User::query()->whereRaw('LOWER(email) = ?', [$emailInput])->value('email') ?: $emailInput;
        $broker = Password::broker();
        $changed = false;
        $status = DB::transaction(function () use ($broker, $email, $validated, &$changed): string {
            // Lock the one-token-per-email row through validation, password
            // update, and broker deletion. Concurrent redemption then sees the
            // consumed token rather than reusing it.
            $tokenTable = (string) config('auth.passwords.users.table', 'password_reset_tokens');
            DB::table($tokenTable)->where('email', $email)->lockForUpdate()->first();

            return $broker->reset(
                ['email' => $email, 'token' => $validated['token'], 'password' => $validated['password']],
                function (User $candidate, string $password) use (&$changed): void {
                    $user = User::query()->whereKey($candidate->getAuthIdentifier())->lockForUpdate()->first();
                    if (!$this->eligibleForRecovery($user)) {
                        return;
                    }

                    // Update only authentication secrets. Role, approval state,
                    // account activation, school identity, and Gmail verification
                    // are intentionally untouched.
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();
                    $this->invalidateDatabaseSessions($user);
                    $changed = true;
                },
            );
        });

        if ($status === Password::PASSWORD_RESET && $changed) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your password has been updated. Sign in with your MinSU institutional email and new password. Your account approval status has not changed.');
        }

        return back()->withErrors(['email' => 'The reset link is invalid, expired, already used, or no longer eligible. Request a new link if needed.']);
    }

    private function eligibleForRecovery(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && (bool) $user->notification_email_enabled
            && $user->notification_email_verified_at !== null
            && filter_var((string) $user->notification_email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i', (string) $user->notification_email) === 1
            && preg_match('/^[^@\s]+@minsu\.edu\.ph$/i', (string) $user->getEmailForPasswordReset()) === 1;
    }

    private function invalidateDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');
        if (Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $user->getKey())->delete();
        }
    }
}
