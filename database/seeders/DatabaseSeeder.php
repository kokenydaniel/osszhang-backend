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
            'name' => 'Összhang Otthon',
            'invite_code' => 'OSSZH123'
        ]);

        User::create([
            'first_name' => 'Dani',
            'last_name' => 'K.',
            'username' => 'dani',
            'password' => Hash::make('password123'),
            'household_id' => $household->id,
            'role' => 'admin',
            'permissions' => ['budget', 'utilities', 'business', 'meters', 'debts', 'savings'],
        ]);
    }
}
