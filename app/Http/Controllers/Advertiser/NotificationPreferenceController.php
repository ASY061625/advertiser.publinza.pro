<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Whether Publinza emails this advertiser when the team replies.
 *
 * Its own endpoint rather than a field on some larger settings form, because
 * the control that sets it lives beside the conversations it governs. A
 * preference you can only change three screens away from the thing it affects
 * is a preference nobody finds.
 */
class NotificationPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['notify_replies' => ['required', 'boolean']]);

        // forceFill, like the sidebar and the changelog marker: `$fillable` on
        // User is the profile a person edits, and a preference toggle does not
        // belong in the same list as their email address.
        $request->user()->forceFill(['notify_replies' => $request->boolean('notify_replies')])->save();

        return back()->with(
            'success',
            $request->boolean('notify_replies')
                ? 'We will email you when the team replies.'
                : 'Reply emails are off. The badge in the header still counts them.',
        );
    }
}
