<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Admin\Models\Admin;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $advertiser = User::factory()->create([
            'name' => 'Demo advertiser',
            'email' => 'advertiser@publinza.test',
        ]);

        Wallet::query()->create([
            'user_id' => $advertiser->id,
            'balance_minor_units' => 250_000,
            'frozen_minor_units' => 0,
            'currency' => 'USD',
        ]);

        Admin::query()->create([
            'name' => 'Demo admin',
            'email' => 'admin@publinza.test',
            'password' => Hash::make('password'),
            'role' => 'super',
            'two_factor_secret' => Crypt::encryptString('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
        ]);

        // Enough spread that the catalog quant-bars are worth looking at.
        Site::factory()->count(200)->create();
        Site::factory()->count(15)->pending()->create();
    }
}
