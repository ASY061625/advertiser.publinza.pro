<?php

declare(strict_types=1);

use App\Domain\Identity\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('answers identically whether or not the address has an account', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'dana@northwind.test']);

    $known = $this->post(advertiserUrl('/forgot-password'), ['email' => 'dana@northwind.test']);
    $unknown = $this->post(advertiserUrl('/forgot-password'), ['email' => 'nobody@northwind.test']);

    // Same status, same message: the form cannot be used to find out who is a
    // customer.
    expect($known->getStatusCode())->toBe($unknown->getStatusCode());
    expect(session('status'))->toContain('If that address has an account');

    $known->assertSessionHasNoErrors();
    $unknown->assertSessionHasNoErrors();
});

it('emails a link only to a real account', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'dana@northwind.test']);

    $this->post(advertiserUrl('/forgot-password'), ['email' => 'dana@northwind.test']);
    $this->post(advertiserUrl('/forgot-password'), ['email' => 'nobody@northwind.test']);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    Notification::assertCount(1);
});

it('sets the new password and rotates the remember token', function (): void {
    $user = User::factory()->create(['email' => 'dana@northwind.test', 'password' => 'old-password-1']);
    $before = $user->remember_token;
    $token = Password::broker()->createToken($user);

    $this->post(advertiserUrl('/reset-password'), [
        'token' => $token,
        'email' => 'dana@northwind.test',
        'password' => 'brand-new-horse-9',
        'password_confirmation' => 'brand-new-horse-9',
    ])->assertRedirect(advertiserUrl('/login'));

    $user->refresh();

    expect(Hash::check('brand-new-horse-9', $user->password))->toBeTrue()
        // Every "remember me" cookie already issued stops working.
        ->and($user->remember_token)->not->toBe($before);
});

it('drops every trusted device on reset', function (): void {
    $user = User::factory()->create(['email' => 'dana@northwind.test']);
    TrustedDevice::query()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'whatever'),
        'expires_at' => now()->addDays(30),
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(advertiserUrl('/reset-password'), [
        'token' => $token,
        'email' => 'dana@northwind.test',
        'password' => 'brand-new-horse-9',
        'password_confirmation' => 'brand-new-horse-9',
    ]);

    // Whoever forced the reset may be sitting on a browser that was trusted.
    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('will not let a reset token be used twice', function (): void {
    $user = User::factory()->create(['email' => 'dana@northwind.test']);
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'dana@northwind.test',
        'password' => 'brand-new-horse-9',
        'password_confirmation' => 'brand-new-horse-9',
    ];

    $this->post(advertiserUrl('/reset-password'), $payload)->assertRedirect(advertiserUrl('/login'));

    $this->post(advertiserUrl('/reset-password'), [...$payload, 'password' => 'second-attempt-9', 'password_confirmation' => 'second-attempt-9'])
        ->assertSessionHasErrors('email');

    expect(Hash::check('brand-new-horse-9', $user->fresh()->password))->toBeTrue();
});

it('rejects a token past its 60-minute life', function (): void {
    $user = User::factory()->create(['email' => 'dana@northwind.test']);
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $this->post(advertiserUrl('/reset-password'), [
        'token' => $token,
        'email' => 'dana@northwind.test',
        'password' => 'brand-new-horse-9',
        'password_confirmation' => 'brand-new-horse-9',
    ])->assertSessionHasErrors('email');
});
