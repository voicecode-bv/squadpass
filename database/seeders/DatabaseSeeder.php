<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full local demo environment. Each part can also be run on its own with
 * `php artisan db:seed --class=...`; DemoClubSeeder needs the other two first.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AddonSeeder::class,
            DemoClubSeeder::class,
        ]);
    }
}
