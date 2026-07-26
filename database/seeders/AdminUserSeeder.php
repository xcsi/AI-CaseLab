<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates one admin account for local development only — this is not
     * meant to run against a real deployment.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $adminRole = Role::where('name', UserRole::Admin->value)->firstOrFail();

        User::firstOrCreate(
            ['email' => 'admin@aicaselab.test'],
            [
                'name' => 'AI CaseLab Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
