<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AiAssistantApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yobel_ai.ollama_url' => 'http://ollama.test',
            'services.yobel_ai.model' => 'test-model',
        ]);
    }

    public function test_member_can_ask_yobel_with_an_authorized_project_context(): void
    {
        Http::fake([
            'http://ollama.test/api/chat' => Http::response([
                'model' => 'test-model',
                'message' => ['content' => json_encode([
                    'answer' => 'Voici un résumé du projet.',
                    'suggestions' => [[
                        'type' => 'task',
                        'title' => 'Vérifier le plan de lancement',
                        'content' => 'Confirmer les responsables des tâches.',
                        'payload' => ['priority' => 'high'],
                    ]],
                ], JSON_UNESCAPED_UNICODE)],
                'prompt_eval_count' => 12,
                'eval_count' => 8,
            ]),
        ]);

        $project = $this->makeProject(['name' => 'Projet confidentiel']);
        $member = $this->makeUser('member');
        $this->addMember($project, $member, 'reader');
        Task::create([
            'project_id' => $project->id,
            'organization_id' => $this->org->id,
            'title' => 'Préparer le lancement',
            'status' => 'todo',
        ]);

        $this->actingAsUser($member);
        $response = $this->postJson('/api/ai/ask', [
            'prompt' => 'Résume les prochaines étapes.',
            'project_id' => $project->id,
        ])->assertCreated()
            ->assertJsonPath('response', 'Voici un résumé du projet.')
            ->assertJsonPath('provider', 'ollama')
            ->assertJsonPath('model', 'test-model')
            ->assertJsonPath('input_tokens', 12)
            ->assertJsonPath('output_tokens', 8)
            ->assertJsonPath('suggestions.0.title', 'Vérifier le plan de lancement');

        $interactionId = $response->json('id');
        $this->assertDatabaseHas('ai_interactions', [
            'id' => $interactionId,
            'user_id' => $member->id,
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('ai_suggestions', [
            'interaction_id' => $interactionId,
            'project_id' => $project->id,
            'type' => 'task',
            'status' => 'pending',
        ]);

        Http::assertSent(function (ClientRequest $request) {
            $systemMessage = data_get($request->data(), 'messages.0.content', '');

            return $request->url() === 'http://ollama.test/api/chat'
                && str_contains($systemMessage, 'Projet confidentiel')
                && str_contains($systemMessage, 'Préparer le lancement');
        });

        $this->getJson("/api/ai/interactions?project_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $interactionId);

        $suggestionId = $response->json('suggestions.0.id');
        $this->postJson("/api/ai/suggestions/{$suggestionId}/review", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('validated_by', $member->id);

        $otherUser = $this->makeUser('member');
        $this->actingAsUser($otherUser);
        $this->getJson('/api/ai/interactions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->postJson("/api/ai/suggestions/{$suggestionId}/review", ['status' => 'rejected'])
            ->assertNotFound();
    }

    public function test_guest_cannot_use_yobel(): void
    {
        $this->actingAsUser($this->makeUser('guest'));

        $this->getJson('/api/ai/interactions')->assertForbidden();
        $this->postJson('/api/ai/ask', ['prompt' => 'Bonjour'])->assertForbidden();
    }

    public function test_project_context_requires_project_visibility(): void
    {
        $member = $this->makeUser('member');
        $project = $this->makeProject();
        $this->actingAsUser($member);

        $this->postJson('/api/ai/ask', [
            'prompt' => 'Résume ce projet.',
            'project_id' => $project->id,
        ])->assertNotFound();

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_interactions', 0);
    }

    public function test_ollama_errors_are_reported_and_saved(): void
    {
        Http::fake(['http://ollama.test/api/chat' => Http::response(['error' => 'model not found'], 500)]);
        $member = $this->makeUser('member');
        $this->actingAsUser($member);

        $response = $this->postJson('/api/ai/ask', ['prompt' => 'Bonjour'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Le moteur Ollama local a refusé la requête.');

        $this->assertDatabaseHas('ai_interactions', [
            'id' => $response->json('interaction_id'),
            'user_id' => $member->id,
            'status' => 'failed',
        ]);
    }

    public function test_assistant_page_is_limited_to_non_guest_roles(): void
    {
        $this->get('/assistant')
            ->assertOk()
            ->assertSee('data-page-roles="admin,project_manager,member"', false)
            ->assertSee('Assistant Yobel');
    }
}
