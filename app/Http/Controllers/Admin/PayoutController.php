<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(): Response
    {
        return inertia('Payouts/Index', ['payouts' => []]);
    }

    public function release(int $payout): RedirectResponse
    {
        return back()->with('success', "Payout #{$payout} released");
    }
}
