<?php

namespace App\Models;

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Policies\PreviewPolicy;
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
 * `project_id` and `provisioned_at` are not mass assignable: the first decides
 * who may see the preview, the second is owned by the provisioner.
 */
#[Fillable(['name', 'slug', 'hostname', 'target_type', 'target', 'status'])]
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
        if ($this->hostname === null || ! $this->status->isVisitable()) {
            return null;
        }

        return 'https://'.$this->hostname;
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
