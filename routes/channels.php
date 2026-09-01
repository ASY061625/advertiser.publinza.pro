<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| The shell listens on one private channel per advertiser. Authorisation is the
| whole security boundary here: without this check any authenticated advertiser
| could subscribe to anyone's cart and conversation counts.
|
*/

Broadcast::channel('advertiser.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});
