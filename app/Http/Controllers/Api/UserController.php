<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        $users = User::where('organization_id', $this->me()->organization_id)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)->orWhere('email', 'like', $s));
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($this->perPage($request));

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        $user->load(['memberships' => fn ($q) => $q->with('project:id,reference,name,status,project_type_id')->latest()]);

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->me()->organization_id;
        $projectIds = $request->input('project_ids');
        if (! is_array($projectIds) && $request->filled('project_id')) {
            $projectIds = [(int) $request->input('project_id')];
            $request->merge(['project_ids' => $projectIds]);
        }
        $projectIds = is_array($projectIds)
            ? array_values(array_unique(array_map('intval', array_filter($projectIds))))
            : [];

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::enum(Role::class)],
            'project_id' => [
                'required_without:project_ids',
                'prohibited_unless:role,guest',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('organization_id', $organizationId),
            ],
            'project_ids' => [
                'required_if:role,guest',
                'prohibited_unless:role,guest',
                'array',
                'min:1',
            ],
            'project_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('projects', 'id')->where('organization_id', $organizationId),
            ],
            'task_ids' => ['prohibited_unless:role,guest', 'nullable', 'array'],
            'task_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tasks', 'id')
                    ->where('organization_id', $organizationId)
                    ->whereIn('project_id', $projectIds),
            ],
            'phone' => 'nullable|string|max:30',
            'job_title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'employee_number' => 'nullable|string|max:50',
        ]);

        $temporary = Str::password(12, symbols: false);

        $projectId = $data['project_id'] ?? null;
        $projectIds = $projectIds ?: ($projectId ? [(int) $projectId] : []);
        $taskIds = $data['task_ids'] ?? [];
        unset($data['project_id'], $data['project_ids'], $data['task_ids']);
        $createdBy = $this->me();

        $user = DB::transaction(function () use ($data, $temporary, $projectIds, $taskIds, $createdBy, $organizationId) {
            $user = User::create($data + [
                'organization_id' => $organizationId,
                'password' => $temporary,
                'must_change_password' => true,
                'created_by' => $createdBy->id,
            ]);

            foreach ($projectIds as $projectId) {
                ProjectMember::updateOrCreate(
                    ['project_id' => $projectId, 'user_id' => $user->id],
                    ProjectMember::PRESETS['contributor'] + [
                        'project_role' => 'client_contact',
                        'joined_at' => now(),
                        'left_at' => null,
                        'added_by' => $createdBy->id,
                    ]
                );
            }

            if (! empty($taskIds)) {
                $tasks = Task::query()
                    ->whereIn('project_id', $projectIds)
                    ->whereIn('id', $taskIds)
                    ->get();
                foreach ($tasks as $task) {
                    $task->update(['assignee_id' => $user->id]);
                    Notifier::send($user, 'task.assigned', "Nouvelle tâche : {$task->title}", null, [
                        'project_id' => (int) $task->project_id,
                        'task_id' => $task->id,
                    ]);
                }
            }

            return $user;
        });

        $this->sendCredentials($user, $temporary);

        return response()->json(['user' => $user, 'temporary_password' => $temporary], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'phone' => 'nullable|string|max:30',
            'job_title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'employee_number' => 'nullable|string|max:50',
        ]);

        if ($user->id === $this->me()->id && isset($data['role']) && $data['role'] !== $user->role->value) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre rôle.'], 422);
        }

        if (($data['role'] ?? null) === Role::Guest->value
            && $user->memberships()->whereNull('left_at')->where('can_view', true)->count() < 1) {
            return response()->json([
                'message' => 'Un invité doit être affecté à au moins un projet.',
                'errors' => ['role' => ['Affectez cet utilisateur à au moins un projet avant de lui attribuer le rôle invité.']],
            ], 422);
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function destroy(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        if ($user->id === $this->me()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $avatarPath = $user->avatar_path;

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('activity_logs')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $user->notifications()->delete();

            $interactionIds = DB::table('ai_interactions')
                ->where('user_id', $user->id)
                ->pluck('id');
            DB::table('ai_suggestions')->whereIn('interaction_id', $interactionIds)->delete();
            DB::table('ai_interactions')->whereIn('id', $interactionIds)->delete();

            DB::table('meeting_minutes')
                ->where('organization_id', $user->organization_id)
                ->where(function ($query) use ($user) {
                    $query->whereJsonContains('attendees', (int) $user->id)
                        ->orWhereJsonContains('attendees', (string) $user->id);
                })
                ->get(['id', 'attendees'])
                ->each(function (object $minute) use ($user) {
                    $attendees = json_decode($minute->attendees, true) ?? [];
                    $attendees = array_values(array_filter(
                        $attendees,
                        fn ($attendeeId) => (int) $attendeeId !== (int) $user->id
                    ));

                    DB::table('meeting_minutes')
                        ->where('id', $minute->id)
                        ->update(['attendees' => json_encode($attendees)]);
                });

            $user->forceDelete();

            DB::table('activity_logs')
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->id)
                ->delete();
        });

        if ($avatarPath && ! Storage::disk('public')->delete($avatarPath)) {
            Log::warning('Could not delete the avatar of a permanently deleted user.', [
                'user_id' => $user->id,
                'avatar_path' => $avatarPath,
            ]);
        }

        return response()->json(null, 204);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        if ($user->id === $this->me()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 422);
        }

        $active = ! $user->is_active;
        $user->update(['is_active' => $active, 'deactivated_at' => $active ? null : now()]);

        if (! $active) {
            $user->tokens()->delete();
        }

        return response()->json($user->fresh());
    }

    public function resetPassword(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        $temporary = Str::password(12, symbols: false);
        $user->update(['password' => $temporary, 'must_change_password' => true, 'failed_login_attempts' => 0, 'locked_until' => null]);
        $user->tokens()->delete();

        $this->sendCredentials($user, $temporary);

        return response()->json(['temporary_password' => $temporary]);
    }

    private function sendCredentials(User $user, string $temporary): void
    {
        try {
            Mail::raw(
                "Bonjour {$user->first_name},\n\nUn accès à la plateforme a été créé pour vous.\n\n"
                ."Adresse e-mail : {$user->email}\nMot de passe temporaire : {$temporary}\n\n"
                .'Connectez-vous : '.config('platform.frontend_url')."\nVous devrez changer ce mot de passe à votre première connexion.",
                fn ($message) => $message->to($user->email)->subject('Votre accès à la plateforme')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
