<?php

namespace Tests\Feature\Api;

use App\Models\Decision;
use App\Models\MeetingMinute;
use App\Models\Task;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TaskDecisionApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_editor_creates_a_task_and_notifies_the_assignee(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $assignee = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');
        $this->addMember($project, $assignee, 'reader');

        $this->actingAsUser($editor);
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'Maquette accueil', 'assignee_id' => $assignee->id, 'due_date' => '2026-12-15', 'priority' => 'high'])
            ->assertCreated()->assertJsonPath('assignee.id', $assignee->id);

        $this->assertSame(1, $assignee->notifications()->count());
    }

    public function test_task_cannot_be_assigned_to_a_non_member(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');
        $stranger = $this->makeUser('member');

        $this->actingAsUser($editor);
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'X', 'assignee_id' => $stranger->id])->assertStatus(422);
    }

    public function test_member_can_create_a_personal_task_and_receives_its_edit_permissions(): void
    {
        $member = $this->makeUser('member');
        $this->actingAsUser($member);

        $this->postJson('/api/tasks', ['title' => 'Préparer le compte rendu', 'assignee_id' => $member->id])
            ->assertCreated()
            ->assertJsonPath('assignee.id', $member->id);

        $this->getJson('/api/tasks?assignee_id=me')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.can_change_status', true);
    }

    public function test_reader_cannot_create_tasks(): void
    {
        $project = $this->makeProject();
        $reader = $this->makeUser('member');
        $this->addMember($project, $reader, 'reader');

        $this->actingAsUser($reader);
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'X'])->assertForbidden();
    }

    public function test_assignee_without_edit_right_can_only_change_the_status(): void
    {
        $project = $this->makeProject();
        $assignee = $this->makeUser('member');
        $this->addMember($project, $assignee, 'reader');
        $task = Task::create(['project_id' => $project->id, 'organization_id' => $this->org->id, 'title' => 'Original', 'assignee_id' => $assignee->id]);

        $this->actingAsUser($assignee);
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Piraté', 'status' => 'done'])->assertOk();

        $task = $task->fresh();
        $this->assertSame('Original', $task->title);
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);

        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_my_tasks_filter_and_isolation(): void
    {
        $visible = $this->makeProject();
        $hidden = $this->makeProject();
        $me = $this->makeUser('member');
        $this->addMember($visible, $me, 'reader');

        Task::create(['project_id' => $visible->id, 'organization_id' => $this->org->id, 'title' => 'Mienne', 'assignee_id' => $me->id]);
        Task::create(['project_id' => $visible->id, 'organization_id' => $this->org->id, 'title' => 'Autre', 'assignee_id' => $this->makeUser()->id]);
        $secret = Task::create(['project_id' => $hidden->id, 'organization_id' => $this->org->id, 'title' => 'Secrète']);

        $this->actingAsUser($me);
        $titles = collect($this->getJson('/api/tasks')->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Mienne'));
        $this->assertFalse($titles->contains('Secrète'));

        $mine = $this->getJson('/api/tasks?assignee_id=me')->json('data');
        $this->assertCount(1, $mine);
        $this->assertFalse($mine[0]['can_edit']);
        $this->assertTrue($mine[0]['can_change_status']);
        $this->getJson("/api/tasks/{$secret->id}")->assertNotFound();
    }

    public function test_a_new_decision_supersedes_the_previous_one(): void
    {
        $project = $this->makeProject();
        $editor = $this->makeUser('member');
        $colleague = $this->makeUser('member');
        $this->addMember($project, $editor, 'editor');
        $this->addMember($project, $colleague, 'reader');
        $this->actingAsUser($editor);

        $first = $this->postJson("/api/projects/{$project->id}/decisions", [
            'title' => 'Hébergement', 'decision' => 'Hostinger', 'justification' => 'Prix', 'status' => 'validated', 'decided_at' => '2026-10-01',
        ])->assertCreated()->json('id');

        $second = $this->postJson("/api/projects/{$project->id}/decisions", [
            'title' => 'Hébergement (révisé)', 'decision' => 'VPS dédié', 'supersedes_id' => $first, 'participants' => [$colleague->id],
        ])->assertCreated()->assertJsonCount(1, 'participants')->json('id');

        $this->assertSame('superseded', Decision::find($first)->status);
        $this->assertSame('proposed', Decision::find($second)->status);
        $this->getJson("/api/projects/{$project->id}/decisions")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_general_decisions_are_reserved_to_staff(): void
    {
        $this->actingAsUser($this->makeUser('member'));
        $this->postJson('/api/decisions', ['title' => 'Règle', 'decision' => 'Télétravail le jeudi'])->assertForbidden();

        $this->actingAsUser($this->makeUser('project_manager'));
        $this->postJson('/api/decisions', ['title' => 'Règle', 'decision' => 'Télétravail le jeudi'])->assertCreated();
    }

    public function test_general_meeting_minutes_are_available_to_staff_and_readable_by_members(): void
    {
        $staff = $this->makeUser('project_manager');
        $member = $this->makeUser('member');
        $guest = $this->makeUser('guest');

        $this->actingAsUser($member);
        $this->getJson('/api/meeting-minutes')->assertOk();
        $this->postJson('/api/meeting-minutes', ['title' => 'Réunion', 'content' => 'Décision prise'])->assertForbidden();

        $this->actingAsUser($guest);
        $this->getJson('/api/meeting-minutes')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsUser($staff);
        $id = $this->postJson('/api/meeting-minutes', [
            'title' => 'Réunion générale',
            'content' => 'Compte rendu initial',
            'meeting_date' => '2026-09-10',
            'attendees' => [$member->id],
        ])->assertCreated()
            ->assertJsonPath('attendees.0', $member->id)
            ->json('id');

        $this->getJson('/api/meeting-minutes')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/meeting-minutes/{$id}")->assertOk()->assertJsonPath('content', 'Compte rendu initial');
        $this->putJson("/api/meeting-minutes/{$id}", ['title' => 'Réunion mise à jour'])->assertOk()
            ->assertJsonPath('title', 'Réunion mise à jour');
        $this->deleteJson("/api/meeting-minutes/{$id}")->assertNoContent();
        $this->assertSoftDeleted(MeetingMinute::class, ['id' => $id]);
    }

    public function test_guest_sees_only_project_decisions_and_meeting_minutes(): void
    {
        $visibleProject = $this->makeProject();
        $hiddenProject = $this->makeProject();
        $guest = $this->makeUser('guest');
        $creator = $this->makeUser('admin');
        $this->addMember($visibleProject, $guest, 'reader');
        $visibleDecision = Decision::create([
            'organization_id' => $this->org->id,
            'project_id' => $visibleProject->id,
            'created_by' => $creator->id,
            'title' => 'Décision visible',
            'decision' => 'Continuer',
        ]);
        $hiddenDecision = Decision::create([
            'organization_id' => $this->org->id,
            'project_id' => $hiddenProject->id,
            'created_by' => $creator->id,
            'title' => 'Décision masquée',
            'decision' => 'Confidentiel',
        ]);
        $generalMinute = MeetingMinute::create([
            'organization_id' => $this->org->id,
            'created_by' => $creator->id,
            'title' => 'Compte rendu général',
            'content' => 'Interne',
        ]);
        $projectMinute = MeetingMinute::create([
            'organization_id' => $this->org->id,
            'project_id' => $visibleProject->id,
            'created_by' => $creator->id,
            'title' => 'Compte rendu projet',
            'content' => 'Visible',
        ]);

        $this->actingAsUser($guest);
        $this->getJson('/api/decisions')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleDecision->id);
        $this->getJson('/api/meeting-minutes')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $projectMinute->id);
        $this->getJson("/api/decisions/{$hiddenDecision->id}")->assertNotFound();
        $this->getJson("/api/meeting-minutes/{$generalMinute->id}")->assertForbidden();
    }

    public function test_guest_notifications_are_limited_to_accessible_projects(): void
    {
        $visibleProject = $this->makeProject();
        $hiddenProject = $this->makeProject();
        $guest = $this->makeUser('guest');
        $this->addMember($visibleProject, $guest, 'reader');
        $guest->notify(new AppNotification(['type' => 'message.reply', 'title' => 'Visible', 'project_id' => $visibleProject->id]));
        $guest->notify(new AppNotification(['type' => 'message.reply', 'title' => 'Hidden', 'project_id' => $hiddenProject->id]));
        $guest->notify(new AppNotification(['type' => 'announcement', 'title' => 'General']));
        $visibleNotification = $guest->notifications()->where('data->title', 'Visible')->firstOrFail();
        $hiddenNotification = $guest->notifications()->where('data->title', 'Hidden')->firstOrFail();

        $this->actingAsUser($guest);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications.data')
            ->assertJsonPath('notifications.data.0.data.title', 'Visible');
        $this->postJson("/api/notifications/{$hiddenNotification->id}/read")->assertNotFound();
        $this->postJson("/api/notifications/{$visibleNotification->id}/read")->assertOk();
    }

    public function test_notifications_can_be_read(): void
    {
        $project = $this->makeProject();
        $user = $this->makeUser('member');
        $this->addMember($project, $user, 'reader');
        $this->actingAsUser($this->makeUser('admin'));
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'A', 'assignee_id' => $user->id])->assertCreated();
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'B', 'assignee_id' => $user->id])->assertCreated();

        $this->actingAsUser($user);
        $list = $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 2);
        $id = $list->json('notifications.data.0.id');

        $this->postJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 1);
        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications?unread=1')->assertJsonPath('unread_count', 0);
    }
}
