<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            CategorySeeder::class,
            PSGCSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Business Owner',
            'email' => 'owner@example.com',
            'role' => UserRole::OWNER
        ]);

        User::factory()->create([
            'name' => 'Consumer',
            'email' => 'consumer@example.com',
        ]);
    }
}
