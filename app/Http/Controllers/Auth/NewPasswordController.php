<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RevokesSessions;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    use RevokesSessions;

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => mb_strtolower((string) $request->string('email')),
                'password' => (string) $request->string('password'),
                'password_confirmation' => (string) $request->string('password_confirmation'),
                'token' => (string) $request->string('token'),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // hashed by the model cast
                ])->save();

                // A reset is a recovery action: every existing session and
                // remember-me cookie of this account must die, including the
                // attacker's. revokeSessions() clears the remember token too.
                $this->revokeSessions($user);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Ihr Passwort wurde geändert. Bitte melden Sie sich neu an.');
        }

        // One generic message for invalid token, unknown address and throttling
        // alike, so the form reveals nothing about which accounts exist.
        return back()->withInput($request->only('email'))->withErrors([
            'email' => 'Der Link zum Zurücksetzen ist ungültig oder abgelaufen.',
        ]);
    }
}
