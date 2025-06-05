<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        // Middleware to ensure only authenticated users with 'admin' role can access
        $this->middleware('permission:log-list', ['only' => ['index','store']]);
    }

    public function index()
    {
        // Fetch activity logs with pagination (latest first)
        $logs = ActivityLog::with('user')->orderBy('logged_at', 'desc')->paginate(10);

        return view('activity_logs.index', compact('logs'));
    }
}
