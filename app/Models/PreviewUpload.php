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
     * The folder this upload is unpacked into, or null while the project has
     * no folder.
     */
    public function directory(): ?string
    {
        $parent = $this->preview?->uploadsDirectory();

        return $parent === null ? null : $parent.'/'.$this->id;
    }

    /**
     * Where it is unpacked first. Hidden, so the preview host never serves a
     * half-written draft; renamed into directory() once complete.
     */
    public function stagingDirectory(): ?string
    {
        $parent = $this->preview?->uploadsDirectory();

        return $parent === null ? null : self::stagingDirectoryIn($parent, $this->id);
    }

    public static function stagingDirectoryIn(string $parent, string $uploadId): string
    {
        return $parent.'/.eingang-'.$uploadId;
    }

    /**
     * The uploaded ZIP on config('previews.uploads.disk'), until it is
     * unpacked or rejected.
     */
    public function archivePath(): string
    {
        return self::archivePathFor($this->id);
    }

    public static function archivePathFor(string $uploadId): string
    {
        return config('previews.uploads.directory').'/'.$uploadId.'.zip';
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
