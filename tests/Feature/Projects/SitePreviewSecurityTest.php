<?php

declare(strict_types=1);

use App\Domain\Projects\Actions\FetchSitePreview;
use App\Domain\Projects\Support\UrlNormalizer;
use App\Models\User;
use App\Support\OutboundUrlGuard;
use App\Support\PublicSuffix;

function previewUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

it('refuses every address that points inside the network', function (string $url): void {
    // Fetching a URL a user typed makes the request from inside Publinza's
    // network. Left open it reaches cloud metadata, private hosts and
    // anything the firewall trusts.
    $result = OutboundUrlGuard::check($url);

    expect($result['ok'])->toBeFalse("{$url} was allowed through");
})->with([
    'loopback v4' => 'http://127.0.0.1/admin',
    'loopback name' => 'http://localhost/',
    'loopback v6' => 'http://[::1]/',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'private 10' => 'http://10.0.0.5/',
    'private 192' => 'http://192.168.1.1/',
    'private 172' => 'http://172.16.0.1/',
    'carrier NAT' => 'http://100.64.0.1/',
    'this network' => 'http://0.0.0.0/',
    'unique local v6' => 'http://[fd00::1]/',
    'link local v6' => 'http://[fe80::1]/',
    // An IPv4 address wearing an IPv6 hat is still that IPv4 address.
    'v4-mapped loopback' => 'http://[::ffff:127.0.0.1]/',
    'v4-mapped metadata' => 'http://[::ffff:169.254.169.254]/',
    '6to4 loopback' => 'http://[2002:7f00:1::]/',
    'NAT64 loopback' => 'http://[64:ff9b::7f00:1]/',
    'broadcast' => 'http://255.255.255.255/',
    'decimal encoded' => 'http://2130706433/',
    'hex encoded' => 'http://0x7f000001/',
    'file scheme' => 'file:///etc/passwd',
    'gopher scheme' => 'gopher://127.0.0.1/',
    'javascript scheme' => 'javascript:alert(1)',
]);

it('recognises a public address as public', function (): void {
    expect(OutboundUrlGuard::isPublic('93.184.215.14'))->toBeTrue()
        ->and(OutboundUrlGuard::isPublic('8.8.8.8'))->toBeTrue()
        ->and(OutboundUrlGuard::isPublic('2606:2800:220:1:248:1893:25c8:1946'))->toBeTrue();
});

it('returns a refusal rather than reaching a private host', function (): void {
    $result = app(FetchSitePreview::class)->handle('http://169.254.169.254/latest/meta-data/');

    expect($result['ok'])->toBeFalse()
        // And it never leaks what is or is not there.
        ->and($result)->not->toHaveKey('title')
        ->and($result)->not->toHaveKey('description');
});

it('throttles the preview endpoint', function (): void {
    $user = previewUser();

    // The one endpoint that makes the server fetch an address the caller
    // chose, so the limit matters more than on the rest of the wizard.
    $limited = false;

    foreach (range(1, 25) as $attempt) {
        $response = $this->actingAs($user)
            ->postJson(advertiserUrl('/projects/preview'), ['url' => "https://example{$attempt}.test"]);

        if ($response->getStatusCode() === 429) {
            $limited = true;

            break;
        }
    }

    expect($limited)->toBeTrue('the preview endpoint accepted more than 20 requests in a minute');
});

it('requires a signed-in advertiser for every wizard endpoint', function (): void {
    $this->postJson(advertiserUrl('/projects/preview'), ['url' => 'https://example.test'])->assertUnauthorized();
    $this->patchJson(advertiserUrl('/projects/draft'), ['step' => 1, 'payload' => []])->assertUnauthorized();
    $this->get(advertiserUrl('/projects/create'))->assertRedirect();
});

it('normalises a URL to one canonical spelling', function (string $input, ?string $expected): void {
    expect(UrlNormalizer::normalize($input))->toBe($expected);
})->with([
    ['Example.COM', 'https://example.com'],
    // http is upgraded rather than preserved: this is the site being promoted.
    ['http://Example.com/', 'https://example.com'],
    // The path keeps its case — hosts are case-insensitive, paths are not.
    ['example.com/Path/', 'https://example.com/Path'],
    ['https://EXAMPLE.com:443/x?a=1#frag', 'https://example.com/x?a=1'],
    ['example.com:8080', 'https://example.com:8080'],
    ['münchen.de', 'https://xn--mnchen-3ya.de'],
    ['not a url', null],
    ['javascript:alert(1)', null],
    ['', null],
]);

it('knows which hosts share a registrable domain', function (): void {
    expect(PublicSuffix::sameSite('www.acme.com', 'acme.com'))->toBeTrue()
        ->and(PublicSuffix::sameSite('blog.acme.co.uk', 'shop.acme.co.uk'))->toBeTrue()
        ->and(PublicSuffix::sameSite('acme.com', 'evil.com'))->toBeFalse()
        // The multi-label suffix is the case a naive "last two labels" check
        // gets wrong, and it would let every .co.uk site match every other.
        ->and(PublicSuffix::sameSite('acme.co.uk', 'other.co.uk'))->toBeFalse()
        ->and(PublicSuffix::registrable('a.b.example.com.au'))->toBe('example.com.au')
        ->and(PublicSuffix::registrable('1.2.3.4'))->toBeNull();
});
