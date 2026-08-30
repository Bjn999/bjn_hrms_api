<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Company;

class DefaultRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $companies = Company::all();
        
        $roles = [
            [
                'name' => 'company_admin',
                'display_name' => 'مدير الشركة',
                'guard_name' => 'web'
            ],
            [
                'name' => 'hr_manager',
                'display_name' => 'مدير الموارد البشرية',
                'guard_name' => 'web'
            ],
            [
                'name' => 'employee',
                'display_name' => 'موظف',
                'guard_name' => 'web'
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                [
                    'name' => $roleData['name'], 
                    'guard_name' => $roleData['guard_name']
                ],
                [
                    'display_name' => $roleData['display_name']
                ]
            );
            
            if ($roleData['name'] === 'company_admin') {
                $role->syncPermissions(Permission::all());
            } elseif ($roleData['name'] === 'employee') {
                $role->syncPermissions(['view_dashboard', 'view_own_salary']);
            } elseif ($roleData['name'] === 'hr_manager') {
                $role->syncPermissions(Permission::whereNotIn('name', ['manage_settings', 'manage_roles'])->get());
            }
        }
    }
}
