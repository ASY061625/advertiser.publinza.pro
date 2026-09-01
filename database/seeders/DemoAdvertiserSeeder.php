<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Posts\Enums\ActorType;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Support\PostStatusContext;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\OrderStatus;
use App\Domain\Trading\Enums\PaidFrom;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One demo advertiser with three projects and twenty posts covering every
 * status in the lifecycle.
 *
 * Each post is walked along the real transitions rather than inserted at its
 * final status, so post_status_history is a legal path and the observer is
 * exercised by the seed itself.
 */
class DemoAdvertiserSeeder extends Seeder
{
    /** 20 posts across all nine statuses. */
    private const DISTRIBUTION = [
        [PostStatus::Draft, 2],
        [PostStatus::New, 3],
        [PostStatus::InProgress, 3],
        [PostStatus::ContentReview, 3],
        [PostStatus::Posted, 3],
        [PostStatus::Completed, 3],
        [PostStatus::Rejected, 1],
        [PostStatus::Cancelled, 1],
        [PostStatus::Refunded, 1],
    ];

    public function run(): void
    {
        fake()->seed(20260901);

        $user = User::query()->updateOrCreate(
            ['email' => 'advertiser@publinza.test'],
            [
                'name' => 'Dana Okafor',
                'password' => Hash::make('password'),
                'company' => 'Northwind Software',
                'country' => 'US',
                'vat_no' => 'US482910337',
                'phone' => '+1 415 555 0142',
                'timezone' => 'America/Los_Angeles',
                'locale' => 'en',
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
                'referrer_source' => 'organic',
            ],
        );

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['available_cents' => 0, 'frozen_cents' => 0, 'currency' => 'USD'],
        );

        // Funded through the real ledger path, so the transaction history is
        // consistent with the balance rather than a hand-set number.
        if ($wallet->transactions()->count() === 0) {
            $wallet->deposit(Money::fromMajorUnits('12500.00'), null, 'Opening demo balance');
        }

        $projects = $this->seedProjects($user);
        $this->seedPosts($user, $projects);
    }

    /**
     * @return list<Project>
     */
    private function seedProjects(User $user): array
    {
        $categories = WebsiteCategory::query()->pluck('id', 'slug');
        $countries = Country::query()->whereIn('code', ['US', 'GB', 'DE', 'CA'])->pluck('id');
        $languages = Language::query()->whereIn('code', ['en', 'de'])->pluck('id');
        $topics = SensitiveTopic::query()->whereIn('slug', ['cryptocurrency'])->pluck('id');

        $definitions = [
            [
                'name' => 'Northwind CRM launch',
                'website_url' => 'https://northwind.software/crm',
                'category' => 'technology',
                'task' => 'Write for a technical buyer. No superlatives, one link in the body, '
                    .'mention the free tier once.',
                'folders' => ['Product pages', 'Comparison posts'],
            ],
            [
                'name' => 'Payments API content push',
                'website_url' => 'https://northwind.software/payments',
                'category' => 'finance',
                'task' => 'Developer audience. Include a short code sample. Avoid investment language.',
                'folders' => ['Docs & guides'],
            ],
            [
                'name' => 'Northwind brand awareness',
                'website_url' => 'https://northwind.software',
                'category' => 'business',
                'task' => 'General brand mentions. Homepage link only.',
                'folders' => [],
            ],
        ];

        $projects = [];

        foreach ($definitions as $definition) {
            $project = Project::query()->updateOrCreate(
                ['user_id' => $user->id, 'name' => $definition['name']],
                [
                    'website_url' => $definition['website_url'],
                    'category_id' => $categories[$definition['category']],
                    'status' => 'active',
                    'publisher_task' => $definition['task'],
                ],
            );

            $project->countries()->sync($countries);
            $project->languages()->sync($languages);
            $project->sensitiveTopics()->sync($definition['category'] === 'finance' ? $topics : []);

            foreach ($definition['folders'] as $index => $folderName) {
                $folder = ProjectFolder::query()->updateOrCreate(
                    ['project_id' => $project->id, 'name' => $folderName],
                    ['sort_order' => $index],
                );

                LandingPage::query()->updateOrCreate(
                    ['project_id' => $project->id, 'folder_id' => $folder->id, 'anchor_text' => 'northwind crm'],
                    ['url' => $definition['website_url'], 'sort_order' => 0],
                );
            }

            LandingPage::query()->updateOrCreate(
                ['project_id' => $project->id, 'folder_id' => null, 'anchor_text' => 'northwind software'],
                ['url' => $definition['website_url'], 'sort_order' => 1],
            );

            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * @param  list<Project>  $projects
     */
    private function seedPosts(User $user, array $projects): void
    {
        if (Post::query()->where('user_id', $user->id)->exists()) {
            return; // Already seeded; posts are not idempotent to re-walk.
        }

        $websites = Website::query()->with('prices')->inRandomOrder()->take(20)->get();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => Order::generateNumber(),
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 0,
            'currency' => 'USD',
            'status' => OrderStatus::Paid,
            'paid_from' => PaidFrom::Wallet,
            'paid_at' => now()->subDays(21),
        ]);

        $index = 0;
        $total = 0;

        foreach (self::DISTRIBUTION as [$targetStatus, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $website = $websites[$index % $websites->count()];
                $project = $projects[$index % count($projects)];
                $price = $website->prices->first()?->price_cents ?? 15_000;
                $index++;
                $total += $price;

                // The seed is the system acting, not a signed-in person.
                PostStatusContext::actingAs(ActorType::System, null, function () use (
                    $user, $project, $website, $order, $price, $targetStatus
                ): void {
                    $post = Post::query()->create([
                        'order_id' => $targetStatus === PostStatus::Draft ? null : $order->id,
                        'user_id' => $user->id,
                        'project_id' => $project->id,
                        'folder_id' => $project->folders()->value('id'),
                        'website_id' => $website->id,
                        'status' => PostStatus::Draft,
                        'anchor_text' => fake()->randomElement([
                            'northwind crm', 'crm for engineers', 'northwind software', 'payments api',
                        ]),
                        'target_url' => $project->website_url,
                        'content_mode' => fake()->boolean(60)
                            ? ContentMode::PublisherWrites
                            : ContentMode::AdvertiserProvides,
                        'price_cents' => $price,
                        'deadline_at' => now()->addDays(fake()->numberBetween(2, 20)),
                    ]);

                    $this->walk($post, $targetStatus);
                });
            }
        }

        $order->update(['subtotal_cents' => $total, 'total_cents' => $total]);
    }

    /** Moves a post along real transitions to its target status. */
    private function walk(Post $post, PostStatus $target): void
    {
        $path = match ($target) {
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

        foreach ($path as $step) {
            $attributes = match ($step) {
                PostStatus::Posted => [
                    'published_url' => "https://{$post->website->domain}/".fake()->slug(4),
                    'published_at' => now()->subDays(fake()->numberBetween(1, 5)),
                    // `posted` opens the 3-day verification window.
                    'frozen_until' => now()->addDays(3),
                ],
                PostStatus::Rejected => ['rejection_reason' => 'The draft did not meet the site guidelines.'],
                default => [],
            };

            $post->transitionTo($step, match ($step) {
                PostStatus::Rejected => 'Rejected by the publisher.',
                PostStatus::Cancelled => 'Cancelled by the advertiser.',
                default => null,
            }, $attributes);
        }
    }
}
