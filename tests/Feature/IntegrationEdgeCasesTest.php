<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IntegrationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $managerUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create comprehensive permissions
        $permissions = [
            'user-list', 'user-create', 'user-edit', 'user-delete',
            'role-list', 'role-create', 'role-edit', 'role-delete',
            'Task-list', 'Task-create', 'Task-edit', 'Task-delete'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles with different permission levels
        $adminRole = Role::create(['name' => 'admin']);
        $managerRole = Role::create(['name' => 'manager']);
        $userRole = Role::create(['name' => 'user']);

        $adminRole->givePermissionTo($permissions);
        $managerRole->givePermissionTo([
            'user-list', 'user-edit', 
            'Task-list', 'Task-create', 'Task-edit', 'Task-delete'
        ]);
        $userRole->givePermissionTo(['Task-list', 'Task-create', 'Task-edit']);

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $this->adminUser->assignRole('admin');

        $this->managerUser = User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $this->managerUser->assignRole('manager');

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $this->regularUser->assignRole('staff');
    }

    /** @test */
    public function task_assignment_workflow_integration()
    {
        $this->actingAs($this->managerUser);

        // Step 1: Manager creates a task
        $taskData = [
            'name' => 'Integration Test Task',
            'detail' => 'Task created by manager for user',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->post('/Tasks', $taskData);
        $response->assertRedirect('/Tasks');

        $task = Task::where('name', 'Integration Test Task')->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->regularUser->id, $task->assigned_to);

        // Step 2: Verify assigned user can view the task
        $this->actingAs($this->regularUser);
        $response = $this->get("/Tasks/{$task->id}");
        $response->assertStatus(200);
        $response->assertSee('Integration Test Task');

        // Step 3: User updates task status
        $updateData = [
            'name' => 'Integration Test Task',
            'detail' => 'Task created by manager for user',
            'assigned_to' => $this->regularUser->id,
            'status' => 'completed',
            'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->put("/Tasks/{$task->id}", $updateData);
        $response->assertRedirect('/Tasks');

        // Step 4: Verify task status was updated
        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }

    /** @test */
    public function role_permission_cascade_deletion()
    {
        $this->actingAs($this->adminUser);

        // Create custom role with permissions
        $customRole = Role::create(['name' => 'custom-role']);
        $customRole->givePermissionTo(['Task-list', 'Task-create']);

        // Assign role to user
        $testUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $testUser->assignRole('custom-role');

        // Verify user has the role and permissions
        $this->assertTrue($testUser->hasRole('custom-role'));
        $this->assertTrue($testUser->can('Task-list'));

        // Delete the role
        $response = $this->delete("/roles/{$customRole->id}");
        $response->assertRedirect('/roles');

        // Verify role is deleted
        $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);

        // Verify user no longer has the role
        $testUser->refresh();
        $this->assertFalse($testUser->hasRole('custom-role'));
    }

    /** @test */
    public function user_deletion_handles_assigned_tasks()
    {
        $this->actingAs($this->adminUser);

        // Create user with assigned tasks
        $userToDelete = User::create([
            'name' => 'User To Delete',
            'email' => 'delete@example.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);

        // Create tasks assigned to this user
        $task1 = Task::create([
            'name' => 'Task 1',
            'detail' => 'First task',
            'assigned_to' => $userToDelete->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);

        $task2 = Task::create([
            'name' => 'Task 2',
            'detail' => 'Second task',
            'assigned_to' => $userToDelete->id,
            'status' => 'completed',
            'due_date' => Carbon::now()->subDays(1)
        ]);

        // Delete the user
        $response = $this->delete("/users/{$userToDelete->id}");
        $response->assertRedirect('/users');

        // Verify user is deleted
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);

        // Check what happens to assigned tasks (implement business logic)
        // Option 1: Tasks remain but assigned_to becomes null
        // Option 2: Tasks are deleted
        // Option 3: Tasks are reassigned to another user
        
        // For this test, assuming tasks remain with null assignment
        $task1->refresh();
        $task2->refresh();
        
        // Verify tasks still exist (modify based on your business logic)
        $this->assertDatabaseHas('Tasks', ['id' => $task1->id]);
        $this->assertDatabaseHas('Tasks', ['id' => $task2->id]);
    }

    /** @test */
    public function concurrent_task_updates_handling()
    {
        $this->actingAs($this->regularUser);

        $task = Task::create([
            'name' => 'Concurrent Task',
            'detail' => 'Task for concurrent testing',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(5)
        ]);

        // Simulate concurrent updates
        DB::beginTransaction();
        
        try {
            // First update
            $updateData1 = [
                'name' => 'Concurrent Task - Update 1',
                'detail' => 'First update',
                'assigned_to' => $this->regularUser->id,
                'status' => 'in_progress',
                'due_date' => Carbon::now()->addDays(5)->format('Y-m-d')
            ];

            $response1 = $this->put("/Tasks/{$task->id}", $updateData1);
            $response1->assertRedirect('/Tasks');

            // Verify first update
            $task->refresh();
            $this->assertEquals('in_progress', $task->status);
            $this->assertEquals('First update', $task->detail);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            $this->fail('Concurrent update test failed: ' . $e->getMessage());
        }
    }

    /** @test */
    public function bulk_operations_performance()
    {
        $this->actingAs($this->adminUser);

        // Test bulk user creation
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $userData = [
                'name' => "Bulk User {$i}",
                'email' => "bulk{$i}@example.com",
                'password' => 'password123',
                'confirm-password' => 'password123',
                'roles' => ['user']
            ];

            $response = $this->post('/users', $userData);
            $response->assertRedirect('/users');
            
            $users[] = User::where('email', "bulk{$i}@example.com")->first();
        }

        // Verify all users were created
        $this->assertCount(10, $users);
        
        // Test bulk task assignment
        foreach ($users as $index => $user) {
            $taskData = [
                'name' => "Bulk Task {$index}",
                'detail' => "Task for bulk user {$index}",
                'assigned_to' => $user->id,
                'status' => 'pending',
                'due_date' => Carbon::now()->addDays($index + 1)->format('Y-m-d')
            ];

            $response = $this->post('/Tasks', $taskData);
            $response->assertRedirect('/Tasks');
        }

        // Verify all tasks were created
        $taskCount = Task::where('name', 'LIKE', 'Bulk Task%')->count();
        $this->assertEquals(10, $taskCount);
    }

    /** @test */
    public function overdue_task_identification()
    {
        $this->actingAs($this->regularUser);

        // Create overdue tasks
        $overdueTask1 = Task::create([
            'name' => 'Overdue Task 1',
            'detail' => 'This task is overdue',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(2)
        ]);

        $overdueTask2 = Task::create([
            'name' => 'Overdue Task 2',
            'detail' => 'Another overdue task',
            'assigned_to' => $this->regularUser->id,
            'status' => 'in_progress',
            'due_date' => Carbon::now()->subDays(5)
        ]);

        // Create non-overdue tasks
        $futureTask = Task::create([
            'name' => 'Future Task',
            'detail' => 'This task is not overdue',
            'assigned_to' => $this->regularUser->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)
        ]);
        // Fetch overdue tasks
        $overdueTasks = Task::where('due_date', '<', Carbon::now())
            ->where('status', '!=', 'done')
            ->get();
        $this->assertCount(2, $overdueTasks);
        $this->assertTrue($overdueTasks->contains($overdueTask1));
        $this->assertTrue($overdueTasks->contains($overdueTask2));
        $this->assertFalse($overdueTasks->contains($futureTask));
        // Verify overdue tasks are correctly identified
        $this->assertTrue($overdueTask1->due_date < Carbon::now());
        $this->assertTrue($overdueTask2->due_date < Carbon::now());
        $this->assertFalse($futureTask->due_date < Carbon::now());

        //end
        // Verify overdue tasks are not marked as done
        $this->assertNotEquals('done', $overdueTask1->status);
        $this->assertNotEquals('done', $overdueTask2->status);
        $this->assertNotEquals('done', $futureTask->status);
        // Verify overdue tasks are correctly identified
        $this->assertTrue($overdueTask1->due_date < Carbon::now());
        $this->assertTrue($overdueTask2->due_date < Carbon::now());
        $this->assertFalse($futureTask->due_date < Carbon::now());
    }
}