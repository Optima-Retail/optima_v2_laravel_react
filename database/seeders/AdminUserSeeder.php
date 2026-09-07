<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@optima.test'],
            [
                'name' => 'Optima Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $admin->syncRoles([RoleEnum::Admin->value]);

        $member = User::query()->updateOrCreate(
            ['email' => 'user@optima.test'],
            [
                'name' => 'Optima User',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $member->syncRoles([RoleEnum::User->value]);
    }
}
