<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Household;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $household = Household::create([
            'name' => 'PénzPilot Otthon',
            'invite_code' => 'PILOT123'
        ]);

        User::create([
            'first_name' => 'Dani',
            'last_name' => 'K.',
            'email' => 'dani@example.com',
            'password' => Hash::make('password123'),
            'household_id' => $household->id
        ]);
    }
}
