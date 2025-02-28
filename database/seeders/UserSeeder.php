<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert(
            [
                [
                    'nom' => 'Admin',
                    'prenom' => 'Général',
                    'email' => 'yousseramcf@gmail.com',
                    'tel' => '123456789',
                    'role' => 'Admin',
                    'sexe' => 'male',
                    'password' => Hash::make('12345678'),
                    'email_verified_at'=>now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]
        );
    }
}
