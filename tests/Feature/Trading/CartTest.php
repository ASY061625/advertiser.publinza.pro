<?php

declare(strict_types=1);

use App\Domain\Billing\Models\PromoCode;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Models\CartItem;
use Inertia\Testing\AssertableInertia;

it('groups the cart by project and subtotals each group', function (): void {
    $user = buyer();
    $spring = Project::factory()->for($user, 'owner')->create(['name' => 'Spring launch']);
    $autumn = Project::factory()->for($user, 'owner')->create(['name' => 'Autumn push']);

    line($user, priced(100_00), ['project_id' => $spring->id]);
    line($user, priced(150_00), ['project_id' => $spring->id]);
    line($user, priced(80_00), ['project_id' => $autumn->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Cart/Index')
            ->has('cart.groups', 2)
            // Sorted by project name, so the order does not shuffle between
            // loads on whatever the database felt like returning first.
            ->where('cart.groups.0.project.name', 'Autumn push')
            ->where('cart.groups.0.subtotalCents', 80_00)
            ->where('cart.groups.1.project.name', 'Spring launch')
            ->where('cart.groups.1.subtotalCents', 250_00)
            ->where('cart.itemCount', 3),
        );
});

it('itemises the two optional fees rather than folding them into the price', function (): void {
    $user = buyer();
    $website = priced(200_00, writing: 45_00, express: 30_00);

    line($user, $website, [
        'content_mode' => ContentMode::PublisherWrites,
        'express' => true,
    ]);

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.groups.0.items.0.baseCents', 200_00)
            ->where('cart.groups.0.items.0.writingFeeCents', 45_00)
            ->where('cart.groups.0.items.0.expressFeeCents', 30_00)
            ->where('cart.groups.0.items.0.totalCents', 275_00)
            // The summary keeps them apart too: the subtotal is base prices, so
            // the fee lines under it read as additions rather than as a
            // breakdown of a number that already contains them.
            ->where('cart.totals.subtotalCents', 200_00)
            ->where('cart.totals.writingFeesCents', 45_00)
            ->where('cart.totals.expressFeesCents', 30_00)
            ->where('cart.totals.totalCents', 275_00),
        );
});

it('charges the live price and says what the line was quoted', function (): void {
    $user = buyer();
    $website = priced(100_00);

    line($user, $website);

    // The publisher raises their price after the line was added.
    WebsitePrice::query()->where('website_id', $website->id)->update(['price_cents' => 130_00]);

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.groups.0.items.0.totalCents', 130_00)
            ->where('cart.groups.0.items.0.quotedCents', 100_00)
            ->where('cart.totals.totalCents', 130_00),
        );
});

it('says nothing about the price when it has not moved', function (): void {
    $user = buyer();
    line($user, priced(100_00));

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.groups.0.items.0.quotedCents', null),
        );
});

it('warns about a topic the site refuses and a placement made recently', function (): void {
    $user = buyer();
    $topic = SensitiveTopic::factory()->create([
        'name' => 'Crypto',
        'slug' => 'crypto',
    ]);

    $project = Project::factory()->for($user, 'owner')->create();
    $project->sensitiveTopics()->attach($topic->id);

    $website = priced(100_00, attributes: ['accepts_sensitive_topics' => []]);

    Post::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'website_id' => $website->id,
        'created_at' => now()->subDays(6),
    ]);

    line($user, $website, ['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(function (AssertableInertia $page): void {
            $warnings = collect($page->toArray()['props']['cart']['groups'][0]['items'][0]['warnings']);

            expect($warnings->pluck('kind')->all())->toContain('topic', 'duplicate')
                ->and($warnings->firstWhere('kind', 'topic')['message'])->toContain('Crypto')
                ->and($warnings->firstWhere('kind', 'duplicate')['message'])->toContain('6 days ago');
        });
});

it('keeps a dismissed warning dismissed', function (): void {
    $user = buyer();
    $topic = SensitiveTopic::factory()->create(['name' => 'Crypto', 'slug' => 'crypto']);
    $project = Project::factory()->for($user, 'owner')->create();
    $project->sensitiveTopics()->attach($topic->id);

    $item = line($user, priced(100_00, attributes: ['accepts_sensitive_topics' => []]), [
        'project_id' => $project->id,
    ]);

    $this->actingAs($user)->post(advertiserUrl("/cart/{$item->id}/dismiss"), ['kind' => 'topic']);

    // It stays dismissed across loads, because a warning that reappears every
    // time is one people learn to scroll past — and by then the strip has
    // stopped working for the warnings that do matter.
    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('cart.groups.0.items.0.warnings', 0),
        );
});

it('warns loudly when a publisher has withdrawn the service', function (): void {
    $user = buyer();
    $website = priced(100_00);

    line($user, $website);
    WebsitePrice::query()->where('website_id', $website->id)->delete();

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(function (AssertableInertia $page): void {
            $warnings = collect($page->toArray()['props']['cart']['groups'][0]['items'][0]['warnings']);

            expect($warnings->pluck('kind')->all())->toContain('unavailable');
        });
});

it('removes and moves several lines at once, and drops the old folder', function (): void {
    $user = buyer();
    $from = Project::factory()->for($user, 'owner')->create();
    $to = Project::factory()->for($user, 'owner')->create(['name' => 'Somewhere else']);
    $folder = ProjectFolder::query()->create(['project_id' => $from->id, 'name' => 'Blog', 'sort_order' => 0]);

    $keep = line($user, priced(100_00), ['project_id' => $from->id, 'folder_id' => $folder->id]);
    $drop = line($user, priced(120_00), ['project_id' => $from->id]);

    $this->actingAs($user)->post(advertiserUrl('/cart/bulk'), [
        'action' => 'move',
        'ids' => [$keep->id],
        'project_id' => $to->id,
    ])->assertRedirect();

    // The folder belongs to the old project, so it cannot come along — left
    // set, the checkout would file the post into a folder in a project the post
    // is no longer part of.
    expect($keep->fresh()->project_id)->toBe($to->id)
        ->and($keep->fresh()->folder_id)->toBeNull();

    $this->actingAs($user)->post(advertiserUrl('/cart/bulk'), [
        'action' => 'remove',
        'ids' => [$drop->id],
    ])->assertRedirect();

    expect(CartItem::query()->find($drop->id))->toBeNull();
});

it('touches nothing when a bulk action names another advertiser’s lines', function (): void {
    $user = buyer();
    $stranger = buyer();

    line($user, priced(100_00));
    $theirs = line($stranger, priced(100_00));

    $this->actingAs($user)->post(advertiserUrl('/cart/bulk'), [
        'action' => 'remove',
        'ids' => [$theirs->id],
    ])->assertRedirect();

    expect(CartItem::query()->find($theirs->id))->not->toBeNull();
});

it('will not move a line into a project owned by somebody else', function (): void {
    $user = buyer();
    $theirs = Project::factory()->for(buyer(), 'owner')->create();
    $item = line($user, priced(100_00));

    $this->actingAs($user)->post(advertiserUrl('/cart/bulk'), [
        'action' => 'move',
        'ids' => [$item->id],
        'project_id' => $theirs->id,
    ])->assertRedirect();

    expect($item->fresh()->project_id)->toBeNull();
});

it('applies a promo code and names the reason when it cannot', function (): void {
    $user = buyer();
    line($user, priced(200_00));

    PromoCode::query()->create([
        'code' => 'SPRING20',
        'percent_off' => 20,
        'minimum_spend_cents' => 0,
        'is_active' => true,
    ]);

    PromoCode::query()->create([
        'code' => 'BIGSPEND',
        'percent_off' => 50,
        'minimum_spend_cents' => 500_00,
        'is_active' => true,
    ]);

    // A code with a minimum says how much more is needed, rather than "invalid".
    $this->actingAs($user)
        ->post(advertiserUrl('/cart/promo'), ['code' => 'BIGSPEND'])
        ->assertSessionHasErrors('code');

    expect(session('errors')->first('code'))->toContain('$300.00');

    $this->actingAs($user)->post(advertiserUrl('/cart/promo'), ['code' => 'SPRING20'])->assertRedirect();

    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.promo.code', 'SPRING20')
            ->where('cart.totals.discountCents', 40_00)
            ->where('cart.totals.totalCents', 160_00),
        );
});

it('stops applying a code that expires while it sits on the cart', function (): void {
    $user = buyer();
    line($user, priced(200_00));

    $promo = PromoCode::query()->create([
        'code' => 'SHORTLIVED',
        'percent_off' => 25,
        'minimum_spend_cents' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(advertiserUrl('/cart/promo'), ['code' => 'SHORTLIVED']);

    $promo->update(['ends_at' => now()->subDay()]);

    // The total goes back up, and the card says why rather than letting the
    // number move on its own between one page load and the next.
    $this->actingAs($user)
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.promo.expired', true)
            ->where('cart.totals.discountCents', 0)
            ->where('cart.totals.totalCents', 200_00),
        );
});

it('survives a logout, because it lives on the server', function (): void {
    $user = buyer();
    line($user, priced(100_00));

    $this->actingAs($user)->post(advertiserUrl('/logout'));

    $this->actingAs($user->fresh())
        ->get(advertiserUrl('/cart'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('cart.itemCount', 1));
});
