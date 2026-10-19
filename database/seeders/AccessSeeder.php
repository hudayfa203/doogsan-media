<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::CATALOGUE as $group => $items) {
            foreach ($items as $key => $label) {
                Permission::updateOrCreate(['key' => $key], ['group' => $group, 'label' => $label]);
            }
        }

        foreach (Permissions::ROLES as $key => [$name, $description, $requires2fa, $grants]) {
            $role = Role::updateOrCreate(['key' => $key], ['name' => $name, 'description' => $description, 'is_system' => true, 'requires_2fa' => $requires2fa]);
            $role->permissions()->sync(Permission::whereIn('key', Permissions::expand($grants))->pluck('id'));
        }

        // First administrator. There is no default password: set ADMIN_PASSWORD
        // in .env, or a random one is generated and printed once to the console.
        $email = env('ADMIN_EMAIL', 'admin@doogsanmedia.com');
        if (! User::where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: \Illuminate\Support\Str::password(20);
            if (! env('ADMIN_PASSWORD')) {
                $this->command?->warn("Administrator {$email} created with a one-time password: {$password}");
                $this->command?->warn('Sign in and change it immediately (Admin → Account security).');
            }
            User::create([
                'name' => env('ADMIN_NAME', 'Doogsan Administrator'),
                'email' => $email,
                'password' => $password,
                'role_id' => Role::where('key', 'super-admin')->value('id'),
                'is_active' => true,
            ]);
        }
    }
}
