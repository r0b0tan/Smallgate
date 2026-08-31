<?php

namespace Database\Factories;

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Models\Preview;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Preview>
 */
class PreviewFactory extends Factory
{
    protected $model = Preview::class;

    /**
     * Defaults to a draft, because an "available" preview additionally needs a
     * hostname and a target -- a database CHECK constraint enforces that.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'project_id' => Project::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'hostname' => null,
            'target_type' => PreviewTargetType::StaticDirectory,
            'target' => null,
            'status' => PreviewStatus::Draft,
            'provisioned_at' => null,
        ];
    }

    public function for_project(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }

    /**
     * A fully provisioned preview, with a target inside the first configured
     * allow-listed root.
     */
    public function available(): static
    {
        return $this->state(function (array $attributes) {
            $root = (array) config('previews.allowed_roots', []);
            $slug = $attributes['slug'] ?? Str::lower(Str::random(8));

            return [
                'hostname' => $slug.'.'.config('previews.base_domain'),
                'target_type' => PreviewTargetType::StaticDirectory,
                'target' => rtrim((string) ($root[0] ?? '/srv/previews'), '/').'/'.$slug,
                'status' => PreviewStatus::Available,
                'provisioned_at' => now(),
            ];
        });
    }
}
