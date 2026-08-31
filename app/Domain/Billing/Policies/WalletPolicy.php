<?php

declare(strict_types=1);

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Models\Wallet;
use App\Models\User;

class WalletPolicy
{
    public function view(User $user, Wallet $wallet): bool
    {
        return $wallet->user_id === $user->id;
    }

    public function topUp(User $user, Wallet $wallet): bool
    {
        return $this->view($user, $wallet);
    }
}
