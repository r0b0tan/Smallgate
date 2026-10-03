<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed-in visit to exactly one preview on its own host (ADR 0003). The
 * cookie holds the token, the database only its hash. Being valid is not
 * enough on its own: every request re-checks PreviewPolicy::open.
 *
 * Nothing here is mass assignable: every column is set explicitly by the code
 * that redeems a handoff.
 */
#[Guarded(['*'])]
class PreviewSession extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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
}
