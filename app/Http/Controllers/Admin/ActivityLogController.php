<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ActivityLog::class);

        $activityLogs = ActivityLog::query()
            ->with(['actor', 'targetUser'])
            ->latest()
            ->paginate(25);

        return view('admin.activity.index', [
            'activityLogs' => $activityLogs,
        ]);
    }
}
