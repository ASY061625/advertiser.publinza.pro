<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Website;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Article;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\SavedView;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Models\User;
use App\Support\HtmlSanitizer;
use App\Support\PostGridPreferences;

function actionUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    Wallet::query()->create(['user_id' => $user->id, 'available_cents' => 500_000, 'frozen_cents' => 0]);

    return $user;
}

it('exports the filtered rows as CSV', function (): void {
    $user = actionUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $site = Website::factory()->create(['domain' => 'quietledger.test']);
    Post::factory()->create([
        'user_id' => $user->id, 'project_id' => $project->id, 'website_id' => $site->id,
        'anchor_text' => 'best invoicing tool', 'price_cents' => 24_500,
    ]);
    Post::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $response = $this->actingAs($user)->get(advertiserUrl('/posts/export?q=quietledger'));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('quietledger.test')
        ->toContain('best invoicing tool')
        ->toContain('245.00')
        // The filter applied to the grid applies to the file.
        ->and(substr_count($csv, "\n"))->toBe(2);
});

it('neutralises a formula smuggled into a CSV cell', function (): void {
    $user = actionUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Post::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        // Opens as a formula in Excel and Sheets unless it is defused.
        'anchor_text' => '=HYPERLINK("http://evil.test","click")',
    ]);

    $csv = $this->actingAs($user)->get(advertiserUrl('/posts/export'))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

it('exports only the selected rows when ids are given', function (): void {
    $user = actionUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $posts = Post::factory()->count(3)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $csv = $this->actingAs($user)
        ->get(advertiserUrl('/posts/export?ids[]='.$posts[0]->id))
        ->streamedContent();

    expect(substr_count($csv, "\n"))->toBe(2);
});

it('cancels the cancellable posts in a selection and reports what it skipped', function (): void {
    $user = actionUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $draft = Post::factory()->status(PostStatus::Draft)->create(['user_id' => $user->id, 'project_id' => $project->id]);
    $live = Post::factory()->status(PostStatus::Posted)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/bulk'), [
            'action' => 'cancel',
            'ids' => [$draft->id, $live->id],
            'reason' => 'Changed the campaign',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 post cancelled')
            && str_contains($message, 'before it goes live'));

    // A live post is untouched rather than failing the whole batch.
    expect($draft->fresh()->status)->toBe(PostStatus::Cancelled)
        ->and($live->fresh()->status)->toBe(PostStatus::Posted);
});

it('refuses to cancel a post belonging to someone else', function (): void {
    $mine = actionUser();
    $theirs = actionUser();
    $project = Project::factory()->create(['user_id' => $theirs->id]);
    $post = Post::factory()->status(PostStatus::Draft)->create(['user_id' => $theirs->id, 'project_id' => $project->id]);

    $this->actingAs($mine)->post(advertiserUrl('/posts/bulk'), [
        'action' => 'cancel',
        'ids' => [$post->id],
        'reason' => 'Not mine to cancel',
    ]);

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

it('only moves a post into a folder of its own project', function (): void {
    $user = actionUser();
    $alpha = Project::factory()->create(['user_id' => $user->id]);
    $beta = Project::factory()->create(['user_id' => $user->id]);

    $folder = ProjectFolder::query()->create(['project_id' => $alpha->id, 'name' => 'Landing pages']);

    $inAlpha = Post::factory()->create(['user_id' => $user->id, 'project_id' => $alpha->id]);
    $inBeta = Post::factory()->create(['user_id' => $user->id, 'project_id' => $beta->id]);

    $this->actingAs($user)->post(advertiserUrl('/posts/bulk'), [
        'action' => 'move',
        'ids' => [$inAlpha->id, $inBeta->id],
        'folder_id' => $folder->id,
    ])->assertSessionHas('success', fn (string $m): bool => str_contains($m, '1 post moved'));

    expect($inAlpha->fresh()->folder_id)->toBe($folder->id)
        // Moving it would have silently reassigned it to another project's brief.
        ->and($inBeta->fresh()->folder_id)->toBeNull();
});

it('zips the selected articles, and says so when there are none', function (): void {
    $user = actionUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $withArticle = Post::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Article::query()->create([
        'post_id' => $withArticle->id,
        'title' => 'How invoicing works',
        'body_html' => '<p>Body</p>',
        'word_count' => 2,
        'version' => 1,
    ]);
    $without = Post::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $zip = $this->actingAs($user)
        ->post(advertiserUrl('/posts/bulk'), ['action' => 'download', 'ids' => [$withArticle->id]])
        ->assertOk()
        ->streamedContent();

    expect($zip)->toContain('how-invoicing-works');

    $empty = $this->actingAs($user)
        ->post(advertiserUrl('/posts/bulk'), ['action' => 'download', 'ids' => [$without->id]])
        ->streamedContent();

    // An empty archive reads as a broken download, so it carries an explanation.
    expect($empty)->toContain('README.txt');
});

it('duplicates a post as a draft in another project, carrying nothing about the original', function (): void {
    $user = actionUser();
    $from = Project::factory()->create(['user_id' => $user->id]);
    $to = Project::factory()->create(['user_id' => $user->id]);

    $original = Post::factory()->status(PostStatus::Posted)->create([
        'user_id' => $user->id,
        'project_id' => $from->id,
        'anchor_text' => 'best invoicing tool',
        'published_url' => 'https://live.test/article',
    ]);

    $this->actingAs($user)
        ->post(advertiserUrl("/posts/{$original->id}/duplicate"), ['project_id' => $to->id])
        ->assertSessionHas('success');

    $copy = Post::query()->where('project_id', $to->id)->firstOrFail();

    expect($copy->status)->toBe(PostStatus::Draft)
        ->and($copy->anchor_text)->toBe('best invoicing tool')
        ->and($copy->website_id)->toBe($original->website_id)
        // A copy of a live placement must not claim to be live.
        ->and($copy->published_url)->toBeNull()
        ->and($copy->published_at)->toBeNull()
        ->and($copy->order_id)->toBeNull();
});

it('refuses to duplicate into a project that is not yours', function (): void {
    $mine = actionUser();
    $theirs = actionUser();
    $myProject = Project::factory()->create(['user_id' => $mine->id]);
    $theirProject = Project::factory()->create(['user_id' => $theirs->id]);
    $post = Post::factory()->create(['user_id' => $mine->id, 'project_id' => $myProject->id]);

    $this->actingAs($mine)
        ->post(advertiserUrl("/posts/{$post->id}/duplicate"), ['project_id' => $theirProject->id])
        ->assertSessionHas('error');

    expect(Post::query()->where('project_id', $theirProject->id)->count())->toBe(0);
});

it('saves, applies and deletes a named view', function (): void {
    $user = actionUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/views'), ['name' => 'Live links', 'tab' => 'posted', 'min_dr' => 40])
        ->assertSessionHas('success');

    $view = SavedView::query()->firstOrFail();

    // A saved view is the query string with a name on it, so applying one and
    // opening a shared link are the same operation.
    expect($view->query)->toBe(['tab' => 'posted', 'min_dr' => 40]);

    // Saving the same name again replaces it rather than making a second.
    $this->actingAs($user)->post(advertiserUrl('/posts/views'), ['name' => 'Live links', 'tab' => 'draft']);
    expect(SavedView::query()->count())->toBe(1);

    $this->actingAs($user)->delete(advertiserUrl("/posts/views/{$view->id}"))->assertSessionHas('success');
    expect(SavedView::query()->count())->toBe(0);
});

it('will not save a view with no filters in it', function (): void {
    $user = actionUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/views'), ['name' => 'Everything'])
        ->assertSessionHas('error');

    expect(SavedView::query()->count())->toBe(0);
});

it('will not let one advertiser delete another\'s saved view', function (): void {
    $mine = actionUser();
    $theirs = actionUser();

    $view = SavedView::query()->create([
        'user_id' => $theirs->id, 'surface' => 'posts', 'name' => 'Theirs', 'query' => ['tab' => 'draft'],
    ]);

    $this->actingAs($mine)->delete(advertiserUrl("/posts/views/{$view->id}"))->assertNotFound();
    expect(SavedView::query()->count())->toBe(1);
});

it('stores column order and refuses to hide a column the grid needs', function (): void {
    $user = actionUser();

    $this->actingAs($user)->patchJson(advertiserUrl('/posts/columns'), [
        'order' => ['status', 'website', 'price', 'id'],
        // Website and Status are not hideable; the request says otherwise.
        'hidden' => ['price', 'website', 'status', 'not_a_column'],
    ])->assertOk();

    $stored = PostGridPreferences::for($user->fresh());

    expect($stored['hidden'])->toBe(['price'])
        ->and(array_slice($stored['order'], 0, 4))->toBe(['status', 'website', 'price', 'id'])
        // A column added to the product later still appears for someone who
        // saved an order before it existed.
        ->and($stored['order'])->toHaveCount(count(PostGridPreferences::COLUMNS));
});

it('returns the drawer payload with the article HTML sanitised', function (): void {
    $user = actionUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    Article::query()->create([
        'post_id' => $post->id,
        'title' => 'Draft',
        // Publisher-authored, and rendered unescaped in the advertiser's
        // authenticated session — the exact shape of a stored XSS.
        'body_html' => '<p>Fine</p><script>alert(document.cookie)</script><a href="javascript:alert(1)">x</a>',
        'word_count' => 1,
        'version' => 1,
    ]);

    $payload = $this->actingAs($user)
        ->getJson(advertiserUrl("/posts/{$post->id}/detail"))
        ->assertOk()
        ->json();

    expect($payload['article']['bodyHtml'])
        ->toContain('<p>Fine</p>')
        ->not->toContain('<script')
        ->not->toContain('javascript:');
});

it('refuses the drawer payload for someone else\'s post', function (): void {
    $mine = actionUser();
    $theirs = actionUser();
    $project = Project::factory()->create(['user_id' => $theirs->id]);
    $post = Post::factory()->create(['user_id' => $theirs->id, 'project_id' => $project->id]);

    $this->actingAs($mine)->getJson(advertiserUrl("/posts/{$post->id}/detail"))->assertForbidden();
});

it('strips script, handlers and unsafe schemes but keeps formatting', function (): void {
    $cases = [
        ['<script>alert(1)</script><p>after</p>', '<p>after</p>'],
        ['<p onclick="alert(1)">click</p>', '<p>click</p>'],
        ['<img src=x onerror=alert(1)>', '<img src="x">'],
        ['<iframe src="//evil"></iframe>', ''],
        ['<style>body{display:none}</style>ok', 'ok'],
        ['<!--[if IE]><script>bad()</script><![endif]-->safe', 'safe'],
        ['<div><span>unwrapped</span></div>', 'unwrapped'],
    ];

    foreach ($cases as [$input, $expected]) {
        expect(HtmlSanitizer::clean($input))->toBe($expected);
    }

    // A scheme with a control character in it is still that scheme.
    expect(HtmlSanitizer::clean("<a href=\"jav\tascript:alert(1)\">x</a>"))->toBe('<a>x</a>');

    // Legitimate links survive, and leave with no handle back to the app.
    expect(HtmlSanitizer::clean('<a href="https://ok.test">link</a>'))
        ->toContain('href="https://ok.test"')
        ->toContain('rel="noopener noreferrer nofollow"');
});
