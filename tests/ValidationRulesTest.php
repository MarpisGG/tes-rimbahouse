<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create basic roles and permissions
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
        
        Permission::create(['name' => 'user-list']);
        Permission::create(['name' => 'user-create']);
        Permission::create(['name' => 'product-list']);
        Permission::create(['name' => 'product-create']);
    }

    /** @test */
    public function it_validates_user_creation_rules()
    {
        // Valid user data
        $validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'confirm-password' => 'password123',
            'roles' => ['user']
        ];

        $validator = Validator::make($validData, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password',
            'roles' => 'required'
        ]);

        $this->assertTrue($validator->passes());

        // Invalid email format
        $invalidEmailData = $validData;
        $invalidEmailData['email'] = 'invalid-email';
        
        $validator = Validator::make($invalidEmailData, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password',
            'roles' => 'required'
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));

        // Password mismatch
        $passwordMismatchData = $validData;
        $passwordMismatchData['confirm-password'] = 'different-password';
        
        $validator = Validator::make($passwordMismatchData, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password',
            'roles' => 'required'
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('password'));
    }

    /** @test */
    public function it_validates_user_update_rules()
    {
        // Create existing user
        $existingUser = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password')
        ]);

        // Valid update data
        $validUpdateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'newpassword',
            'confirm-password' => 'newpassword',
            'roles' => ['user']
        ];

        $validator = Validator::make($validUpdateData, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $existingUser->id,
            'password' => 'same:confirm-password',
            'roles' => 'required'
        ]);

        $this->assertTrue($validator->passes());

        // Test email uniqueness (should fail with existing email)
        $duplicateEmailData = $validUpdateData;
        $duplicateEmailData['email'] = 'existing@example.com';
        
        $validator = Validator::make($duplicateEmailData, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . ($existingUser->id + 1), // Different ID
            'password' => 'same:confirm-password',
            'roles' => 'required'
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));
    }

    /** @test */
    public function it_validates_role_creation_rules()
    {
        // Valid role data
        $validRoleData = [
            'name' => 'new-role',
            'permission' => [1, 2] // Assuming permission IDs 1 and 2 exist
        ];

        $validator = Validator::make($validRoleData, [
            'name' => 'required|unique:roles,name',
            'permission' => 'required',
        ]);

        $this->assertTrue($validator->passes());

        // Create role to test uniqueness
        Role::create(['name' => 'existing-role']);

        // Test duplicate role name
        $duplicateNameData = [
            'name' => 'existing-role',
            'permission' => [1, 2]
        ];

        $validator = Validator::make($duplicateNameData, [
            'name' => 'required|unique:roles,name',
            'permission' => 'required',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));

        // Test missing permissions
        $missingPermissionData = [
            'name' => 'another-role'
        ];

        $validator = Validator::make($missingPermissionData, [
            'name' => 'required|unique:roles,name',
            'permission' => 'required',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('permission'));
    }

    /** @test */
    public function it_validates_product_creation_rules()
    {
        // Create a user for assignment
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        // Valid product data
        $validProductData = [
            'name' => 'Test Task',
            'detail' => 'This is a test task',
            'assigned_to' => $user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
        ];

        $validator = Validator::make($validProductData, [
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date',
        ]);

        $this->assertTrue($validator->passes());

        // Test missing required fields
        $missingFieldsData = [
            'name' => 'Test Task'
            // Missing other required fields
        ];

        $validator = Validator::make($missingFieldsData, [
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('detail'));
        $this->assertTrue($validator->errors()->has('assigned_to'));
        $this->assertTrue($validator->errors()->has('status'));
        $this->assertTrue($validator->errors()->has('due_date'));

        // Test invalid date format
        $invalidDateData = $validProductData;
        $invalidDateData['due_date'] = 'invalid-date';

        $validator = Validator::make($invalidDateData, [
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('due_date'));
    }

    /** @test */
    public function it_validates_custom_business_rules()
    {
        // Create user and product for testing
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        // Test that due date cannot be in the past for new tasks
        $pastDueDateData = [
            'name' => 'Past Due Task',
            'detail' => 'This task has a past due date',
            'assigned_to' => $user->id,
            'status' => 'pending',
            'due_date' => Carbon::now()->subDays(1)->format('Y-m-d')
        ];

        // Custom validation rule
        $validator = Validator::make($pastDueDateData, [
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date|after:yesterday',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('due_date'));

        // Test valid future date
        $futureDueDateData = $pastDueDateData;
        $futureDueDateData['due_date'] = Carbon::now()->addDays(1)->format('Y-m-d');

        $validator = Validator::make($futureDueDateData, [
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date|after:yesterday',
        ]);

        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_validates_status_transitions()
    {
        // Test valid status values
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        
        foreach ($validStatuses as $status) {
            $data = [
                'name' => 'Test Task',
                'detail' => 'Test detail',
                'assigned_to' => 1,
                'status' => $status,
                'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
            ];

            $validator = Validator::make($data, [
                'name' => 'required',
                'detail' => 'required',
                'assigned_to' => 'required',
                'status' => 'required|in:pending,in_progress,completed,cancelled',
                'due_date' => 'required|date',
            ]);

            $this->assertTrue($validator->passes(), "Status '{$status}' should be valid");
        }

        // Test invalid status
        $invalidStatusData = [
            'name' => 'Test Task',
            'detail' => 'Test detail',
            'assigned_to' => 1,
            'status' => 'invalid_status',
            'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
        ];

        $validator = Validator::make($invalidStatusData, [
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'due_date' => 'required|date',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('status'));
    }
}