<?php

namespace Tests\Feature\Api;

use App\Models\Discussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class DiscussionMessageApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_member_with_comment_right_opens_a_thread_with_a_first_message(): void
    {
        $project = $this->makeProject();
        $member = $this->makeUser('member');
        $this->addMember($project, $member, 'contributor');
        $this->actingAsUser($member);

        $id = $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Choix hébergeur', 'body' => 'On part sur quoi ?'])
            ->assertCreated()->json('id');

        $this->assertSame(1, Discussion::find($id)->messages_count);
        $this->getJson("/api/discussions/{$id}/messages")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/projects/{$project->id}/discussions")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reader_cannot_write(): void
    {
        $project = $this->makeProject();
        $reader = $this->makeUser('member');
        $this->addMember($project, $reader, 'reader');
        Discussion::create([
            'project_id' => $project->id,
            'organization_id' => $this->org->id,
            'title' => 'Discussion visible en lecture',
            'created_by' => $reader->id,
        ]);
        $this->actingAsUser($reader);

        $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Test'])->assertForbidden();
        $this->getJson("/api/projects/{$project->id}/discussions")->assertOk();
    }

    public function test_mentions_notify_only_people_who_can_see_the_project(): void
    {
        $project = $this->makeProject();
        $author = $this->makeUser('member');
        $teammate = $this->makeUser('member');
        $outsider = $this->makeUser('member');
        $this->addMember($project, $author, 'contributor');
        $this->addMember($project, $teammate, 'reader');
        $this->actingAsUser($author);

        $discussionId = $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Point'])->json('id');

        $this->postJson("/api/discussions/{$discussionId}/messages", [
            'body' => '@équipe merci de relire', 'mentions' => [$teammate->id, $outsider->id],
        ])->assertCreated()->assertJsonCount(1, 'mentions');

        $this->assertSame(1, $teammate->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
    }

    public function test_project_messages_notify_all_visible_members_and_support_incremental_loading(): void
    {
        $project = $this->makeProject();
        $author = $this->makeUser('member');
        $reader = $this->makeUser('member');
        $outsider = $this->makeUser('member');
        $this->addMember($project, $author, 'contributor');
        $this->addMember($project, $reader, 'reader');
        $this->actingAsUser($author);

        $discussionId = $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Avancement'])->json('id');
        $response = $this->postJson("/api/discussions/{$discussionId}/messages", ['body' => 'Point d’avancement'])
            ->assertCreated();
        $this->assertFalse((bool) $response->json('notification_warning'), json_encode($response->json()));
        $messageId = $response->json('id');

        $this->assertSame(1, $reader->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $this->assertSame(
            [$messageId],
            collect($this->getJson("/api/discussions/{$discussionId}/messages?after_id=0")->json('data'))->pluck('id')->all()
        );
        $this->assertSame(
            [],
            $this->getJson("/api/discussions/{$discussionId}/messages?after_id={$messageId}")->json('data')
        );
    }

    public function test_locked_discussion_rejects_messages_from_simple_members(): void
    {
        $project = $this->makeProject();
        $manager = $this->makeUser('project_manager');
        $member = $this->makeUser('member');
        $this->addMember($project, $manager, 'manager');
        $this->addMember($project, $member, 'contributor');

        $this->actingAsUser($manager);
        $id = $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Clos'])->json('id');
        $this->putJson("/api/discussions/{$id}", ['is_locked' => true])->assertOk();

        $this->actingAsUser($member);
        $this->postJson("/api/discussions/{$id}/messages", ['body' => 'Hello'])->assertStatus(423);

        $this->actingAsUser($manager);
        $this->postJson("/api/discussions/{$id}/messages", ['body' => 'Hello'])->assertCreated();
    }

    public function test_message_edit_rules(): void
    {
        $project = $this->makeProject();
        $author = $this->makeUser('member');
        $other = $this->makeUser('member');
        $this->addMember($project, $author, 'contributor');
        $this->addMember($project, $other, 'contributor');

        $this->actingAsUser($author);
        $discussionId = $this->postJson("/api/projects/{$project->id}/discussions", ['title' => 'Sujet'])->json('id');
        $messageId = $this->postJson("/api/discussions/{$discussionId}/messages", ['body' => 'Version 1'])->json('id');

        $this->putJson("/api/messages/{$messageId}", ['body' => 'Version 2'])->assertOk()->assertJsonPath('body', 'Version 2');

        $this->actingAsUser($other);
        $this->putJson("/api/messages/{$messageId}", ['body' => 'Piraté'])->assertForbidden();
        $this->deleteJson("/api/messages/{$messageId}")->assertForbidden();

        $this->actingAsUser($author);
        $this->travel(config('platform.message_edit_window_minutes') + 5)->minutes();
        $this->putJson("/api/messages/{$messageId}", ['body' => 'Trop tard'])->assertForbidden();
        $this->travelBack();

        $this->deleteJson("/api/messages/{$messageId}")->assertNoContent();
        $this->assertSame(0, Discussion::find($discussionId)->messages_count);
    }

    public function test_general_space_is_open_to_members_but_not_guests(): void
    {
        $this->actingAsUser($this->makeUser('member'));
        $id = $this->postJson('/api/discussions', ['title' => 'Machine à café', 'body' => 'Qui a acheté du café ?'])->assertCreated()->json('id');
        $this->getJson('/api/discussions')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/discussions/{$id}/messages")->assertOk();

        $this->actingAsUser($this->makeUser('guest'));
        $this->getJson('/api/discussions')->assertForbidden();
        $this->getJson("/api/discussions/{$id}/messages")->assertForbidden();
    }
}
