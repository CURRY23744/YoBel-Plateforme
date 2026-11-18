<?php

namespace Tests\Feature\Api;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProjectCrudApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private ProjectType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = ProjectType::factory()->create(['organization_id' => $this->org->id]);
    }

    private function payload(array $override = []): array
    {
        return array_merge(['name' => 'Refonte du site', 'project_type_id' => $this->type->id, 'start_date' => '2026-11-01', 'planned_end_date' => '2027-01-31'], $override);
    }

    public function test_manager_creates_a_project_and_becomes_its_manager(): void
    {
        $manager = $this->actingAsUser($this->makeUser('project_manager'));

        $response = $this->postJson('/api/projects', $this->payload(['technologies' => ['Laravel', 'MySQL']]))->assertCreated();

        $this->assertMatchesRegularExpression('/^PRJ-\d{4}-\d{2}-0001$/', $response->json('reference'));
        $this->assertSame($manager->id, $response->json('manager_id'));
        $this->assertDatabaseHas('project_members', ['project_id' => $response->json('id'), 'user_id' => $manager->id, 'can_manage' => 1]);
    }

    public function test_admin_can_choose_project_viewers_when_creating_a_project(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $allowed = $this->makeUser('member');
        $denied = $this->makeUser('member');

        $projectId = $this->postJson('/api/projects', $this->payload([
            'access_user_ids' => [$allowed->id],
        ]))->assertCreated()->json('id');

        $this->actingAsUser($allowed);
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsUser($denied);
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $projectId,
            'user_id' => $allowed->id,
            'can_view' => true,
            'left_at' => null,
        ]);
    }

    public function test_references_are_sequential(): void
    {
        $this->actingAsUser($this->makeUser('admin'));

        $first = $this->postJson('/api/projects', $this->payload())->json('reference');
        $second = $this->postJson('/api/projects', $this->payload(['name' => 'Autre']))->json('reference');

        $this->assertStringEndsWith('0001', $first);
        $this->assertStringEndsWith('0002', $second);
    }

    public function test_member_cannot_create_projects(): void
    {
        $this->actingAsUser($this->makeUser('member'));

        $this->postJson('/api/projects', $this->payload())->assertForbidden();
    }

    public function test_validation_rejects_foreign_type_and_bad_dates(): void
    {
        $foreignType = ProjectType::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAsUser($this->makeUser('admin'));

        $this->postJson('/api/projects', $this->payload(['project_type_id' => $foreignType->id]))->assertStatus(422)->assertJsonValidationErrors('project_type_id');
        $this->postJson('/api/projects', $this->payload(['planned_end_date' => '2020-01-01']))->assertStatus(422)->assertJsonValidationErrors('planned_end_date');
        $this->postJson('/api/projects', $this->payload(['status' => 'inconnu']))->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson('/api/projects', ['name' => 'Sans type'])->assertStatus(422)->assertJsonValidationErrors('project_type_id');
    }

    public function test_completing_a_project_sets_the_real_end_date(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $project = $this->makeProject(['project_type_id' => $this->type->id]);

        $this->putJson("/api/projects/{$project->id}", ['status' => 'completed'])->assertOk();

        $this->assertSame(now()->toDateString(), $project->fresh()->getAttribute('actual_end_date')->format('Y-m-d'));
    }

    public function test_only_admin_can_delete_a_project(): void
    {
        $project = $this->makeProject(['project_type_id' => $this->type->id]);
        $manager = $this->makeUser('project_manager');
        $this->addMember($project, $manager, 'manager');

        $this->actingAsUser($manager);
        $this->deleteJson("/api/projects/{$project->id}")->assertForbidden();

        $this->actingAsUser($this->makeUser('admin'));
        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_archive_and_restore(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $project = $this->makeProject(['project_type_id' => $this->type->id]);

        $this->postJson("/api/projects/{$project->id}/archive")->assertOk()->assertJsonPath('status', 'archived');
        $this->assertCount(0, $this->getJson('/api/projects')->json('data'));
        $this->assertCount(1, $this->getJson('/api/projects?include_archived=1')->json('data'));

        $this->postJson("/api/projects/{$project->id}/restore", ['status' => 'on_hold'])->assertOk()->assertJsonPath('status', 'on_hold');
        $this->assertCount(1, $this->getJson('/api/projects')->json('data'));
    }

    public function test_duplicate_copies_project_tags_and_folders(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $project = $this->makeProject(['project_type_id' => $this->type->id, 'name' => 'Original']);
        $parent = Folder::create(['project_id' => $project->id, 'name' => 'Contrat']);
        Folder::create(['project_id' => $project->id, 'parent_id' => $parent->id, 'name' => 'Signés']);

        $response = $this->postJson("/api/projects/{$project->id}/duplicate", ['copy_folders' => true])->assertCreated();

        $copyId = $response->json('id');
        $this->assertNotEquals($project->id, $copyId);
        $this->assertSame('Original (copie)', $response->json('name'));
        $this->assertSame('idea', $response->json('status'));
        $this->assertSame(2, Folder::where('project_id', $copyId)->count());
    }

    public function test_filters_by_type_status_and_overdue(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $other = ProjectType::factory()->create(['organization_id' => $this->org->id]);

        $a = $this->makeProject(['project_type_id' => $this->type->id, 'status' => 'in_progress']);
        $b = $this->makeProject(['project_type_id' => $other->id, 'status' => 'completed']);
        $late = $this->makeProject(['project_type_id' => $this->type->id, 'status' => 'in_progress', 'planned_end_date' => now()->subDays(3)->toDateString()]);

        $ids = fn (string $query) => collect($this->getJson("/api/projects{$query}")->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$a->id, $late->id], $ids("?project_type_id={$this->type->id}"));
        $this->assertSame([$b->id], $ids('?status=completed'));
        $this->assertSame([$late->id], $ids('?overdue=1'));
        $this->assertSame([$a->id], $ids("?q={$a->reference}"));
    }

    public function test_admin_can_restrict_project_visibility_to_selected_members(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $project = $this->makeProject(['project_type_id' => $this->type->id]);
        $allowed = $this->makeUser('member');
        $denied = $this->makeUser('member');

        $this->putJson("/api/projects/{$project->id}/access", ['user_ids' => [$allowed->id]])->assertOk();

        $this->actingAsUser($allowed);
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/projects/{$project->id}")->assertOk();

        $this->actingAsUser($denied);
        $this->putJson("/api/projects/{$project->id}/access", ['user_ids' => [$denied->id]])->assertForbidden();
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/projects/{$project->id}")->assertNotFound();

        $this->actingAsUser($this->makeUser('admin'));
        $this->putJson("/api/projects/{$project->id}/access", ['user_ids' => []])->assertOk();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $allowed->id,
            'can_view' => false,
        ]);
    }

    public function test_filters_by_project_creation_date_range(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $included = $this->makeProject(['project_type_id' => $this->type->id, 'created_at' => '2026-11-10 12:00:00']);
        $excluded = $this->makeProject(['project_type_id' => $this->type->id, 'created_at' => '2026-11-20 12:00:00']);

        $response = $this->getJson('/api/projects?from=2026-11-10&to=2026-11-15')->assertOk();

        $this->assertSame([$included->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_stats_and_show_details(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $project = $this->makeProject(['project_type_id' => $this->type->id]);

        $this->getJson('/api/projects/stats')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('by_status.in_progress', 1);
        $this->getJson("/api/projects/{$project->id}")->assertOk()->assertJsonStructure(['id', 'type', 'tags', 'milestones', 'members', 'my_permissions']);
    }

    public function test_milestones_crud(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $project = $this->makeProject(['project_type_id' => $this->type->id]);

        $id = $this->postJson("/api/projects/{$project->id}/milestones", ['title' => 'Livraison maquettes', 'due_date' => '2026-12-01'])->assertCreated()->json('id');
        $this->putJson("/api/milestones/{$id}", ['status' => 'completed'])->assertOk();
        $this->assertNotNull(Project::find($project->id)->milestones()->first()->completed_at);
        $this->deleteJson("/api/milestones/{$id}")->assertNoContent();
    }
}
