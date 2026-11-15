<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Awa', 'last_name' => 'Koffi', 'email' => 'awa@example.com', 'role' => 'member',
        ], $override);
    }

    public function test_admin_creates_an_account_with_temporary_password(): void
    {
        $this->actingAsUser($this->makeUser('admin'));

        $response = $this->postJson('/api/users', $this->payload())->assertCreated();

        $this->assertNotEmpty($response->json('temporary_password'));
        $this->assertDatabaseHas('users', [
            'email' => 'awa@example.com', 'role' => 'member', 'must_change_password' => 1, 'organization_id' => $this->org->id,
        ]);
    }

    public function test_only_admin_can_create_accounts(): void
    {
        $this->actingAsUser($this->makeUser('project_manager'));
        $this->postJson('/api/users', $this->payload())->assertForbidden();

        $this->actingAsUser($this->makeUser('member'));
        $this->postJson('/api/users', $this->payload())->assertForbidden();
    }

    public function test_manager_can_list_but_member_cannot(): void
    {
        $this->actingAsUser($this->makeUser('project_manager'));
        $this->getJson('/api/users')->assertOk();

        $this->actingAsUser($this->makeUser('member'));
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_email_must_be_unique_and_role_valid(): void
    {
        $this->makeUser('member', null, ['email' => 'awa@example.com']);
        $this->actingAsUser($this->makeUser('admin'));

        $this->postJson('/api/users', $this->payload())->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/users', $this->payload(['email' => 'autre@example.com', 'role' => 'dieu']))->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_admin_cannot_deactivate_or_demote_himself(): void
    {
        $admin = $this->actingAsUser($this->makeUser('admin'));

        $this->postJson("/api/users/{$admin->id}/toggle-active")->assertStatus(422);
        $this->putJson("/api/users/{$admin->id}", ['role' => 'member'])->assertStatus(422);
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $employee = $this->makeUser('member');

        $this->postJson("/api/users/{$employee->id}/toggle-active")->assertOk()->assertJsonPath('is_active', false);
        $this->postJson("/api/users/{$employee->id}/toggle-active")->assertOk()->assertJsonPath('is_active', true);
    }

    public function test_admin_cannot_reach_users_of_another_organization(): void
    {
        $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAsUser($this->makeUser('admin'));

        $this->getJson("/api/users/{$other->id}")->assertNotFound();
        $this->putJson("/api/users/{$other->id}", ['first_name' => 'Hack'])->assertNotFound();
        $this->postJson("/api/users/{$other->id}/toggle-active")->assertNotFound();

        $emails = collect($this->getJson('/api/users')->json('data'))->pluck('email');
        $this->assertFalse($emails->contains($other->email));
    }

    public function test_admin_can_reset_a_password(): void
    {
        $this->actingAsUser($this->makeUser('admin'));
        $employee = $this->makeUser('member');

        $this->postJson("/api/users/{$employee->id}/reset-password")->assertOk()->assertJsonStructure(['temporary_password']);
        $this->assertTrue((bool) $employee->fresh()->must_change_password);
    }
}
