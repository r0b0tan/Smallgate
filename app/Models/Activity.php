<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Policies\ActivityPolicy;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * One line of the activity log in the administration: who did what, when.
 *
 * Personal data stays where it already is. An entry only points at the user
 * and the subject; it never copies a name, an email address, a comment or an
 * IP address. Entries older than the retention period are deleted.
 */
#[Guarded(['*'])]
#[UsePolicy(ActivityPolicy::class)]
class Activity extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Record an action. The actor defaults to the signed-in user; the customer
     * is taken from the subject, or from the actor for a customer user's own
     * account actions.
     *
     * @param  array<string, scalar>  $properties
     */
    public static function record(
        ActivityAction $action,
        ?Model $subject = null,
        ?User $actor = null,
        array $properties = [],
    ): self {
        $actor ??= Auth::user();

        $activity = new self;
        $activity->action = $action;
        $activity->actor_id = $actor?->id;
        $activity->customer_id = self::customerIdOf($subject) ?? $actor?->customer_id;
        $activity->subject()->associate($subject);
        $activity->subject_label = self::labelOf($subject);
        $activity->properties = $properties ?: null;
        $activity->save();

        // Expired entries go on every write, so no scheduler is needed. With a
        // handful of customers that is one cheap indexed delete per action.
        self::pruneExpired();

        return $activity;
    }

    public static function pruneExpired(): int
    {
        return self::query()->where('created_at', '<', self::retentionStart())->delete();
    }

    public static function retentionStart(): Carbon
    {
        return Carbon::now()->subDays((int) config('smallgate.activity.retention_days', 90));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * What the entry is about, as shown in the log: the current name if the
     * subject still exists, the name it had otherwise.
     */
    public function subjectName(): ?string
    {
        return match (true) {
            $this->subject instanceof Preview => $this->subject->name.' · '.$this->subject->project?->name,
            $this->subject instanceof User, $this->subject instanceof Invitation => $this->subject->name,
            $this->subject instanceof Model => $this->subject->getAttribute('name'),
            default => $this->subject_label,
        };
    }

    private static function customerIdOf(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Customer => $subject->id,
            $subject instanceof Project, $subject instanceof User, $subject instanceof Invitation => $subject->customer_id,
            $subject instanceof Preview => $subject->project?->customer_id,
            default => null,
        };
    }

    /**
     * Names of things, never of people: users and invitations are left out.
     */
    private static function labelOf(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Customer, $subject instanceof Project, $subject instanceof Preview => $subject->name,
            default => null,
        };
    }
}
