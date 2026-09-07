<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Domain\Catalog\Models\Country;
use App\Domain\Identity\Enums\TokenAbility;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * The shapes the profile page is sent.
 *
 * One place, because five tabs read the same account and five payloads
 * assembled separately drift into five slightly different answers about the
 * same person.
 */
final class ProfilePresenter
{
    private const LOGIN_HISTORY = 20;

    /**
     * @return array<string, mixed>
     */
    public function account(User $user): array
    {
        return [
            'name' => $user->name,
            'displayName' => $user->display_name,
            'displayNameFallback' => $user->displayName(),
            'email' => $user->email,
            'emailVerified' => $user->email_verified_at !== null,
            'pendingEmail' => $user->pending_email,
            'pendingEmailSentAt' => $user->pending_email_sent_at?->toIso8601String(),
            'phone' => $user->phone,
            'phoneCountry' => $user->phone_country,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'dateFormat' => $user->date_format,
            'numberFormat' => $user->number_format,
            'avatarUrl' => $user->avatarUrl(),
            'deletionRequestedAt' => $user->deletion_requested_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function company(User $user): array
    {
        return [
            'company' => $user->company,
            'registrationNo' => $user->registration_no,
            'vatNo' => $user->vat_no,
            'country' => $user->country,
            'billingAddress' => $user->billing_address,
            'billingEmail' => $user->billing_email,
            'accountEmail' => $user->email,
            'logoUrl' => $user->company_logo_path === null
                ? null
                : '/profile/company-logo/'.md5($user->company_logo_path),
            'vatExample' => VatNumber::exampleFor($user->country),
        ];
    }

    /**
     * Every browser currently signed in as this person.
     *
     * Only answerable on the database session driver, which is what this
     * deployment uses. On Redis or files the sessions are not enumerable, and
     * the tab says so rather than showing an empty list that looks like "you
     * are signed in nowhere".
     *
     * @return array{supported: bool, sessions: list<array<string, mixed>>}
     */
    public function sessions(User $user, string $currentId): array
    {
        if (config('session.driver') !== 'database') {
            return ['supported' => false, 'sessions' => []];
        }

        $rows = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return [
            'supported' => true,
            'sessions' => $rows->map(function ($row) use ($currentId): array {
                $device = DeviceLabel::parse($row->user_agent);

                return [
                    // The session id is a bearer credential in a cookie, so it
                    // never reaches the browser as data. Revoking addresses a
                    // session by a hash of it instead.
                    'id' => hash('sha256', (string) $row->id),
                    'current' => hash_equals((string) $row->id, $currentId),
                    'browser' => $device['browser'],
                    'platform' => $device['platform'],
                    'label' => $device['label'],
                    'ip' => $row->ip_address,
                    'lastActiveAt' => $row->last_activity === null
                        ? null
                        : now()->setTimestamp((int) $row->last_activity)->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * The last twenty sign-in attempts, with anything from an unfamiliar
     * country flagged.
     *
     * "New" means: not seen on a *successful* sign-in before this one. Judging
     * against all attempts would let an attacker's own failed try establish
     * their country as familiar, which is precisely backwards.
     *
     * @return array{rows: list<array<string, mixed>>, geoAvailable: bool}
     */
    public function loginHistory(User $user): array
    {
        $attempts = LoginAttempt::query()
            ->where('email', $user->email)
            ->latest('created_at')
            ->latest('id')
            ->take(self::LOGIN_HISTORY)
            ->get();

        /*
         * Countries this account had already signed in from before the oldest
         * row on screen.
         *
         * Seeded from history rather than starting empty, or somebody's own
         * country gets flagged as new the moment their history is longer than
         * twenty rows.
         */
        $oldest = $attempts->last();

        $seen = LoginAttempt::query()
            ->where('email', $user->email)
            ->where('successful', true)
            ->whereNotNull('country')
            ->when($oldest !== null, fn ($q) => $q->where('id', '<', $oldest->id))
            ->distinct()
            ->pluck('country')
            ->all();

        $names = Country::query()->pluck('name', 'code');
        $rows = [];

        // Oldest first, so the *first* sign-in from a country is the one
        // flagged rather than every later one.
        foreach ($attempts->reverse() as $attempt) {
            $country = $attempt->country;

            /*
             * New means: not seen on a successful sign-in before this attempt.
             *
             * Judged against successful ones only — letting an attacker's own
             * failed try establish their country as familiar is precisely
             * backwards. And never on the very first sign-in an account ever
             * makes, which is new by definition and means nothing.
             */
            $isNew = $country !== null
                && $seen !== []
                && ! in_array($country, $seen, true);

            if ($country !== null && $attempt->successful && ! in_array($country, $seen, true)) {
                $seen[] = $country;
            }

            $device = DeviceLabel::parse($attempt->user_agent);

            $rows[] = [
                'id' => $attempt->id,
                'successful' => $attempt->successful,
                'ip' => $attempt->ip_address,
                'country' => $country,
                'countryName' => $country === null ? null : ($names[$country] ?? $country),
                'label' => $device['label'],
                'at' => $attempt->created_at?->toIso8601String(),
                'newCountry' => $isNew,
            ];
        }

        return [
            'rows' => array_reverse($rows),
            // Where the deployment has no geo header, every country is null and
            // the column says so rather than looking like a bug.
            'geoAvailable' => config('publinza.geolocation.country_header') !== '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tokens(User $user): array
    {
        return PersonalAccessToken::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get()
            ->map(static fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'createdAt' => $token->created_at?->toIso8601String(),
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'expiresAt' => $token->expires_at?->toIso8601String(),
                'expired' => $token->isExpired(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function api(): array
    {
        return [
            'abilities' => array_map(static fn (TokenAbility $ability): array => [
                'value' => $ability->value,
                'label' => $ability->label(),
                'description' => $ability->description(),
                'destructive' => $ability->isDestructive(),
            ], TokenAbility::cases()),
            'docsUrl' => (string) config('publinza.api.docs_url'),
            'baseUrl' => (string) config('publinza.api.base_url'),
            // The real ceiling, read from the same config the limiter uses,
            // so a page that promises 120 an hour cannot outlive a change to 60.
            'rateLimit' => [
                'requests' => (int) config('publinza.api.rate_limit'),
                'window' => 'minute',
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function countries(): array
    {
        return Country::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(static fn (Country $country): array => [
                'value' => $country->code,
                'label' => $country->name,
            ])
            ->values()
            ->all();
    }

    /** The session the request itself is on, for "this device". */
    public static function currentSessionId(): string
    {
        return (string) Request::session()->getId();
    }
}
