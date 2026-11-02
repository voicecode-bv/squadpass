<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The fixed platform accounts (password for both: "password"):
 * - superadmin@squadpass.nl (superadmin)
 * - owner@squadpass.nl (owner of every demo club)
 *
 * Built without factories, so it runs without the dev dependencies, and
 * idempotent: existing accounts are left untouched.
 */
class UserSeeder extends Seeder
{
    public const string SUPERADMIN_EMAIL = 'superadmin@squadpass.nl';

    public const string OWNER_EMAIL = 'owner@squadpass.nl';

    public function run(): void
    {
        $this->account(self::SUPERADMIN_EMAIL, 'Superadmin', isSuperadmin: true);
        $this->account(self::OWNER_EMAIL, 'Demo Eigenaar');
    }

    protected function account(string $email, string $name, bool $isSuperadmin = false): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'is_superadmin' => $isSuperadmin,
            ])->save();
        }

        return $user;
    }
}
