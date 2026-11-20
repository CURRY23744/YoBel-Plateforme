<?php

namespace App\Http\Controllers\Api;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends ApiController
{
    private const SORTABLE = ['created_at', 'updated_at', 'name', 'reference', 'status', 'priority', 'priority_score', 'start_date', 'planned_end_date'];

    public function index(Request $request)
    {
        $me = $this->me();

        $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => 'nullable|in:asc,desc',
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => [
                'nullable',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($request) {
                    $from = $request->query('from');
                    if ($value && $from && $value < $from) {
                        $fail('La date de fin doit être postérieure ou égale à la date de début.');
                    }
                },
            ],
        ]);

        $query = Project::visibleTo($me)
            ->with([
                'type:id,name,slug,color',
                'client:id,name',
                'manager:id,first_name,last_name',
                'tags:id,name,color',
            ])
            ->withCount(['members' => fn ($q) => $q->whereNull('left_at')])
            ->when($request->filled('project_type_id'), fn ($q) => $q->where('project_type_id', $request->query('project_type_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when(! $request->filled('status') && ! $request->boolean('include_archived'), fn ($q) => $q->where('is_archived', false))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->query('client_id')))
            ->when($request->filled('manager_id'), fn ($q) => $q->where('manager_id', $request->query('manager_id')))
            ->when($request->filled('tag_id'), fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $request->query('tag_id'))))
            ->when($request->filled('member_id'), fn ($q) => $q->whereHas('members', fn ($m) => $m->where('user_id', $request->query('member_id'))->whereNull('left_at')))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('start_date', (int) $request->query('year')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('projects.created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('projects.created_at', '<=', $request->query('to')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('reference', 'like', $s)->orWhere('description', 'like', $s));
            });

        $query->orderBy($request->query('sort', 'created_at'), $request->query('direction', 'desc'));

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function stats(): JsonResponse
    {
        $base = Project::visibleTo($this->me());

        return response()->json([
            'total' => (clone $base)->count(),
            'by_status' => (clone $base)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'by_priority' => (clone $base)->select('priority', DB::raw('count(*) as total'))->groupBy('priority')->pluck('total', 'priority'),
            'by_type' => (clone $base)->select('project_type_id', DB::raw('count(*) as total'))->groupBy('project_type_id')->pluck('total', 'project_type_id'),
            'overdue' => (clone $base)->overdue()->count(),
        ]);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $project->load([
            'type', 'client', 'creator:id,first_name,last_name', 'manager:id,first_name,last_name',
            'tags', 'milestones',
            'members' => fn ($q) => $q->with('user:id,first_name,last_name,job_title,avatar_path')->orderBy('joined_at'),
        ]);

        $project->setAttribute('my_permissions', $this->me()->abilitiesOn($project));

        return response()->json($project);
    }

    public function store(Request $request): JsonResponse
    {
        $me = $this->me();
        abort_unless($me->canCreateProjects(), 403, 'Vous ne pouvez pas créer de projet.');

        $data = $request->validate($this->rules(true));
        $tags = Arr::pull($data, 'tags');
        $accessUserIds = [];

        if ($request->exists('access_user_ids')) {
            abort_unless($me->isAdmin(), 403, 'Seul un administrateur peut définir les accès lors de la création.');
            $accessUserIds = $request->validate([
                'access_user_ids' => ['array'],
                'access_user_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('users', 'id')
                        ->where('organization_id', $me->organization_id)
                        ->where('is_active', true),
                ],
            ])['access_user_ids'] ?? [];
        }

        if (empty($data['manager_id']) && $me->isManager()) {
            $data['manager_id'] = $me->id;
        }

        $project = DB::transaction(function () use ($data, $tags, $accessUserIds, $me) {
            $project = Project::create($data + [
                'organization_id' => $me->organization_id,
                'reference' => Project::nextReference($me->organization_id),
                'created_by' => $me->id,
                'updated_by' => $me->id,
            ]);

            if (is_array($tags)) {
                $project->tags()->sync($tags);
            }

            foreach ($accessUserIds as $userId) {
                ProjectMember::updateOrCreate(
                    ['project_id' => $project->id, 'user_id' => $userId],
                    ProjectMember::PRESETS['reader'] + [
                        'project_role' => 'developer',
                        'joined_at' => now(),
                        'left_at' => null,
                        'added_by' => $me->id,
                    ]
                );
            }

            if (! $me->isAdmin()) {
                ProjectMember::grantFull($project, $me, 'manager', $me->id);
            }

            if ($project->manager_id && $project->manager_id !== $me->id) {
                ProjectMember::grantFull($project, $project->manager, 'manager', $me->id);
            }

            return $project;
        });

        if ($project->manager_id) {
            Notifier::send($project->manager, 'project.assigned', "Vous êtes responsable du projet {$project->name}", null, ['project_id' => $project->id]);
        }

        return response()->json($project->load(['type', 'client', 'tags']), 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'edit');

        $data = $request->validate($this->rules(false));
        $tags = Arr::pull($data, 'tags');

        if (array_key_exists('manager_id', $data)) {
            $this->authorizeProject($project, 'manage');
        }

        $previousStatus = $project->status;

        if (isset($data['status'])) {
            if (in_array($data['status'], ['completed', 'abandoned'], true) && empty($data['actual_end_date']) && ! $project->actual_end_date) {
                $data['actual_end_date'] = now()->toDateString();
            }

            if ($data['status'] === 'archived') {
                $data += ['is_archived' => true, 'archived_at' => now()];
            } elseif ($project->is_archived) {
                $data += ['is_archived' => false, 'archived_at' => null];
            }
        }

        $data['updated_by'] = $this->me()->id;

        DB::transaction(function () use ($project, $data, $tags) {
            $project->update($data);

            if (is_array($tags)) {
                $project->tags()->sync($tags);
            }

            if (! empty($data['manager_id'])) {
                ProjectMember::grantFull($project, $project->fresh()->manager, 'manager', $this->me()->id);
            }
        });

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $recipients = $project->members()->whereNull('left_at')->with('user')->get()->pluck('user');
            Notifier::send($recipients, 'project.status_changed', "Projet {$project->name} : statut « {$data['status']} »", null, ['project_id' => $project->id]);
        }

        return response()->json($project->fresh(['type', 'client', 'tags', 'manager:id,first_name,last_name']));
    }

    public function destroy(Project $project): JsonResponse
    {
        abort_unless($this->me()->isAdmin(), 403, 'Seul un administrateur peut supprimer un projet.');

        $project->delete();

        return response()->json(null, 204);
    }

    public function archive(Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'manage');

        $project->update(['status' => 'archived', 'is_archived' => true, 'archived_at' => now(), 'updated_by' => $this->me()->id]);

        return response()->json($project->fresh());
    }

    public function restore(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'manage');

        $data = $request->validate(['status' => ['nullable', Rule::in(array_diff(ProjectStatus::values(), ['archived']))]]);

        $project->update([
            'status' => $data['status'] ?? 'completed',
            'is_archived' => false,
            'archived_at' => null,
            'updated_by' => $this->me()->id,
        ]);

        return response()->json($project->fresh());
    }

    public function duplicate(Request $request, Project $project): JsonResponse
    {
        $me = $this->me();
        $this->authorizeProject($project);
        abort_unless($me->canCreateProjects(), 403);

        $options = $request->validate([
            'name' => 'nullable|string|max:255',
            'copy_members' => 'nullable|boolean',
            'copy_folders' => 'nullable|boolean',
        ]);

        $copy = DB::transaction(function () use ($project, $options, $me) {
            $copy = $project->replicate([
                'reference', 'status', 'priority_score', 'actual_cost', 'actual_end_date', 'decision_date',
                'progress', 'health', 'is_archived', 'archived_at', 'created_by', 'updated_by',
            ]);
            $copy->name = $options['name'] ?? $project->name.' (copie)';
            $copy->reference = Project::nextReference($me->organization_id);
            $copy->created_by = $me->id;
            $copy->updated_by = $me->id;
            $copy->status = 'idea';
            $copy->progress = 0;
            $copy->is_archived = false;
            $copy->save();

            $copy->tags()->sync($project->tags()->pluck('tags.id')->all());

            if (! $me->isAdmin()) {
                ProjectMember::grantFull($copy, $me, 'manager', $me->id);
            }

            if (! empty($options['copy_members'])) {
                foreach ($project->members()->whereNull('left_at')->get() as $member) {
                    ProjectMember::updateOrCreate(
                        ['project_id' => $copy->id, 'user_id' => $member->user_id],
                        $member->only(['project_role', 'can_view', 'can_comment', 'can_upload', 'can_edit', 'can_delete', 'can_manage'])
                            + ['joined_at' => now(), 'added_by' => $me->id]
                    );
                }
            }

            if (! empty($options['copy_folders'])) {
                $this->copyFolders($project, $copy, $me->id);
            }

            return $copy;
        });

        return response()->json($copy->load(['type', 'tags']), 201);
    }

    private function copyFolders(Project $from, Project $to, int $userId): void
    {
        $map = [];
        $folders = Folder::where('project_id', $from->id)->orderBy('id')->get();

        // Les parents sont créés avant leurs enfants (id croissants).
        foreach ($folders as $folder) {
            $new = Folder::create([
                'project_id' => $to->id,
                'parent_id' => $folder->parent_id ? ($map[$folder->parent_id] ?? null) : null,
                'name' => $folder->name,
                'description' => $folder->description,
                'is_restricted' => false, // les restrictions ne sont pas copiées
                'sort_order' => $folder->sort_order,
                'created_by' => $userId,
            ]);
            $map[$folder->id] = $new->id;
        }
    }

    private function rules(bool $creating): array
    {
        $org = $this->me()->organization_id;
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => "$required|string|max:255",
            'project_type_id' => [$required, Rule::exists('project_types', 'id')->where('organization_id', $org)],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('organization_id', $org)],
            'description' => 'nullable|string',
            'objectives' => 'nullable|string',
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'health' => 'nullable|in:on_track,at_risk,late',
            'budget_amount' => 'nullable|numeric|min:0',
            'budget_currency' => 'nullable|string|size:3',
            'actual_cost' => 'nullable|numeric|min:0',
            'technologies' => 'nullable|array',
            'technologies.*' => 'string|max:100',
            'repository_url' => 'nullable|url|max:500',
            'staging_url' => 'nullable|url|max:500',
            'production_url' => 'nullable|url|max:500',
            'start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:start_date',
            'actual_end_date' => 'nullable|date',
            'decision_date' => 'nullable|date',
            'progress' => 'nullable|integer|between:0,100',
            'manager_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'tags' => 'nullable|array',
            'tags.*' => [Rule::exists('tags', 'id')->where('organization_id', $org)],
        ];
    }
}
