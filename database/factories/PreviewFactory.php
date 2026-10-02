<?php

namespace Database\Factories;

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Enums\ThumbnailStatus;
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
            'version' => 0,
        ];
    }

    /**
     * A released preview opened at its upstream URL, path included, on the
     * test environment's allow-listed host. No subdomain of its own.
     */
    public function upstream(string $path = 'zimmerei-holzmann'): static
    {
        return $this->available()->state(fn () => [
            'hostname' => null,
            'target_type' => PreviewTargetType::UpstreamUrl,
            'target' => 'https://'.((array) config('previews.allowed_upstream_hosts'))[0].'/'.$path,
        ]);
    }

    /**
     * A ready thumbnail for the preview's current version. The file itself is
     * up to the test.
     */
    public function withThumbnail(string $path = 'preview-thumbnails/test/v1.jpg'): static
    {
        return $this->state(fn (array $attributes) => [
            'thumbnail_status' => ThumbnailStatus::Ready,
            'thumbnail_version' => $attributes['version'] ?? 1,
            'thumbnail_path' => $path,
            'thumbnail_generated_at' => now(),
        ]);
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
                'version' => 1,
            ];
        });
    }
}
