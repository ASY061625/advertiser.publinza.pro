<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Site;
use Inertia\Testing\AssertableInertia;

it('renders the marketing home page on the apex domain', function (): void {
    Site::factory()->count(3)->create();

    $this->get(marketingUrl('/'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->where('siteCount', 3),
        );
});

it('does not serve advertiser routes on the apex domain', function (): void {
    $this->get(marketingUrl('/catalog'))->assertNotFound();
});
