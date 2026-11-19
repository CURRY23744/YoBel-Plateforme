<?php

namespace App\Http\Controllers\Api;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class TaskController extends ApiController
{
    public function index(Request $request)
    {
        $me = $this->me();
        $projectIds = Project::visibleTo($me)->pluck('id');

        $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
        ]);

        $tasks = Task::query()
            ->where(function ($w) use ($projectIds, $me) {
                $w->whereIn('project_id', $projectIds);
                if (! $me->isGuest()) {
                    $w->orWhere(fn ($g) => $g->whereNull('project_id')->where(fn ($h) => $h->where('assignee_id', $me->id)->orWhere('created_by', $me->id)));
                }
            })
            ->when($me->isGuest(), fn ($q) => $q->where('assignee_id', $me->id))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when(! $me->isGuest() && $request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->query('assignee_id') === 'me' ? $me->id : $request->query('assignee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('due_before'), fn ($q) => $q->whereDate('due_date', '<=', $request->query('due_before')))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereDate('due_date', '<', now()->toDateString())->whereNotIn('status', ['done', 'cancelled']))
            ->when($request->boolean('top_level'), fn ($q) => $q->whereNull('parent_id'))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->query('q').'%'))
            ->with(['assignee:id,first_name,last_name', 'project:id,organization_id,reference,name'])
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id')
            ->paginate($this->perPage($request));

        $editableProjectIds = $me->isAdmin()
            ? $projectIds->all()
            : ProjectMember::where('user_id', $me->id)
                ->whereNull('left_at')
                ->where('can_edit', true)
                ->whereIn('project_id', $projectIds)
                ->pluck('project_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $tasks->getCollection()->each(function (Task $task) use ($editableProjectIds, $me) {
            $canEdit = $task->project_id
                ? in_array((int) $task->project_id, $editableProjectIds, true)
                : ($me->isStaff() || $task->created_by === $me->id);
            $task->setAttribute('can_edit', $canEdit);
            $task->setAttribute('can_change_status', $canEdit || $task->assignee_id === $me->id);
        });

        return response()->json($tasks);
    }

    public function store(Request $request, ?Project $project = null): JsonResponse
    {
        $me = $this->me();
        $project ? $this->authorizeProject($project, 'edit') : $this->authorizeGeneral();

        $data = $request->validate($this->rules($project));

        if (! empty($data['assignee_id']) && $project && ! $this->isActiveMember($project, (int) $data['assignee_id'])) {
            return response()->json(['message' => 'La personne assignée doit être membre du projet.', 'errors' => ['assignee_id' => ['Non membre du projet.']]], 422);
        }

        if (($data['status'] ?? null) === 'done') {
            $data['completed_at'] = now();
        }

        $task = Task::create($data + [
            'project_id' => $project?->id,
            'organization_id' => $me->organization_id,
            'created_by' => $me->id,
        ]);

        if ($task->assignee_id) {
            Notifier::send($task->assignee, 'task.assigned', "Nouvelle tâche : {$task->title}", null, ['project_id' => $task->project_id, 'task_id' => $task->id]);
        }

        return response()->json($task->load(['assignee:id,first_name,last_name', 'project:id,reference,name']), 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorizeTask($task);
        $task->load([
            'assignee:id,first_name,last_name',
            'creator:id,first_name,last_name',
            'project:id,organization_id,reference,name',
            'subtasks' => fn ($query) => $query->when($this->me()->isGuest(), fn ($q) => $q->where('assignee_id', $this->me()->id)),
        ]);

        return response()->json($task);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $me = $this->me();
        $this->authorizeTask($task);

        $canEdit = $task->project
            ? $me->canOnProject($task->project, 'edit')
            : ($me->isStaff() || $task->created_by === $me->id);
        $isAssignee = $task->assignee_id === $me->id && ! $canEdit;

        abort_unless($canEdit || $isAssignee, 403, 'Vous ne pouvez pas modifier cette tâche.');

        $data = $request->validate($this->rules($task->project, true));

        // L'assigné sans droit d'édition peut uniquement changer le statut.
        if (! $canEdit) {
            $data = Arr::only($data, ['status']);
        }

        if (! empty($data['assignee_id']) && $task->project && ! $this->isActiveMember($task->project, (int) $data['assignee_id'])) {
            return response()->json(['message' => 'La personne assignée doit être membre du projet.', 'errors' => ['assignee_id' => ['Non membre du projet.']]], 422);
        }

        $previousAssignee = $task->assignee_id;

        if (isset($data['status'])) {
            $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        }

        $task->update($data);

        if (isset($data['assignee_id']) && $data['assignee_id'] !== $previousAssignee) {
            Notifier::send($task->assignee, 'task.assigned', "Tâche assignée : {$task->title}", null, ['project_id' => $task->project_id, 'task_id' => $task->id]);
        }

        return response()->json($task->fresh(['assignee:id,first_name,last_name']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $me = $this->me();
        $this->authorizeTask($task);

        $allowed = $task->project ? $me->canOnProject($task->project, 'edit') : ($me->isStaff() || $task->created_by === $me->id);
        abort_unless($allowed, 403);

        $task->delete();

        return response()->json(null, 204);
    }

    private function authorizeTask(Task $task): void
    {
        $me = $this->me();

        if ($task->project_id) {
            abort_unless($task->project, 404);
            $this->authorizeProject($task->project);
            abort_if($me->isGuest() && (int) $task->assignee_id !== (int) $me->id, 404);
        } else {
            abort_unless($task->created_by === $me->id || $task->assignee_id === $me->id || $me->isStaff(), 404);
        }
    }

    private function isActiveMember(Project $project, int $userId): bool
    {
        $user = User::where('organization_id', $project->organization_id)->find($userId);

        return $user && ($user->isAdmin() || $user->membershipFor($project));
    }

    private function rules(?Project $project, bool $partial = false): array
    {
        $org = $this->me()->organization_id;

        return [
            'title' => ($partial ? 'sometimes' : 'required').'|string|max:255',
            'description' => 'nullable|string',
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'parent_id' => ['nullable', Rule::exists('tasks', 'id')->where('organization_id', $org)],
            'milestone_id' => ['nullable', Rule::exists('milestones', 'id')->when($project, fn ($r) => $r->where('project_id', $project->id))],
            'message_id' => ['nullable', 'integer', Rule::exists('messages', 'id')],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_minutes' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
