<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Note the deliberately narrow fillable list: `role`, `customer_id` and
 * `is_active` are NOT mass assignable. They decide what a user may see and do,
 * so they are only ever set explicitly by administrator-only code paths.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relations

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_user_id');
    }

    // ------------------------------------------------------------------- roles

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCustomerUser(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /**
     * The single place that answers "which customers may this user see?".
     *
     * Everything that scopes data by customer goes through here, so widening
     * the MVP's one-customer-per-user rule to a many-to-many relation later
     * means changing this method and the schema -- not every query.
     *
     * @return list<string>
     */
    public function accessibleCustomerIds(): array
    {
        return $this->customer_id === null ? [] : [$this->customer_id];
    }

    /**
     * Whether the account may sign in and use the portal at all. A deactivated
     * user is locked out, and so is a user whose customer has been deactivated.
     */
    public function canAccessPortal(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        // Queried rather than read off the loaded relation on purpose: this is
        // called on every request by EnsureAccountIsActive, and it must reflect
        // the customer's *current* state. A relation cached earlier in the same
        // request would keep a just-deactivated customer alive.
        return $this->customer()->where('is_active', true)->exists();
    }

    // ------------------------------------------------------------------ scopes

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function role(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }

    // ------------------------------------------------------------------- misc

    /**
     * Emails are stored lowercase; a database CHECK constraint enforces it too.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    /**
     * Send the password reset notification.
     *
     * Deactivated accounts are silently skipped: the caller must not be able to
     * tell the difference between "no such user" and "locked account".
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        if (! $this->canAccessPortal()) {
            return;
        }

        Notification::send($this, new ResetPasswordNotification($token));
    }
}
