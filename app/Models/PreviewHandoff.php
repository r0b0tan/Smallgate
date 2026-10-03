<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time token that carries a signed-in portal user over to a preview
 * host (ADR 0003). Valid for seconds, redeemed once, stored only as a hash.
 *
 * Nothing here is mass assignable: every column is set explicitly by the code
 * that issues or redeems the handoff.
 */
#[Guarded(['*'])]
class PreviewHandoff extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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
