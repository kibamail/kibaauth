<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdministratorUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate a secure random password
        $password = Str::random(16);

        // Log the password to console
        $this->command->info("Administrator account created:");
        $this->command->info("Email: engineering@kibaauth.com");
        $this->command->info("Password: {$password}");
        $this->command->warn("Please save this password securely - it will not be shown again!");

        // Create the administrator user
        User::updateOrCreate(
            ['email' => 'engineering@kibaauth.com'],
            [
                'email' => 'engineering@kibaauth.com',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'administrator' => true,
            ]
        );
    }
}
