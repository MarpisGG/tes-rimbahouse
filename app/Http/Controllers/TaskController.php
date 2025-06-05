<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:task-list|task-create|task-edit|task-delete', ['only' => ['index','show']]);
        $this->middleware('permission:task-create', ['only' => ['create','store']]);
        $this->middleware('permission:task-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:task-delete', ['only' => ['destroy']]);
    }

    public function index(): View
    {
        $tasks = Task::where('assigned_to', Auth::id())
                 ->latest()
                 ->paginate(5);

        return view('tasks.index', compact('tasks'))
            ->with('i', (request()->input('page', 1) - 1) * 5);
    }

    public function create(): View
    {
        $users = User::whereHas('roles', function($query) {
            $query->where('name', 'Staff');
        })->get();

        return view('tasks.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date',
        ]);

        $task = Task::create($request->all());

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create_task',
            'description' => 'Created a task: ' . $task->name . ' assigned to ' . $task->assignedUser->name,
            'logged_at' => Carbon::now(),
        ]);

        return redirect()->route('tasks.index')
                         ->with('success', 'Task created successfully.');
    }

    public function show(Task $task): View
    {
        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task): View
    {
        $users = User::whereHas('roles', function($query) {
            $query->where('name', 'Staff');
        })->get();

        return view('tasks.edit', compact('task', 'users'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $request->validate([
            'name' => 'required',
            'detail' => 'required',
            'assigned_to' => 'required',
            'status' => 'required',
            'due_date' => 'required|date',
        ]);

        $task->update($request->all());

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update_task',
            'description' => 'Updated task: ' . $task->name . ' assigned to ' . $task->assignedUser->name,
            'logged_at' => Carbon::now(),
        ]);

        return redirect()->route('tasks.index')
                         ->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $userName = $task->assignedUser->name;

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete_task',
            'description' => 'Deleted task: ' . $task->name . ' assigned to ' . $userName,
            'logged_at' => Carbon::now(),
        ]);

        $task->delete();

        return redirect()->route('tasks.index')
                         ->with('success', 'Task deleted successfully.');
    }
}
