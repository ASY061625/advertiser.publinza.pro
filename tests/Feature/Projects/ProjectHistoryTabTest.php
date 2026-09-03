<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\PostStatusHistory;
use App\Domain\Projects\Actions\GetProjectHistory;
use App\Domain\Projects\DTOs\HistoryFilters;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\System\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;

function historyUser(): User
{
    return User::factory()->create(['name' => 'Dana Reed', 'email_verified_at' => now()]);
}

function historyProject(User $user): Project
{
    $project = Project::factory()->for($user, 'owner')->create(['name' => 'Nomad Bank']);
    ProjectFolder::query()->create(['project_id' => $project->id, 'name' => 'General', 'sort_order' => 0]);

    return $project;
}

function historyAudit(User $user, Project $project, string $action, ?array $changes = null): AuditLog
{
    return AuditLog::query()->create([
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'action' => $action,
        'auditable_type' => Project::class,
        'auditable_id' => $project->id,
        'changes' => $changes,
    ]);
}

function historyOf(Project $project, array $query = []): array
{
    return app(GetProjectHistory::class)->handle(
        $project,
        HistoryFilters::fromRequest(new Request($query)),
    );
}

it('unions every source into one timeline, newest first', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    $post = Post::factory()->for($user, 'advertiser')->for($project)->create(['anchor_text' => 'pricing']);

    historyAudit($user, $project, 'project.name.updated', ['field' => 'name', 'from' => 'Old', 'to' => 'Nomad Bank']);
    historyAudit($user, $project, 'folder.added', ['folder' => 'Spring', 'landing_pages' => 2]);

    $post->transitionTo(PostStatus::New, 'Ordered');

    $wallet = Wallet::query()->firstOrCreate(['user_id' => $user->id], ['available_cents' => 0, 'frozen_cents' => 0]);
    Transaction::query()->create([
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Freeze,
        'amount_cents' => -20_000,
        'balance_after_cents' => 0,
        'frozen_after_cents' => 20_000,
        'reference_type' => Post::class,
        'reference_id' => $post->id,
        'description' => 'Held for placement',
    ]);

    $conversation = Conversation::query()->create([
        'user_id' => $user->id, 'post_id' => $post->id, 'website_id' => $post->website_id,
        'subject' => 'Draft ready', 'last_message_at' => now(),
    ]);
    Message::query()->create([
        'conversation_id' => $conversation->id, 'sender_type' => SenderType::Admin, 'body' => 'Here is the draft.',
    ]);

    $result = historyOf($project);
    $families = collect($result['events'])->pluck('family')->unique()->sort()->values()->all();

    expect($families)->toBe(['folder', 'message', 'money', 'post', 'project'])
        ->and($result['total'])->toBeGreaterThanOrEqual(5);

    // Newest first, always.
    $times = collect($result['events'])->pluck('occurredAt')->all();
    expect($times)->toBe(collect($times)->sortDesc()->values()->all());
});

it('writes each family in plain language', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    historyAudit($user, $project, 'project.archived');
    historyAudit($user, $project, 'project.category.updated', [
        'field' => 'category', 'from' => 'Finance', 'to' => 'Technology',
    ]);
    historyAudit($user, $project, 'folder.added', ['folder' => 'Spring', 'landing_pages' => 2]);

    $descriptions = collect(historyOf($project)['events'])->pluck('description')->all();

    expect($descriptions)->toContain('Project archived')
        ->toContain('Category changed')
        ->toContain('Folder “Spring” added');
});

it('names the advertiser, hides who at Publinza, and calls the rest System', function (): void {
    $user = historyUser();
    $project = historyProject($user);
    $post = Post::factory()->for($user, 'advertiser')->for($project)->create();

    historyAudit($user, $project, 'project.archived');

    // A staff action on the post: never attributed to a named person.
    $post->transitionTo(PostStatus::New, 'Ordered');
    PostStatusHistory::query()->latest('id')->first()
        ->forceFill(['actor_type' => 'admin', 'actor_id' => 1])->save();

    $actors = collect(historyOf($project)['events'])->pluck('actor')->unique()->values()->all();

    expect($actors)->toContain('Dana Reed')
        ->toContain('Publinza team')
        ->not->toContain('admin');
});

it('expands a field change into a before and after', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    historyAudit($user, $project, 'project.category.updated', [
        'field' => 'category', 'from' => 'Finance', 'to' => 'Technology',
    ]);

    $event = collect(historyOf($project)['events'])->firstWhere('family', 'project');

    expect($event['detail']['kind'])->toBe('fields')
        ->and($event['detail']['rows'][0])->toBe([
            'field' => 'category', 'from' => 'Finance', 'to' => 'Technology',
        ]);
});

it('marks a brief change for a text diff rather than a table', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    historyAudit($user, $project, 'project.brief.updated', [
        'field' => 'brief', 'from' => 'Keep it plain.', 'to' => 'Keep it plain and mention the trial.',
    ]);

    $event = collect(historyOf($project)['events'])->firstWhere('family', 'project');

    expect($event['detail']['kind'])->toBe('text-diff');
});

it('expands a post event into the placement behind it', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    $post = Post::factory()->for($user, 'advertiser')->for($project)->create([
        'anchor_text' => 'best invoicing software',
        'target_url' => 'https://nomadbank.test/pricing',
        'price_cents' => 24_000,
    ]);
    $post->transitionTo(PostStatus::New, 'Ordered');

    $event = collect(historyOf($project)['events'])->firstWhere('family', 'post');

    expect($event['detail']['kind'])->toBe('post')
        ->and($event['detail']['postId'])->toBe($post->id)
        ->and($event['detail']['anchorText'])->toBe('best invoicing software')
        ->and($event['detail']['priceCents'])->toBe(24_000);
});

it('filters by family, actor, window and search', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    $post = Post::factory()->for($user, 'advertiser')->for($project)->create(['anchor_text' => 'pricing page']);
    $post->transitionTo(PostStatus::New, 'Ordered');

    historyAudit($user, $project, 'project.archived');
    historyAudit($user, $project, 'folder.added', ['folder' => 'Spring', 'landing_pages' => 1]);

    expect(collect(historyOf($project, ['families' => ['project']])['events'])->pluck('family')->unique()->all())
        ->toBe(['project']);

    // Post history rows are written by the system when a factory transitions.
    expect(historyOf($project, ['families' => ['post']])['total'])->toBeGreaterThan(0);

    // Search reaches the columns the description is built from. Both of the
    // post's status rows carry the anchor — its creation and its order — so
    // the match is the post's whole story, not one line of it.
    $found = historyOf($project, ['q' => 'pricing page']);
    expect($found['total'])->toBe(2)
        ->and(collect($found['events'])->pluck('family')->unique()->all())->toBe(['post']);

    // A window that excludes everything.
    expect(historyOf($project, ['from' => '2000-01-01', 'to' => '2000-01-02'])['total'])->toBe(0);
});

it('reads from a position, so a new event mid-scroll cannot repeat a row', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    // All sixty in the same second, which is the case an offset gets wrong:
    // a timestamp alone cannot say which of them the reader has already seen.
    foreach (range(1, 60) as $i) {
        historyAudit($user, $project, 'project.name.updated', ['field' => 'name', 'from' => "N{$i}", 'to' => "N{$i}b"]);
    }

    $first = historyOf($project);
    expect($first['events'])->toHaveCount(HistoryFilters::PER_PAGE)
        ->and($first['hasMore'])->toBeTrue()
        ->and($first['nextCursor'])->not->toBeNull();

    // Something happens between the two requests, in that same second. With an
    // offset this is exactly where page two repeats the last row of page one.
    historyAudit($user, $project, 'project.archived');

    $second = historyOf($project, ['cursor' => $first['nextCursor']]);

    $overlap = array_intersect(
        collect($first['events'])->pluck('id')->all(),
        collect($second['events'])->pluck('id')->all(),
    );

    expect($overlap)->toBe([])
        ->and($second['events'])->toHaveCount(10)
        ->and($second['hasMore'])->toBeFalse()
        ->and($second['nextCursor'])->toBeNull();

    // Every row exactly once, and the new one only at the top of a fresh read.
    $ids = [...collect($first['events'])->pluck('id'), ...collect($second['events'])->pluck('id')];
    expect($ids)->toHaveCount(60)
        ->and(array_unique($ids))->toHaveCount(60);
});

it('jumps to a date without filtering the log down to it', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    $old = historyAudit($user, $project, 'project.restored');
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    historyAudit($user, $project, 'project.archived');

    // The whole log is still five entries wide; the jump only moves where the
    // reading starts, which is what distinguishes it from the date filter.
    $jumped = historyOf($project, ['cursor' => now()->subDays(9)->toDateString()]);

    expect(collect($jumped['events'])->pluck('description')->all())->toBe(['Project restored'])
        ->and($jumped['total'])->toBe(2);
});

it('serves the tab, and only for its own tab', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    historyAudit($user, $project, 'project.archived');

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=history"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tab', 'history')
            ->has('history.events', 1)
            ->where('history.hasAnyHistory', true)
            ->where('history.perPage', HistoryFilters::PER_PAGE)
            ->where('history.cursor', null)
            ->where('history.nextCursor', null)
            ->etc());

    foreach (['general', 'statistics'] as $tab) {
        $this->actingAs($user)
            ->get(advertiserUrl("/projects/{$project->id}?tab={$tab}"))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('history', null)->etc());
    }
});

it('carries the cursor through the request, not just the action', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    foreach (range(1, 60) as $i) {
        historyAudit($user, $project, 'project.name.updated', ['field' => 'name', 'from' => "N{$i}", 'to' => "N{$i}b"]);
    }

    $cursor = null;

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=history"))
        ->assertInertia(function (AssertableInertia $page) use (&$cursor): void {
            $page->has('history.events', HistoryFilters::PER_PAGE)->where('history.hasMore', true)->etc();

            $cursor = $page->toArray()['props']['history']['nextCursor'];
        });

    expect($cursor)->toBeString();

    // A cursor the tab can hand straight back, and a log that continues from
    // it rather than starting again — the page below the first fifty is the
    // last ten, and the whole log is still sixty entries.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=history&cursor=".urlencode((string) $cursor)))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('history.events', 10)
            ->where('history.hasMore', false)
            ->where('history.total', 60)
            ->where('history.cursor', $cursor)
            ->where('history.hasAnyHistory', true)
            ->etc());
});

it('separates an empty log from an empty filter', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=history"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('history.hasAnyHistory', false)
            ->where('history.total', 0)
            ->etc());

    historyAudit($user, $project, 'project.archived');

    // History exists, the filter just excludes it — a different situation with
    // a different thing to do about it.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=history&q=nothing-like-this"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('history.hasAnyHistory', true)
            ->where('history.isFiltering', true)
            ->where('history.total', 0)
            ->etc());
});

it('exports the timeline as a CSV honouring the filters', function (): void {
    $user = historyUser();
    $project = historyProject($user);

    historyAudit($user, $project, 'project.archived');
    historyAudit($user, $project, 'folder.added', ['folder' => 'Spring', 'landing_pages' => 1]);

    $response = $this->actingAs($user)->get(advertiserUrl("/projects/{$project->id}/history/export"));
    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('When,Family,Event,Actor,Description')
        ->toContain('Project archived')
        ->toContain('Folder “Spring” added')
        ->toContain('Dana Reed');

    // And the filters travel with it.
    $filtered = $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}/history/export?families[]=folder"))
        ->streamedContent();

    expect($filtered)->toContain('Folder “Spring” added')
        ->not->toContain('Project archived');
});

it('keeps one project’s history out of another’s', function (): void {
    $user = historyUser();
    $mine = historyProject($user);
    $theirs = historyProject($user);

    historyAudit($user, $mine, 'project.archived');
    historyAudit($user, $theirs, 'project.restored');

    $descriptions = collect(historyOf($mine)['events'])->pluck('description')->all();

    expect($descriptions)->toBe(['Project archived']);
});

it('will not show another advertiser the log', function (): void {
    $project = historyProject(historyUser());

    // One outsider for both requests, not two: AuthenticateSession ties a
    // session to the password hash on it, so a second person arriving on the
    // first one's session is logged out before any policy is consulted — and
    // then the test would be asserting the wrong refusal.
    $outsider = historyUser();

    $this->actingAs($outsider)
        ->get(advertiserUrl("/projects/{$project->id}?tab=history"))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(advertiserUrl("/projects/{$project->id}/history/export"))
        ->assertForbidden();
});
