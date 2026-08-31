<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'role' => UserRole::Customer,
            'token_hash' => Invitation::hashToken(Str::random(64)),
            'expires_at' => now()->addHours((int) config('smallgate.invitations.ttl_hours', 72)),
            'accepted_at' => null,
        ];
    }

    /**
     * Set a known plaintext token, so a test can redeem the invitation.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn () => ['token_hash' => Invitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
