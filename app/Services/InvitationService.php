<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates and redeems invitations.
 *
 * The plaintext token is generated here, handed straight to the mail and then
 * dropped. Only its SHA-256 hash is persisted, so neither the database nor a
 * database backup contains a usable invitation link.
 */
class InvitationService
{
    /**
     * Invite a user to a customer and send the mail.
     *
     * @return array{invitation: Invitation, token: string} the token is returned
     *                                                      for tests only
     */
    public function invite(Customer $customer, string $name, string $email, User $invitedBy): array
    {
        $token = $this->generateToken();

        $invitation = DB::transaction(function () use ($customer, $name, $email, $invitedBy, $token) {
            // A fresh invitation supersedes any pending one for the same
            // address, so an old link cannot be used after a re-invite.
            $customer->invitations()
                ->where('email', mb_strtolower(trim($email)))
                ->whereNull('accepted_at')
                ->delete();

            $invitation = new Invitation;
            $invitation->customer_id = $customer->id;
            $invitation->name = $name;
            $invitation->email = mb_strtolower(trim($email));
            $invitation->role = UserRole::Customer;
            $invitation->token_hash = Invitation::hashToken($token);
            $invitation->expires_at = $this->expiry();
            $invitation->invited_by_user_id = $invitedBy->id;
            $invitation->save();

            return $invitation;
        });

        $invitation->notify(new InvitationNotification($token));

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * Issue a new token for an existing, unaccepted invitation and mail it.
     *
     * The previous token stops working immediately, because the stored hash is
     * replaced.
     */
    public function resend(Invitation $invitation): string
    {
        $token = $this->generateToken();

        $invitation->token_hash = Invitation::hashToken($token);
        $invitation->expires_at = $this->expiry();
        $invitation->save();

        $invitation->notify(new InvitationNotification($token));

        return $token;
    }

    /**
     * Look up a redeemable invitation by its plaintext token.
     *
     * Returns null for unknown, expired, already used and blocked invitations
     * alike -- the caller shows one generic message for all of them.
     */
    public function findRedeemable(#[\SensitiveParameter] string $token): ?Invitation
    {
        $invitation = Invitation::query()
            ->with('customer')
            ->where('token_hash', Invitation::hashToken($token))
            ->first();

        if ($invitation === null || ! $invitation->isRedeemable()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Redeem an invitation and create the user account.
     *
     * The update is guarded by a conditional UPDATE inside a transaction: two
     * concurrent redemptions of the same token cannot both create a user,
     * because only one of them sees a row still marked unaccepted.
     */
    public function redeem(Invitation $invitation, string $name, #[\SensitiveParameter] string $password): ?User
    {
        return DB::transaction(function () use ($invitation, $name, $password): ?User {
            $claimed = Invitation::query()
                ->whereKey($invitation->getKey())
                ->whereNull('accepted_at')
                ->where('expires_at', '>', Carbon::now())
                ->lockForUpdate()
                ->first();

            if ($claimed === null) {
                return null;
            }

            $user = new User;
            $user->name = $name;
            $user->email = $claimed->email;
            $user->password = $password; // hashed by the model cast
            $user->role = $claimed->role;
            $user->customer_id = $claimed->customer_id;
            $user->is_active = true;
            // Accepting the invitation proves control of the mailbox.
            $user->email_verified_at = Carbon::now();
            $user->save();

            $claimed->accepted_at = Carbon::now();
            $claimed->accepted_user_id = $user->id;
            $claimed->save();

            return $user;
        });
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes((int) config('smallgate.invitations.token_bytes', 32)));
    }

    private function expiry(): Carbon
    {
        return Carbon::now()->addHours((int) config('smallgate.invitations.ttl_hours', 72));
    }
}
