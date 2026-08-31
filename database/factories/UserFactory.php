<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The default is a customer user belonging to a fresh customer, because
     * that is what the database CHECK constraint requires of role "customer".
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'password' => 'password-for-tests-only',
            'role' => UserRole::Customer,
            'customer_id' => Customer::factory(),
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Administrators never belong to a customer.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'customer_id' => null,
        ]);
    }

    public function for_customer(Customer $customer): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Customer,
            'customer_id' => $customer->id,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
