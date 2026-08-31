<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always answer with the same confirmation.
     *
     * Whether the address is unknown, belongs to a blocked account or was
     * simply throttled must not be distinguishable from the outside -- the
     * form would otherwise be a free account enumeration oracle.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        Password::sendResetLink([
            'email' => mb_strtolower((string) $request->string('email')),
        ]);

        return back()->with('status', 'Falls ein Zugang zu dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen verschickt.');
    }
}
