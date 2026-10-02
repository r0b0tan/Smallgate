<?php

namespace App\Models;

use App\Enums\FeedbackDecision;
use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Enums\ThumbnailStatus;
use App\Policies\PreviewPolicy;
use App\Services\Previews\PreviewTargetGuard;
use Carbon\CarbonInterface;
use Database\Factories\PreviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * `project_id`, `status` and `provisioned_at` are not mass assignable: the
 * first decides who may see the preview, the second whether the customer is
 * offered it at all, and the third is owned by the provisioner. Status changes
 * go through the provision and disable actions, never through a form field.
 *
 * `version` and the `thumbnail_*` columns are not mass assignable either: the
 * version only moves on a successful provisioning, the thumbnail state only
 * through App\Jobs\GeneratePreviewThumbnail.
 */
#[Fillable(['name', 'slug', 'hostname', 'target_type', 'target'])]
#[UsePolicy(PreviewPolicy::class)]
class Preview extends Model
{
    /** @use HasFactory<PreviewFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        // Thumbnails show customer work; they go with the preview.
        static::deleted(function (Preview $preview) {
            Storage::disk(config('previews.thumbnails.disk'))
                ->deleteDirectory(config('previews.thumbnails.directory').'/'.$preview->id);
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PreviewStatus::class,
            'target_type' => PreviewTargetType::class,
            'provisioned_at' => 'datetime',
            'version' => 'integer',
            'thumbnail_status' => ThumbnailStatus::class,
            'thumbnail_version' => 'integer',
            'thumbnail_generated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PreviewFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(PreviewFeedback::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Restrict a query to previews whose project the user may see.
     *
     * @param  Builder<Preview>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereHas('project', fn (Builder $project) => $project->visibleTo($user));
    }

    /**
     * The URL the customer is offered. Only ever produced for an available
     * preview with a valid address.
     */
    public function url(): ?string
    {
        return $this->status->isVisitable() ? $this->openUrl() : null;
    }

    /**
     * Where the preview is opened, regardless of status: an upstream preview
     * is opened at its own URL, path included (e.g. a page below a shared
     * customer host); a static one at its subdomain. Administrators use this
     * to check a preview before releasing it; customers only get url().
     */
    public function openUrl(): ?string
    {
        // A switched-off type has nothing behind its address.
        if ($this->target_type?->isEnabled() !== true) {
            return null;
        }

        return $this->target_type === PreviewTargetType::UpstreamUrl
            ? $this->upstreamUrl()
            : $this->hostUrl();
    }

    /**
     * The upstream target as a link. Checked against the allowlist again on
     * every call, not just on the way in: the portal redirects to it, and a
     * row changed outside the application -- or a host taken off the list --
     * must not turn that redirect into one to somewhere else.
     */
    public function upstreamUrl(): ?string
    {
        if ($this->target_type !== PreviewTargetType::UpstreamUrl) {
            return null;
        }

        return app(PreviewTargetGuard::class)->isAllowed($this->target_type, $this->target)
            ? $this->target
            : null;
    }

    /**
     * The address as the customer reads it: host and path, without scheme.
     */
    public function displayAddress(): ?string
    {
        $url = $this->openUrl();

        return $url === null ? null : rtrim(Str::after($url, 'https://'), '/');
    }

    /**
     * The address of the preview subdomain regardless of status -- how a
     * static preview is opened. Customers are only ever offered url(), which
     * keeps the status gate.
     */
    public function hostUrl(): ?string
    {
        $base = mb_strtolower((string) config('previews.base_domain'));

        // Only ever a host below the configured base domain. Validation already
        // enforces that on the way in; re-checking it here means a row changed
        // outside the application cannot turn a preview link -- or the portal
        // redirect built on it -- into a redirect to somewhere else entirely.
        if ($this->hostname === null || $base === '' || ! str_ends_with($this->hostname, '.'.$base)) {
            return null;
        }

        return 'https://'.$this->hostname;
    }

    /**
     * What "last updated" means to the customer: when the preview was last put
     * live, falling back to the last edit for one that was never provisioned.
     */
    public function lastUpdatedAt(): CarbonInterface
    {
        return $this->provisioned_at ?? $this->updated_at;
    }

    /**
     * Edited since it was last provisioned, so what is configured is not what
     * is live. provision() pins updated_at to provisioned_at, which is what
     * makes the plain comparison meaningful.
     */
    public function needsProvisioning(): bool
    {
        return $this->provisioned_at === null || $this->updated_at->gt($this->provisioned_at);
    }

    /**
     * The customer's latest answer to the version that is live right now. Older
     * answers belong to older versions and do not count any more.
     */
    public function currentFeedback(): ?PreviewFeedback
    {
        return $this->feedback
            ->where('preview_version', $this->version)
            ->sortByDesc(fn (PreviewFeedback $feedback) => [$feedback->created_at, $feedback->id])
            ->first();
    }

    /**
     * Offered to the customer, but not answered yet in its current version.
     */
    public function awaitsFeedback(): bool
    {
        return $this->status->isVisitable() && $this->currentFeedback() === null;
    }

    public function isApproved(): bool
    {
        return $this->currentFeedback()?->decision === FeedbackDecision::Approved;
    }

    /**
     * A thumbnail that shows exactly the version that is live. A picture of an
     * older version is stale and never shown to the customer.
     */
    public function hasCurrentThumbnail(): bool
    {
        return $this->thumbnail_status === ThumbnailStatus::Ready
            && $this->thumbnail_path !== null
            && $this->thumbnail_version === $this->version;
    }

    public function hasStaleThumbnail(): bool
    {
        return $this->thumbnail_path !== null && $this->thumbnail_version !== $this->version;
    }

    /**
     * Record a successful provisioning: the customer is now offered a new
     * version, which asks for fresh feedback and a fresh thumbnail.
     */
    public function publishNewVersion(): void
    {
        $this->version = $this->version + 1;
        $this->thumbnail_status = config('previews.thumbnails.enabled') ? ThumbnailStatus::Pending : null;
    }

    public function setSlugAttribute(?string $value): void
    {
        $this->attributes['slug'] = $value === null ? null : mb_strtolower(trim($value));
    }

    public function setHostnameAttribute(?string $value): void
    {
        $value = $value === null ? null : mb_strtolower(trim($value));

        $this->attributes['hostname'] = $value === '' ? null : $value;
    }

    public function setTargetAttribute(?string $value): void
    {
        $value = $value === null ? null : trim($value);

        $this->attributes['target'] = $value === '' ? null : $value;
    }
}
