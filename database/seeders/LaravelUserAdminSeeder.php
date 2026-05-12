<?php

namespace Susheelhbti\LaravelUserAdmin\Database\Seeders;

use Illuminate\Database\Seeder;
use Susheelhbti\LaravelUserAdmin\Models\Permission;
use Susheelhbti\LaravelUserAdmin\Models\Role;

class LaravelUserAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $admin     = Role::firstOrCreate(['slug' => 'admin'],     ['name' => 'Administrator', 'description' => 'Full system access']);
        $moderator = Role::firstOrCreate(['slug' => 'moderator'], ['name' => 'Moderator',     'description' => 'Can manage users but not admin settings']);
        $user      = Role::firstOrCreate(['slug' => 'user'],      ['name' => 'User',           'description' => 'Regular user']);

        // Permissions
        $permissions = [
            ['name' => 'View Users',        'slug' => 'view_users',        'group' => 'users'],
            ['name' => 'Create Users',       'slug' => 'create_users',      'group' => 'users'],
            ['name' => 'Edit Users',         'slug' => 'edit_users',        'group' => 'users'],
            ['name' => 'Delete Users',       'slug' => 'delete_users',      'group' => 'users'],
            ['name' => 'Suspend Users',      'slug' => 'suspend_users',     'group' => 'users'],
            ['name' => 'Manage Roles',       'slug' => 'manage_roles',      'group' => 'roles'],
            ['name' => 'View Logs',          'slug' => 'view_logs',         'group' => 'logs'],
            ['name' => 'View Admin Logs',    'slug' => 'view_admin_logs',   'group' => 'logs'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // Assign all permissions to admin
        $admin->permissions()->sync(Permission::all()->pluck('id'));

        // Assign subset to moderator
        $moderator->permissions()->sync(
            Permission::whereIn('slug', ['view_users', 'edit_users', 'suspend_users'])->pluck('id')
        );
    }
}
