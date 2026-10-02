<?php

namespace App\Models;

use App\Enums\FeedbackDecision;
use App\Policies\PreviewFeedbackPolicy;
use Database\Factories\PreviewFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's answer to one version of a preview.
 *
 * `preview_id`, `user_id` and `preview_version` are not mass assignable: they
 * are taken from the authorised preview and the signed-in user, never from the
 * request.
 */
#[Fillable(['decision', 'comment'])]
#[UsePolicy(PreviewFeedbackPolicy::class)]
class PreviewFeedback extends Model
{
    /** @use HasFactory<PreviewFeedbackFactory> */
    use HasFactory, HasUlids;

    protected $table = 'preview_feedback';

    protected function casts(): array
    {
        return [
            'decision' => FeedbackDecision::class,
            'preview_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Preview, $this>
     */
    public function preview(): BelongsTo
    {
        return $this->belongsTo(Preview::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setCommentAttribute(?string $value): void
    {
        $value = $value === null ? null : trim($value);

        $this->attributes['comment'] = $value === '' ? null : $value;
    }
}
