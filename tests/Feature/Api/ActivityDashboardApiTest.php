<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\ProjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ActivityDashboardApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_project_creation_is_logged_and_visible_to_admin_only_globally(): void
    {
        $type = ProjectType::factory()->create(['organization_id' => $this->org->id]);
        $manager = $this->actingAsUser($this->makeUser('project_manager'));

        $id = $this->postJson('/api/projects', ['name' => 'Journalisé', 'project_type_id' => $type->id])->assertCreated()->json('id');

        $this->assertTrue(ActivityLog::where('project_id', $id)->where('action', 'created')->where('user_id', $manager->id)->exists());
        $this->getJson('/api/activity-logs')->assertForbidden();
        $this->getJson("/api/projects/{$id}/activity")->assertOk();

        $this->actingAsUser($this->makeUser('admin'));
        $this->getJson('/api/activity-logs?action=created')->assertOk();
    }

    public function test_activity_log_masks_passwords_and_exports_csv(): void
    {
        $admin = $this->actingAsUser($this->makeUser('admin'));
        $this->postJson('/api/users', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'ab@example.com', 'role' => 'member'])->assertCreated();

        $log = ActivityLog::where('subject_type', 'like', '%User')->where('action', 'created')->latest('id')->first();
        $this->assertSame('***', $log->properties['new']['password']);

        $response = $this->get('/api/activity-logs?export=csv')->assertOk();
        $this->assertStringContainsString('date,utilisateur', $response->streamedContent());
    }

    public function test_dashboard_is_adapted_to_the_role(): void
    {
        $project = $this->makeProject();
        $member = $this->makeUser('member');
        $this->addMember($project, $member, 'reader');

        $this->actingAsUser($member);
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('role', 'member')
            ->assertJsonPath('projects_by_status.in_progress', 1)
            ->assertJsonMissingPath('recent_activity')
            ->assertJsonMissingPath('pending_decisions');

        $this->actingAsUser($this->makeUser('admin'));
        $this->getJson('/api/dashboard')->assertOk()->assertJsonStructure(['recent_activity', 'latest_users', 'pending_decisions', 'overdue_projects']);
    }

    public function test_announcements_are_published_by_staff_for_everyone(): void
    {
        $this->actingAsUser($this->makeUser('member'));
        $this->postJson('/api/announcements', ['title' => 'X', 'body' => 'Y'])->assertForbidden();

        $employee = $this->makeUser('member');
        $this->actingAsUser($this->makeUser('admin'));
        $this->postJson('/api/announcements', ['title' => 'Fermeture', 'body' => 'Bureaux fermés lundi', 'is_pinned' => true])->assertCreated();
        $this->postJson('/api/announcements', ['title' => 'Futur', 'body' => 'Plus tard', 'published_at' => now()->addWeek()->toDateTimeString()])->assertCreated();

        $this->assertSame(1, $employee->notifications()->count());

        $this->actingAsUser($employee);
        $this->assertCount(1, $this->getJson('/api/announcements')->json('data'));
    }

    public function test_meeting_minutes_in_project_and_general_space(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');

        $this->actingAsUser($editor);
        $id = $this->postJson("/api/projects/{$project->id}/meeting-minutes", ['title' => 'Réunion 1', 'content' => 'Points abordés...', 'meeting_date' => '2026-11-03', 'attendees' => [$editor->id]])
            ->assertCreated()->json('id');
        $this->getJson("/api/projects/{$project->id}/meeting-minutes")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/meeting-minutes/{$id}")->assertOk();
        $this->postJson('/api/meeting-minutes', ['title' => 'Général', 'content' => 'x'])->assertForbidden();

        $this->actingAsUser($this->makeUser('project_manager'));
        $this->postJson('/api/meeting-minutes', ['title' => 'Général', 'content' => 'x'])->assertCreated();
    }

    public function test_clients_and_tags(): void
    {
        $this->actingAsUser($this->makeUser('member'));
        $this->postJson('/api/clients', ['name' => 'ACME'])->assertForbidden();

        $this->actingAsUser($this->makeUser('project_manager'));
        $clientId = $this->postJson('/api/clients', ['name' => 'ACME', 'email' => 'contact@acme.test'])->assertCreated()->json('id');
        $this->getJson('/api/clients?search=ACM')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/tags', ['name' => 'E-commerce'])->assertCreated();
        $this->postJson('/api/tags', ['name' => 'E-commerce'])->assertOk(); // idempotent
        $this->assertCount(1, $this->getJson('/api/tags')->json());

        $project = $this->makeProject(['client_id' => $clientId]);
        $this->deleteJson("/api/clients/{$clientId}")->assertStatus(409);
    }
}
