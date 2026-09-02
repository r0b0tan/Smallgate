<?php

namespace App\Models;

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Policies\PreviewPolicy;
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

/**
 * `project_id`, `status` and `provisioned_at` are not mass assignable: the
 * first decides who may see the preview, the second whether the customer is
 * offered it at all, and the third is owned by the provisioner. Status changes
 * go through the provision and disable actions, never through a form field.
 */
#[Fillable(['name', 'slug', 'hostname', 'target_type', 'target'])]
#[UsePolicy(PreviewPolicy::class)]
class Preview extends Model
{
    /** @use HasFactory<PreviewFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => PreviewStatus::class,
            'target_type' => PreviewTargetType::class,
            'provisioned_at' => 'datetime',
        ];
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
     * preview that actually has a hostname.
     */
    public function url(): ?string
    {
        return $this->status->isVisitable() ? $this->hostUrl() : null;
    }

    /**
     * The address of the preview host regardless of status. Administrators need
     * it to check a preview before releasing it; customers are only ever
     * offered url(), which keeps the status gate.
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
