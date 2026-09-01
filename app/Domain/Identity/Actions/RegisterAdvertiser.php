<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Billing\Models\Wallet;
use App\Domain\Identity\DTOs\RegistrationData;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use App\Domain\Trading\Models\Cart;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Creates an advertiser account.
 *
 * There is no publisher role anywhere in this product: every account created
 * here buys placements, and the sites are ours.
 *
 * The wallet and cart are created in the same transaction as the user so no
 * later code has to branch on a missing record — an advertiser always has both,
 * from their first request onwards.
 */
final class RegisterAdvertiser
{
    public function handle(RegistrationData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'company' => $data->company,
                'country' => $data->country,
                'referrer_source' => $data->referrerSource,
                'timezone' => $data->timezone,
                'locale' => 'en',
                'status' => UserStatus::Active,
            ]);

            Wallet::query()->create([
                'user_id' => $user->id,
                'available_cents' => 0,
                'frozen_cents' => 0,
                'currency' => config('publinza.billing.currency', 'USD'),
            ]);

            Cart::query()->create(['user_id' => $user->id]);

            return $user;
        });

        // Outside the transaction: the verification mail is queued, and queuing
        // it inside would risk a worker reading the row before the commit.
        event(new Registered($user));

        return $user;
    }
}
