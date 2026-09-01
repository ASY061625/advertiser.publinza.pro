<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    RateLimiter::clear('verify-resend:1');
});

it('verifies through the signed link', function (): void {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(advertiserUrl('/dashboard'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('refuses an unsigned or tampered link', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(advertiserUrl("/verify-email/{$user->id}/".sha1($user->email)))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends at most once a minute', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(advertiserUrl('/verify-email/resend'))->assertRedirect();
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

    // A second request inside the window is answered, but sends nothing.
    $this->actingAs($user)->post(advertiserUrl('/verify-email/resend'))->assertRedirect();
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

    $this->travel(61)->seconds();

    $this->actingAs($user)->post(advertiserUrl('/verify-email/resend'));
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);
});

it('sends a verified advertiser away from the notice', function (): void {
    $user = User::factory()->create(['email_verified_at' => now(), 'last_login_at' => now()]);

    $this->actingAs($user)->get(advertiserUrl('/verify-email'))->assertRedirect(advertiserUrl('/dashboard'));
});
