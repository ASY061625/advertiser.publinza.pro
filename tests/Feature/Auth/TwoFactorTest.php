<?php

declare(strict_types=1);

use App\Domain\Identity\Models\TrustedDevice;
use App\Domain\Identity\Support\RecoveryCodes;
use App\Domain\Identity\Support\TrustedDevices;
use App\Domain\Identity\Support\TwoFactor;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

function withTwoFactor(): array
{
    $user = User::factory()->create([
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
        'email_verified_at' => now(),
        'last_login_at' => now()->subWeek(),
    ]);

    $twoFactor = app(TwoFactor::class);
    $secret = $twoFactor->generateSecret($user);
    $twoFactor->confirm($user);

    return [$user->fresh(), $secret];
}

function currentCode(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

beforeEach(function (): void {
    Cache::flush();
});

it('stops at the challenge instead of signing in', function (): void {
    withTwoFactor();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertRedirect(advertiserUrl('/two-factor-challenge'));

    // A stolen password alone gets nowhere.
    $this->assertGuest();
});

it('completes the sign-in with a valid code', function (): void {
    [, $secret] = withTwoFactor();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    $this->post(advertiserUrl('/two-factor-challenge'), ['code' => currentCode($secret)])
        ->assertRedirect(advertiserUrl('/dashboard'));

    $this->assertAuthenticated();
});

it('rejects a wrong code and says what to do', function (): void {
    withTwoFactor();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    $this->post(advertiserUrl('/two-factor-challenge'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(strtolower((string) session('errors')->first('code')))->not->toContain('invalid');
});

it('generates exactly eight recovery codes and stores them hashed', function (): void {
    [$user] = withTwoFactor();

    $codes = RecoveryCodes::generate($user);

    expect($codes)->toHaveCount(8)
        ->and(RecoveryCodes::remaining($user->fresh()))->toBe(8)
        // The plaintext is not recoverable from the column.
        ->and($user->fresh()->two_factor_recovery_codes)->not->toContain($codes[0]);
});

it('spends a recovery code once and only once', function (): void {
    [$user] = withTwoFactor();
    $codes = RecoveryCodes::generate($user);

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    $this->post(advertiserUrl('/two-factor-challenge'), ['code' => $codes[0]])
        ->assertRedirect(advertiserUrl('/dashboard'));

    expect(RecoveryCodes::remaining($user->fresh()))->toBe(7);

    $this->post(advertiserUrl('/logout'));
    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    // The same code a second time is worthless.
    $this->post(advertiserUrl('/two-factor-challenge'), ['code' => $codes[0]])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('skips the challenge on a device the advertiser chose to trust', function (): void {
    [$user, $secret] = withTwoFactor();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    $response = $this->post(advertiserUrl('/two-factor-challenge'), [
        'code' => currentCode($secret),
        'trust_device' => true,
    ]);

    $response->assertRedirect();
    $cookie = $response->getCookie(TrustedDevices::COOKIE, false);
    expect($cookie)->not->toBeNull();

    // Only the hash is stored, never the token itself.
    $device = TrustedDevice::query()->where('user_id', $user->id)->first();
    expect($device)->not->toBeNull()
        ->and($device->token_hash)->not->toBe($cookie->getValue())
        // Carbon 3 signs the difference, so measure forwards from now.
        ->and(now()->diffInDays($device->expires_at))->toBeGreaterThan(29);

    $this->post(advertiserUrl('/logout'));

    // Second sign-in on the same browser goes straight through. withCookie()
    // encrypts what it is given, so hand it the decrypted token — passing the
    // response's raw value would encrypt an already-encrypted string and the
    // app would decrypt one layer down to ciphertext.
    $this->withCookie(TrustedDevices::COOKIE, $response->getCookie(TrustedDevices::COOKIE)->getValue())
        ->post(advertiserUrl('/login'), [
            'email' => 'dana@northwind.test',
            'password' => 'Correct-Horse-9',
        ])->assertRedirect(advertiserUrl('/dashboard'));
});

it('does not trust the device unless asked', function (): void {
    [$user, $secret] = withTwoFactor();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    $this->post(advertiserUrl('/two-factor-challenge'), ['code' => currentCode($secret)]);

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('is not enforced until a code has been confirmed', function (): void {
    $user = User::factory()->create([
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
        'email_verified_at' => now(),
        'last_login_at' => now()->subWeek(),
    ]);

    // Setup started but never confirmed: a half-finished setup must not lock
    // anyone out of their own account.
    app(TwoFactor::class)->generateSecret($user);

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ])->assertRedirect(advertiserUrl('/dashboard'));
});

it('drops trusted devices when two-factor is turned off', function (): void {
    [$user, $secret] = withTwoFactor();

    $this->post(advertiserUrl('/login'), ['email' => 'dana@northwind.test', 'password' => 'Correct-Horse-9']);
    $this->post(advertiserUrl('/two-factor-challenge'), ['code' => currentCode($secret), 'trust_device' => true]);

    app(TwoFactor::class)->disable($user->fresh());

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('keeps the device trusted across an ordinary sign-out', function (): void {
    [$user, $secret] = withTwoFactor();

    $this->post(advertiserUrl('/login'), [
        'email' => 'dana@northwind.test',
        'password' => 'Correct-Horse-9',
    ]);

    $response = $this->post(advertiserUrl('/two-factor-challenge'), [
        'code' => currentCode($secret),
        'trust_device' => true,
    ]);

    $token = $response->getCookie(TrustedDevices::COOKIE)->getValue();

    // Signing out on this browser must not void a 30-day promise: the device
    // is still trusted afterwards. Revocation belongs to a password reset or
    // to the advertiser removing the device deliberately.
    $this->withCookie(TrustedDevices::COOKIE, $token)->post(advertiserUrl('/logout'));

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1);

    $this->withCookie(TrustedDevices::COOKIE, $token)
        ->post(advertiserUrl('/login'), [
            'email' => 'dana@northwind.test',
            'password' => 'Correct-Horse-9',
        ])->assertRedirect(advertiserUrl('/dashboard'));
});
