<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CrudOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        $permissions = [
            'user-list', 'user-create', 'user-edit', 'user-delete',
            'role-list', 'role-create', 'role-edit', 'role-delete',
            'Task-list', 'Task-create', 'Task-edit', 'Task-delete'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        $adminRole->givePermissionTo($permissions);
        $userRole->givePermissionTo([
            'Task-list', 'Task-create', 'Task-edit', 'Task-delete'
        ]);

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password')
        ]);
        $this->adminUser->assignRole('admin');

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password')
        ]);
        $this->regularUser->assignRole('user');
    }

    // USER CRUD TESTS

    /** @test */
    public function admin_can_create_user()
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
        $response->assertSessionHas('success', 'User created successfully');
        
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com'
        ]);

        // Verify user was assigned the role
        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue($newUser->hasRole('user'));
    }

    /** @test */
    public function admin_can_view_user_list()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/users');

        $response->assertStatus(200);
        $response->assertSee('Admin User');
        $response->assertSee('Regular User');
    }

    /** @test */
    public function admin_can_view_individual_user()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get("/users/{$this->regularUser->id}");

        $response->assertStatus(200);
        $response->assertSee($this->regularUser->name);
        $response->assertSee($this->regularUser->email);
    }

    /** @test */
    public function admin_can_update_user()
    {
        $this->actingAs($this->adminUser);

        $updateData = [
            'name' => 'Updated User Name',
            'email' => 'updated@example.com',
            'password' => '',
            'confirm-password' => '',
            'roles' => ['admin']
        ];

        $response = $this->put("/users/{$this->regularUser->id}", $updateData);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'User updated successfully');
        
        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'Updated User Name',
            'email' => 'updated@example.com'
        ]);

        // Verify role was updated
        $this->regularUser->refresh();
        $this->assertTrue($this->regularUser->hasRole('admin'));
        $this->assertFalse($this->regularUser->hasRole('user'));
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $this->actingAs($this->adminUser);

        $userToDelete = User::create([
            'name' => 'User To Delete',
            'email' => 'delete@example.com',
            'password' => Hash::make('password')
        ]);

        $response = $this->delete("/users/{$userToDelete->id}");

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'User deleted successfully');
        
        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id
        ]);
    }

    /** @test */
    public function user_creation_validates_required_fields()
    {
        $this->actingAs($this->adminUser);

        $invalidData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'password',
            'confirm-password' => 'different-password',
            'roles' => []
        ];

        $response = $this->post('/users', $invalidData);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
    }

    // ROLE CRUD TESTS

    /** @test */
    public function admin_can_create_role()
    {
        $this->actingAs($this->adminUser);

        $roleData = [
            'name' => 'manager',
            'permission' => [1, 2, 3] // Permission IDs
        ];

        $response = $this->post('/roles', $roleData);

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success', 'Role created successfully');
        
        $this->assertDatabaseHas('roles', ['name' => 'manager']);
    }

    /** @test */
    public function admin_can_view_role_list()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/roles');

        $response->assertStatus(200);
        $response->assertSee('admin');
        $response->assertSee('user');
    }

    /** @test */
    public function admin_can_update_role()
    {
        $this->actingAs($this->adminUser);

        $role = Role::create(['name' => 'editor']);
        
        $updateData = [
            'name' => 'senior-editor',
            'permission' => [1, 2]
        ];

        $response = $this->put("/roles/{$role->id}", $updateData);

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success', 'Role updated successfully');
        
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'senior-editor'
        ]);
    }

    /** @test */
    public function admin_can_delete_role()
    {
        $this->actingAs($this->adminUser);

        $roleToDelete = Role::create(['name' => 'temp-role']);

        $response = $this->delete("/roles/{$roleToDelete->id}");

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success', 'Role deleted successfully');
        
        $this->assertDatabaseMissing('roles', [
            'id' => $roleToDelete->id
        ]);
    }

    // Task CRUD TESTS

    /** @test */
    public function user_can_create_Task()
    {
        $this->actingAs($this->regularUser);

        $TaskData = [
            'name' => 'New Task',
            'detail' => 'Task description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->post('/Tasks', $TaskData);

        $response->assertRedirect('/Tasks');
        $response->assertSessionHas('success', 'Task created successfully.');
        
        $this->assertDatabaseHas('Tasks', [
            'name' => 'New Task',
            'detail' => 'Task description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function user_can_view_Task_list()
    {
        $this->actingAs($this->regularUser);

        // Create some test Tasks
        Task::create([
            'name' => 'Task 1',
            'detail' => 'First task',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        Task::create([
            'name' => 'Task 2',
            'detail' => 'Second task',
            'assigned_to' => $this->regularUser->id,
            'status' => 'completed',
            'due_date' => Carbon::now()->addDays(5)
        ]);

        $response = $this->get('/Tasks');

        $response->assertStatus(200);
        $response->assertSee('Task 1');
        $response->assertSee('Task 2');
    }

    /** @test */
    public function user_can_view_individual_Task()
    {
        $this->actingAs($this->regularUser);

        $Task = Task::create([
            'name' => 'View Task',
            'detail' => 'Task to view',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        $response = $this->get("/Tasks/{$Task->id}");

        $response->assertStatus(200);
        $response->assertSee('View Task');
        $response->assertSee('Task to view');
    }

    /** @test */
    public function user_can_update_Task()
    {
        $this->actingAs($this->regularUser);

        $Task = Task::create([
            'name' => 'Original Task',
            'detail' => 'Original description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        $updateData = [
            'name' => 'Updated Task',
            'detail' => 'Updated description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'completed',
            'due_date' => Carbon::now()->addDays(5)->format('Y-m-d')
        ];

        $response = $this->put("/Tasks/{$Task->id}", $updateData);

        $response->assertRedirect('/Tasks');
        $response->assertSessionHas('success', 'Task updated successfully');
        
        $this->assertDatabaseHas('Tasks', [
            'id' => $Task->id,
            'name' => 'Updated Task',
            'detail' => 'Updated description',
            'status' => 'completed'
        ]);
    }

    /** @test */
    public function user_can_delete_Task()
    {
        $this->actingAs($this->regularUser);

        $Task = Task::create([
            'name' => 'Task to Delete',
            'detail' => 'This task will be deleted',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        $response = $this->delete("/Tasks/{$Task->id}");

        $response->assertRedirect('/Tasks');
        $response->assertSessionHas('success', 'Task deleted successfully');
        
        $this->assertDatabaseMissing('Tasks', [
            'id' => $Task->id
        ]);
    }

    /** @test */
    public function Task_creation_validates_required_fields()
    {
        $this->actingAs($this->regularUser);

        $invalidData = [
            'name' => '',
            'detail' => '',
            'assigned_to' => '',
            'status' => '',
            'due_date' => 'invalid-date'
        ];

        $response = $this->post('/Tasks', $invalidData);

        $response->assertSessionHasErrors([
            'name', 'detail', 'assigned_to', 'status', 'due_date'
        ]);
    }

    /** @test */
    public function Task_update_validates_date_format()
    {
        $this->actingAs($this->regularUser);

        $Task = Task::create([
            'name' => 'Test Task',
            'detail' => 'Test description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        $invalidUpdateData = [
            'name' => 'Updated Task',
            'detail' => 'Updated description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => 'not-a-date'
        ];

        $response = $this->put("/Tasks/{$Task->id}", $invalidUpdateData);

        $response->assertSessionHasErrors(['due_date']);
    }

    /** @test */
    public function pagination_works_correctly()
    {
        $this->actingAs($this->regularUser);

        // Create more than 5 Tasks (default pagination limit)
        for ($i = 1; $i <= 7; $i++) {
            Task::create([
                'name' => "Task {$i}",
                'detail' => "Description {$i}",
                'assigned_to' => $this->regularUser->id,
                'status' => 'pending',
                'due_date' => Carbon::now()->addDays($i)
            ]);
        }

        $response = $this->get('/Tasks');
        $response->assertStatus(200);
        
        // Check if pagination links are present
        $response->assertSee('Next');
        
        // Check second page
        $response = $this->get('/Tasks?page=2');
        $response->assertStatus(200);
    }

    /** @test */
    public function search_functionality_works()
    {
        $this->actingAs($this->regularUser);

        Task::create([
            'name' => 'Unique Task Name',
            'detail' => 'Special description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        Task::create([
            'name' => 'Regular Task',
            'detail' => 'Normal description',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(5)
        ]);

        // Test search (if implemented in your application)
        $response = $this->get('/Tasks?search=Unique');
        $response->assertStatus(200);
        $response->assertSee('Unique Task Name');
        $response->assertDontSee('Regular Task');
    }
}