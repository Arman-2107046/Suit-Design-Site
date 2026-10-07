<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * The first super admin, for a fresh install. Administrators are their own
     * accounts (App\Models\Admin), not customer users. An existing account is
     * left exactly as it is, password included.
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            [
                'email' => 'armanr.rafi@gmail.com',
            ],
            [
                'name' => 'Arman',
                'password' => 'arman007',
                'role' => AdminRole::SuperAdmin,
            ]
        );
    }
}
