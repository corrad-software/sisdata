<?php

namespace App\Modules\Wfe\Database\Seeders;

use Illuminate\Database\Seeder;

/** php artisan db:seed --class="App\Modules\Wfe\Database\Seeders\WfeSeeder" */
class WfeSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CutiUmumSelangorSeeder::class, ContohAliranSeeder::class]);
    }
}
