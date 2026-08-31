<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteUserRequest;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Managing the user accounts of one customer: invite, re-invite, block.
 *
 * There is no "create user with a password" action on purpose -- accounts are
 * only ever created by the invited person themselves, so an administrator never
 * knows a customer's password.
 */
class CustomerUserController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(Customer $customer): View
    {
        $this->authorize('manageUsers', $customer);

        return view('admin.customers.users', [
            'customer' => $customer,
            'users' => $customer->users()->orderBy('name')->get(),
            'invitations' => $customer->invitations()->latest()->get(),
        ]);
    }

    public function invite(InviteUserRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('manageUsers', $customer);

        if (! $customer->is_active) {
            return back()->with('error', 'Für einen deaktivierten Kunden können keine Einladungen versendet werden.');
        }

        $this->invitations->invite(
            $customer,
            $request->validated('name'),
            $request->validated('email'),
            $request->user(),
        );

        return back()->with('status', 'Einladung wurde versendet.');
    }

    public function resend(Customer $customer, Invitation $invitation): RedirectResponse
    {
        $this->authorize('manageUsers', $customer);
        abort_unless($invitation->customer_id === $customer->id, 404);
        $this->authorize('resend', $invitation);

        // A new token is issued, which immediately invalidates the old link.
        $this->invitations->resend($invitation);

        return back()->with('status', 'Einladung wurde erneut versendet.');
    }

    public function revoke(Customer $customer, Invitation $invitation): RedirectResponse
    {
        $this->authorize('manageUsers', $customer);
        abort_unless($invitation->customer_id === $customer->id, 404);
        $this->authorize('revoke', $invitation);

        $invitation->delete();

        return back()->with('status', 'Einladung wurde zurückgezogen.');
    }

    /**
     * Block or unblock a customer user. EnsureAccountIsActive terminates any
     * running session of a blocked account on its next request.
     */
    public function toggleBlock(Customer $customer, User $user): RedirectResponse
    {
        $this->authorize('manageUsers', $customer);
        abort_unless($user->customer_id === $customer->id, 404);
        $this->authorize('block', $user);

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('status', $user->is_active
            ? 'Zugang wurde entsperrt.'
            : 'Zugang wurde gesperrt.');
    }
}
