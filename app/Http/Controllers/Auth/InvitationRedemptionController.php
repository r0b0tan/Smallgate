<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Redeeming an invitation is the only way an account ever comes into existence
 * besides an administrator creating one. It is explicitly *not* a registration
 * form: the email, the customer and the role all come from the invitation, and
 * the visitor only chooses a name and a password.
 */
class InvitationRedemptionController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function create(string $token): View
    {
        $invitation = $this->invitations->findRedeemable($token);

        if ($invitation === null) {
            return view('auth.invitation-invalid');
        }

        return view('auth.invitation', [
            'token' => $token,
            'invitation' => $invitation,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findRedeemable($token);

        if ($invitation === null) {
            // Expired, already used or unknown -- all the same answer.
            return redirect()->route('invitations.show', ['token' => $token]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $this->invitations->redeem(
            $invitation,
            $validated['name'],
            $validated['password'],
        );

        if ($user === null) {
            // Lost the race against a concurrent redemption of the same token.
            return redirect()->route('invitations.show', ['token' => $token]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('status', 'Willkommen! Ihr Zugang ist eingerichtet.');
    }
}
