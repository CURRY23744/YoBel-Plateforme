<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\YobelAssistantException;
use App\Models\AiInteraction;
use App\Models\AiSuggestion;
use App\Models\Decision;
use App\Models\MeetingMinute;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Services\YobelAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiAssistantController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeGeneral();
        $data = $request->validate(['project_id' => 'nullable|integer']);
        $user = $this->me();
        $projectId = $data['project_id'] ?? null;

        if ($projectId) {
            $project = Project::visibleTo($user)->findOrFail($projectId);
            $this->authorizeProject($project);
        }

        $interactions = AiInteraction::query()
            ->where('user_id', $user->id)
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId), fn ($query) => $query->whereNull('project_id'))
            ->with('suggestions')
            ->latest('id')
            ->paginate($this->perPage($request));

        return response()->json($interactions);
    }

    public function ask(Request $request, YobelAssistant $assistant): JsonResponse
    {
        $this->authorizeGeneral();
        $data = $request->validate([
            'prompt' => 'required|string|min:2|max:4000',
            'project_id' => 'nullable|integer',
        ]);
        $user = $this->me();
        $project = null;

        if (! empty($data['project_id'])) {
            $project = Project::visibleTo($user)->findOrFail($data['project_id']);
            $this->authorizeProject($project);
        }

        $projectContext = $project ? $this->projectContext($project) : null;
        $interaction = AiInteraction::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'mode' => 'on_demand',
            'prompt' => trim($data['prompt']),
            'status' => 'pending',
            'provider' => 'gemini',
            'context' => [
                'project_id' => $project?->id,
                'sources' => $project ? ['project', 'task_summary', 'tasks', 'milestones', 'decisions', 'meeting_minutes'] : [],
            ],
        ]);

        $history = AiInteraction::query()
            ->where('user_id', $user->id)
            ->where('project_id', $project?->id)
            ->where('status', 'completed')
            ->whereNotNull('response')
            ->latest('id')
            ->limit(10)
            ->get(['prompt', 'response'])
            ->reverse()
            ->flatMap(fn (AiInteraction $item) => [
                ['role' => 'user', 'content' => mb_substr($item->prompt, 0, 4000)],
                ['role' => 'assistant', 'content' => mb_substr((string) $item->response, 0, 8000)],
            ])
            ->values()
            ->all();
        $history[] = ['role' => 'user', 'content' => $interaction->prompt];

        try {
            $result = $assistant->reply($history, $projectContext);
        } catch (YobelAssistantException $exception) {
            $interaction->update([
                'provider' => 'gemini',
                'model' => config('services.yobel_ai.gemini_model'),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }

        DB::transaction(function () use ($interaction, $result, $user, $project) {
            $interaction->update([
                'response' => $result['answer'],
                'provider' => $result['provider'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'status' => 'completed',
                'error' => null,
            ]);
            foreach ($result['suggestions'] as $suggestion) {
                AiSuggestion::create($suggestion + [
                    'organization_id' => $user->organization_id,
                    'project_id' => $project?->id,
                    'interaction_id' => $interaction->id,
                    'status' => 'pending',
                ]);
            }
        });

        return response()->json($interaction->fresh()->load('suggestions'), 201);
    }

    public function reviewSuggestion(Request $request, AiSuggestion $suggestion): JsonResponse
    {
        $this->authorizeGeneral();
        $data = $request->validate(['status' => 'required|in:accepted,rejected']);
        $interaction = $suggestion->interaction;

        abort_unless($interaction && (int) $interaction->user_id === (int) $this->me()->id, 404);
        if ($interaction->project_id) {
            $this->authorizeProject(Project::find($interaction->project_id));
        }
        abort_if($suggestion->status !== 'pending', 409, 'Cette suggestion a déjà été examinée.');

        $suggestion->update([
            'status' => $data['status'],
            'validated_by' => $this->me()->id,
            'validated_at' => now(),
        ]);

        return response()->json($suggestion->fresh());
    }

    private function projectContext(Project $project): array
    {
        $taskCounts = Task::query()
            ->where('project_id', $project->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status => (int) $row->aggregate]);
        $totalTasks = (int) $taskCounts->sum();

        return [
            'project' => $project->only([
                'reference', 'name', 'description', 'objectives', 'status', 'priority',
                'progress', 'start_date', 'planned_end_date', 'health',
            ]),
            'task_summary' => [
                'total' => $totalTasks,
                'open' => $totalTasks - (int) $taskCounts->get('done', 0) - (int) $taskCounts->get('cancelled', 0),
                'done' => (int) $taskCounts->get('done', 0),
                'overdue' => Task::query()
                    ->where('project_id', $project->id)
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today())
                    ->count(),
                'upcoming' => Task::query()
                    ->where('project_id', $project->id)
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->whereBetween('due_date', [today(), today()->addDays(7)])
                    ->count(),
            ],
            'tasks' => Task::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->limit(30)
                ->get(['title', 'description', 'status', 'priority', 'due_date', 'estimated_minutes'])
                ->toArray(),
            'overdue_tasks' => Task::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->orderBy('due_date')
                ->limit(8)
                ->get(['title', 'status', 'priority', 'due_date'])
                ->toArray(),
            'upcoming_tasks' => Task::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->orderBy('due_date')
                ->limit(8)
                ->get(['title', 'status', 'priority', 'due_date'])
                ->toArray(),
            'upcoming_milestones' => Milestone::query()
                ->where('project_id', $project->id)
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->orderBy('due_date')
                ->limit(8)
                ->get(['title', 'status', 'due_date'])
                ->toArray(),
            'milestones' => Milestone::query()
                ->where('project_id', $project->id)
                ->orderBy('due_date')
                ->limit(20)
                ->get(['title', 'description', 'status', 'due_date'])
                ->toArray(),
            'decisions' => Decision::query()
                ->where('project_id', $project->id)
                ->latest('decided_at')
                ->limit(15)
                ->get(['title', 'decision', 'justification', 'status', 'decided_at'])
                ->toArray(),
            'meeting_minutes' => MeetingMinute::query()
                ->where('project_id', $project->id)
                ->latest('meeting_date')
                ->limit(5)
                ->get(['title', 'content', 'meeting_date'])
                ->toArray(),
        ];
    }
}
