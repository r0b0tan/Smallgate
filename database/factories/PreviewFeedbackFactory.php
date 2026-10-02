<?php

namespace Database\Factories;

use App\Enums\FeedbackDecision;
use App\Models\Preview;
use App\Models\PreviewFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreviewFeedback>
 */
class PreviewFeedbackFactory extends Factory
{
    protected $model = PreviewFeedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'preview_id' => Preview::factory()->available(),
            'user_id' => User::factory(),
            'preview_version' => 1,
            'decision' => FeedbackDecision::Approved,
            'comment' => null,
        ];
    }

    public function changesRequested(?string $comment = null): static
    {
        return $this->state(fn () => [
            'decision' => FeedbackDecision::ChangesRequested,
            'comment' => $comment,
        ]);
    }
}
