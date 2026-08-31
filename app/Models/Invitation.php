<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Policies\InvitationPolicy;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * An invitation to create a customer user account.
 *
 * The plaintext token is never stored: only its SHA-256 hash lands in the
 * database, so a database leak does not hand out working invitation links.
 * Creation and redemption live in App\Services\InvitationService.
 */
#[Guarded(['*'])]
#[UsePolicy(InvitationPolicy::class)]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Hash a plaintext token the same way it is stored.
     *
     * SHA-256 is the right tool here (unlike for passwords): the token is 256
     * bits of CSPRNG output, so it is not brute forceable and needs no slow
     * hash -- but it must still be looked up in constant time by exact match.
     */
    /**
     * Where the invitation mail is sent. The invitation itself is the
     * notifiable -- there is no user account yet.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public static function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * An invitation is only redeemable while it is unused, unexpired and its
     * customer is still active.
     */
    public function isRedeemable(): bool
    {
        if ($this->isAccepted() || $this->isExpired()) {
            return false;
        }

        return $this->customer === null || $this->customer->is_active;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isAccepted() => 'Angenommen',
            $this->isExpired() => 'Abgelaufen',
            default => 'Offen',
        };
    }

    /**
     * @param  Builder<Invitation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', Carbon::now());
    }
}
