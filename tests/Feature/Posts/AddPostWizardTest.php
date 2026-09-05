<?php

declare(strict_types=1);

use App\Domain\Billing\Models\PromoCode;
use App\Domain\Billing\Models\PromoRedemption;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\PostDraft;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * A project with a folder and one saved landing page — what the wizard's first
 * step is built to walk through.
 *
 * @return array{0: User, 1: Project, 2: ProjectFolder, 3: LandingPage}
 */
function addPostProject(int $walletCents = 1_000_00): array
{
    $user = buyer();

    Wallet::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['available_cents' => $walletCents, 'frozen_cents' => 0, 'currency' => 'USD'],
    );

    $project = Project::factory()->for($user, 'owner')->create([
        'name' => 'Ledgerly',
        'website_url' => 'https://ledgerly.test',
        'publisher_task' => 'House style: plain, no superlatives.',
    ]);

    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id,
        'name' => 'Blog',
        'sort_order' => 0,
        'publisher_task' => 'Blog voice: first person, practical.',
    ]);

    $page = LandingPage::query()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'anchor_text' => 'invoicing software',
        'url' => 'https://ledgerly.test/invoicing',
        'sort_order' => 0,
    ]);

    return [$user->fresh(), $project, $folder, $page];
}

/**
 * The three fields every submission carries, so each test only names what it
 * is actually about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addPostPayload(array $overrides = []): array
{
    return $overrides + [
        'intent' => 'cart',
        'service_type' => ServiceType::ArticlePlacement->value,
        'content_mode' => ContentMode::AdvertiserProvides->value,
    ];
}

it('hands the wizard everything it needs in one request', function (): void {
    [$user, $project, $folder, $page] = addPostProject();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/posts/wizard/options'))
        ->assertOk()
        ->assertJsonPath('projects.0.name', 'Ledgerly')
        ->assertJsonPath('projects.0.folders.0.name', 'Blog')
        // The folder's own brief comes with it: it overrides the project's, and
        // the content step prefills from whichever applies.
        ->assertJsonPath('projects.0.folders.0.publisherTask', 'Blog voice: first person, practical.')
        ->assertJsonPath('projects.0.publisherTask', 'House style: plain, no superlatives.')
        ->assertJsonPath('projects.0.landingPages.0.anchorText', 'invoicing software')
        ->assertJsonPath('projects.0.landingPages.0.folderId', $folder->id)
        // Step 1 warns when a typed URL points somewhere other than the
        // project's own site, and has nothing to compare against without this.
        ->assertJsonPath('projects.0.websiteUrl', 'https://ledgerly.test')
        ->assertJsonPath('wallet.availableCents', 1_000_00)
        ->assertJsonPath('draft', null);

    expect($page->url)->toBe('https://ledgerly.test/invoicing');
});

it('leaves archived projects out of the picker', function (): void {
    [$user] = addPostProject();

    Project::factory()->for($user, 'owner')->create([
        'name' => 'Retired',
        'status' => ProjectStatus::Archived,
    ]);

    // An archived project is read-only, so a post filed under one would be work
    // nobody can act on.
    $names = collect($this->actingAs($user)->getJson(advertiserUrl('/posts/wizard/options'))->json('projects'))
        ->pluck('name');

    expect($names)->toContain('Ledgerly')->not->toContain('Retired');
});

it('pre-filters the picker by the project’s own targeting', function (): void {
    [$user, $project] = addPostProject();

    // site() rather than priced(): the catalog's metrics join is an inner one,
    // so a site nobody has measured is invisible to any search — which is the
    // catalog's own rule, and the picker runs the catalog's own search.
    $wanted = site(['domain' => 'match.test']);
    site(['domain' => 'miss.test']);

    $project->update(['category_id' => $wanted->category_id]);

    $domains = collect(
        $this->actingAs($user)
            ->getJson(advertiserUrl("/posts/wizard/websites?project={$project->id}"))
            ->assertOk()
            ->json('sites'),
    )->pluck('domain');

    expect($domains)->toContain('match.test')->not->toContain('miss.test');

    // And the seeding can be turned off, which is what clearing the filters in
    // the step does.
    $unseeded = collect(
        $this->actingAs($user)
            ->getJson(advertiserUrl("/posts/wizard/websites?project={$project->id}&unseeded=1"))
            ->json('sites'),
    )->pluck('domain');

    expect($unseeded)->toContain('match.test', 'miss.test');
});

it('carries the picker’s own query so the full catalog opens on the same results', function (): void {
    [$user, $project] = addPostProject();
    site();

    $response = $this->actingAs($user)
        ->getJson(advertiserUrl("/posts/wizard/websites?project={$project->id}&q=ledger&dr=40-100"))
        ->assertOk();

    expect($response->json('query.q'))->toBe('ledger')
        ->and($response->json('query.dr'))->toBe('40-100')
        ->and($response->json('query.project'))->toBe($project->id)
        // The quant bars scale against the whole catalog here exactly as they
        // do in the full one.
        ->and($response->json('ranges.domainRating'))->toBeArray();
});

it('serves one site with the terms the summary strip shows', function (): void {
    [$user] = addPostProject();
    $website = priced(200_00, writing: 45_00, attributes: [
        'min_words' => 800,
        'word_count_tiers' => [800, 1500],
    ]);

    $this->actingAs($user)
        ->getJson(advertiserUrl("/posts/wizard/websites/{$website->slug}"))
        ->assertOk()
        ->assertJsonPath('minWords', 800)
        ->assertJsonPath('wordCountTiers', [800, 1500])
        ->assertJsonPath('services.0.priceCents', 200_00)
        ->assertJsonPath('services.0.writingFeeCents', 45_00);
});

it('saves and resumes a draft, and forgets it on request', function (): void {
    [$user] = addPostProject();

    $this->actingAs($user)
        ->patchJson(advertiserUrl('/posts/wizard/draft'), [
            'step' => 3,
            'payload' => ['projectId' => '4', 'website_domain' => 'stackpulse.io', 'project_name' => 'Ledgerly'],
        ])
        ->assertOk()
        ->assertJsonStructure(['saved_at']);

    // The options endpoint is what the modal reads on open, so the draft comes
    // back through the same door the wizard already knocks on.
    $this->actingAs($user)
        ->getJson(advertiserUrl('/posts/wizard/options'))
        ->assertJsonPath('draft.step', 3)
        ->assertJsonPath('draft.payload.projectId', '4');

    // And the dashboard names it, rather than saying "an unfinished post".
    $this->actingAs($user)
        ->get(advertiserUrl('/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('postDraft.step', 3)
            ->where('postDraft.summary', 'stackpulse.io for Ledgerly'),
        );

    $this->actingAs($user)->deleteJson(advertiserUrl('/posts/wizard/draft'))->assertOk();

    expect(PostDraft::query()->count())->toBe(0);
});

it('keeps one advertiser’s draft out of another’s dashboard', function (): void {
    [$user] = addPostProject();

    $this->actingAs($user)->patchJson(advertiserUrl('/posts/wizard/draft'), [
        'step' => 2,
        'payload' => ['projectId' => '1'],
    ]);

    $this->flushSession();

    $this->actingAs(buyer())
        ->get(advertiserUrl('/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('postDraft', null));
});

it('ends at a cart line, the same one the catalog makes', function (): void {
    [$user, $project, $folder, $page] = addPostProject();
    $website = priced(200_00, writing: 45_00);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/wizard'), addPostPayload([
            'project_id' => $project->id,
            'folder_id' => $folder->id,
            'landing_page_id' => $page->id,
            'website_id' => $website->id,
            'body' => str_repeat('word ', 900),
            'title' => 'A draft',
        ]))
        ->assertRedirect();

    // No server flash on this path: the wizard shows its own toast with an
    // "Open cart" action, and a second message would say it twice.
    expect(session('success'))->toBeNull();

    $item = CartItem::query()->firstOrFail();

    expect($item->website_id)->toBe($website->id)
        ->and($item->project_id)->toBe($project->id)
        ->and($item->folder_id)->toBe($folder->id)
        // The saved page's anchor and URL are read from the database, not from
        // the form, so a saved label cannot arrive with somebody else's URL.
        ->and($item->anchor_text)->toBe('invoicing software')
        ->and($item->target_url)->toBe('https://ledgerly.test/invoicing')
        ->and($item->article_word_count)->toBe(900)
        ->and($item->brief)->toBeNull();

    // And it prices like any other line, because it is one.
    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.itemCount', 1)
            ->where('cart.totals.totalCents', 200_00),
        );
});

it('stores the brief when the publisher writes it, and no article', function (): void {
    [$user, $project] = addPostProject();
    $website = priced(200_00, writing: 45_00, attributes: [
        'min_words' => 800,
        'word_count_tiers' => [800, 1500],
    ]);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/wizard'), addPostPayload([
            'project_id' => $project->id,
            'website_id' => $website->id,
            'content_mode' => ContentMode::PublisherWrites->value,
            'anchor_text' => 'invoicing software',
            'target_url' => 'https://ledgerly.test/invoicing',
            'brief' => 'Cover the switch from spreadsheets.',
            'keywords' => 'invoicing, freelance',
            'tone' => 'Practical',
            'target_words' => 1500,
        ]))
        ->assertRedirect();

    $item = CartItem::query()->firstOrFail();

    expect($item->brief['brief'])->toBe('Cover the switch from spreadsheets.')
        ->and($item->brief['tone'])->toBe('Practical')
        ->and($item->brief['target_words'])->toBe(1500)
        // The two are exclusive: a line the publisher writes carries a brief,
        // never an article, or the checkout could not say which was meant.
        ->and($item->article_word_count)->toBeNull()
        // The writing fee applies, priced live like every other fee.
        ->and($item->fresh()->content_mode)->toBe(ContentMode::PublisherWrites);

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.totals.totalCents', 245_00),
        );
});

it('clamps a target length below what the publisher accepts', function (): void {
    [$user, $project] = addPostProject();
    $website = priced(200_00, writing: 45_00, attributes: ['min_words' => 800]);

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'project_id' => $project->id,
        'website_id' => $website->id,
        'content_mode' => ContentMode::PublisherWrites->value,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
        'target_words' => 400,
    ]));

    // 400 words against an 800-word minimum is a placement that comes back
    // rejected. The wizard is the last place that can stop it cheaply.
    expect(CartItem::query()->firstOrFail()->brief['target_words'])->toBe(800);
});

it('reads the words out of an uploaded article', function (): void {
    Storage::fake('local');

    [$user, $project] = addPostProject();
    $website = priced(200_00);

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'project_id' => $project->id,
        'website_id' => $website->id,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
        'article' => UploadedFile::fake()->createWithContent('draft.md', '# Heading

Six more words go here now.'),
        'image' => UploadedFile::fake()->image('hero.jpg'),
    ]))->assertRedirect();

    $item = CartItem::query()->firstOrFail();

    expect($item->article_word_count)->toBe(8)
        ->and($item->article_file_path)->not->toBeNull()
        ->and($item->image_path)->not->toBeNull();
});

it('requires a landing page in one of its two forms', function (): void {
    [$user, $project] = addPostProject();
    $website = priced(200_00);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/wizard'), addPostPayload([
            'project_id' => $project->id,
            'website_id' => $website->id,
        ]))
        ->assertSessionHasErrors('target_url');

    expect(CartItem::query()->count())->toBe(0);
});

it('will not file a post under somebody else’s project or folder', function (): void {
    [$user, $project] = addPostProject();
    $website = priced(200_00);

    $theirs = Project::factory()->for(buyer(), 'owner')->create();
    $theirFolder = ProjectFolder::query()->create([
        'project_id' => $theirs->id,
        'name' => 'Theirs',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'project_id' => $theirs->id,
        'folder_id' => $theirFolder->id,
        'website_id' => $website->id,
        'anchor_text' => 'anchor',
        'target_url' => 'https://ledgerly.test/x',
    ]))->assertRedirect();

    // Neither is taken on trust. The line lands with no project rather than in
    // a stranger's.
    $item = CartItem::query()->firstOrFail();

    expect($item->project_id)->toBeNull()->and($item->folder_id)->toBeNull()
        ->and($project->id)->not->toBe($theirs->id);
});

it('places an order for just this line and leaves the rest of the cart alone', function (): void {
    Notification::fake();

    [$user, $project] = addPostProject();
    $website = priced(200_00);

    // Something already in the cart, from the catalog side.
    $existing = line($user, priced(150_00), ['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/wizard'), addPostPayload([
            'intent' => 'order',
            'project_id' => $project->id,
            'website_id' => $website->id,
            'anchor_text' => 'invoicing software',
            'target_url' => 'https://ledgerly.test/invoicing',
            'body' => str_repeat('word ', 900),
        ]))
        ->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect($order->total_cents)->toBe(200_00)
        ->and(Post::query()->count())->toBe(1)
        ->and(Post::query()->value('website_id'))->toBe($website->id)
        // The rest of the cart survives, which is what makes "place this one
        // now" safe to offer somebody mid-assembly.
        ->and(CartItem::query()->pluck('id')->all())->toBe([$existing->id])
        ->and(Wallet::query()->where('user_id', $user->id)->value('frozen_cents'))->toBe(200_00);

    Notification::assertSentTo($user, OrderPlacedNotification::class);
});

it('does not spend the cart’s promo code on a one-line order', function (): void {
    Notification::fake();

    [$user, $project] = addPostProject();
    line($user, priced(150_00), ['project_id' => $project->id]);

    PromoCode::query()->create([
        'code' => 'SPRING20',
        'percent_off' => 20,
        'minimum_spend_cents' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(advertiserUrl('/cart/promo'), ['code' => 'SPRING20']);

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'intent' => 'order',
        'project_id' => $project->id,
        'website_id' => priced(200_00)->id,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
    ]));

    // The code belongs to the cart the advertiser assembled. Burning a
    // one-per-advertiser code on a single placement they did not choose to
    // spend it on is worse than not applying it.
    $order = Order::query()->firstOrFail();

    expect($order->discount_cents)->toBe(0)
        ->and($order->total_cents)->toBe(200_00)
        ->and(PromoRedemption::query()->count())->toBe(0)
        // And it is still on the cart, for the order the buyer is assembling.
        ->and(Cart::query()->value('promo_code_id'))->not->toBeNull();
});

it('leaves the line in the cart when the balance will not cover it', function (): void {
    Notification::fake();

    [$user, $project] = addPostProject(walletCents: 50_00);
    $website = priced(200_00);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/wizard'), addPostPayload([
            'intent' => 'order',
            'project_id' => $project->id,
            'website_id' => $website->id,
            'anchor_text' => 'invoicing software',
            'target_url' => 'https://ledgerly.test/invoicing',
        ]))
        ->assertRedirect()
        ->assertSessionHas('error');

    // Nothing bought, nothing charged — and the work survives as a cart line
    // one top-up away from being an order.
    expect(Order::query()->count())->toBe(0)
        ->and(Post::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(1)
        ->and(Wallet::query()->where('user_id', $user->id)->value('available_cents'))->toBe(50_00);

    Notification::assertNothingSent();
});

it('clears the draft once the wizard has produced a real line', function (): void {
    [$user, $project] = addPostProject();

    $this->actingAs($user)->patchJson(advertiserUrl('/posts/wizard/draft'), [
        'step' => 4,
        'payload' => ['projectId' => (string) $project->id],
    ]);

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'project_id' => $project->id,
        'website_id' => priced(200_00)->id,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
    ]))->assertRedirect();

    // A draft that survived would offer to recreate a line that already exists.
    expect(PostDraft::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(1);
});

it('carries the brief onto the post when the order is placed', function (): void {
    Notification::fake();

    [$user, $project] = addPostProject();

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'intent' => 'order',
        'project_id' => $project->id,
        'website_id' => priced(200_00, writing: 45_00)->id,
        'content_mode' => ContentMode::PublisherWrites->value,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
        'brief' => 'Cover the switch from spreadsheets.',
        'tone' => 'Practical',
    ]))->assertRedirect();

    $post = Post::query()->firstOrFail();

    // The instructions travel with the placement rather than being left behind
    // in a cart row that is about to be deleted.
    expect($post->brief['brief'])->toBe('Cover the switch from spreadsheets.')
        ->and($post->brief['tone'])->toBe('Practical')
        // A publisher-written line needs nothing from the buyer, so it goes
        // straight into the queue.
        ->and($post->status)->toBe(PostStatus::New);
});

it('leaves a post with no article as a draft, ordered and paid for', function (): void {
    Notification::fake();

    [$user, $project] = addPostProject();

    $this->actingAs($user)->post(advertiserUrl('/posts/wizard'), addPostPayload([
        'intent' => 'order',
        'project_id' => $project->id,
        'website_id' => priced(200_00)->id,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
    ]))->assertRedirect();

    // "I'll write it later" is a legitimate answer: the order goes through, the
    // money is frozen, and the post waits for the copy.
    expect(Post::query()->value('status'))->toBe(PostStatus::Draft)
        ->and(Wallet::query()->where('user_id', $user->id)->value('frozen_cents'))->toBe(200_00);
});

it('refuses a site that has withdrawn the service', function (): void {
    [$user, $project] = addPostProject();
    $website = Website::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(advertiserUrl('/posts/wizard'), addPostPayload([
            'project_id' => $project->id,
            'website_id' => $website->id,
            'anchor_text' => 'invoicing software',
            'target_url' => 'https://ledgerly.test/invoicing',
        ]))
        ->assertSessionHasErrors('website_id');

    expect(CartItem::query()->count())->toBe(0)
        ->and(WebsitePrice::query()->where('website_id', $website->id)->count())->toBe(0);
});
