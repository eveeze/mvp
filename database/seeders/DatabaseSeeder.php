<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Panggil Seeder untuk Super Admin agar akun admin terbentuk
        $this->call([
            SuperAdminSeeder::class,
        ]);
        
        // Opsional: Jika ingin user dummy tambahan untuk testing
        // \App\Models\User::factory()->create([
        //     'name' => 'Test Advertiser',
        //     'email' => 'test@example.com',
        //     'role' => 'advertiser',
        // ]);
    }
}