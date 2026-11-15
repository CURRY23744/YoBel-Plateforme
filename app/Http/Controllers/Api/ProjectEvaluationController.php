<?php

namespace App\Http\Controllers\Api;

use App\Models\EvaluationCriterion;
use App\Models\Project;
use App\Models\ProjectEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectEvaluationController extends ApiController
{
    /** Détail des évaluations d'un projet, critère par critère. */
    public function show(Project $project): JsonResponse
    {
        abort_unless($this->me()->isStaff(), 403, 'Les évaluations sont réservées à la direction.');
        $this->authorizeProject($project);

        $criteria = EvaluationCriterion::where('is_active', true)->orderBy('sort_order')->get();
        $evaluations = ProjectEvaluation::where('project_id', $project->id)->with('evaluator:id,first_name,last_name')->get()->groupBy('criterion_id');

        $rows = $criteria->map(function ($criterion) use ($evaluations) {
            $items = $evaluations->get($criterion->id, collect());

            return [
                'criterion' => $criterion,
                'average' => $items->isEmpty() ? null : round($items->avg('score'), 2),
                'evaluations' => $items->values(),
            ];
        });

        return response()->json([
            'project_id' => $project->id,
            'priority_score' => $project->priority_score,
            'criteria' => $rows,
        ]);
    }

    /** Enregistre (ou met à jour) les notes de l'utilisateur connecté. */
    public function store(Request $request, Project $project): JsonResponse
    {
        $me = $this->me();
        abort_unless($me->isStaff(), 403, 'Les évaluations sont réservées à la direction.');
        $this->authorizeProject($project);

        $data = $request->validate([
            'scores' => 'required|array|min:1',
            'scores.*.criterion_id' => ['required', 'integer', 'distinct', Rule::exists('evaluation_criteria', 'id')->where('organization_id', $me->organization_id)->where('is_active', true)],
            'scores.*.score' => 'required|integer|between:1,10',
            'scores.*.justification' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($data, $project, $me) {
            foreach ($data['scores'] as $row) {
                ProjectEvaluation::updateOrCreate(
                    ['project_id' => $project->id, 'criterion_id' => $row['criterion_id'], 'evaluator_id' => $me->id],
                    ['score' => $row['score'], 'justification' => $row['justification'] ?? null, 'evaluated_at' => now()]
                );
            }
        });

        $score = $project->recomputePriorityScore();

        return response()->json(['priority_score' => $score, 'message' => 'Évaluation enregistrée.']);
    }

    /** Classement comparatif des projets visibles par score de priorité. */
    public function ranking(Request $request): JsonResponse
    {
        $me = $this->me();
        abort_unless($me->isStaff(), 403);

        $projects = Project::visibleTo($me)
            ->where('is_archived', false)
            ->whereNotNull('priority_score')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->with('type:id,name,color')
            ->orderByDesc('priority_score')
            ->limit(min((int) $request->query('limit', 50), 200))
            ->get(['id', 'reference', 'name', 'status', 'priority', 'priority_score', 'project_type_id']);

        return response()->json($projects->values()->map(fn ($p, $i) => $p->setAttribute('rank', $i + 1)));
    }
}
