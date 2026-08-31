<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Billing\Actions\TopUpWallet;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\TopUpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $wallet = $request->user()->wallet;

        return inertia('Billing/Index', [
            'wallet' => [
                'balanceMinorUnits' => $wallet?->balance_minor_units ?? 0,
                'frozenMinorUnits' => $wallet?->frozen_minor_units ?? 0,
            ],
            'transactions' => $wallet === null
                ? []
                : Transaction::query()->where('wallet_id', $wallet->id)->latest()->paginate(25),
        ]);
    }

    public function topUp(TopUpRequest $request, TopUpWallet $topUpWallet): RedirectResponse
    {
        $topUpWallet->handle(
            $request->user(),
            Money::fromMajorUnits($request->validated('amount')),
            $request->string('reference')->value(),
        );

        return back()->with('success', 'Balance topped up');
    }
}
