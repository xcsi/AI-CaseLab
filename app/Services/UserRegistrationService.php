<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRegistrationService
{
    /**
     * Register a new user, always defaulting to the student role.
     *
     * Public registration only ever creates students — instructor/admin
     * accounts are provisioned separately (seeder or future admin CMS).
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $studentRole = Role::where('name', UserRole::Student->value)->firstOrFail();

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $studentRole->id,
        ]);
    }
}
