<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/** Cloisonnement : un utilisateur ne voit que ce qui lui est ouvert. */
class ProjectAccessTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_member_without_membership_does_not_see_the_project(): void
    {
        $project = $this->makeProject(['name' => 'ZetaSecretProject']);
        $this->actingAsUser($this->makeUser('member'));

        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/projects')->json('data'));
        $this->assertEmpty($this->getJson('/api/search?q=ZetaSecret')->json('projects'));
    }

    public function test_member_with_reader_rights_can_view_but_not_edit(): void
    {
        $project = $this->makeProject(['name' => 'ZetaSecretProject']);
        $member = $this->makeUser('member');
        $this->addMember($project, $member, 'reader');
        $this->actingAsUser($member);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('my_permissions.view', true)
            ->assertJsonPath('my_permissions.edit', false);

        $this->assertCount(1, $this->getJson('/api/projects')->json('data'));
        $this->assertCount(1, $this->getJson('/api/search?q=ZetaSecret')->json('projects'));
        $this->putJson("/api/projects/{$project->id}", ['name' => 'Piraté'])->assertForbidden();
    }

    public function test_admin_sees_every_project_of_his_organization_only(): void
    {
        $mine = $this->makeProject();
        $foreignOrg = Organization::factory()->create();
        $foreign = $this->makeProject([], $foreignOrg);

        $this->actingAsUser($this->makeUser('admin'));

        $this->getJson("/api/projects/{$mine->id}")->assertOk();
        $this->getJson("/api/projects/{$foreign->id}")->assertNotFound();
        $this->assertSame([$mine->id], collect($this->getJson('/api/projects')->json('data'))->pluck('id')->all());
    }

    public function test_guest_stays_read_only_even_with_editor_rights(): void
    {
        $project = $this->makeProject();
        $guest = $this->makeUser('guest');
        $this->addMember($project, $guest, 'manager');
        $this->actingAsUser($guest);

        $this->getJson("/api/projects/{$project->id}")->assertOk();
        $this->putJson("/api/projects/{$project->id}", ['name' => 'X'])->assertForbidden();
        $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Salut'])->assertForbidden();
        $this->getJson('/api/discussions')->assertForbidden();
    }

    public function test_removed_member_loses_access_but_stays_in_history(): void
    {
        $project = $this->makeProject();
        $manager = $this->makeUser('project_manager');
        $employee = $this->makeUser('member');
        $this->addMember($project, $manager, 'manager');
        $this->addMember($project, $employee, 'editor');

        $this->actingAsUser($manager);
        $this->deleteJson("/api/projects/{$project->id}/members/{$employee->id}")->assertOk();

        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $employee->id, 'can_view' => 0]);
        $this->assertNotNull($project->members()->where('user_id', $employee->id)->first()->left_at);

        $this->actingAsUser($employee->fresh());
        $this->getJson("/api/projects/{$project->id}")->assertNotFound();

        $this->actingAsUser($manager);
        $this->assertCount(1, $this->getJson("/api/projects/{$project->id}/members")->json());
        $this->assertCount(2, $this->getJson("/api/projects/{$project->id}/members?include_former=1")->json());
    }

    public function test_sub_resources_of_a_hidden_project_are_hidden_too(): void
    {
        $project = $this->makeProject();
        $this->actingAsUser($this->makeUser('member'));

        $this->getJson("/api/projects/{$project->id}/members")->assertNotFound();
        $this->getJson("/api/projects/{$project->id}/files")->assertNotFound();
        $this->getJson("/api/projects/{$project->id}/folders")->assertNotFound();
        $this->getJson("/api/projects/{$project->id}/discussions")->assertNotFound();
        $this->getJson("/api/projects/{$project->id}/milestones")->assertNotFound();
        $this->getJson("/api/projects/{$project->id}/activity")->assertNotFound();
    }
}
