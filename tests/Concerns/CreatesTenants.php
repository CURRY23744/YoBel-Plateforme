<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait CreatesTenants
{
    protected Organization $org;

    /** Appelée automatiquement par Laravel au démarrage de chaque test (setUp + nom du trait). */
    protected function setUpCreatesTenants(): void
    {
        $this->org = Organization::factory()->create();
    }

    protected function makeUser(string $role = 'member', ?Organization $org = null, array $attributes = []): User
    {
        return User::factory()->role(Role::from($role))->create(['organization_id' => ($org ?? $this->org)->id] + $attributes);
    }

    protected function makeProject(array $attributes = [], ?Organization $org = null): Project
    {
        return Project::factory()->create(['organization_id' => ($org ?? $this->org)->id] + $attributes);
    }

    /** presets : reader | contributor | editor | manager */
    protected function addMember(Project $project, User $user, string $preset = 'reader'): ProjectMember
    {
        return ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_role' => 'developer',
            'joined_at' => now(),
        ] + ProjectMember::PRESETS[$preset]);
    }

    protected function actingAsUser(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }
}
