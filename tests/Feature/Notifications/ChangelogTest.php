<?php

declare(strict_types=1);

use App\Domain\System\Enums\ChangelogType;
use App\Domain\System\Models\ChangelogEntry;
use App\Domain\System\Support\ChangelogHtml;
use Illuminate\Support\Facades\Storage;

// ------------------------------------------------------------------ the drawer

it('shows the newest ten and marks them seen', function (): void {
    $user = buyer();
    $user->forceFill(['last_seen_changelog_at' => null])->save();

    ChangelogEntry::factory()->count(14)->create();

    $response = $this->actingAs($user)->getJson(advertiserUrl('/shell/changelog'))->assertOk();

    expect($response->json('entries'))->toHaveCount(10)
        // Unread when the drawer opened, so they still render with their dot —
        // the stamp lands after the payload is built.
        ->and($response->json('entries.0.unread'))->toBeTrue()
        ->and($user->fresh()->last_seen_changelog_at)->not->toBeNull();

    $this->actingAs($user->fresh())
        ->getJson(advertiserUrl('/shell/changelog'))
        ->assertJsonPath('entries.0.unread', false);
});

it('never shows an entry that is not published yet', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->unpublished()->create(['title' => 'Not yet']);
    ChangelogEntry::factory()->create(['title' => 'Live', 'published_at' => now()->subHour()]);
    ChangelogEntry::factory()->create(['title' => 'Scheduled', 'published_at' => now()->addWeek()]);

    $titles = collect($this->actingAs($user)->getJson(advertiserUrl('/shell/changelog'))->json('entries'))
        ->pluck('title');

    expect($titles->all())->toBe(['Live']);
});

// -------------------------------------------------------------- the header

it('counts unseen entries, and unseen major ones separately', function (): void {
    $user = buyer();
    $user->forceFill(['last_seen_changelog_at' => now()->subWeek()])->save();

    ChangelogEntry::factory()->create(['published_at' => now()->subDay()]);
    ChangelogEntry::factory()->major()->create(['published_at' => now()->subDays(2)]);
    // Older than the last visit, so neither count moves.
    ChangelogEntry::factory()->major()->create(['published_at' => now()->subMonth()]);

    $counts = props($this->actingAs($user)->get(advertiserUrl('/dashboard')))['shell']['counts'];

    // A dot for anything unseen; the number is only earned by a major release.
    expect($counts['changelog'])->toBe(2)
        ->and($counts['changelogMajor'])->toBe(1);
});

// ------------------------------------------------------------- the modal

it('announces one major release, once', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->major()->create([
        'title' => 'Projects are here',
        'published_at' => now()->subDay(),
    ]);

    expect(props($this->actingAs($user)->get(advertiserUrl('/dashboard')))['announcement']['title'])
        ->toBe('Projects are here');

    $this->actingAs($user)->post(advertiserUrl('/whats-new/acknowledge'));

    expect(props($this->actingAs($user->fresh())->get(advertiserUrl('/dashboard')))['announcement'])
        ->toBeNull();
});

it('does not let opening the drawer dismiss an announcement nobody read', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->major()->create(['published_at' => now()->subDay()]);

    // Opening the drawer clears the unseen dot for everything…
    $this->actingAs($user)->getJson(advertiserUrl('/shell/changelog'));

    // …and must leave the announcement standing, or a major release is
    // announced to nobody who happened to glance at the drawer first.
    expect(props($this->actingAs($user->fresh())->get(advertiserUrl('/dashboard')))['announcement'])
        ->not->toBeNull();
});

it('announces only the newest major release', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->major()->create(['title' => 'Older', 'published_at' => now()->subMonth()]);
    ChangelogEntry::factory()->major()->create(['title' => 'Newer', 'published_at' => now()->subDay()]);

    expect(props($this->actingAs($user)->get(advertiserUrl('/dashboard')))['announcement']['title'])
        ->toBe('Newer');
});

// ---------------------------------------------------------------- the page

it('groups the full changelog by month and filters by type', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->type(ChangelogType::New)->create(['published_at' => now()->subDays(2)]);
    ChangelogEntry::factory()->type(ChangelogType::Fixed)->create(['published_at' => now()->subDays(3)]);
    ChangelogEntry::factory()->type(ChangelogType::New)->create(['published_at' => now()->subMonths(3)]);

    $all = props($this->actingAs($user)->get(advertiserUrl('/whats-new')));

    expect($all['months'])->toHaveCount(2)
        ->and($all['counts']['all'])->toBe(3)
        ->and($all['counts']['new'])->toBe(2)
        ->and($all['counts']['fixed'])->toBe(1);

    $filtered = props($this->actingAs($user)->get(advertiserUrl('/whats-new?type=fixed')));

    expect($filtered['type'])->toBe('fixed')
        ->and($filtered['months'])->toHaveCount(1)
        ->and($filtered['months'][0]['entries'])->toHaveCount(1);
});

it('gives every entry a stable anchor', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->create(['slug' => 'projects-are-here', 'published_at' => now()]);

    expect(props($this->actingAs($user)->get(advertiserUrl('/whats-new')))['months'][0]['entries'][0]['anchor'])
        ->toBe('entry-projects-are-here');
});

it('does not put December and January of two years in one bucket', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->create(['published_at' => now()->startOfYear()->subMonth()]);
    ChangelogEntry::factory()->create(['published_at' => now()->startOfYear()->subYear()->subMonth()]);

    // Same month name, twelve months apart.
    expect(props($this->actingAs($user)->get(advertiserUrl('/whats-new')))['months'])->toHaveCount(2);
});

it('rejects a filter that is not one of the three types', function (): void {
    $this->actingAs(buyer())
        ->get(advertiserUrl('/whats-new?type=banana'))
        ->assertSessionHasErrors('type');
});

// ---------------------------------------------------------------- the body

it('strips anything that can execute out of a changelog body', function (): void {
    expect(ChangelogHtml::clean('<p>Hi</p><script>alert(1)</script>'))->toBe('<p>Hi</p>')
        ->and(ChangelogHtml::clean('<img src=x onerror=alert(1)>'))->toBe('')
        ->and(ChangelogHtml::clean('<a href="javascript:alert(1)">x</a>'))->not->toContain('javascript')
        ->and(ChangelogHtml::clean('<a href="https://a.test" onclick="x()">x</a>'))->not->toContain('onclick');
});

it('keeps the markup a release note actually uses', function (): void {
    $clean = ChangelogHtml::clean('<p>One <strong>two</strong></p><ul><li>a</li></ul>');

    expect($clean)->toContain('<strong>two</strong>')
        ->and($clean)->toContain('<li>a</li>');
});

it('unwraps a disallowed element rather than losing its text', function (): void {
    // A body pasted out of an editor arrives wrapped in divs. Deleting them
    // would delete the release note with them.
    expect(ChangelogHtml::clean('<div class="x">kept <em>text</em></div>'))
        ->toBe('kept <em>text</em>');
});

it('turns a plain-text body into paragraphs', function (): void {
    expect(ChangelogHtml::clean("One.\n\nTwo."))->toBe('<p>One.</p><p>Two.</p>');
});

it('sends the sanitised body to the browser, not the stored one', function (): void {
    $user = buyer();

    ChangelogEntry::factory()->create([
        'body' => '<p>Safe</p><script>alert(1)</script>',
        'published_at' => now()->subHour(),
    ]);

    $body = $this->actingAs($user)->getJson(advertiserUrl('/shell/changelog'))->json('entries.0.body');

    // Sanitised on read, not on write: rows written before the sanitiser
    // existed are still rows.
    expect($body)->toBe('<p>Safe</p>');
});

// ---------------------------------------------------------------- the image

it('serves a published entry’s image and hides an unpublished one’s', function (): void {
    $user = buyer();

    Storage::fake('local');
    Storage::disk('local')->put('changelog/shot.png', 'not-really-a-png');

    $live = ChangelogEntry::factory()->create([
        'image_path' => 'changelog/shot.png',
        'published_at' => now()->subHour(),
    ]);

    $draft = ChangelogEntry::factory()->unpublished()->create(['image_path' => 'changelog/shot.png']);

    $this->actingAs($user)->get(advertiserUrl("/whats-new/{$live->id}/image"))->assertOk();

    // A screenshot that goes up before the release must not leak from a
    // guessable URL.
    $this->actingAs($user)->get(advertiserUrl("/whats-new/{$draft->id}/image"))->assertNotFound();
});

it('reads a category written before the vocabulary settled', function (): void {
    expect(ChangelogType::parse('improvement'))->toBe(ChangelogType::Improved)
        ->and(ChangelogType::parse('fix'))->toBe(ChangelogType::Fixed)
        ->and(ChangelogType::parse('new'))->toBe(ChangelogType::New)
        ->and(ChangelogType::parse(null))->toBe(ChangelogType::Improved);
});
