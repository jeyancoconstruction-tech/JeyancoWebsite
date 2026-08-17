<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // The bootstrap administrator. is_admin is normally kept in step with
        // `role` by the model, but this seeder runs WithoutModelEvents, so it
        // is set explicitly here.
        $admin = User::create([
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@jeyanco.com',
            'password' => \Illuminate\Support\Facades\Hash::make('admin'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $admin->forceFill(['is_admin' => true])->saveQuietly();
    }
}
