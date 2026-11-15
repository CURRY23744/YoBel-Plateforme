<?php

namespace App\Http\Controllers\Api;

use App\Models\EvaluationCriterion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvaluationCriterionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->me()->isStaff(), 403);

        return response()->json(
            EvaluationCriterion::query()
                ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $org = $this->me()->organization_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('evaluation_criteria')->where('organization_id', $org)],
            'description' => 'nullable|string|max:1000',
            'weight' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        return response()->json(EvaluationCriterion::create($data), 201);
    }

    public function update(Request $request, EvaluationCriterion $criterion): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('evaluation_criteria')->where('organization_id', $criterion->organization_id)->ignore($criterion->id)],
            'description' => 'nullable|string|max:1000',
            'weight' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $criterion->update($data);

        return response()->json($criterion->fresh());
    }

    public function destroy(EvaluationCriterion $criterion): JsonResponse
    {
        if ($criterion->evaluations()->exists()) {
            return response()->json(['message' => 'Ce critère a déjà servi à des évaluations. Désactivez-le plutôt.'], 409);
        }

        $criterion->delete();

        return response()->json(null, 204);
    }
}
