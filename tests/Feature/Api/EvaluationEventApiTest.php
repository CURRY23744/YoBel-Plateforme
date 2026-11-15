<?php

namespace Tests\Feature\Api;

use App\Models\EvaluationCriterion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class EvaluationEventApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_only_admin_defines_criteria(): void
    {
        $this->actingAsUser($this->makeUser('project_manager'));
        $this->postJson('/api/evaluation-criteria', ['name' => 'Budget', 'weight' => 2])->assertForbidden();

        $this->actingAsUser($this->makeUser('admin'));
        $this->postJson('/api/evaluation-criteria', ['name' => 'Budget', 'weight' => 2])->assertCreated();
        $this->postJson('/api/evaluation-criteria', ['name' => 'Budget'])->assertStatus(422);
    }

    public function test_weighted_score_and_ranking(): void
    {
        $budget = EvaluationCriterion::create(['organization_id' => $this->org->id, 'name' => 'Budget', 'weight' => 2]);
        $delai = EvaluationCriterion::create(['organization_id' => $this->org->id, 'name' => 'Délai', 'weight' => 1]);

        $strong = $this->makeProject(['name' => 'Projet fort']);
        $weak = $this->makeProject(['name' => 'Projet faible']);
        $manager = $this->makeUser('project_manager');
        $this->addMember($strong, $manager, 'manager');
        $this->addMember($weak, $manager, 'manager');
        $this->actingAsUser($manager);

        // (8*2 + 5*1) / 3 * 10 = 70
        $this->postJson("/api/projects/{$strong->id}/evaluations", ['scores' => [
            ['criterion_id' => $budget->id, 'score' => 8, 'justification' => 'Budget confortable'],
            ['criterion_id' => $delai->id, 'score' => 5],
        ]])->assertOk();
        $this->assertEquals(70.0, (float) $strong->fresh()->priority_score);

        $this->postJson("/api/projects/{$weak->id}/evaluations", ['scores' => [
            ['criterion_id' => $budget->id, 'score' => 2], ['criterion_id' => $delai->id, 'score' => 2],
        ]])->assertOk();

        $ranking = $this->getJson('/api/evaluations/ranking')->assertOk()->json();
        $this->assertSame($strong->id, $ranking[0]['id']);
        $this->assertSame(1, $ranking[0]['rank']);
        $this->assertSame($weak->id, $ranking[1]['id']);

        // Réévaluation : la note est remplacée, pas dupliquée
        $this->postJson("/api/projects/{$strong->id}/evaluations", ['scores' => [['criterion_id' => $budget->id, 'score' => 10]]])->assertOk();
        $this->assertDatabaseCount('project_evaluations', 4);
        $detail = $this->getJson("/api/projects/{$strong->id}/evaluations")->assertOk();
        $this->assertEquals(10, $detail->json('criteria.0.average'));
    }

    public function test_scores_are_validated_and_members_are_excluded(): void
    {
        $criterion = EvaluationCriterion::create(['organization_id' => $this->org->id, 'name' => 'Budget']);
        $project = $this->makeProject();
        $manager = $this->makeUser('project_manager');
        $member = $this->makeUser('member');
        $this->addMember($project, $manager, 'manager');
        $this->addMember($project, $member, 'manager');

        $this->actingAsUser($manager);
        $this->postJson("/api/projects/{$project->id}/evaluations", ['scores' => [['criterion_id' => $criterion->id, 'score' => 11]]])->assertStatus(422);

        $this->actingAsUser($member);
        $this->postJson("/api/projects/{$project->id}/evaluations", ['scores' => [['criterion_id' => $criterion->id, 'score' => 5]]])->assertForbidden();
        $this->getJson('/api/evaluations/ranking')->assertForbidden();
    }

    public function test_project_event_with_participants_and_response(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $guest = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');
        $this->addMember($project, $guest, 'reader');

        $this->actingAsUser($editor);
        $id = $this->postJson('/api/events', [
            'title' => 'Kick-off', 'type' => 'meeting', 'starts_at' => '2026-11-10 09:00:00', 'ends_at' => '2026-11-10 10:00:00',
            'project_id' => $project->id, 'participants' => [$guest->id],
        ])->assertCreated()->assertJsonCount(1, 'participants')->json('id');

        $this->assertSame(1, $guest->notifications()->count());
        $this->getJson('/api/events')->assertOk()->assertJsonPath('0.can_manage', true);

        $this->actingAsUser($guest);
        $this->getJson('/api/events')->assertOk()->assertJsonPath('0.can_manage', false);
        $this->getJson("/api/events/{$id}")->assertOk();
        $this->postJson("/api/events/{$id}/respond", ['response_status' => 'accepted'])->assertOk();
        $this->assertDatabaseHas('event_participants', ['event_id' => $id, 'user_id' => $guest->id, 'response_status' => 'accepted']);
        $this->putJson("/api/events/{$id}", ['title' => 'Piraté'])->assertForbidden();
    }

    public function test_participants_must_have_access_to_the_project(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');
        $stranger = $this->makeUser('member');

        $this->actingAsUser($editor);
        $this->postJson('/api/events', [
            'title' => 'Réunion', 'starts_at' => '2026-11-10 09:00:00', 'project_id' => $project->id, 'participants' => [$stranger->id],
        ])->assertStatus(422);
    }

    public function test_private_events_and_date_range(): void
    {
        $owner = $this->actingAsUser($this->makeUser('member'));
        $id = $this->postJson('/api/events', ['title' => 'RDV perso', 'starts_at' => '2026-11-12 14:00:00', 'visibility' => 'private'])->assertCreated()->json('id');
        $this->postJson('/api/events', ['title' => 'Plus tard', 'starts_at' => '2027-03-01 14:00:00', 'visibility' => 'private'])->assertCreated();

        $titles = collect($this->getJson('/api/events?from=2026-11-01&to=2026-11-30')->json())->pluck('title');
        $this->assertSame(['RDV perso'], $titles->all());

        $this->actingAsUser($this->makeUser('member'));
        $this->getJson("/api/events/{$id}")->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/events')->json());
    }

    public function test_end_date_must_follow_start_date(): void
    {
        $this->actingAsUser($this->makeUser('member'));

        $this->postJson('/api/events', ['title' => 'X', 'starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 09:00:00'])
            ->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }
}
