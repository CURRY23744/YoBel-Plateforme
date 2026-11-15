<?php

namespace App\Http\Controllers\Api;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(Tag::withCount('projects')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $slug = Str::slug($data['name']) ?: 'tag';

        $tag = Tag::firstOrCreate(['slug' => $slug, 'organization_id' => $this->me()->organization_id], $data);

        return response()->json($tag, $tag->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(null, 204);
    }
}
