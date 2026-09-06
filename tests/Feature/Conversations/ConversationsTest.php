<?php

declare(strict_types=1);

use App\Domain\Messaging\Actions\PostMessage;
use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Enums\ConversationStatus;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Messaging\Models\MessageAttachment;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Events\ConversationActivity;
use App\Models\User;
use App\Notifications\TeamReplyNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/** A thread with one message from the advertiser. */
function thread(User $user, array $attributes = [], ?string $body = 'Any news on this?'): Conversation
{
    $conversation = Conversation::query()->create($attributes + [
        'user_id' => $user->id,
        'subject' => 'Publication date',
        'last_message_at' => now(),
    ]);

    if ($body !== null) {
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::User,
            'sender_id' => $user->id,
            'body' => $body,
        ]);
    }

    return $conversation;
}

/** A reply from the Publinza side, unread by default. */
function teamReply(Conversation $conversation, string $body = 'Going live on Thursday.'): Message
{
    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_type' => SenderType::Admin,
        'sender_id' => null,
        'body' => $body,
    ]);
}

function props(TestResponse $response): array
{
    return $response->viewData('page')['props'];
}

// ------------------------------------------------------------------ the page

it('opens the inbox with no thread selected', function (): void {
    $user = buyer();
    thread($user);

    $response = $this->actingAs($user)->get(advertiserUrl('/conversations'));

    $response->assertOk();

    expect(props($response)['thread'])->toBeNull()
        ->and(props($response)['threads'])->toHaveCount(1);
});

it('deep-links one thread and reads it', function (): void {
    $user = buyer();
    $conversation = thread($user);
    teamReply($conversation);

    $response = $this->actingAs($user)->get(advertiserUrl("/conversations?thread={$conversation->id}"));

    $payload = props($response);

    expect($payload['thread']['id'])->toBe($conversation->id)
        ->and($payload['thread']['messages'])->toHaveCount(2)
        // Opening it is reading it: the badge and the pane cannot disagree.
        ->and(Message::query()->whereNull('read_at')->where('sender_type', 'admin')->count())->toBe(0);
});

it('opens a thread the current tab filters out', function (): void {
    $user = buyer();
    $closed = thread($user, ['status' => ConversationStatus::Closed]);

    // A link from an email lands here. The tab must not swallow it.
    $response = $this->actingAs($user)->get(advertiserUrl("/conversations?tab=open&thread={$closed->id}"));

    expect(props($response)['thread']['id'])->toBe($closed->id)
        ->and(props($response)['threads'])->toHaveCount(0);
});

it('keeps one advertiser out of another’s thread', function (): void {
    $mine = thread(buyer());

    $this->flushSession();

    $this->actingAs(buyer())
        ->get(advertiserUrl("/conversations?thread={$mine->id}"))
        ->assertForbidden();
});

// ------------------------------------------------------------- tabs & search

it('filters by tab', function (): void {
    $user = buyer();

    $open = thread($user);
    $closed = thread($user, ['status' => ConversationStatus::Closed]);
    $unread = thread($user);
    teamReply($unread);

    $ids = function (string $tab) use ($user): array {
        $response = $this->actingAs($user)->get(advertiserUrl("/conversations?tab={$tab}"));

        return array_column(props($response)['threads'], 'id');
    };

    expect($ids('all'))->toHaveCount(3)
        ->and($ids('unread'))->toBe([$unread->id])
        ->and($ids('closed'))->toBe([$closed->id])
        ->and($ids('open'))->toContain($open->id)
        ->and($ids('open'))->not->toContain($closed->id);
});

it('searches the subject, the message body and the domain', function (): void {
    $user = buyer();

    $bySubject = thread($user, ['subject' => 'Invoice question'], 'nothing to see');
    $byBody = thread($user, ['subject' => 'Something else'], 'the invoice never arrived');
    $byDomain = thread($user, ['website_id' => site(['domain' => 'invoicely.io'])->id], 'unrelated');
    thread($user, ['subject' => 'Unrelated'], 'unrelated');

    $found = function (string $q) use ($user): array {
        $response = $this->actingAs($user)->get(advertiserUrl('/conversations?q='.urlencode($q)));

        return array_column(props($response)['threads'], 'id');
    };

    expect($found('invoice'))->toHaveCount(3)
        ->and($found('invoice'))->toContain($bySubject->id, $byBody->id, $byDomain->id);
});

it('sorts by last activity, newest first', function (): void {
    $user = buyer();

    $old = thread($user);
    $old->update(['last_message_at' => now()->subDays(3)]);
    $recent = thread($user);
    $recent->update(['last_message_at' => now()]);

    $response = $this->actingAs($user)->get(advertiserUrl('/conversations'));

    expect(array_column(props($response)['threads'], 'id'))->toBe([$recent->id, $old->id]);
});

// ----------------------------------------------------------------- messaging

it('posts a reply and moves the thread to the top', function (): void {
    $user = buyer();
    $other = thread($user);
    $other->update(['last_message_at' => now()]);
    $conversation = thread($user);
    $conversation->update(['last_message_at' => now()->subHour()]);

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), ['body' => 'Thanks!'])
        ->assertRedirect();

    $response = $this->actingAs($user)->get(advertiserUrl('/conversations'));

    expect($conversation->fresh()->messages()->count())->toBe(2)
        ->and(array_column(props($response)['threads'], 'id')[0])->toBe($conversation->id);
});

it('refuses an empty message', function (): void {
    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), ['body' => '   '])
        ->assertSessionHasErrors('body');

    expect($conversation->messages()->count())->toBe(1);
});

it('posts a message that is only an attachment', function (): void {
    Storage::fake('local');

    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), [
            'attachments' => [UploadedFile::fake()->image('screenshot.png')],
        ])
        ->assertRedirect();

    expect($conversation->messages()->count())->toBe(2)
        ->and(MessageAttachment::query()->count())->toBe(1);
});

it('posts the same client token only once', function (): void {
    $user = buyer();
    $conversation = thread($user);

    // The retry a failed-but-actually-succeeded send produces. Each one has to
    // *succeed* — the unique index alone would stop the duplicate row, but as a
    // 500, and the composer would mark a delivered message as failed.
    foreach ([1, 2, 3] as $_) {
        $this->actingAs($user)
            ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), [
                'body' => 'Any update?',
                'client_token' => 'abc-123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($conversation->messages()->where('body', 'Any update?')->count())->toBe(1);
});

it('answers a JSON send with a status code rather than a page', function (): void {
    $user = buyer();
    $conversation = thread($user);

    // The composer sends optimistically and needs to tell a rejected message
    // from a delivered one. A 302 into a full page render would be a second
    // page of HTML fetched to learn one bit.
    $this->actingAs($user)
        ->postJson(advertiserUrl("/conversations/{$conversation->id}/messages"), ['body' => 'Sent from the composer.'])
        ->assertNoContent();

    $this->actingAs($user)
        ->postJson(advertiserUrl("/conversations/{$conversation->id}/messages"), ['body' => '  '])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

it('will not reply to a closed thread', function (): void {
    $user = buyer();
    $conversation = thread($user, ['status' => ConversationStatus::Closed]);

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), ['body' => 'hello'])
        ->assertForbidden();
});

it('rejects a file type and a size the composer would not have sent', function (): void {
    Storage::fake('local');

    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), [
            'body' => 'here',
            'attachments' => [UploadedFile::fake()->create('payload.exe', 10)],
        ])
        ->assertSessionHasErrors('attachments.0');

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/messages"), [
            'body' => 'here',
            'attachments' => [UploadedFile::fake()->create('huge.pdf', 11 * 1024)],
        ])
        ->assertSessionHasErrors('attachments.0');
});

// --------------------------------------------------------------- attachments

it('serves an attachment to its owner and nobody else', function (): void {
    Storage::fake('local');

    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/messages"), [
        'attachments' => [UploadedFile::fake()->create('brief.pdf', 12)],
    ]);

    $attachment = MessageAttachment::query()->firstOrFail();

    $this->actingAs($user)
        ->get(advertiserUrl("/conversations/attachments/{$attachment->id}"))
        ->assertOk()
        ->assertDownload('brief.pdf');

    $this->flushSession();

    $this->actingAs(buyer())
        ->get(advertiserUrl("/conversations/attachments/{$attachment->id}"))
        ->assertForbidden();
});

// ------------------------------------------------------------ starting a thread

it('starts a thread about a post and files it under the post’s site', function (): void {
    $user = buyer();
    $website = site();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => $website->id,
        'status' => PostStatus::InProgress,
    ]);

    $this->actingAs($user)
        ->post(advertiserUrl('/conversations'), [
            'subject' => 'When does this go live?',
            'body' => 'Checking on the publication date.',
            'post_id' => $post->id,
        ])
        ->assertRedirect();

    $conversation = Conversation::query()->firstOrFail();

    // The site is inferred from the post rather than asked for twice.
    expect($conversation->post_id)->toBe($post->id)
        ->and($conversation->website_id)->toBe($website->id)
        ->and($conversation->messages()->count())->toBe(1);
});

it('will not file a thread against somebody else’s post', function (): void {
    $theirs = Post::factory()->create(['user_id' => buyer()->id, 'website_id' => site()->id]);

    $this->flushSession();

    $this->actingAs(buyer())->post(advertiserUrl('/conversations'), [
        'subject' => 'Prying',
        'body' => 'Tell me about this post.',
        'post_id' => $theirs->id,
    ]);

    // Not an error — the thread is opened, but stripped of a subject that was
    // never theirs to name.
    expect(Conversation::query()->latest('id')->first()->post_id)->toBeNull();
});

// ---------------------------------------------------------- thread management

it('marks a thread unread again', function (): void {
    $user = buyer();
    $conversation = thread($user);
    teamReply($conversation, 'first');
    teamReply($conversation, 'second');

    $this->actingAs($user)->get(advertiserUrl("/conversations?thread={$conversation->id}"));

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/unread"))
        ->assertRedirect('/conversations');

    // Only the latest inbound one: "I have not dealt with this", not "forget
    // that I read the other eleven". The advertiser's own message is unread
    // too, by the team, which is a different fact and not this one's business.
    $inbound = $conversation->messages()->where('sender_type', 'admin')->whereNull('read_at')->get();

    expect($inbound)->toHaveCount(1)
        ->and($inbound->first()->body)->toBe('second');
});

it('says so rather than silently doing nothing when there is nothing to unread', function (): void {
    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)
        ->post(advertiserUrl("/conversations/{$conversation->id}/unread"))
        ->assertSessionHas('error');
});

it('closes, reopens and mutes a thread', function (): void {
    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/status"), ['status' => 'closed']);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Closed);

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/status"), ['status' => 'open']);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open);

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/mute"), ['muted' => true]);
    expect($conversation->fresh()->isMuted())->toBeTrue();

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/mute"), ['muted' => false]);
    expect($conversation->fresh()->isMuted())->toBeFalse();
});

// ------------------------------------------------------- the team's own reply

it('broadcasts and emails when the team replies', function (): void {
    Event::fake([ConversationActivity::class]);
    Notification::fake();

    $user = buyer();
    $conversation = thread($user);

    app(PostMessage::class)->handle(
        $conversation,
        new MessageData('Live now.', SenderType::Admin),
    );

    Event::assertDispatched(ConversationActivity::class);
    Notification::assertSentTo($user, TeamReplyNotification::class);
});

it('says nothing to anybody when the advertiser writes', function (): void {
    Event::fake([ConversationActivity::class]);
    Notification::fake();

    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/messages"), ['body' => 'hi']);

    Event::assertNotDispatched(ConversationActivity::class);
    Notification::assertNothingSent();
});

it('respects a muted thread and an account that has turned replies off', function (): void {
    Notification::fake();

    $muted = thread(buyer(), ['muted_at' => now()]);

    $optedOut = buyer();
    $optedOut->forceFill(['notify_replies' => false])->save();
    $quiet = thread($optedOut);

    $post = app(PostMessage::class);

    $post->handle($muted, new MessageData('one', SenderType::Admin));
    $post->handle($quiet, new MessageData('two', SenderType::Admin));

    Notification::assertNothingSent();
});

it('does not email a system notice', function (): void {
    Notification::fake();

    $conversation = thread(buyer());

    app(PostMessage::class)->handle(
        $conversation,
        new MessageData('Article submitted for review', SenderType::System),
    );

    Notification::assertNothingSent();
});

it('turns reply emails off and on from the inbox', function (): void {
    $user = buyer();

    $this->actingAs($user)
        ->patch(advertiserUrl('/settings/notifications'), ['notify_replies' => false])
        ->assertRedirect();

    expect($user->fresh()->notify_replies)->toBeFalse();
});

// --------------------------------------------------------------- the payload

it('carries the excerpt, the unread count and the post badge onto every row', function (): void {
    $user = buyer();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => site(['domain' => 'ledgerwise.com'])->id,
        'status' => PostStatus::ContentReview,
    ]);

    $conversation = thread($user, ['website_id' => $post->website_id, 'post_id' => $post->id]);
    teamReply($conversation, 'The editor sent it back with two notes.');

    $response = $this->actingAs($user)->get(advertiserUrl('/conversations'));
    $row = props($response)['threads'][0];

    expect($row['domain'])->toBe('ledgerwise.com')
        ->and($row['excerpt'])->toBe('The editor sent it back with two notes.')
        ->and($row['unreadCount'])->toBe(1)
        ->and($row['post']['badge'])->toBe('content_review');
});

it('prefixes the excerpt when the last word was the advertiser’s own', function (): void {
    $user = buyer();
    thread($user, [], 'Just checking in.');

    $response = $this->actingAs($user)->get(advertiserUrl('/conversations'));

    expect(props($response)['threads'][0]['excerpt'])->toBe('You: Just checking in.');
});

it('names the attachment count when a message has no words', function (): void {
    Storage::fake('local');

    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)->post(advertiserUrl("/conversations/{$conversation->id}/messages"), [
        'attachments' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
    ]);

    $response = $this->actingAs($user)->get(advertiserUrl('/conversations'));

    expect(props($response)['threads'][0]['excerpt'])->toBe('2 attachments');
});

it('shows the site in the context panel, and the post when there is one', function (): void {
    $user = buyer();
    $website = site(['domain' => 'finanzblick.de']);

    $siteThread = thread($user, ['website_id' => $website->id]);
    $response = $this->actingAs($user)->get(advertiserUrl("/conversations?thread={$siteThread->id}"));

    expect(props($response)['context']['kind'])->toBe('website')
        ->and(props($response)['context']['domain'])->toBe('finanzblick.de')
        ->and(props($response)['context']['metrics'])->toHaveCount(4);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => $website->id,
        'status' => PostStatus::Posted,
    ]);
    $postThread = thread($user, ['website_id' => $website->id, 'post_id' => $post->id]);

    $response = $this->actingAs($user)->get(advertiserUrl("/conversations?thread={$postThread->id}"));

    // The placement wins: it is what the messages keep referring back to.
    expect(props($response)['context']['kind'])->toBe('post')
        ->and(props($response)['context']['id'])->toBe($post->id);
});

it('offers only the advertiser’s own posts and sites in the composer', function (): void {
    $user = buyer();
    Post::factory()->create(['user_id' => $user->id, 'website_id' => site()->id]);
    Post::factory()->create(['user_id' => buyer()->id, 'website_id' => site()->id]);

    $this->flushSession();

    $payload = $this->actingAs($user)->getJson(advertiserUrl('/conversations/new/options'))->json();

    expect($payload['posts'])->toHaveCount(1)
        ->and($payload['websites'])->toHaveCount(1);
});

it('sends the old message addresses to the new one', function (): void {
    $user = buyer();
    $conversation = thread($user);

    $this->actingAs($user)->get(advertiserUrl('/messages'))->assertRedirect('/conversations');

    $this->actingAs($user)
        ->get(advertiserUrl("/messages/{$conversation->id}"))
        ->assertRedirect("/conversations?thread={$conversation->id}");
});
