<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Temporary Role Seeder (Manual SQL)
 * 
 * This seeder creates roles directly using SQL without requiring Spatie package.
 * Use this ONLY if Spatie package is not installed yet.
 * 
 * After installing Spatie, use the original RoleSeeder.php instead.
 */
class RoleSeederTemp extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'organizer', 'guard_name' => 'web'],
            ['name' => 'staff', 'guard_name' => 'web'],
            ['name' => 'attendee', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            // Check if role already exists
            $exists = DB::table('roles')
                ->where('name', $role['name'])
                ->where('guard_name', $role['guard_name'])
                ->exists();

            if (!$exists) {
                DB::table('roles')->insert([
                    'name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $this->command->info("✓ Role '{$role['name']}' created");
            } else {
                $this->command->warn("⚠ Role '{$role['name']}' already exists");
            }
        }

        $this->command->info('');
        $this->command->info('✅ Roles seeded successfully!');
        $this->command->warn('⚠️  Remember to install Spatie package: composer require spatie/laravel-permission');
    }
}
