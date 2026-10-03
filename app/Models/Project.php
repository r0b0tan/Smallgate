<?php

namespace App\Models;

use App\Enums\DirectoryStatus;
use App\Enums\PreviewStatus;
use App\Enums\ProjectStatus;
use App\Policies\ProjectPolicy;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `customer_id` is intentionally not mass assignable -- it decides who may see
 * the project, so it is only ever set explicitly from admin-only code. The
 * `directory*` columns are not either: they are written by the "create
 * folder" action and its job alone.
 */
#[Fillable(['name', 'slug', 'description', 'status'])]
#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'directory_status' => DirectoryStatus::class,
            'directory_created_at' => 'datetime',
        ];
    }

    /**
     * The folder name this project gets when it does not have one yet. Both
     * slugs are constrained to [a-z0-9-] by the database, so the result can
     * neither climb out of the root nor be absolute.
     */
    public function proposedDirectory(): string
    {
        return $this->customer->slug.'/'.$this->slug;
    }

    /**
     * Absolute path of the project's folder below the configured root, or null
     * while no folder name has been fixed.
     */
    public function directoryPath(): ?string
    {
        if ($this->directory === null) {
            return null;
        }

        return rtrim((string) config('smallgate.project_directories.root'), '/').'/'.$this->directory;
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Preview, $this>
     */
    public function previews(): HasMany
    {
        return $this->hasMany(Preview::class);
    }

    /**
     * Restrict a query to the projects a user is allowed to see.
     *
     * This is the workhorse behind "a customer only ever sees their own data".
     * Controllers resolve projects through this scope, so an unknown or foreign
     * id simply yields no row -- and the caller answers 404, never 403.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        // Customer users never see projects of a deactivated customer, and a
        // user without any customer sees nothing at all.
        $query->whereIn('customer_id', $user->accessibleCustomerIds())
            ->whereHas('customer', fn (Builder $customer) => $customer->where('is_active', true));
    }

    /**
     * Load what the customer is actually offered: available previews only,
     * newest first, with the feedback needed to tell new from answered.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function withOfferedPreviews(Builder $query): void
    {
        $query->with(['previews' => fn ($previews) => $previews
            ->where('status', PreviewStatus::Available)
            ->with('feedback')
            ->orderByDesc('provisioned_at')
            ->orderBy('name'),
        ]);
    }

    /**
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function status(Builder $query, ProjectStatus $status): void
    {
        $query->where('status', $status);
    }

    public function setSlugAttribute(?string $value): void
    {
        $this->attributes['slug'] = $value === null ? null : mb_strtolower(trim($value));
    }
}
