<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RevokesSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Own profile and password. Available to both roles; a user may only ever edit
 * themselves, because the record always comes from $request->user().
 */
class ProfileController extends Controller
{
    use RevokesSessions;

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $emailChanged = mb_strtolower($validated['email']) !== $user->email;

        // Only name and email -- role, customer and is_active are not fillable
        // and are not part of this form.
        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Ihr Profil wurde gespeichert.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            // Knowing the current password stops a hijacked session from
            // locking the rightful owner out.
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'], // hashed by the model cast
        ])->save();

        // Every other session of this account is revoked; the current one stays
        // signed in and is re-bound to the new hash.
        $this->revokeSessions($user, $request->session()->getId());

        return back()->with('status', 'Ihr Passwort wurde geändert. Andere Sitzungen wurden abgemeldet.');
    }
}
