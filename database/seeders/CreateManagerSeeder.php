<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class CreateManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $user = User::create([
            'name' => 'Manager', 
            'email' => 'manager@gmail.com',
            'password' => bcrypt('123456'),
            'status' => 'active',
        ]);
        
        $role = Role::create(['name' => 'Manager']);
         
        $permissions = Permission::whereIn('id', [5, 6, 7, 8, 9])->pluck('id', 'id')->all();
       
        $role->syncPermissions($permissions);
         
        $user->assignRole([$role->id]);
    }
}
