<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Country;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('creates an advertiser with a zero-balance wallet and a cart', function (): void {
    Event::fake([Registered::class]);
    Country::query()->create(['code' => 'IE', 'name' => 'Ireland', 'region' => 'Europe']);

    $this->post(advertiserUrl('/signup'), [
        'name' => 'Dana Okafor',
        'email' => 'Dana@Northwind.test',
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
        'company' => 'Northwind Software',
        'country' => 'IE',
        'referrer_source' => 'referral',
        'terms' => true,
    ])->assertRedirect(advertiserUrl('/verify-email'));

    $user = User::query()->firstWhere('email', 'dana@northwind.test');

    expect($user)->not->toBeNull()
        // The address is normalised, so a duplicate cannot slip in by casing.
        ->and($user->email)->toBe('dana@northwind.test')
        ->and($user->referrer_source)->toBe('referral')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->wallet)->not->toBeNull()
        ->and($user->wallet->available_cents)->toBe(0)
        ->and($user->wallet->frozen_cents)->toBe(0)
        ->and($user->cart)->not->toBeNull();

    Event::assertDispatched(Registered::class);
});

it('hashes the password with argon2id', function (): void {
    Event::fake([Registered::class]);

    $this->post(advertiserUrl('/signup'), [
        'name' => 'Dana Okafor',
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
        'terms' => true,
    ]);

    $user = User::query()->firstWhere('email', 'dana@northwind.test');

    expect($user->password)->toStartWith('$argon2id$')
        ->and(Hash::check('Correct-Horse-9', $user->password))->toBeTrue();
});

it('signs the new advertiser in but leaves them unverified', function (): void {
    Event::fake([Registered::class]);

    $this->post(advertiserUrl('/signup'), [
        'name' => 'Dana Okafor',
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
        'terms' => true,
    ]);

    $this->assertAuthenticated();

    // Verified-only routes bounce back to the notice until the address is confirmed.
    $this->get(advertiserUrl('/dashboard'))->assertRedirect(advertiserUrl('/verify-email'));
});

it('explains what is wrong rather than saying the input is invalid', function (): void {
    $response = $this->post(advertiserUrl('/signup'), [
        'name' => '',
        'email' => 'not-an-address',
        'password' => 'short',
        'password_confirmation' => 'different',
        'terms' => false,
    ]);

    $errors = session('errors');

    expect($errors->first('name'))->toBe('Enter your name so we know who to address.')
        ->and($errors->first('email'))->toContain('check for a typo')
        ->and($errors->first('terms'))->toBe('Tick the box to accept the terms and privacy policy.');

    foreach (['name', 'email', 'password', 'terms'] as $field) {
        expect(strtolower((string) $errors->first($field)))->not->toContain('invalid');
    }
});

it('refuses a duplicate address and points to signing in', function (): void {
    User::factory()->create(['email' => 'dana@northwind.test']);

    $this->post(advertiserUrl('/signup'), [
        'name' => 'Dana Okafor',
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
        'terms' => true,
    ])->assertSessionHasErrors(['email' => 'There is already an account with this address. Sign in instead, or reset the password.']);
});

it('has no publisher signup anywhere', function (): void {
    // There is one account type in this product. These are the paths a
    // publisher-facing product would have.
    foreach (['/publisher/signup', '/publishers/register', '/register'] as $path) {
        $this->get(advertiserUrl($path))->assertNotFound();
    }
});
