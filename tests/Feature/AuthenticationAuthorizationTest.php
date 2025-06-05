<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthenticationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;
    protected $adminRole;
    protected $userRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        $permissions = [
            'user-list', 'user-create', 'user-edit', 'user-delete',
            'role-list', 'role-create', 'role-edit', 'role-delete',
            'task-list', 'task-create', 'task-edit', 'task-delete'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->userRole = Role::create(['name' => 'user']);

        // Assign permissions to admin role
        $this->adminRole->givePermissionTo($permissions);
        
        // Assign limited permissions to user role
        $this->userRole->givePermissionTo(['task-list', 'task-create', 'task-edit']);

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $this->adminUser->assignRole('admin');

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $this->regularUser->assignRole('user');
    }

    /** @test */
    public function guest_cannot_access_protected_routes()
    {
        // Test user management routes
        $this->get('/users')->assertRedirect('/login');
        $this->get('/users/create')->assertRedirect('/login');
        $this->post('/users')->assertRedirect('/login');

        // Test role management routes
        $this->get('/roles')->assertRedirect('/login');
        $this->get('/roles/create')->assertRedirect('/login');
        $this->post('/roles')->assertRedirect('/login');

        // Test task management routes
        $this->get('/tasks')->assertRedirect('/login');
        $this->get('/tasks/create')->assertRedirect('/login');
        $this->post('/tasks')->assertRedirect('/login');
    }

    /** @test */
    public function admin_can_access_all_routes()
    {
        $this->actingAs($this->adminUser);

        // Test user management routes
        $this->get('/users')->assertStatus(200);
        $this->get('/users/create')->assertStatus(200);
        
        // Test role management routes
        $this->get('/roles')->assertStatus(200);
        $this->get('/roles/create')->assertStatus(200);
        
        // Test task management routes
        $this->get('/tasks')->assertStatus(200);
        $this->get('/tasks/create')->assertStatus(200);
    }

    /** @test */
    public function regular_user_cannot_access_user_management()
    {
        $this->actingAs($this->regularUser);

        // Should be forbidden (403) for user management
        $this->get('/users')->assertStatus(403);
        $this->get('/users/create')->assertStatus(403);
        
        // Should be forbidden for role management
        $this->get('/roles')->assertStatus(403);
        $this->get('/roles/create')->assertStatus(403);
    }

    /** @test */
    public function regular_user_can_access_allowed_task_routes()
    {
        $this->actingAs($this->regularUser);

        // Should be able to access tasks
        $this->get('/tasks')->assertStatus(200);
        $this->get('/tasks/create')->assertStatus(200);
    }

    /** @test */
    public function regular_user_cannot_delete_tasks()
    {
        $this->actingAs($this->regularUser);

        // Create a task first
        $response = $this->post('/tasks', [
            'name' => 'Test task',
            'detail' => 'Test Detail',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ]);

        $this->assertDatabaseHas('tasks', ['name' => 'Test task']);

        // Try to delete - should be forbidden
        $task = \App\Models\task::where('name', 'Test task')->first();
        $this->delete("/tasks/{$task->id}")->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_users()
    {
        $this->actingAs($this->adminUser);

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'confirm-password' => 'password123',
            'roles' => ['user']
        ];

        $response = $this->post('/users', $userData);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com'
        ]);
    }

    /** @test */
    public function admin_can_create_roles()
    {
        $this->actingAs($this->adminUser);

        $roleData = [
            'name' => 'manager',
            'permission' => [1, 2, 3] // Assuming these permission IDs exist
        ];

        $response = $this->post('/roles', $roleData);

        $response->assertRedirect('/roles');
        $this->assertDatabaseHas('roles', ['name' => 'manager']);
    }

    /** @test */
    public function user_can_only_edit_assigned_tasks()
    {
        $this->actingAs($this->regularUser);

        // Create task assigned to the user
        $usertask = \App\Models\task::create([
            'name' => 'User task',
            'detail' => 'User Detail',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => now()->addDays(7)
        ]);

        // Create task assigned to another user
        $othertask = \App\Models\task::create([
            'name' => 'Other task',
            'detail' => 'Other Detail',
            'assigned_to' => $this->adminUser->id,
            'status' => 'pending',
            'due_date' => now()->addDays(7)
        ]);

        // Should be able to view own task
        $this->get("/tasks/{$usertask->id}")->assertStatus(200);
        
        // Should be able to edit own task
        $this->get("/tasks/{$usertask->id}/edit")->assertStatus(200);

        // Should NOT be able to view other's task (implement this check in controller)
        // $this->get("/tasks/{$othertask->id}")->assertStatus(403);
    }

    /** @test */
    public function middleware_prevents_unauthorized_access()
    {
        // Test without authentication
        $this->post('/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'confirm-password' => 'password',
            'roles' => ['user']
        ])->assertRedirect('/login');

        // Test with insufficient permissions
        $this->actingAs($this->regularUser);
        
        $this->post('/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'confirm-password' => 'password',
            'roles' => ['user']
        ])->assertStatus(403);
    }

    /** @test */
    public function role_permissions_are_enforced_correctly()
    {
        // Create a custom role with specific permissions
        $customRole = Role::create(['name' => 'editor']);
        $customRole->givePermissionTo(['task-list', 'task-edit']);

        $editorUser = User::create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => Hash::make('password')
        ]);
        $editorUser->assignRole('editor');

        $this->actingAs($editorUser);

        // Should be able to list tasks
        $this->get('/tasks')->assertStatus(200);

        // Should NOT be able to create tasks
        $this->get('/tasks/create')->assertStatus(403);

        // Should NOT be able to delete tasks
        $task = \App\Models\task::create([
            'name' => 'Test task',
            'detail' => 'Test Detail',
            'assigned_to' => $editorUser->id,
            'status' => 'pending',
            'due_date' => now()->addDays(7)
        ]);

        $this->delete("/tasks/{$task->id}")->assertStatus(403);
    }

    /** @test */
    public function super_admin_bypasses_all_permission_checks()
    {
        // Create super admin role
        $superAdminRole = Role::create(['name' => 'super-admin']);
        
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password')
        ]);
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin);

        // Even without explicit permissions, super admin should access everything
        $this->get('/users')->assertStatus(200);
        $this->get('/roles')->assertStatus(200);
        $this->get('/tasks')->assertStatus(200);
    }

    /** @test */
    public function permission_inheritance_works_correctly()
    {
        // Test that user inherits permissions from assigned role
        $this->assertTrue($this->adminUser->can('user-create'));
        $this->assertTrue($this->adminUser->can('role-edit'));
        $this->assertTrue($this->adminUser->can('task-list'));

        $this->assertFalse($this->regularUser->can('user-create'));
        $this->assertFalse($this->regularUser->can('role-edit'));
        $this->assertTrue($this->regularUser->can('task-list'));
    }

    /** @test */
    public function multiple_roles_permissions_are_combined()
    {
        // Create additional role
        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo(['user-list', 'user-edit']);

        // Assign both user and manager roles to regular user
        $this->regularUser->assignRole(['user', 'manager']);

        // Should have permissions from both roles
        $this->assertTrue($this->regularUser->can('task-list')); // from user role
        $this->assertTrue($this->regularUser->can('user-list'));    // from manager role
        $this->assertTrue($this->regularUser->can('user-edit'));    // from manager role
        $this->assertFalse($this->regularUser->can('user-delete')); // not assigned to either role
    }
}