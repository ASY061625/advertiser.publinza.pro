<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Role::query()->where('name', 'owner')->firstOrFail();

        Admin::query()->updateOrCreate(
            ['email' => 'owner@publinza.test'],
            [
                'name' => 'Publinza owner',
                'password' => Hash::make('password'),
                'role_id' => $owner->id,
                // A fixed, well-known TOTP secret so local sign-in works without
                // a QR scan. Development seed only.
                'two_factor_secret' => Crypt::encryptString('ABCDEFGHIJKLMNOP'),
                'two_factor_confirmed_at' => now(),
                'status' => 'active',
            ],
        );
    }
}
