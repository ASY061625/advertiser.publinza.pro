<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        // A filled honeypot is a bot. Answer as though it worked, so the bot
        // has nothing to learn, and record nothing.
        if ($request->filled('website')) {
            return back()->with('status', 'Thanks — we will reply within one working day.');
        }

        $data = $request->validated();

        // Logged, not stored as a Conversation: those belong to a registered
        // advertiser and this sender has no account yet. Wire a Mailable here
        // when the support inbox is chosen.
        Log::channel('single')->info('Marketing enquiry', [
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
        ]);

        return back()->with('status', 'Thanks — we will reply within one working day.');
    }
}
