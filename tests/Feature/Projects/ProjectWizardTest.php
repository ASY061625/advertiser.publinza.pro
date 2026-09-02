<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Projects\DTOs\ProjectWizardData;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectDraft;
use App\Domain\Projects\Models\ProjectFolder;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function wizardUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * @return array<string, mixed>
 */
function wizardPayload(array $overrides = []): array
{
    return [
        'website_url' => 'https://acme.test',
        'name' => 'Acme SaaS',
        'category_id' => WebsiteCategory::factory()->create()->id,
        'color' => '#0f9d74',
        'sensitive_topic_ids' => [],
        'country_ids' => [],
        'language_ids' => [],
        'publisher_task' => '<p>Keep it plain.</p>',
        'landing_pages' => [['anchor_text' => 'best invoicing', 'url' => 'https://acme.test/pricing']],
        ...$overrides,
    ];
}

it('serves the wizard with everything the steps need', function (): void {
    $user = wizardUser();
    WebsiteCategory::factory()->create();

    $this->actingAs($user)
        ->get(advertiserUrl('/projects/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Create')
            ->has('categories')
            ->has('topics')
            ->has('countries')
            ->has('languages')
            ->has('colors')
            ->where('draft', null)
        );
});

it('creates the project, its folder and its landing pages in one go', function (): void {
    $user = wizardUser();
    $topic = SensitiveTopic::query()->create(['name' => 'Gambling', 'slug' => 'gambling']);
    $country = Country::query()->create(['code' => 'IE', 'name' => 'Ireland']);
    $language = Language::query()->create(['code' => 'en', 'name' => 'English']);

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload([
            'sensitive_topic_ids' => [$topic->id],
            'country_ids' => [$country->id],
            'language_ids' => [$language->id],
            'landing_pages' => [
                ['anchor_text' => 'pricing', 'url' => 'https://acme.test/pricing'],
                ['anchor_text' => 'how VAT works', 'url' => 'https://www.acme.test/vat'],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Project created');

    $project = Project::query()->where('user_id', $user->id)->firstOrFail();

    expect($project->name)->toBe('Acme SaaS')
        ->and($project->status)->toBe(ProjectStatus::Active)
        ->and($project->color)->toBe('#0f9d74')
        ->and($project->sensitiveTopics)->toHaveCount(1)
        ->and($project->countries)->toHaveCount(1)
        ->and($project->languages)->toHaveCount(1);

    // Every project gets somewhere for posts to live.
    $folder = ProjectFolder::query()->where('project_id', $project->id)->firstOrFail();
    expect($folder->name)->toBe('General');

    $pages = LandingPage::query()->where('project_id', $project->id)->orderBy('sort_order')->get();
    expect($pages)->toHaveCount(2)
        ->and($pages[0]->anchor_text)->toBe('pricing')
        // Order is the order they were entered, not whatever the database
        // hands back.
        ->and($pages[1]->anchor_text)->toBe('how VAT works')
        ->and($pages->pluck('folder_id')->unique()->all())->toBe([$folder->id]);
});

it('rolls the whole thing back if any part of it fails', function (): void {
    $user = wizardUser();

    // A category that does not exist fails validation before anything is
    // written, which is the cheap case; the transaction covers the rest.
    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload(['category_id' => 999_999]))
        ->assertSessionHasErrors('category_id');

    expect(Project::query()->count())->toBe(0)
        ->and(ProjectFolder::query()->count())->toBe(0)
        ->and(LandingPage::query()->count())->toBe(0);
});

it('refuses a landing page that is not on the promoted site, and names both domains', function (): void {
    $user = wizardUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload([
            'landing_pages' => [
                ['anchor_text' => 'ours', 'url' => 'https://acme.test/pricing'],
                ['anchor_text' => 'not ours', 'url' => 'https://competitor.test/pricing'],
            ],
        ]))
        ->assertSessionHasErrors(['landing_pages.1.url']);

    $error = session('errors')->first('landing_pages.1.url');

    // "Must be the same domain" leaves someone staring at two URLs that look
    // alike to them; the message has to name both.
    expect($error)->toContain('acme.test')->toContain('competitor.test');

    expect(Project::query()->count())->toBe(0);
});

it('accepts a subdomain of the promoted site as the same site', function (): void {
    $user = wizardUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload([
            'website_url' => 'https://www.acme.test',
            'landing_pages' => [['anchor_text' => 'blog', 'url' => 'https://blog.acme.test/post']],
        ]))
        ->assertSessionHasNoErrors();

    expect(Project::query()->count())->toBe(1);
});

it('requires at least one landing page', function (): void {
    $user = wizardUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload(['landing_pages' => []]))
        ->assertSessionHasErrors('landing_pages');
});

it('sanitises the brief before it is stored', function (): void {
    $user = wizardUser();

    $this->actingAs($user)->post(advertiserUrl('/projects'), wizardPayload([
        // Authored in a contenteditable, so it arrives as whatever HTML the
        // client chose to send.
        'publisher_task' => '<p>Fine</p><script>alert(document.cookie)</script><a href="javascript:alert(1)">x</a>',
    ]));

    $project = Project::query()->firstOrFail();

    expect($project->publisher_task)
        ->toContain('<p>Fine</p>')
        ->not->toContain('<script')
        ->not->toContain('javascript:');
});

it('measures the brief limit on the text, not the markup', function (): void {
    $user = wizardUser();

    // Well under 3,000 characters of writing, but far more once every word is
    // wrapped in a tag. Charging someone for markup they cannot see would be
    // nonsense.
    $body = str_repeat('<strong>word</strong> ', 200);

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload(['publisher_task' => $body]))
        ->assertSessionHasNoErrors();

    expect(Project::query()->count())->toBe(1);
});

it('normalises the promoted URL before storing it', function (): void {
    $user = wizardUser();

    $this->actingAs($user)->post(advertiserUrl('/projects'), wizardPayload([
        'website_url' => 'http://ACME.test/',
        'landing_pages' => [['anchor_text' => 'a', 'url' => 'HTTP://Acme.test/Pricing/']],
    ]))->assertSessionHasNoErrors();

    $project = Project::query()->firstOrFail();

    expect($project->website_url)->toBe('https://acme.test')
        ->and(LandingPage::query()->firstOrFail()->url)->toBe('https://acme.test/Pricing');
});

it('falls back to a colour derived from the domain when none is chosen', function (): void {
    $user = wizardUser();

    $this->actingAs($user)->post(advertiserUrl('/projects'), wizardPayload(['color' => null]));

    $project = Project::query()->firstOrFail();

    expect($project->color)->toBe(ProjectWizardData::defaultColorFor('https://acme.test'))
        ->and(ProjectWizardData::COLORS)->toContain($project->color);
});

it('gives the same suggested colour for a domain however it is written', function (): void {
    expect(ProjectWizardData::defaultColorFor('https://acme.test'))
        ->toBe(ProjectWizardData::defaultColorFor('https://www.acme.test/pricing?x=1'));
});

it('refuses a colour that is not on the palette', function (): void {
    $user = wizardUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload(['color' => '#ff00ff']))
        ->assertSessionHasErrors('color');
});

it('autosaves a draft and resumes it on the step it was left', function (): void {
    $user = wizardUser();

    $this->actingAs($user)
        ->patchJson(advertiserUrl('/projects/draft'), [
            'step' => 2,
            'payload' => ['website_url' => 'acme.test', 'name' => 'Half done'],
        ])
        ->assertOk()
        ->assertJsonStructure(['saved_at']);

    $this->actingAs($user)
        ->get(advertiserUrl('/projects/create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('draft.step', 2)
            ->where('draft.payload.name', 'Half done')
            // Normalised on the way into the draft too, so resuming does not
            // present a differently-shaped URL from the one a submit stores.
            ->where('draft.payload.website_url', 'https://acme.test')
        );
});

it('keeps one draft per advertiser rather than a pile of them', function (): void {
    $user = wizardUser();

    foreach (['One', 'Two', 'Three'] as $name) {
        $this->actingAs($user)->patchJson(advertiserUrl('/projects/draft'), [
            'step' => 1,
            'payload' => ['name' => $name],
        ]);
    }

    expect(ProjectDraft::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(ProjectDraft::query()->firstOrFail()->payload['name'])->toBe('Three');
});

it('discards the draft once the project is real', function (): void {
    $user = wizardUser();

    $this->actingAs($user)->patchJson(advertiserUrl('/projects/draft'), [
        'step' => 3,
        'payload' => ['name' => 'Nearly'],
    ]);

    expect(ProjectDraft::query()->count())->toBe(1);

    $this->actingAs($user)->post(advertiserUrl('/projects'), wizardPayload());

    expect(ProjectDraft::query()->count())->toBe(0);
});

it('lets someone throw the draft away and start over', function (): void {
    $user = wizardUser();

    $this->actingAs($user)->patchJson(advertiserUrl('/projects/draft'), [
        'step' => 2,
        'payload' => ['name' => 'Abandoned'],
    ]);

    $this->actingAs($user)->delete(advertiserUrl('/projects/draft'))->assertRedirect();

    expect(ProjectDraft::query()->count())->toBe(0);
});

it('never shows one advertiser another advertiser\'s draft', function (): void {
    $mine = wizardUser();
    $theirs = wizardUser();

    // Seeded directly rather than through a request as the other user:
    // AuthenticateSession ties a session to the account that opened it, so
    // switching actingAs mid-test signs the first one out — which is the
    // behaviour the auth suite pins, not something to work around here.
    ProjectDraft::query()->create([
        'user_id' => $theirs->id,
        'step' => 2,
        'payload' => ['name' => 'Theirs'],
    ]);

    $this->actingAs($mine)
        ->get(advertiserUrl('/projects/create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('draft', null));
});

it('offers the next step once, on the project it just created', function (): void {
    $user = wizardUser();

    $project = $this->actingAs($user)
        ->post(advertiserUrl('/projects'), wizardPayload())
        ->assertRedirect();

    $id = Project::query()->firstOrFail()->id;

    $this->actingAs($user)->get(advertiserUrl("/projects/{$id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('justCreated', true));

    // Flashed, not stored: it is about this moment, not about the project.
    $this->actingAs($user)->get(advertiserUrl("/projects/{$id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('justCreated', false));
});
