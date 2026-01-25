<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /** Run the database seeds. */

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'hassistosrl@gmail.com'],  // Evita duplicati se lo lanci più volte
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'google_token' => null,  // Sarà popolato dopo il login OAuth
            ]
        );
    }
}
