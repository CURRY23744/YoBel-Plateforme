<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProjectMemberApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_manager_adds_a_member_with_a_preset_and_notifies_him(): void
    {
        $project = $this->makeProject();
        $manager = $this->makeUser('project_manager');
        $this->addMember($project, $manager, 'manager');
        $employee = $this->makeUser('member');

        $this->actingAsUser($manager);
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $employee->id, 'preset' => 'contributor', 'project_role' => 'designer'])
            ->assertCreated()
            ->assertJsonPath('can_upload', true)
            ->assertJsonPath('can_edit', false)
            ->assertJsonPath('project_role', 'designer');

        $this->assertSame(1, $employee->notifications()->count());
    }

    public function test_explicit_flags_override_the_preset(): void
    {
        $project = $this->makeProject();
        $this->actingAsUser($this->makeUser('admin'));
        $employee = $this->makeUser('member');

        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $employee->id, 'preset' => 'reader', 'can_comment' => true])
            ->assertCreated()->assertJsonPath('can_comment', true)->assertJsonPath('can_upload', false);
    }

    public function test_simple_member_cannot_manage_the_team(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');
        $other = $this->makeUser('member');

        $this->actingAsUser($editor);
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $other->id])->assertForbidden();
        $this->deleteJson("/api/projects/{$project->id}/members/{$editor->id}")->assertForbidden();
    }

    public function test_cannot_add_a_user_of_another_organization(): void
    {
        $project = $this->makeProject();
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAsUser($this->makeUser('admin'));
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $foreign->id])->assertNotFound();
    }

    public function test_update_rights_then_readd_a_former_member(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeUser('member');
        $this->addMember($project, $employee, 'reader');
        $this->actingAsUser($this->makeUser('admin'));

        $this->putJson("/api/projects/{$project->id}/members/{$employee->id}", ['preset' => 'editor'])->assertOk()->assertJsonPath('can_edit', true);

        $this->deleteJson("/api/projects/{$project->id}/members/{$employee->id}")->assertOk();
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $employee->id, 'preset' => 'reader'])->assertCreated();

        $this->assertSame(1, $project->members()->where('user_id', $employee->id)->count());
        $this->assertNull($project->members()->where('user_id', $employee->id)->first()->left_at);
    }
}
