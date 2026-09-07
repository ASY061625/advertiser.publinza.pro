<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Models\NotificationPreference;
use App\Domain\Identity\Support\NotificationSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The one switch on the conversations page: does a team reply reach this
 * person by email.
 *
 * Its own endpoint rather than a field on a larger settings form, because the
 * control that sets it lives beside the conversations it governs — a preference
 * you can only change three screens away from the thing it affects is a
 * preference nobody finds.
 *
 * It writes the same `new_message` row the profile's notification matrix
 * writes. It used to write a `notify_replies` column of its own, which meant
 * two places answered "does this person want this email" and could disagree.
 */
class NotificationPreferenceController extends Controller
{
    public function update(Request $request, NotificationSettings $settings): RedirectResponse
    {
        $request->validate(['notify_replies' => ['required', 'boolean']]);

        $user = $request->user();
        $wanted = $request->boolean('notify_replies');
        $current = $settings->matrix($user)[NotificationEvent::NewMessage->value];

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'event' => NotificationEvent::NewMessage->value],
            // Only the email channel is being decided here. In-app and push
            // keep whatever the profile set — this switch is not a claim about
            // them, and overwriting them would silently undo that screen.
            [...$current, NotificationChannel::Email->column() => $wanted],
        );

        return back()->with(
            'success',
            $wanted
                ? 'We will email you when the team replies.'
                : 'Reply emails are off. The badge in the header still counts them.',
        );
    }
}
