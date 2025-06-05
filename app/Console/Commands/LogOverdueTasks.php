<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\ActivityLog;
use Carbon\Carbon;

class LogOverdueTasks extends Command
{
    protected $signature = 'tasks:log-overdue';

    protected $description = 'Log overdue tasks into activity logs';

    public function handle()
    {
        $now = Carbon::now();

        $overdueTasks = Task::where('due_date', '<', $now)
                            ->where('status', '!=', 'done')
                            ->get();

        foreach ($overdueTasks as $task) {
            ActivityLog::create([
                'user_id' => $task->assigned_to, // atau null jika tidak ingin diset
                'action' => 'task_overdue',
                'description' => "Task overdue: {$task->id} via scheduler",
                'logged_at' => $now,
            ]);
        }

        $this->info("Logged " . count($overdueTasks) . " overdue tasks.");
    }
}
