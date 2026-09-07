<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Billing\Actions\GetWalletBalance;
use App\Domain\Identity\Support\DisplayFormats;
use App\Support\ShellData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleAdvertiserInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly GetWalletBalance $getWalletBalance,
        private readonly ShellData $shellData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    // What the app calls them, which is not always their legal
                    // name. Invoices use `name`; the header and messages use
                    // this, falling back to the first word of the other.
                    'displayName' => $user->displayName(),
                    'email' => $user->email,
                    'avatarUrl' => $user->avatarUrl(),
                ],
            ],
            /*
             * How this person wants dates and numbers rendered.
             *
             * Shared on every response rather than fetched by the screen that
             * sets it, because it drives every table, chart and timestamp in
             * the app — main.tsx feeds it into @shared/lib/format before the
             * first paint, so nothing renders in the wrong format first.
             */
            'formats' => [
                'date' => $user?->date_format ?? DisplayFormats::DEFAULT_DATE,
                'number' => $user?->number_format ?? DisplayFormats::DEFAULT_NUMBER,
                'timeZone' => $user?->timezone ?? 'UTC',
            ],
            // The wallet chip sits in the header on every advertiser screen.
            'balanceCents' => fn (): int => $user === null
                ? 0
                : $this->getWalletBalance->handle($user)->cents,
            // Everything the persistent shell renders. A closure, so an
            // Inertia partial reload that does not ask for it pays nothing.
            'shell' => fn (): ?array => $user === null ? null : $this->shellData->forUser($user),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'status' => $request->session()->get('status'),
                // Recovery codes are flashed exactly once, by the response that
                // generated them. They are hashed at rest and cannot be re-read.
                'recoveryCodes' => $request->session()->get('recoveryCodes'),
                // A list move, carrying everything undo needs to reverse it.
                // It has to travel with the response: the note and the reason
                // are gone from the database by the time the toast renders, so
                // re-reading them would find nothing.
                'moved' => $request->session()->get('moved'),
                // What a blacklist import actually did, in three named groups.
                // More than a toast can hold, and the unmatched domains are the
                // whole reason to look.
                'importReport' => $request->session()->get('importReport'),
                // A personal access token, flashed by the request that minted
                // it. Only its hash is stored, so this is the one and only
                // render in which it exists.
                'newToken' => $request->session()->get('newToken'),
            ],
        ];
    }
}
