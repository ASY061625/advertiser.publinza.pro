<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use Inertia\Testing\AssertableInertia;

it('renders the marketing home page on the apex domain', function (): void {
    Website::factory()->count(3)->create();
    Website::factory()->pending()->create();

    $this->get(marketingUrl('/'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            // Pending sites are not part of the public count.
            ->where('siteCount', 3),
        );
});

it('does not serve advertiser routes on the apex domain', function (): void {
    $this->get(marketingUrl('/catalog'))->assertNotFound();
});
