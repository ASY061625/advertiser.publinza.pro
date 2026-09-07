<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Wallet;
use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Enums\TokenAbility;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\NotificationPreference;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Identity\Support\DeviceLabel;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Identity\Support\TwoFactor;
use App\Domain\Identity\Support\VatNumber;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\System\Models\AuditLog;
use App\Notifications\EmailChangeVerificationNotification;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

// ------------------------------------------------------------------ the page

it('opens on account and deep-links to each tab', function (): void {
    $user = buyer();

    expect(props($this->actingAs($user)->get(advertiserUrl('/profile')))['tab'])->toBe('account');

    foreach (['company', 'security', 'notifications', 'api'] as $tab) {
        $response = $this->actingAs($user)->get(advertiserUrl("/profile?tab={$tab}"));

        $response->assertOk();
        expect(props($response)['tab'])->toBe($tab);
    }
});

it('sends the old two-factor address to the security tab', function (): void {
    $this->actingAs(buyer())
        ->get(advertiserUrl('/settings/two-factor'))
        ->assertRedirect('/profile?tab=security');
});

// -------------------------------------------------------------- the account

it('saves the account and leaves the email alone', function (): void {
    Notification::fake();

    $user = buyer();

    $this->actingAs($user)->patch(advertiserUrl('/profile/account'), [
        'name' => 'Dana Okafor',
        'display_name' => 'Dana',
        'email' => $user->email,
        'phone' => '7700 900123',
        'phone_country' => 'gb',
        'timezone' => 'Europe/London',
        'locale' => 'en',
        'date_format' => 'euro',
        'number_format' => 'space',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Dana Okafor')
        ->and($user->display_name)->toBe('Dana')
        ->and($user->phone_country)->toBe('GB')
        ->and($user->date_format)->toBe('euro')
        ->and($user->pending_email)->toBeNull();

    Notification::assertNothingSent();
});

it('holds a new email until it is confirmed from its own inbox', function (): void {
    Notification::fake();

    $user = buyer();
    $original = $user->email;

    $this->actingAs($user)->patch(advertiserUrl('/profile/account'), [
        'name' => $user->name,
        'email' => 'new@example.com',
        'timezone' => 'UTC',
        'locale' => 'en',
        'date_format' => 'medium',
        'number_format' => 'plain',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $user->refresh();

    // The account is untouched. A typo here cannot lock anybody out, and a
    // hijacked session cannot redirect password resets.
    expect($user->email)->toBe($original)
        ->and($user->pending_email)->toBe('new@example.com');

    /*
     * The recipient, not just the fact one was sent.
     *
     * assertSentTo() passes whatever address the mail is routed to, and this
     * message going to the old inbox would confirm nothing — it would let
     * whoever holds the session move the account on their own say-so.
     */
    Notification::assertSentTo(
        $user,
        EmailChangeVerificationNotification::class,
        fn (EmailChangeVerificationNotification $mail): bool => $mail->routeNotificationForMail($user) === 'new@example.com',
    );
});

it('confirms an email change and refuses a stale or wrong token', function (): void {
    $user = buyer();
    $original = $user->email;

    $this->actingAs($user)->patch(advertiserUrl('/profile/account'), [
        'name' => $user->name,
        'email' => 'moved@example.com',
        'timezone' => 'UTC',
        'locale' => 'en',
        'date_format' => 'medium',
        'number_format' => 'plain',
    ]);

    $this->actingAs($user)->get(advertiserUrl('/profile/email/not-the-token'))->assertRedirect('/profile');
    expect($user->fresh()->email)->toBe($original);

    // The plaintext only ever exists in the notification, so the test mints a
    // known one the same way the action does.
    $token = 'a-known-token';
    $user->forceFill(['pending_email_token' => hash('sha256', $token)])->save();

    $this->actingAs($user)->get(advertiserUrl("/profile/email/{$token}"))->assertRedirect('/profile');

    expect($user->fresh()->email)->toBe('moved@example.com')
        ->and($user->fresh()->pending_email)->toBeNull()
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

it('expires an email confirmation link', function (): void {
    $user = buyer();
    $token = 'a-known-token';

    $user->forceFill([
        'pending_email' => 'late@example.com',
        'pending_email_token' => hash('sha256', $token),
        'pending_email_sent_at' => now()->subHours(3),
    ])->save();

    $this->actingAs($user)->get(advertiserUrl("/profile/email/{$token}"));

    expect($user->fresh()->email)->not->toBe('late@example.com');
});

it('will not move to an address another account already has', function (): void {
    $taken = buyer();

    $this->flushSession();

    $mine = buyer();

    $this->actingAs($mine)->patch(advertiserUrl('/profile/account'), [
        'name' => $mine->name,
        'email' => $taken->email,
        'timezone' => 'UTC',
        'locale' => 'en',
        'date_format' => 'medium',
        'number_format' => 'plain',
    ])->assertSessionHasErrors('email');

    expect($mine->fresh()->pending_email)->toBeNull();
});

it('rejects a format it does not know how to render', function (): void {
    $user = buyer();

    $this->actingAs($user)->patch(advertiserUrl('/profile/account'), [
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => 'UTC',
        'locale' => 'en',
        'date_format' => "d/M/y'; DROP",
        'number_format' => 'plain',
    ])->assertSessionHasErrors('date_format');
});

it('shares the chosen formats with every page', function (): void {
    $user = buyer();
    $user->forceFill(['date_format' => 'iso', 'number_format' => 'euro', 'timezone' => 'Asia/Tokyo'])->save();

    // Not the profile page — any page, because the preference drives rendering
    // across the whole app rather than on the screen that sets it.
    $response = $this->actingAs($user)->get(advertiserUrl('/dashboard'));

    expect($response->viewData('page')['props']['formats'])
        ->toBe(['date' => 'iso', 'number' => 'euro', 'timeZone' => 'Asia/Tokyo']);
});

// --------------------------------------------------------------- the avatar

it('stores an avatar and serves it back to its owner only', function (): void {
    Storage::fake('local');

    $user = buyer();

    $this->actingAs($user)
        ->post(advertiserUrl('/profile/avatar'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 512, 512),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();

    $url = $user->avatarUrl();
    $this->actingAs($user)->get(advertiserUrl($url))->assertOk();

    $this->flushSession();

    // Addressed by a hash of the *path*, so somebody else's URL resolves
    // against their own null avatar and 404s rather than serving a face.
    $this->actingAs(buyer())->get(advertiserUrl($url))->assertNotFound();
});

// -------------------------------------------------------------- the company

it('validates a VAT number against the chosen country', function (): void {
    $user = buyer();

    $save = fn (string $vat, string $country) => $this->actingAs($user)->patch(advertiserUrl('/profile/company'), [
        'company' => 'Northwind Ltd',
        'vat_no' => $vat,
        'country' => $country,
    ]);

    // The factory seeds a VAT number, so "unchanged" is the assertion — not
    // "null", which would pass for the wrong reason on an empty account.
    $before = $user->vat_no;

    $save('DE12345', 'DE')->assertSessionHasErrors('vat_no');
    expect($user->fresh()->vat_no)->toBe($before);

    // Spaces are how people write these; they are normalised, not rejected.
    $save('GB 123 4567 89', 'GB')->assertRedirect()->assertSessionHasNoErrors();
    expect($user->fresh()->vat_no)->toBe('GB123456789');
});

it('checks the shape of every country it claims to know', function (): void {
    foreach (VatNumber::knownCountries() as $country) {
        $example = VatNumber::exampleFor($country);

        expect($example)->not->toBeNull()
            // The example a person is shown has to pass the rule they are
            // being held to. This caught nothing, and that is the point.
            ->and(VatNumber::matches($example, $country))->toBeTrue();
    }
});

it('writes the company change to the security log', function (): void {
    $user = buyer();

    $this->actingAs($user)->patch(advertiserUrl('/profile/company'), ['company' => 'Northwind Ltd']);

    expect(AuditLog::query()->where('action', 'company.updated')->where('actor_id', $user->id)->exists())
        ->toBeTrue();
});

// ------------------------------------------------------------- the password

it('changes the password, ends other sessions and emails', function (): void {
    Notification::fake();

    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    $this->actingAs($user)
        ->patch(advertiserUrl('/profile/password'), [
            'current_password' => 'Correct-Horse-9',
            'password' => 'Battery-Staple-42',
            'password_confirmation' => 'Battery-Staple-42',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Hash::check('Battery-Staple-42', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->toBeNull()
        ->and(AuditLog::query()->where('action', 'password.changed')->exists())->toBeTrue();

    Notification::assertSentTo($user, PasswordChangedNotification::class);
});

it('refuses the wrong current password and refuses reusing the old one', function (): void {
    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    $this->actingAs($user)->patch(advertiserUrl('/profile/password'), [
        'current_password' => 'not-it',
        'password' => 'Battery-Staple-42',
        'password_confirmation' => 'Battery-Staple-42',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($user)->patch(advertiserUrl('/profile/password'), [
        'current_password' => 'Correct-Horse-9',
        'password' => 'Correct-Horse-9',
        'password_confirmation' => 'Correct-Horse-9',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('Correct-Horse-9', $user->fresh()->password))->toBeTrue();
});

// -------------------------------------------------------------- two-factor

it('lets a half-finished setup be abandoned without a password', function (): void {
    $user = buyer();
    app(TwoFactor::class)->generateSecret($user);

    // Nothing is protecting anything yet, so demanding a password here would
    // strand the advertiser on a setup screen they cannot leave.
    $this->actingAs($user->fresh())
        ->delete(advertiserUrl('/settings/two-factor'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('asks for the password before turning a confirmed second factor off', function (): void {
    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    $twoFactor = app(TwoFactor::class);
    $twoFactor->generateSecret($user);
    $twoFactor->confirm($user->fresh());

    $this->actingAs($user->fresh())
        ->delete(advertiserUrl('/settings/two-factor'), ['password' => 'not-it'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($user->fresh())
        ->delete(advertiserUrl('/settings/two-factor'), ['password' => 'Correct-Horse-9'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'two_factor.disabled')->exists())->toBeTrue();
});

it('asks for the password before minting a fresh set of recovery codes', function (): void {
    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    $twoFactor = app(TwoFactor::class);
    $twoFactor->generateSecret($user);
    $twoFactor->confirm($user->fresh());

    $before = $user->fresh()->two_factor_recovery_codes;

    // A hijacked session that can do this silently voids the eight codes the
    // real owner has on paper and issues itself eight standing bypasses.
    $this->actingAs($user->fresh())
        ->post(advertiserUrl('/settings/two-factor/recovery-codes'))
        ->assertSessionHasErrors('password');

    expect($user->fresh()->two_factor_recovery_codes)->toBe($before);

    $this->actingAs($user->fresh())
        ->post(advertiserUrl('/settings/two-factor/recovery-codes'), ['password' => 'Correct-Horse-9'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('recoveryCodes');

    expect($user->fresh()->two_factor_recovery_codes)->not->toBe($before);
});

// ---------------------------------------------------------- login history

it('flags a sign-in from a country the account has not used before', function (): void {
    $user = buyer();

    $attempt = function (string $country, bool $ok, int $minutesAgo) use ($user): void {
        LoginAttempt::query()->create([
            'email' => $user->email,
            'ip_address' => '203.0.113.7',
            'country' => $country,
            'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/120.0 Safari/537.36',
            'successful' => $ok,
        ])->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
    };

    $attempt('GB', true, 60);
    $attempt('GB', true, 50);
    $attempt('BR', true, 10);

    $rows = props($this->actingAs($user)->get(advertiserUrl('/profile?tab=security')))['security']['loginHistory']['rows'];
    $byCountry = collect($rows)->keyBy('country');

    expect($byCountry['GB']['newCountry'])->toBeFalse()
        ->and($byCountry['BR']['newCountry'])->toBeTrue();
});

it('does not let a failed attempt make a country familiar', function (): void {
    $user = buyer();

    foreach ([['GB', true, 60], ['RU', false, 30], ['RU', true, 10]] as [$country, $ok, $ago]) {
        LoginAttempt::query()->create([
            'email' => $user->email,
            'ip_address' => '203.0.113.7',
            'country' => $country,
            'successful' => $ok,
        ])->forceFill(['created_at' => now()->subMinutes($ago)])->save();
    }

    $rows = props($this->actingAs($user)->get(advertiserUrl('/profile?tab=security')))['security']['loginHistory']['rows'];

    // Both RU rows are flagged: the failed one established nothing, so the
    // successful one that follows it is still the first from that country.
    expect(collect($rows)->where('country', 'RU')->pluck('newCountry')->all())->toBe([true, true]);
});

it('reads a device out of a user agent', function (): void {
    expect(DeviceLabel::parse('Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36 Edg/120.0')['label'])
        // Edge claims to be Chrome, and Chrome claims to be Safari.
        ->toBe('Edge on Windows 11')
        ->and(DeviceLabel::parse('Mozilla/5.0 (iPhone) Version/17.0 Mobile Safari/604.1')['label'])
        ->toBe('Safari on iPhone')
        ->and(DeviceLabel::parse('Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/120.0 Safari/537.36')['label'])
        // And there is no word boundary inside 'HeadlessChrome'.
        ->toBe('Chrome on Linux')
        ->and(DeviceLabel::parse(null)['label'])->toBe('Unknown device');
});

// ------------------------------------------------------------ notifications

it('saves the matrix and keeps receipts on whatever is stored', function (): void {
    $user = buyer();
    $settings = app(NotificationSettings::class);

    $this->actingAs($user)->patch(advertiserUrl('/profile/notifications'), [
        'matrix' => [
            NotificationEvent::WeeklySummary->value => ['email' => false, 'in_app' => false, 'push' => false],
            // Sent as off. It is transactional, so it must still get through.
            NotificationEvent::RefundProcessed->value => ['email' => false, 'in_app' => false, 'push' => false],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($settings->wants($user, NotificationEvent::WeeklySummary, NotificationChannel::Email))->toBeFalse()
        ->and($settings->wants($user, NotificationEvent::RefundProcessed, NotificationChannel::Email))->toBeTrue();
});

it('pauses everything except the receipts', function (): void {
    $user = buyer();
    $settings = app(NotificationSettings::class);

    $this->actingAs($user)->patch(advertiserUrl('/profile/notifications'), [
        'matrix' => [],
        'paused_until' => now()->addWeek()->toDateString(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->notificationsArePaused())->toBeTrue()
        ->and($settings->wants($user, NotificationEvent::DeadlineApproaching, NotificationChannel::Email))->toBeFalse()
        ->and($settings->wants($user, NotificationEvent::TopUpConfirmed, NotificationChannel::Email))->toBeTrue();
});

it('will not pause into the past', function (): void {
    $this->actingAs(buyer())->patch(advertiserUrl('/profile/notifications'), [
        'matrix' => [],
        'paused_until' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('paused_until');
});

it('drives the conversations switch off the same row', function (): void {
    $user = buyer();

    $this->actingAs($user)->patch(advertiserUrl('/settings/notifications'), ['notify_replies' => false]);

    // One source of truth: the toggle beside the inbox and the profile matrix
    // are the same `new_message` row.
    expect(app(NotificationSettings::class)->wants($user, NotificationEvent::NewMessage, NotificationChannel::Email))
        ->toBeFalse()
        ->and(props($this->actingAs($user)->get(advertiserUrl('/profile?tab=notifications')))['notifications']['matrix'][NotificationEvent::NewMessage->value]['email'])->toBeFalse();
});

it('leaves the other channels alone when the inbox switch is used', function (): void {
    $user = buyer();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'event' => NotificationEvent::NewMessage->value,
        'email' => true,
        'in_app' => true,
        'push' => true,
    ]);

    $this->actingAs($user)->patch(advertiserUrl('/settings/notifications'), ['notify_replies' => false]);

    $row = NotificationPreference::query()->where('user_id', $user->id)->firstOrFail();

    expect($row->email)->toBeFalse()
        ->and($row->in_app)->toBeTrue()
        ->and($row->push)->toBeTrue();
});

// -------------------------------------------------------------- api tokens

it('mints a token, shows it once and stores only its hash', function (): void {
    $user = buyer();

    $response = $this->actingAs($user)->post(advertiserUrl('/profile/tokens'), [
        'name' => 'Reporting script',
        'abilities' => ['posts:read', 'billing:read'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $plain = session('newToken')['plain'];
    $stored = PersonalAccessToken::query()->firstOrFail();

    expect($plain)->toStartWith('pzt_')
        ->and($stored->token)->toBe(hash('sha256', $plain))
        // The plaintext is nowhere in the row.
        ->and($stored->token)->not->toBe($plain)
        ->and($stored->abilities)->toBe(['posts:read', 'billing:read']);

    // And it is not in the payload of the next page either.
    $listed = props($this->actingAs($user)->get(advertiserUrl('/profile?tab=api')))['tokens'];

    expect(json_encode($listed))->not->toContain($plain);
});

it('refuses a token with no scopes or an unknown one', function (): void {
    $user = buyer();

    $this->actingAs($user)->post(advertiserUrl('/profile/tokens'), ['name' => 'Empty', 'abilities' => []])
        ->assertSessionHasErrors('abilities');

    $this->actingAs($user)->post(advertiserUrl('/profile/tokens'), [
        'name' => 'Sneaky',
        'abilities' => ['billing:write'],
    ])->assertSessionHasErrors('abilities.0');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('keeps one advertiser out of another’s token', function (): void {
    $theirs = PersonalAccessToken::query()->create([
        'user_id' => buyer()->id,
        'name' => 'Theirs',
        'token' => hash('sha256', 'x'),
        'abilities' => ['posts:read'],
    ]);

    $this->flushSession();

    $this->actingAs(buyer())
        ->delete(advertiserUrl("/profile/tokens/{$theirs->id}"))
        ->assertNotFound();

    expect($theirs->fresh())->not->toBeNull();
});

it('knows when a token has expired', function (): void {
    $token = new PersonalAccessToken(['abilities' => ['posts:read'], 'expires_at' => now()->subDay()]);

    expect($token->isExpired())->toBeTrue()
        ->and($token->can(TokenAbility::ReadPosts))->toBeFalse();
});

// ---------------------------------------------------------------- deletion

it('refuses to close an account with active posts or frozen funds', function (): void {
    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => site()->id,
        'status' => PostStatus::InProgress,
    ]);

    $this->actingAs($user)->post(advertiserUrl('/profile/delete'), [
        'password' => 'Correct-Horse-9',
        'confirm' => true,
    ])->assertSessionHasErrors('confirm');

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('closes an account that has nothing in flight, and can undo it', function (): void {
    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    $this->actingAs($user)->post(advertiserUrl('/profile/delete'), [
        'password' => 'Correct-Horse-9',
        'confirm' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($user->fresh()->deletion_requested_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.deletion_requested')->exists())->toBeTrue();

    $this->actingAs($user)->delete(advertiserUrl('/profile/delete'))->assertRedirect();

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('names both blockers so somebody knows what to clear', function (): void {
    $user = buyer();

    Wallet::query()->create([
        'user_id' => $user->id,
        'available_cents' => 0,
        'frozen_cents' => 25_000,
        'currency' => 'USD',
    ]);

    Post::factory()->create(['user_id' => $user->id, 'website_id' => site()->id, 'status' => PostStatus::Posted]);

    $deletion = props($this->actingAs($user)->get(advertiserUrl('/profile')))['deletion'];

    expect($deletion['blocked'])->toBeTrue()
        ->and($deletion['activePosts'])->toBe(1)
        ->and($deletion['frozenCents'])->toBe(25_000);
});

it('refuses deletion without the right password', function (): void {
    $user = buyer();
    $user->forceFill(['password' => Hash::make('Correct-Horse-9')])->save();

    $this->actingAs($user)->post(advertiserUrl('/profile/delete'), [
        'password' => 'wrong',
        'confirm' => true,
    ])->assertSessionHasErrors('password');

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});
