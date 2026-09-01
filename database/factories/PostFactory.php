<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Website;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ContentMode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'website_id' => Website::factory(),
            'status' => PostStatus::Draft,
            'anchor_text' => fake()->words(3, true),
            'target_url' => fake()->url(),
            'content_mode' => fake()->boolean(60)
                ? ContentMode::PublisherWrites
                : ContentMode::AdvertiserProvides,
            'price_cents' => fake()->numberBetween(80_00, 1_200_00),
            'deadline_at' => now()->addDays(fake()->numberBetween(3, 21)),
        ];
    }

    /**
     * Walks the post along the real lifecycle to reach a status, so its history
     * is a legal path rather than a fabricated end state.
     */
    public function status(PostStatus $target): static
    {
        return $this->afterCreating(function (Post $post) use ($target): void {
            foreach (self::pathTo($target) as $step) {
                $post->transitionTo($step, 'Seeded');
            }
        });
    }

    /**
     * @return list<PostStatus>
     */
    public static function pathTo(PostStatus $target): array
    {
        return match ($target) {
            PostStatus::Draft => [],
            PostStatus::New => [PostStatus::New],
            PostStatus::InProgress => [PostStatus::New, PostStatus::InProgress],
            PostStatus::ContentReview => [PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview],
            PostStatus::Posted => [
                PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview, PostStatus::Posted,
            ],
            PostStatus::Completed => [
                PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview,
                PostStatus::Posted, PostStatus::Completed,
            ],
            PostStatus::Rejected => [PostStatus::New, PostStatus::InProgress, PostStatus::Rejected],
            PostStatus::Cancelled => [PostStatus::New, PostStatus::Cancelled],
            PostStatus::Refunded => [PostStatus::New, PostStatus::Cancelled, PostStatus::Refunded],
        };
    }
}
