<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Models\User;

it('redirects an anonymous visitor from the admin panel to the admin login', function (): void {
    $this->get(adminUrl())->assertRedirect(adminUrl('/login'));
});

it('will not let an advertiser session reach the admin panel', function (): void {
    // Separate guards, separate tables: a `web` session is not an admin.
    $this->actingAs(User::factory()->create())
        ->get(adminUrl())
        ->assertRedirect(adminUrl('/login'));
});

it('sends an authenticated admin to the two-factor screen before the panel', function (): void {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(adminUrl())
        ->assertRedirect(adminUrl('/two-factor'));
});

it('serves the panel once two-factor is confirmed for the session', function (): void {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->withSession(['admin.two_factor_confirmed_at' => now()->toIso8601String()])
        ->get(adminUrl())
        ->assertOk();
});

it('sets the hardening headers on every admin response', function (): void {
    $this->get(adminUrl('/login'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('does not expose the admin panel on the advertiser subdomain', function (): void {
    $this->get(advertiserUrl('/asylogin'))->assertNotFound();
});
