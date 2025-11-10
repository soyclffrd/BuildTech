<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TrainerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Trainer One',
            'email' => 'trainer@skilllink.com',
            'password' => Hash::make('trainer123'),
            'role' => 'trainer',
        ]);
    }
}
