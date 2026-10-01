<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@lorito.local'],
            ['name' => 'Administrador Lori', 'password' => Hash::make('lorito2026'), 'role' => 'admin', 'sales_point_id' => null],
        );
    }
}
