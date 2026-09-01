<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\LoginAttempt;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

function advertiser(array $attributes = []): User
{
    return User::factory()->create([
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    Cache::flush();
});

it('sends a returning advertiser to the dashboard', function (): void {
    advertiser(['last_login_at' => now()->subWeek()]);

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertRedirect(advertiserUrl('/dashboard'));

    $this->assertAuthenticated();
});

it('sends a first-ever sign-in to project creation instead', function (): void {
    // last_login_at is null on a brand-new account, and there is nothing on the
    // dashboard for someone with no projects.
    advertiser(['last_login_at' => null]);

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertRedirect(advertiserUrl('/projects/create'));
});

it('stamps the sign-in so the next one is not treated as the first', function (): void {
    $user = advertiser(['last_login_at' => null]);

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->last_login_ip)->not->toBeNull();
});

it('regenerates the session id on sign-in', function (): void {
    advertiser();

    $this->get(advertiserUrl('/login'));
    $before = session()->getId();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    // A session fixed before sign-in is worthless afterwards.
    expect(session()->getId())->not->toBe($before);
});

it('gives the same message whether the account exists or the password is wrong', function (): void {
    advertiser();

    $wrongPassword = $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'not-the-password',
    ]);

    $noAccount = $this->post(advertiserUrl('/login'), [
        'email' => 'nobody@northwind.test',
        'password' => 'not-the-password',
    ]);

    $message = 'That email and password do not match an account. Check both, or reset your password.';

    $wrongPassword->assertSessionHasErrors(['email' => $message]);
    $noAccount->assertSessionHasErrors(['email' => $message]);
    $this->assertGuest();
});

it('refuses a suspended account with a reason', function (): void {
    advertiser(['status' => UserStatus::Suspended]);

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs every attempt with its outcome, address and browser', function (): void {
    advertiser();

    $this->withHeader('User-Agent', 'PublinzaTest/1.0')
        ->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'wrong']);

    $this->withHeader('User-Agent', 'PublinzaTest/1.0')
        ->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'Correct-Horse-9']);

    $attempts = LoginAttempt::query()->orderBy('id')->get();

    expect($attempts)->toHaveCount(2)
        ->and($attempts[0]->successful)->toBeFalse()
        ->and($attempts[1]->successful)->toBeTrue()
        ->and($attempts[0]->ip_address)->not->toBeNull()
        ->and($attempts[0]->user_agent)->toBe('PublinzaTest/1.0')
        ->and($attempts[0]->email)->toBe('dana@northwind.test');
});

it('locks the account for 15 minutes after five failures and emails about it', function (): void {
    Notification::fake();
    $user = advertiser();

    for ($i = 0; $i < 5; $i++) {
        $this->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'wrong']);
    }

    // The correct password does not get in while the lockout stands.
    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    Notification::assertSentTo($user, AccountLockedNotification::class);
});

it('emails about a lockout once, not once per further attempt', function (): void {
    Notification::fake();
    $user = advertiser();

    for ($i = 0; $i < 9; $i++) {
        $this->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'wrong']);
    }

    Notification::assertSentToTimes($user, AccountLockedNotification::class, 1);
});

it('does not email a stranger about an account they do not have', function (): void {
    Notification::fake();

    for ($i = 0; $i < 6; $i++) {
        $this->post(advertiserUrl('/login'), ['email' => 'nobody@northwind.test', 'password' => 'wrong']);
    }

    Notification::assertNothingSent();
});

it('clears the lockout allowance on a successful sign-in', function (): void {
    advertiser();

    foreach (range(1, 4) as $ignored) {
        $this->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'wrong']);
    }

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertRedirect();

    $this->post(advertiserUrl('/logout'));

    // Four more failures would trip the lockout if the counter had not reset.
    foreach (range(1, 4) as $ignored) {
        $this->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'wrong']);
    }

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertRedirect(advertiserUrl('/dashboard'));
});

it('signs out and invalidates the session', function (): void {
    $user = advertiser(['last_login_at' => now()->subDay()]);

    $this->actingAs($user)->post(advertiserUrl('/logout'))->assertRedirect(advertiserUrl('/login'));

    $this->assertGuest();
});
