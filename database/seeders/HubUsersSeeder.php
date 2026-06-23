<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class HubUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('hub.default_password');

        $superAdmin = User::updateOrCreate(
            ['email' => 'startupm3.5@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'is_super_admin' => true,
                'wp_username' => null,
            ]
        );

        $this->attachTenantAdmins('beauty-of-image', [
            [
                'email' => 'info@beautyofimage.com',
                'name' => 'Info Beauty',
                'wp_username' => 'info',
            ],
            [
                'email' => 'emilia@beautyofimage.com',
                'name' => 'Emilia',
                'wp_username' => 'emilia',
            ],
            [
                'email' => 'pasquale.costantino@ferrero.com',
                'name' => 'Pasquale',
                'wp_username' => 'pasquale',
            ],
        ]);

        $this->attachTenantAdmins('piramide35', [
            [
                'email' => 'startupm3.5@gmail.com',
                'name' => 'Super Admin',
                'wp_username' => null,
                'existing' => $superAdmin,
            ],
        ]);
    }

    /** @param  array<int, array{email: string, name: string, wp_username: ?string, existing?: User}>  $users */
    private function attachTenantAdmins(string $tenantSlug, array $users): void
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return;
        }

        $password = config('hub.default_password');

        foreach ($users as $data) {
            $user = $data['existing'] ?? User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'is_super_admin' => false,
                    'wp_username' => $data['wp_username'],
                ]
            );

            $tenant->users()->syncWithoutDetaching([
                $user->id => ['role' => 'admin'],
            ]);
        }
    }
}