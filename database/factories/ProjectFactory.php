<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Locale independent: the de_DE Faker locale has no catchPhrase().
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'customer_id' => Customer::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Active,
        ];
    }

    public function for_customer(Customer $customer): static
    {
        return $this->state(fn () => ['customer_id' => $customer->id]);
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
