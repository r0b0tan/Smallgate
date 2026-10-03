<?php

namespace App\Models;

use App\Enums\PreviewUploadStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A draft uploaded as a ZIP and unpacked into a folder of its own (ADR 0004).
 * The folder's path follows from the ids; it is never stored, so no row can
 * point the job that deletes old uploads anywhere else.
 *
 * Nothing here is mass assignable: every column is set explicitly by the
 * upload request or the job that unpacks it.
 */
#[Guarded(['*'])]
class PreviewUpload extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => PreviewUploadStatus::class,
            'size_bytes' => 'integer',
            'file_count' => 'integer',
            'finished_at' => 'datetime',
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
