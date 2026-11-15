<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AiAssistantApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_member_can_ask_yobel_with_an_authorized_project_context(): void
    {
        $this->fakeGemini('Projet confidentiel : 1 tâche ouverte.');
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
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('model', 'gemini-3.6-flash')
            ->assertJsonPath('input_tokens', 12)
            ->assertJsonPath('output_tokens', 20)
            ->assertJsonPath('suggestions', []);

        $interactionId = $response->json('id');
        $this->assertStringContainsString('1 tâche ouverte', $response->json('response'));
        $this->assertDatabaseHas('ai_interactions', [
            'id' => $interactionId,
            'user_id' => $member->id,
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseCount('ai_suggestions', 0);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), ':generateContent')
            && str_contains(data_get($request->data(), 'systemInstruction.parts.0.text', ''), 'Projet confidentiel')
            && data_get($request->data(), 'contents.0.parts.0.text') === 'Résume les prochaines étapes.');

        $this->getJson("/api/ai/interactions?project_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $interactionId);

        $otherUser = $this->makeUser('member');
        $this->actingAsUser($otherUser);
        $this->getJson('/api/ai/interactions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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

    public function test_yobel_answers_general_questions_through_gemini(): void
    {
        $this->fakeGemini('Bonjour ! Que souhaitez-vous explorer ?');
        $member = $this->makeUser('member');
        $this->actingAsUser($member);

        $response = $this->postJson('/api/ai/ask', ['prompt' => 'Bonjour'])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('response', 'Bonjour ! Que souhaitez-vous explorer ?');

        $this->assertDatabaseHas('ai_interactions', [
            'id' => $response->json('id'),
            'user_id' => $member->id,
            'status' => 'completed',
        ]);
        Http::assertSent(fn (HttpRequest $request) => data_get($request->data(), 'contents.0.parts.0.text') === 'Bonjour');
    }

    public function test_recent_completed_exchange_is_sent_as_conversation_history(): void
    {
        $this->fakeGemini('Je comprends, continuons.');
        $this->actingAsUser($this->makeUser('member'));

        $this->postJson('/api/ai/ask', ['prompt' => 'Je réfléchis à une idée de projet.'])->assertCreated();
        $this->postJson('/api/ai/ask', ['prompt' => 'Aide-moi à la préciser.'])->assertCreated();

        Http::assertSent(fn (HttpRequest $request) => data_get($request->data(), 'contents.0.parts.0.text') === 'Je réfléchis à une idée de projet.'
            && data_get($request->data(), 'contents.1.role') === 'model'
            && data_get($request->data(), 'contents.1.parts.0.text') === 'Je comprends, continuons.'
            && data_get($request->data(), 'contents.2.parts.0.text') === 'Aide-moi à la préciser.');
    }

    public function test_assistant_page_is_limited_to_non_guest_roles(): void
    {
        $this->get('/assistant')
            ->assertOk()
            ->assertSee('data-page-roles="admin,project_manager,member"', false)
            ->assertSee('Assistant Yobel');
    }

    private function fakeGemini(string $answer): void
    {
        config([
            'services.yobel_ai.gemini_api_key' => 'test-key',
            'services.yobel_ai.gemini_model' => 'gemini-3.6-flash',
        ]);
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode(['answer' => $answer, 'suggestions' => []], JSON_UNESCAPED_UNICODE)],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 12,
                    'candidatesTokenCount' => 20,
                ],
            ]),
        ]);
    }
}
