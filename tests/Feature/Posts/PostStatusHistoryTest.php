<?php

declare(strict_types=1);

use App\Domain\Posts\Enums\ActorType;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\PostStatusHistory;
use App\Domain\Posts\Support\PostStatusContext;
use App\Exceptions\InvalidStatusTransition;
use App\Models\User;

beforeEach(function (): void {
    PostStatusContext::reset();
});

it('writes a history row when a post is created', function (): void {
    $post = Post::factory()->create();

    expect($post->statusHistory()->count())->toBe(1);

    $row = $post->statusHistory()->first();
    expect($row->from_status)->toBeNull()
        ->and($row->to_status)->toBe(PostStatus::Draft);
});

it('writes a history row for every transition', function (): void {
    $post = Post::factory()->create();

    $post->transitionTo(PostStatus::New);
    $post->transitionTo(PostStatus::InProgress);
    $post->transitionTo(PostStatus::ContentReview);

    // One for creation plus three transitions.
    expect($post->statusHistory()->count())->toBe(4);

    $moves = $post->statusHistory()->get()->map(
        fn (PostStatusHistory $row): string => ($row->from_status?->value ?? 'null').'->'.$row->to_status->value,
    )->all();

    expect($moves)->toBe([
        'null->draft',
        'draft->new',
        'new->in_progress',
        'in_progress->content_review',
    ]);
});

it('refuses an illegal transition and writes nothing', function (): void {
    $post = Post::factory()->create();
    $before = $post->statusHistory()->count();

    expect(fn () => $post->transitionTo(PostStatus::Posted))
        ->toThrow(InvalidStatusTransition::class);

    expect($post->fresh()->status)->toBe(PostStatus::Draft)
        ->and($post->statusHistory()->count())->toBe($before);
});

it('records history even when the status is changed without transitionTo', function (): void {
    // The guarantee is enforced on the model event, so bypassing the sanctioned
    // API still produces a history row rather than a silent mutation.
    $post = Post::factory()->create();

    $post->status = PostStatus::New;
    $post->save();

    expect($post->statusHistory()->count())->toBe(2)
        ->and($post->statusHistory()->latest('id')->first()->to_status)->toBe(PostStatus::New);
});

it('refuses an illegal transition made without transitionTo', function (): void {
    $post = Post::factory()->create();

    $post->status = PostStatus::Completed;

    expect(fn () => $post->save())->toThrow(InvalidStatusTransition::class);
});

it('records the actor and the note', function (): void {
    $admin = 42;
    $post = Post::factory()->create();

    PostStatusContext::actingAs(ActorType::Admin, $admin, function () use ($post): void {
        $post->transitionTo(PostStatus::New, 'Paid by wallet');
    });

    $row = $post->statusHistory()->latest('id')->first();

    expect($row->actor_type)->toBe(ActorType::Admin)
        ->and($row->actor_id)->toBe($admin)
        ->and($row->note)->toBe('Paid by wallet');
});

it('attributes the change to the signed-in advertiser by default', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);
    $post->transitionTo(PostStatus::New);

    $row = $post->statusHistory()->latest('id')->first();

    expect($row->actor_type)->toBe(ActorType::User)
        ->and($row->actor_id)->toBe($user->id);
});

it('falls back to the system actor when nobody is signed in', function (): void {
    $post = Post::factory()->create();

    $post->transitionTo(PostStatus::New);

    expect($post->statusHistory()->latest('id')->first()->actor_type)->toBe(ActorType::System);
});

it('never leaves a status change without a history row', function (): void {
    $post = Post::factory()->create();

    foreach ([PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview, PostStatus::Posted] as $status) {
        $post->transitionTo($status);
    }

    // Every distinct status the post has ever held appears in the history.
    $recorded = $post->statusHistory()->pluck('to_status')->map(fn ($s) => $s->value)->all();

    expect($recorded)->toBe(['draft', 'new', 'in_progress', 'content_review', 'posted']);
});
