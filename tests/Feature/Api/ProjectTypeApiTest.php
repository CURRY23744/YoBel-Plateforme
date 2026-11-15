<?php

namespace Tests\Feature\Api;

use App\Models\ProjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProjectTypeApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_admin_manages_types(): void
    {
        $this->actingAsUser($this->makeUser('admin'));

        $id = $this->postJson('/api/project-types', ['name' => 'Site web', 'color' => '#ff0000'])
            ->assertCreated()->assertJsonPath('slug', 'site-web')->json('id');

        $this->putJson("/api/project-types/{$id}", ['name' => 'Sites vitrines'])->assertOk()->assertJsonPath('name', 'Sites vitrines');
        $this->deleteJson("/api/project-types/{$id}")->assertNoContent();
    }

    public function test_non_admin_cannot_change_types(): void
    {
        $type = ProjectType::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAsUser($this->makeUser('project_manager'));

        $this->postJson('/api/project-types', ['name' => 'Jeu'])->assertForbidden();
        $this->putJson("/api/project-types/{$type->id}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/project-types/{$type->id}")->assertForbidden();
        $this->getJson('/api/project-types')->assertOk();
    }

    public function test_type_name_must_be_unique_in_organization(): void
    {
        $this->actingAsUser($this->makeUser('admin'));

        $this->postJson('/api/project-types', ['name' => 'Jeu'])->assertCreated();
        $this->postJson('/api/project-types', ['name' => 'Jeu'])->assertStatus(422);
    }

    public function test_type_in_use_cannot_be_deleted(): void
    {
        $type = ProjectType::factory()->create(['organization_id' => $this->org->id]);
        $this->makeProject(['project_type_id' => $type->id]);
        $this->actingAsUser($this->makeUser('admin'));

        $this->deleteJson("/api/project-types/{$type->id}")->assertStatus(409);
    }

    public function test_project_counts_only_include_visible_projects(): void
    {
        $type = ProjectType::factory()->create(['organization_id' => $this->org->id]);
        $visible = $this->makeProject(['project_type_id' => $type->id]);
        $this->makeProject(['project_type_id' => $type->id]);

        $member = $this->makeUser('member');
        $this->addMember($visible, $member);

        $this->actingAsUser($member);
        $count = collect($this->getJson('/api/project-types')->json())->firstWhere('id', $type->id)['projects_count'];
        $this->assertSame(1, $count);

        $this->actingAsUser($this->makeUser('admin'));
        $count = collect($this->getJson('/api/project-types')->json())->firstWhere('id', $type->id)['projects_count'];
        $this->assertSame(2, $count);
    }
}
