<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Actions\ConfirmEmailChange;
use App\Domain\Identity\Actions\RequestAccountDeletion;
use App\Domain\Identity\Actions\RequestEmailChange;
use App\Domain\Identity\Enums\TokenAbility;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Identity\Support\DisplayFormats;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Identity\Support\ProfilePresenter;
use App\Domain\Identity\Support\RecoveryCodes;
use App\Domain\Identity\Support\SecurityLog;
use App\Domain\Identity\Support\TwoFactor;
use App\Domain\Identity\Support\VatNumber;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The advertiser's own account: who they are, who they invoice as, how they
 * sign in, what reaches them, and what their scripts may do.
 *
 * Five tabs over one URL, each saving on its own. Independently, deliberately:
 * these are five unrelated decisions, and a single Save that writes a password
 * change and a timezone in the same request makes both harder to reason about
 * and one of them harder to audit.
 */
class ProfileController extends Controller
{
    private const TABS = ['account', 'company', 'security', 'notifications', 'api'];

    public function index(
        Request $request,
        ProfilePresenter $presenter,
        NotificationSettings $notifications,
        TwoFactor $twoFactor,
        RequestAccountDeletion $deletion,
    ): Response {
        $user = $request->user();

        $tab = in_array($request->string('tab')->value(), self::TABS, true)
            ? $request->string('tab')->value()
            : 'account';

        return inertia('Profile/Index', [
            'tab' => $tab,
            'account' => $presenter->account($user),
            'company' => $presenter->company($user),
            'countries' => $presenter->countries(),
            'dateFormats' => DisplayFormats::dates(),
            'numberFormats' => DisplayFormats::numbers(),
            'vatCountries' => VatNumber::knownCountries(),
            'security' => [
                'twoFactor' => [
                    'enabled' => $user->hasTwoFactorEnabled(),
                    'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
                    'secret' => $user->two_factor_confirmed_at === null ? $twoFactor->secretFor($user) : null,
                    'provisioningUri' => $user->two_factor_confirmed_at === null
                        ? $twoFactor->provisioningUri($user)
                        : null,
                    'recoveryCodesLeft' => RecoveryCodes::remaining($user),
                ],
                'sessions' => $presenter->sessions($user, ProfilePresenter::currentSessionId()),
                'loginHistory' => $presenter->loginHistory($user),
                'log' => app(SecurityLog::class)->recentFor($user),
            ],
            'notifications' => [
                'matrix' => $notifications->matrix($user),
                'events' => $notifications->catalogue(),
                'channels' => $notifications->channels(),
                'pausedUntil' => $user->notifications_paused_until?->toDateString(),
            ],
            'api' => $presenter->api(),
            'tokens' => $presenter->tokens($user),
            'deletion' => [
                'retentionDays' => RequestAccountDeletion::RETENTION_DAYS,
                'requestedAt' => $user->deletion_requested_at?->toIso8601String(),
                ...$deletion->blockers($user),
            ],
        ]);
    }

    // -------------------------------------------------------------- account

    public function updateAccount(Request $request, SecurityLog $log, RequestEmailChange $emailChange): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'locale' => ['required', 'string', 'max:8'],
            'date_format' => ['required', 'string', Rule::in(array_column(DisplayFormats::dates(), 'value'))],
            'number_format' => ['required', 'string', Rule::in(array_column(DisplayFormats::numbers(), 'value'))],
        ]);

        $newEmail = mb_strtolower(trim($data['email']));
        $emailIsChanging = $newEmail !== mb_strtolower($user->email);

        if ($emailIsChanging) {
            $taken = User::query()
                ->where('email', $newEmail)
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => 'That address is already on another Publinza account.',
                ]);
            }
        }

        $user->forceFill([
            'name' => $data['name'],
            'display_name' => $this->nullable($data['display_name'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'phone_country' => $this->upper($data['phone_country'] ?? null),
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
            'date_format' => $data['date_format'],
            'number_format' => $data['number_format'],
        ])->save();

        // The email is the one field here that is not simply saved. It goes to
        // pending_email and has to be proven from the new inbox first.
        if ($emailIsChanging) {
            $emailChange->handle($user, $newEmail);

            $log->record($user, 'email.change_requested', ['to' => $newEmail]);

            return back()->with(
                'success',
                "Saved. Check {$newEmail} for a link — your address changes once you confirm it there.",
            );
        }

        return back()->with('success', 'Account saved.');
    }

    public function cancelEmailChange(Request $request, RequestEmailChange $emailChange): RedirectResponse
    {
        $emailChange->cancel($request->user());

        return back()->with('success', 'Email change cancelled.');
    }

    public function resendEmailChange(Request $request, RequestEmailChange $emailChange): RedirectResponse
    {
        $user = $request->user();

        if ($user->pending_email === null) {
            return back()->with('error', 'There is no email change waiting.');
        }

        $emailChange->handle($user, $user->pending_email);

        return back()->with('success', "Sent again to {$user->pending_email}.");
    }

    /** The link from the new inbox. Signed-in only — it changes an account. */
    public function confirmEmail(Request $request, string $token, ConfirmEmailChange $confirm): RedirectResponse
    {
        if (! $confirm->handle($request->user(), $token)) {
            return redirect('/profile')->with(
                'error',
                'That link has expired or has already been used. Start the change again from your profile.',
            );
        }

        return redirect('/profile')->with('success', 'Your email address is updated.');
    }

    public function updateAvatar(Request $request, SecurityLog $log): RedirectResponse
    {
        $max = (int) config('publinza.uploads.avatar_max_kb');

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.$max, 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'avatar.max' => 'That image is larger than '.round($max / 1024, 1).' MB.',
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => $request->file('avatar')->store("avatars/{$user->id}", 'local'),
        ])->save();

        // Deleted after the new one is written, so a failed upload never leaves
        // somebody with no avatar and no way back.
        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return back()->with('success', 'Photo updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path !== null) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('success', 'Photo removed.');
    }

    /**
     * Serves an avatar through the app.
     *
     * Addressed by a hash of its path rather than by user id, so the URL does
     * not enumerate accounts, and served from the private disk so a stale URL
     * stops working the moment the file is replaced.
     */
    public function avatar(Request $request, string $hash): StreamedResponse
    {
        return $this->serveImage($request->user()->avatar_path, $hash);
    }

    public function companyLogo(Request $request, string $hash): StreamedResponse
    {
        return $this->serveImage($request->user()->company_logo_path, $hash);
    }

    // -------------------------------------------------------------- company

    public function updateCompany(Request $request, SecurityLog $log): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'company' => ['nullable', 'string', 'max:190'],
            'registration_no' => ['nullable', 'string', 'max:64'],
            'vat_no' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'size:2'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'billing_email' => ['nullable', 'email', 'max:190'],
        ]);

        $country = $this->upper($data['country'] ?? null);
        $vat = $data['vat_no'] ?? null;

        if ($vat !== null && ! VatNumber::matches($vat, $country)) {
            $example = VatNumber::exampleFor($country);

            throw ValidationException::withMessages([
                'vat_no' => $example === null
                    ? 'That does not look like a VAT number. It should start with a two-letter country code.'
                    : "That is not the shape of a {$country} VAT number. They look like {$example}.",
            ]);
        }

        $user->forceFill([
            'company' => $this->nullable($data['company'] ?? null),
            'registration_no' => $this->nullable($data['registration_no'] ?? null),
            'vat_no' => $vat === null ? null : VatNumber::normalise($vat),
            'country' => $country,
            'billing_address' => $this->nullable($data['billing_address'] ?? null),
            'billing_email' => $this->nullable($data['billing_email'] ?? null),
        ])->save();

        $log->record($user, 'company.updated');

        return back()->with(
            'success',
            'Company details saved. They appear on invoices issued from now on — invoices already issued keep what they were issued with.',
        );
    }

    public function updateCompanyLogo(Request $request): RedirectResponse
    {
        $max = (int) config('publinza.uploads.logo_max_kb');

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.$max],
        ]);

        $user = $request->user();
        $previous = $user->company_logo_path;

        $user->forceFill([
            'company_logo_path' => $request->file('logo')->store("logos/{$user->id}", 'local'),
        ])->save();

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return back()->with('success', 'Logo updated. It appears on invoices issued from now on.');
    }

    public function destroyCompanyLogo(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->company_logo_path !== null) {
            Storage::disk('local')->delete($user->company_logo_path);
            $user->forceFill(['company_logo_path' => null])->save();
        }

        return back()->with('success', 'Logo removed.');
    }

    // ------------------------------------------------------------- security

    public function updatePassword(Request $request, ChangePassword $change): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'That is the password you already have. Choose a different one.',
            ]);
        }

        $revoked = $change->handle($user, $data['password'], $request->session()->getId());

        // The current session's hash has to move with the password or the next
        // request signs this browser out too — which is the correct behaviour
        // for every *other* session and the wrong one for this one.
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        return back()->with('success', $revoked > 0
            ? "Password changed. {$revoked} other ".($revoked === 1 ? 'session was' : 'sessions were').' signed out.'
            : 'Password changed.');
    }

    public function revokeSession(Request $request): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'string', 'size:64']]);

        abort_unless(config('session.driver') === 'database', 400);

        $user = $request->user();
        $current = $request->session()->getId();
        $removed = 0;

        foreach (DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->pluck('id') as $id) {
            // The browser only ever knew a hash of the id, so the match happens
            // here rather than in the query.
            if (hash('sha256', (string) $id) !== $data['id'] || hash_equals((string) $id, $current)) {
                continue;
            }

            DB::table(config('session.table', 'sessions'))->where('id', $id)->delete();
            $removed++;
        }

        app(SecurityLog::class)->record($user, 'sessions.revoked', ['count' => $removed]);

        return back()->with('success', $removed > 0 ? 'That browser is signed out.' : 'That session had already ended.');
    }

    public function revokeOtherSessions(Request $request, SecurityLog $log): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 400);

        $user = $request->user();

        $removed = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $log->record($user, 'sessions.revoked', ['count' => $removed, 'scope' => 'all_others']);

        return back()->with('success', $removed === 0
            ? 'You are not signed in anywhere else.'
            : $removed.' other '.($removed === 1 ? 'session' : 'sessions').' signed out.');
    }

    // -------------------------------------------------------- notifications

    public function updateNotifications(Request $request, NotificationSettings $settings, SecurityLog $log): RedirectResponse
    {
        $data = $request->validate([
            // `present`, not `required`: Laravel counts an empty array as
            // empty, so `required` rejects a save that only changes the pause
            // window — and rejects it as a redirect, which looks like success.
            'matrix' => ['present', 'array'],
            'matrix.*.email' => ['boolean'],
            'matrix.*.in_app' => ['boolean'],
            'matrix.*.push' => ['boolean'],
            'paused_until' => ['nullable', 'date', 'after:today', 'before:'.now()->addYear()->toDateString()],
        ]);

        $user = $request->user();

        /** @var array<string, array<string, bool>> $matrix */
        $matrix = $data['matrix'];

        $settings->save($user, $matrix);

        $user->forceFill([
            'notifications_paused_until' => $data['paused_until'] ?? null,
        ])->save();

        $log->record($user, 'notifications.updated', [
            'pausedUntil' => $data['paused_until'] ?? null,
        ]);

        return back()->with('success', 'Notification settings saved.');
    }

    // ------------------------------------------------------------------ api

    public function createToken(Request $request, SecurityLog $log): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_column(TokenAbility::cases(), 'value'))],
            'expires_at' => ['nullable', 'date', 'after:today', 'before:'.now()->addYears(2)->toDateString()],
        ], [
            'abilities.required' => 'Choose at least one thing this token may do.',
        ]);

        ['plain' => $plain, 'hash' => $hash] = PersonalAccessToken::mint();

        $token = PersonalAccessToken::query()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'token' => $hash,
            'abilities' => array_values(array_unique($data['abilities'])),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $log->record($request->user(), 'token.created', [
            'name' => $token->name,
            'abilities' => $token->abilities,
        ]);

        // Flashed, which means it exists in one response and is never
        // retrievable again — the same treatment recovery codes get.
        return back()->with('newToken', ['name' => $token->name, 'plain' => $plain]);
    }

    public function revokeToken(Request $request, PersonalAccessToken $token, SecurityLog $log): RedirectResponse
    {
        abort_if($token->user_id !== $request->user()->id, 404);

        $name = $token->name;
        $token->delete();

        $log->record($request->user(), 'token.revoked', ['name' => $name]);

        return back()->with('success', "Token “{$name}” revoked. Anything using it stops working now.");
    }

    // ------------------------------------------------------------- deletion

    public function requestDeletion(Request $request, RequestAccountDeletion $deletion): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => 'Tick the box to confirm you understand what happens.',
        ]);

        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'That is not your password.']);
        }

        $blockers = $deletion->blockers($user);

        if ($blockers['blocked']) {
            throw ValidationException::withMessages([
                'confirm' => 'Your account still has work or money in flight. See the reasons above.',
            ]);
        }

        $deletion->request($user);

        return back()->with('success', sprintf(
            'Your account will be deleted in %d days. Sign in any time before then to cancel it.',
            RequestAccountDeletion::RETENTION_DAYS,
        ));
    }

    public function cancelDeletion(Request $request, RequestAccountDeletion $deletion): RedirectResponse
    {
        $deletion->cancel($request->user());

        return back()->with('success', 'Your account will not be deleted.');
    }

    // ------------------------------------------------------------ internals

    private function serveImage(?string $path, string $hash): StreamedResponse
    {
        abort_if($path === null || ! hash_equals(md5($path), $hash), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            // Private: this is one person's face, on one person's account.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function upper(?string $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : mb_strtoupper($value);
    }
}
