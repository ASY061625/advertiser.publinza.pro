<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Models\NotificationPreference;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\NotificationDigest;
use App\Domain\Notifications\Support\DigestGate;
use App\Domain\Notifications\Support\NotificationCentre;
use App\Models\User;
use App\Notifications\Publinza\DigestSummaryNotification;
use App\Notifications\Publinza\PostPublishedNotification;
use App\Notifications\Publinza\RefundProcessedNotification;
use App\Notifications\Publinza\WeeklySummaryNotification;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** One stored notification, without going through a channel. */
function record(User $user, NotificationType $type, array $data = [], ?string $at = null): DatabaseNotification
{
    $row = new DatabaseNotification([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => [
            'type' => $type->value,
            'title' => $type->value,
            'body' => 'Something happened.',
            'href' => '/posts/1',
            'icon' => $type->icon(),
            'tone' => $type->tone(),
            ...$data,
        ],
    ]);

    $row->id = $row->getAttribute('id');
    $row->save();

    if ($at !== null) {
        $row->forceFill(['created_at' => $at])->save();
    }

    return $row->fresh();
}

// ------------------------------------------------------------ the type table

it('gives every type a preference, an icon, a tone and a summary', function (): void {
    foreach (NotificationType::cases() as $type) {
        expect($type->preference())->toBeInstanceOf(NotificationEvent::class)
            ->and($type->icon())->not->toBe('')
            ->and($type->tone())->not->toBe('')
            ->and($type->summary(3))->toContain('3');
    }

    // Fifteen types, fanning into thirteen preference rows. The fan-in is the
    // point: nobody wants to be asked separately about a deadline approaching
    // and a deadline missed.
    expect(NotificationType::cases())->toHaveCount(15)
        ->and(NotificationEvent::cases())->toHaveCount(13);
});

// -------------------------------------------------------------- the channels

it('always writes the in-app record, whatever the preferences say', function (): void {
    $user = buyer();

    // Everything off, including the in-app switch.
    foreach (NotificationEvent::cases() as $event) {
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event' => $event->value,
            'email' => false,
            'in_app' => false,
            'push' => false,
        ]);
    }

    $notification = new WeeklySummaryNotification(2, 1, 40_00);

    expect($notification->via($user))->toBe(['database']);
});

it('adds broadcast and mail when the preferences allow them', function (): void {
    $user = buyer();

    $channels = (new WeeklySummaryNotification(2, 1, 40_00))->via($user);

    // WeeklySummary defaults to email on, in-app off.
    expect($channels)->toContain('database')
        ->and($channels)->toContain('mail')
        ->and($channels)->not->toContain('broadcast');
});

it('cannot have a transactional email switched off', function (): void {
    $user = buyer();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'event' => NotificationEvent::RefundProcessed->value,
        'email' => false,
        'in_app' => false,
        'push' => false,
    ]);

    $channels = (new RefundProcessedNotification(240_00, 'The publisher withdrew.'))->via($user);

    // Money coming back is a receipt, not marketing.
    expect($channels)->toContain('mail');
});

it('carries the push flag from the preference, not from the browser', function (): void {
    $user = buyer();
    $settings = app(NotificationSettings::class);

    $wanted = $settings->wants($user, NotificationEvent::PostPublished, NotificationChannel::Push);

    $payload = (new PostPublishedNotification(1, 'techweekly.com', null))->toBroadcast($user)->data;

    expect($payload['push'])->toBe($wanted)
        // PostPublished defaults to push off, so this is the interesting half.
        ->and($payload['push'])->toBeFalse();
});

it('writes a finished sentence into the row, not ids to reassemble later', function (): void {
    $user = buyer();

    $data = (new PostPublishedNotification(7, 'techweekly.com', 'https://techweekly.com/x'))->toArray($user);

    expect($data['title'])->toBe('Published on techweekly.com')
        ->and($data['body'])->toContain('https://techweekly.com/x')
        ->and($data['href'])->toBe('/posts/7');
});

// ----------------------------------------------------------- the fifteen-minute window

it('sends the first email and holds the second', function (): void {
    $user = buyer();
    $gate = app(DigestGate::class);

    expect($gate->allow($user, NotificationType::PostPublished, 'a'))->toBeTrue()
        ->and($gate->allow($user, NotificationType::PostPublished, 'b'))->toBeFalse()
        ->and($gate->allow($user, NotificationType::PostPublished, 'c'))->toBeFalse();

    $digest = NotificationDigest::query()->firstWhere('user_id', $user->id);

    // Held, not dropped. Two suppressed, both remembered.
    expect($digest->pending_count)->toBe(2)
        ->and($digest->pending_ids)->toBe(['b', 'c']);
});

it('keeps a separate window per type', function (): void {
    $user = buyer();
    $gate = app(DigestGate::class);

    expect($gate->allow($user, NotificationType::PostPublished, 'a'))->toBeTrue()
        // A different kind of news is not the same email.
        ->and($gate->allow($user, NotificationType::TopUpConfirmed, 'b'))->toBeTrue();
});

it('opens the window again once fifteen minutes have passed', function (): void {
    $user = buyer();
    $gate = app(DigestGate::class);

    $gate->allow($user, NotificationType::PostPublished, 'a');

    expect($gate->allow($user, NotificationType::PostPublished, 'b'))->toBeFalse();

    $this->travel(16)->minutes();

    expect($gate->allow($user, NotificationType::PostPublished, 'c'))->toBeTrue();
});

it('sends one summary for everything the window held back', function (): void {
    Notification::fake();

    $user = buyer();
    $gate = app(DigestGate::class);

    $first = record($user, NotificationType::PostPublished, ['title' => 'Published on a.com']);
    $second = record($user, NotificationType::PostPublished, ['title' => 'Published on b.com']);

    $gate->allow($user, NotificationType::PostPublished, $first->id);
    $gate->allow($user, NotificationType::PostPublished, $second->id);

    $this->travel(16)->minutes();

    $this->artisan('notifications:send-digests')->assertExitCode(0);

    Notification::assertSentTo($user, DigestSummaryNotification::class);

    // And the batch is closed, so the next minute does not send it again.
    $digest = NotificationDigest::query()->firstWhere('user_id', $user->id);

    expect($digest->pending_count)->toBe(0)
        ->and($digest->pending_ids)->toBe([]);
});

it('does not send a summary while the window is still open', function (): void {
    Notification::fake();

    $user = buyer();
    $gate = app(DigestGate::class);

    $gate->allow($user, NotificationType::PostPublished, record($user, NotificationType::PostPublished)->id);
    $gate->allow($user, NotificationType::PostPublished, record($user, NotificationType::PostPublished)->id);

    $this->artisan('notifications:send-digests');

    Notification::assertNothingSent();
});

it('only mails once in fifteen minutes, end to end', function (): void {
    /*
     * Counted off MessageSent rather than Mail::fake().
     *
     * A notification's mail channel hands the mailer a view and a builder, not
     * a Mailable, so MailFake records nothing and assertSentCount() is happily
     * zero however many emails went out. This is the assertion that would have
     * passed for the wrong reason.
     */
    $sent = 0;
    Event::listen(MessageSent::class, function () use (&$sent): void {
        $sent++;
    });

    $user = buyer();

    // PostPublished is transactional, so email cannot be switched off — which
    // makes it the case where the window is the only thing between an
    // advertiser and three emails in one minute.
    $user->notify(new PostPublishedNotification(1, 'a.com', null));
    $user->notify(new PostPublishedNotification(2, 'b.com', null));
    $user->notify(new PostPublishedNotification(3, 'c.com', null));

    expect($sent)->toBe(1)
        // All three are still in the drawer. Held, not dropped.
        ->and($user->notifications()->count())->toBe(3);

    $this->travel(16)->minutes();
    $this->artisan('notifications:send-digests');

    // And the two that waited arrive as one summary.
    expect($sent)->toBe(2);
});

// ------------------------------------------------------------ the drawer

it('buckets by today, yesterday and earlier in the reader’s timezone', function (): void {
    $user = buyer();
    $user->forceFill(['timezone' => 'UTC'])->save();

    record($user, NotificationType::TopUpConfirmed, [], now()->subHours(2)->toDateTimeString());
    record($user, NotificationType::TopUpConfirmed, [], now()->subDay()->startOfDay()->addHours(9)->toDateTimeString());
    record($user, NotificationType::TopUpConfirmed, [], now()->subDays(9)->toDateTimeString());

    $groups = app(NotificationCentre::class)->forUser($user->fresh())['groups'];

    expect(array_column($groups, 'key'))->toBe(['today', 'yesterday', 'earlier']);
});

it('collapses three of a kind and leaves two alone', function (): void {
    $user = buyer();
    $user->forceFill(['timezone' => 'UTC'])->save();

    foreach (range(1, 3) as $i) {
        record($user, NotificationType::PostPublished, ['post_id' => $i], now()->subMinutes($i)->toDateTimeString());
    }

    $items = app(NotificationCentre::class)->forUser($user->fresh())['groups'][0]['items'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['kind'])->toBe('group')
        ->and($items[0]['title'])->toBe('3 posts published')
        ->and($items[0]['items'])->toHaveCount(3);

    // Two of the same is not worth a disclosure triangle.
    DatabaseNotification::query()->where('notifiable_id', $user->id)->limit(1)->delete();

    $items = app(NotificationCentre::class)->forUser($user->fresh())['groups'][0]['items'];

    expect($items)->toHaveCount(2)
        ->and($items[0]['kind'])->toBe('item');
});

it('does not merge a run across something of a different kind', function (): void {
    $user = buyer();
    $user->forceFill(['timezone' => 'UTC'])->save();

    // Three publications with a rejection sitting in the middle of them.
    record($user, NotificationType::PostPublished, ['post_id' => 1], now()->subMinutes(1)->toDateTimeString());
    record($user, NotificationType::PostPublished, ['post_id' => 2], now()->subMinutes(2)->toDateTimeString());
    record($user, NotificationType::PostPublished, ['post_id' => 3], now()->subMinutes(3)->toDateTimeString());
    record($user, NotificationType::PostRejected, ['post_id' => 4], now()->subMinutes(4)->toDateTimeString());
    record($user, NotificationType::PostPublished, ['post_id' => 5], now()->subMinutes(5)->toDateTimeString());

    $items = app(NotificationCentre::class)->forUser($user->fresh())['groups'][0]['items'];

    // Group of three, the rejection, then a lone publication — not a group of
    // four with the rejection stranded after it.
    expect(array_column($items, 'kind'))->toBe(['group', 'item', 'item'])
        ->and($items[0]['items'])->toHaveCount(3);
});

it('never collapses a type that does not arrive in bursts', function (): void {
    $user = buyer();
    $user->forceFill(['timezone' => 'UTC'])->save();

    foreach (range(1, 3) as $i) {
        record($user, NotificationType::WeeklySummary, [], now()->subMinutes($i)->toDateTimeString());
    }

    $items = app(NotificationCentre::class)->forUser($user->fresh())['groups'][0]['items'];

    expect($items)->toHaveCount(3)
        ->and($items[0]['kind'])->toBe('item');
});

// -------------------------------------------------------------- the endpoints

it('opens the drawer with counts and a filter', function (): void {
    $user = buyer();

    $read = record($user, NotificationType::TopUpConfirmed);
    $read->markAsRead();
    record($user, NotificationType::PostRejected);

    $all = $this->actingAs($user)->getJson(advertiserUrl('/notifications/list'))->assertOk();
    $unread = $this->actingAs($user)->getJson(advertiserUrl('/notifications/list?filter=unread'))->assertOk();

    expect($all->json('counts'))->toBe(['all' => 2, 'unread' => 1])
        ->and($unread->json('groups.0.items'))->toHaveCount(1);
});

it('marks one read, and refuses somebody else’s', function (): void {
    $mine = buyer();
    $theirs = buyer();

    $ours = record($mine, NotificationType::PostPublished);
    $other = record($theirs, NotificationType::PostPublished);

    $this->actingAs($mine)->postJson(advertiserUrl("/notifications/{$ours->id}/read"))->assertOk();

    // `notifications` is one table for every account, so an id on its own is
    // not authorisation.
    $this->actingAs($mine)->postJson(advertiserUrl("/notifications/{$other->id}/read"))->assertNotFound();

    expect($ours->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

it('marks a group’s items read in one request', function (): void {
    $user = buyer();
    $theirs = buyer();

    $a = record($user, NotificationType::PostPublished);
    $b = record($user, NotificationType::PostPublished);
    $other = record($theirs, NotificationType::PostPublished);

    $this->actingAs($user)
        ->postJson(advertiserUrl('/notifications/read-many'), ['ids' => [$a->id, $b->id, $other->id]])
        ->assertOk();

    // Scoped through the relation: an id on its own is not authorisation, so
    // somebody else's row in the same list is simply not matched.
    expect($a->fresh()->read_at)->not->toBeNull()
        ->and($b->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

it('marks everything read at once', function (): void {
    $user = buyer();

    record($user, NotificationType::PostPublished);
    record($user, NotificationType::PostRejected);

    $this->actingAs($user)->postJson(advertiserUrl('/notifications/read-all'))->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('serves the full page for somebody arriving from an email', function (): void {
    $user = buyer();

    record($user, NotificationType::TopUpConfirmed);

    $response = $this->actingAs($user)->get(advertiserUrl('/notifications'));

    expect(props($response)['centre']['counts']['all'])->toBe(1)
        ->and(props($response)['filter'])->toBe('all');
});

it('puts the unread count on the shell for the bell', function (): void {
    $user = buyer();

    record($user, NotificationType::PostRejected);

    $response = $this->actingAs($user)->get(advertiserUrl('/dashboard'));

    expect(props($response)['shell']['counts']['notifications'])->toBe(1);
});
