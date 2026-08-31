<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('requires authentication', function (): void {
    $this->get(advertiserUrl('/catalog'))->assertRedirect();
});

it('lists approved sites with the catalog-wide metric ranges', function (): void {
    Site::factory()->create(['traffic' => 1_000]);
    Site::factory()->create(['traffic' => 90_000]);
    Site::factory()->pending()->create(['traffic' => 500_000]);

    $this->actingAs(User::factory()->create())
        ->get(advertiserUrl('/catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Catalog/Index')
            ->has('sites.data', 2)
            // The quant-bars scale against approved sites only, so the pending
            // 500k row must not stretch the range.
            ->where('ranges.traffic', [1_000, 90_000]),
        );
});

it('renders the advertiser surface into its own root template', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(advertiserUrl('/catalog'))
        ->assertOk()
        // The advertiser page never references the admin entry point.
        ->assertDontSee('resources/js/admin/main.tsx');
});
