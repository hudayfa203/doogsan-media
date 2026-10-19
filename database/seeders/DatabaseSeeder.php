<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessSeeder::class,
            CategorySeeder::class,
            PageSeeder::class,
            MenuSeeder::class,
            SettingSeeder::class,
            BroadcastSeeder::class,
        ]);

    }
}
