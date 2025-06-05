<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $taskService;
    protected $user;
    protected $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles and permissions
        $this->adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        
        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);
        
        $this->user->assignRole('user');
    }

    /** @test */
    public function it_can_validate_role_assignment()
    {
        // Test valid role assignment
        $this->assertTrue($this->user->hasRole('user'));
        $this->assertFalse($this->user->hasRole('admin'));
        
        // Test role reassignment
        $this->user->syncRoles(['admin']);
        $this->assertTrue($this->user->hasRole('admin'));
        $this->assertFalse($this->user->hasRole('user'));
    }

    /** @test */
    public function it_can_identify_overdue_tasks()
    {
        // Create overdue task
        $overdueTask = Task::create([
            'name' => 'Overdue Task',
            'detail' => 'This task is overdue',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(2)
        ]);

        // Create future task
        $futureTask = Task::create([
            'name' => 'Future Task',
            'detail' => 'This task is not overdue',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(2)
        ]);

        // Create completed task (should not be overdue regardless of date)
        $completedTask = Task::create([
            'name' => 'Completed Task',
            'detail' => 'This task is completed',
            'assigned_to' => $this->user->id,
            'status' => 'completed',
            'due_date' => Carbon::now()->subDays(5)
        ]);

        // Test overdue logic
        $this->assertTrue($this->isTaskOverdue($overdueTask));
        $this->assertFalse($this->isTaskOverdue($futureTask));
        $this->assertFalse($this->isTaskOverdue($completedTask));
    }

    /** @test */
    public function it_can_calculate_task_priority()
    {
        $highPriorityTask = Task::create([
            'name' => 'High Priority Task',
            'detail' => 'Due tomorrow',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDay()
        ]);

        $lowPriorityTask = Task::create([
            'name' => 'Low Priority Task',
            'detail' => 'Due next month',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addMonth()
        ]);

        $this->assertEquals('high', $this->calculateTaskPriority($highPriorityTask));
        $this->assertEquals('low', $this->calculateTaskPriority($lowPriorityTask));
    }

    /** @test */
    public function it_can_validate_task_assignment()
    {
        $task = Task::create([
            'name' => 'Test Task',
            'detail' => 'Test task detail',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(7)
        ]);

        // Test valid assignment
        $this->assertTrue($this->isValidTaskAssignment($task, $this->user->id));
        
        // Test invalid assignment (non-existent user)
        $this->assertFalse($this->isValidTaskAssignment($task, 999));
    }

    /** @test */
    public function it_can_get_user_task_statistics()
    {
        // Create various tasks for user
        Task::create([
            'name' => 'Pending Task 1',
            'detail' => 'Pending task',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(5)
        ]);

        Task::create([
            'name' => 'Completed Task 1',
            'detail' => 'Completed task',
            'assigned_to' => $this->user->id,
            'status' => 'completed',
            'due_date' => Carbon::now()->subDays(2)
        ]);

        Task::create([
            'name' => 'Overdue Task 1',
            'detail' => 'Overdue task',
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(3)
        ]);

        $stats = $this->getUserTaskStatistics($this->user->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['completed']);
        $this->assertEquals(1, $stats['overdue']);
    }

    /** @test */
    public function it_can_bulk_assign_tasks()
    {
        $tasks = [
            [
                'name' => 'Bulk Task 1',
                'detail' => 'First bulk task',
                'status' => 'pending',
                'due_date' => Carbon::now()->addDays(3)
            ],
            [
                'name' => 'Bulk Task 2',
                'detail' => 'Second bulk task',
                'status' => 'pending',
                'due_date' => Carbon::now()->addDays(5)
            ]
        ];

        $result = $this->bulkAssignTasks($tasks, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['created_count']);
        
        // Verify tasks were created
        $this->assertDatabaseHas('Tasks', [
            'name' => 'Bulk Task 1',
            'assigned_to' => $this->user->id
        ]);
        
        $this->assertDatabaseHas('Tasks', [
            'name' => 'Bulk Task 2',
            'assigned_to' => $this->user->id
        ]);
    }

    // Helper methods that would typically be in a service class

    private function isTaskOverdue(Task $task): bool
    {
        if ($task->status === 'completed') {
            return false;
        }
        
        return Carbon::parse($task->due_date)->isPast();
    }

    private function calculateTaskPriority(Task $task): string
    {
        $dueDate = Carbon::parse($task->due_date);
        $daysUntilDue = $dueDate->diffInDays(Carbon::now());

        if ($daysUntilDue <= 2) {
            return 'high';
        } elseif ($daysUntilDue <= 7) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function isValidTaskAssignment(Task $task, int $userId): bool
    {
        return User::where('id', $userId)->exists();
    }

    private function getUserTaskStatistics(int $userId): array
    {
        $tasks = Task::where('assigned_to', $userId)->get();
        
        $stats = [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'overdue' => 0
        ];

        foreach ($tasks as $task) {
            if ($this->isTaskOverdue($task)) {
                $stats['overdue']++;
            }
        }

        return $stats;
    }

    private function bulkAssignTasks(array $tasks, int $userId): array
    {
        try {
            $createdCount = 0;
            
            foreach ($tasks as $taskData) {
                $taskData['assigned_to'] = $userId;
                Task::create($taskData);
                $createdCount++;
            }

            return [
                'success' => true,
                'created_count' => $createdCount
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}