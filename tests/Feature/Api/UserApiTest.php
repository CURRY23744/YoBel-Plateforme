<?php

namespace Tests\Feature\Api;

use App\Models\Discussion;
use App\Models\MeetingMinute;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_creates_a_guest_with_one_project_and_selected_tasks(): void
    {
        $project = $this->makeProject();
        $task = Task::create([
            'project_id' => $project->id,
            'organization_id' => $this->org->id,
            'title' => 'Tâche réservée à l’invité',
        ]);
        $otherTask = Task::create([
            'project_id' => $project->id,
            'organization_id' => $this->org->id,
            'title' => 'Tâche interne',
        ]);
        $this->actingAsUser($this->makeUser('admin'));

        $guest = $this->postJson('/api/users', $this->payload([
            'email' => 'invite@example.com',
            'role' => 'guest',
            'project_id' => $project->id,
            'task_ids' => [$task->id],
        ]))->assertCreated()->json('user');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $guest['id'],
            'can_view' => 1,
            'can_comment' => 1,
        ]);
        $this->assertSame($guest['id'], $task->fresh()->assignee_id);
        $this->assertNull($otherTask->fresh()->assignee_id);
    }

    public function test_guest_creation_requires_a_project_in_the_same_organization(): void
    {
        $foreign = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAsUser($this->makeUser('admin'));

        $this->postJson('/api/users', $this->payload([
            'email' => 'invite@example.com',
            'role' => 'guest',
        ]))->assertStatus(422)->assertJsonValidationErrors('project_id');

        $this->postJson('/api/users', $this->payload([
            'email' => 'invite@example.com',
            'role' => 'guest',
            'project_id' => $foreign->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('project_id');
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

    public function test_admin_permanently_deletes_user_data_and_releases_the_login_email(): void
    {
        Storage::fake('public');
        $project = $this->makeProject();
        $employee = $this->makeUser('member', null, [
            'email' => 'deleted@example.com',
            'avatar_path' => 'avatars/employee/profile.png',
            'password' => 'OldPassword123',
        ]);
        Storage::disk('public')->put($employee->avatar_path, 'avatar');
        $this->addMember($project, $employee, 'contributor');
        $project->update(['manager_id' => $employee->id]);
        $task = Task::create([
            'project_id' => $project->id,
            'organization_id' => $this->org->id,
            'title' => 'Tâche partagée',
            'assignee_id' => $employee->id,
            'created_by' => $employee->id,
        ]);
        $discussion = Discussion::create([
            'project_id' => $project->id,
            'organization_id' => $this->org->id,
            'title' => 'Discussion partagée',
            'created_by' => $employee->id,
        ]);
        $message = Message::create([
            'discussion_id' => $discussion->id,
            'user_id' => $employee->id,
            'body' => 'Message conservé sans auteur.',
        ]);
        $minute = MeetingMinute::create([
            'organization_id' => $this->org->id,
            'project_id' => $project->id,
            'created_by' => $employee->id,
            'title' => 'Compte rendu partagé',
            'content' => 'Contenu conservé.',
            'attendees' => [$employee->id],
        ]);
        $employee->favoriteProjects()->attach($project->id);
        $employee->createToken('test-token');
        $employee->notify(new AppNotification(['type' => 'task.assigned', 'title' => 'Notification privée']));

        DB::table('sessions')->insert([
            'id' => 'deleted-user-session',
            'user_id' => $employee->id,
            'payload' => 'session data',
            'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $employee->email,
            'token' => 'reset-token',
            'created_at' => now(),
        ]);
        $interactionId = DB::table('ai_interactions')->insertGetId([
            'organization_id' => $this->org->id,
            'user_id' => $employee->id,
            'prompt' => 'Question privée',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_suggestions')->insert([
            'organization_id' => $this->org->id,
            'interaction_id' => $interactionId,
            'type' => 'summary',
            'title' => 'Suggestion privée',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('mail_accounts')->insert([
            'organization_id' => $this->org->id,
            'user_id' => $employee->id,
            'email' => 'mailbox@example.com',
            'provider' => 'google',
            'is_shared' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mailAccountId = DB::table('mail_accounts')->where('user_id', $employee->id)->value('id');
        DB::table('mail_messages')->insert([
            'organization_id' => $this->org->id,
            'mail_account_id' => $mailAccountId,
            'provider_message_id' => 'provider-message-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_weekly_availabilities')->insert([
            'user_id' => $employee->id,
            'weekday' => 1,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('time_entries')->insert([
            'project_id' => $project->id,
            'user_id' => $employee->id,
            'entry_date' => now()->toDateString(),
            'minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($this->makeUser('admin'));
        $this->deleteJson("/api/users/{$employee->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
        $this->assertDatabaseMissing('users', ['email' => 'deleted@example.com']);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'deleted@example.com']);
        $this->assertDatabaseMissing('ai_interactions', ['id' => $interactionId]);
        $this->assertDatabaseMissing('ai_suggestions', ['interaction_id' => $interactionId]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $employee->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $employee->id]);
        $this->assertDatabaseMissing('mail_accounts', ['id' => $mailAccountId]);
        $this->assertDatabaseMissing('mail_messages', ['mail_account_id' => $mailAccountId]);
        $this->assertDatabaseMissing('user_weekly_availabilities', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('time_entries', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('project_members', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('project_favorites', ['user_id' => $employee->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'manager_id' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assignee_id' => null, 'created_by' => null]);
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'user_id' => null]);
        $this->assertDatabaseHas('meeting_minutes', ['id' => $minute->id, 'created_by' => null, 'attendees' => '[]']);
        Storage::disk('public')->assertMissing('avatars/employee/profile.png');

        $this->postJson('/api/auth/login', [
            'email' => 'deleted@example.com',
            'password' => 'OldPassword123',
        ])->assertUnauthorized();

        $replacement = $this->postJson('/api/users', $this->payload(['email' => 'deleted@example.com']))
            ->assertCreated();
        $this->postJson('/api/auth/login', [
            'email' => 'deleted@example.com',
            'password' => $replacement->json('temporary_password'),
        ])->assertOk()->assertJsonPath('user.id', $replacement->json('user.id'));
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
