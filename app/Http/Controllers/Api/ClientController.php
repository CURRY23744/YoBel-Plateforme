<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends ApiController
{
    public function index(Request $request)
    {
        return response()->json(
            Client::query()
                ->withCount('projects')
                ->when($request->filled('search'), function ($q) use ($request) {
                    $s = '%'.$request->query('search').'%';
                    $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('company', 'like', $s)->orWhere('email', 'like', $s));
                })
                ->orderBy('name')
                ->paginate($this->perPage($request))
        );
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json($client->loadCount('projects'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        return response()->json(Client::create($data + ['created_by' => $this->me()->id]), 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $client->update($request->validate($this->rules(true)));

        return response()->json($client->fresh());
    }

    public function destroy(Client $client): JsonResponse
    {
        if ($client->projects()->exists()) {
            return response()->json(['message' => 'Ce client est lié à des projets. Désactivez-le plutôt que de le supprimer.'], 409);
        }

        $client->delete();

        return response()->json(null, 204);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => "$required|string|max:255",
            'company' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'country' => 'nullable|string|size:2',
            'tax_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
