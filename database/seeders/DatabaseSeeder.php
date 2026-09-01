<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Order matters: websites need categories, the demo advertiser
            // needs websites, and the owner admin needs its role.
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            AdminSeeder::class,
            WebsiteSeeder::class,
            DemoAdvertiserSeeder::class,
        ]);
    }
}
