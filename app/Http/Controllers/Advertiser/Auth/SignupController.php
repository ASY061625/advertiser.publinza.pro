<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Catalog\Models\Country;
use App\Domain\Identity\Actions\RegisterAdvertiser;
use App\Domain\Identity\DTOs\RegistrationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\Auth\SignupRequest;
use App\Support\NetworkStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class SignupController extends Controller
{
    /** Fixed options, so the column holds a small comparable set rather than free text. */
    public const REFERRER_SOURCES = [
        'search' => 'Search engine',
        'referral' => 'A colleague or friend',
        'agency' => 'My agency recommended it',
        'linkedin' => 'LinkedIn',
        'community' => 'A forum or community',
        'newsletter' => 'A newsletter',
        'event' => 'A conference or event',
        'other' => 'Somewhere else',
    ];

    public function create(NetworkStats $stats): Response
    {
        return inertia('Auth/Signup', [
            'countries' => $this->countries(),
            'referrerSources' => collect(self::REFERRER_SOURCES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'proofLines' => $stats->proofLines(),
        ]);
    }

    public function store(SignupRequest $request, RegisterAdvertiser $register): RedirectResponse
    {
        $user = $register->handle(RegistrationData::fromArray($request->validated()));

        // Signed in immediately, but unverified: the verification gate decides
        // what an unverified account may reach, not the sign-in itself.
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function countries(): array
    {
        /** @var list<array{value: string, label: string}> $countries */
        $countries = Cache::remember('auth:countries', now()->addDay(), fn (): array => Country::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Country $c): array => ['value' => $c->code, 'label' => $c->name])
            ->all());

        return $countries;
    }
}
