<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Decision;
use App\Models\Event;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function index(): JsonResponse
    {
        $me = $this->me();
        $visible = Project::visibleTo($me);
        $projectIds = (clone $visible)->pluck('id');

        $data = [
            'role' => $me->role->value,
            'projects_by_status' => (clone $visible)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'overdue_projects' => (clone $visible)->overdue()->with('type:id,name,color')->orderBy('planned_end_date')->limit(10)->get(['id', 'reference', 'name', 'planned_end_date', 'status', 'project_type_id']),
            'my_tasks' => Task::where('assignee_id', $me->id)->whereNotIn('status', ['done', 'cancelled'])
                ->orderByRaw('due_date is null')->orderBy('due_date')->limit(10)->get(['id', 'title', 'status', 'priority', 'due_date', 'project_id']),
            'upcoming_events' => Event::query()
                ->where(fn ($w) => $w->where('created_by', $me->id)->orWhereHas('participants', fn ($p) => $p->where('users.id', $me->id)))
                ->where('starts_at', '>=', now())->orderBy('starts_at')->limit(10)->get(['id', 'title', 'type', 'starts_at', 'project_id']),
            'upcoming_milestones' => Milestone::whereIn('project_id', $projectIds)->whereNull('completed_at')
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->orderBy('due_date')->limit(10)->get(['id', 'project_id', 'title', 'due_date']),
        ];

        if ($me->isStaff()) {
            $data['projects_by_type'] = (clone $visible)->select('project_type_id', DB::raw('count(*) as total'))->groupBy('project_type_id')->pluck('total', 'project_type_id');
            $data['pending_decisions'] = Decision::whereIn('project_id', $projectIds)->where('status', 'proposed')->count();
            $data['overdue_tasks'] = Task::whereIn('project_id', $projectIds)->whereDate('due_date', '<', now()->toDateString())->whereNotIn('status', ['done', 'cancelled'])->count();
        }

        if ($me->isAdmin()) {
            $data['recent_activity'] = ActivityLog::with('user:id,first_name,last_name')->latest('created_at')->latest('id')->limit(10)->get();
            $data['latest_users'] = User::where('organization_id', $me->organization_id)->latest()->limit(5)->get(['id', 'first_name', 'last_name', 'role', 'created_at']);
        }

        return response()->json($data);
    }
}
