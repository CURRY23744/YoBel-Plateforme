<?php

namespace Tests\Feature\Api;

use App\Models\Folder;
use App\Models\ProjectFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FileApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name = 'cahier.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    public function test_contributor_uploads_and_reader_downloads(): void
    {
        $project = $this->makeProject();
        $contributor = $this->makeUser('member');
        $reader = $this->makeUser('member');
        $this->addMember($project, $contributor, 'contributor');
        $this->addMember($project, $reader, 'reader');

        $this->actingAsUser($contributor);
        $response = $this->postJson("/api/projects/{$project->id}/files", ['file' => $this->pdf(), 'category' => 'specification', 'description' => 'V1'])
            ->assertCreated()
            ->assertJsonPath('category', 'specification')
            ->assertJsonMissingPath('path');

        $file = ProjectFile::find($response->json('id'));
        $this->assertTrue(Storage::disk('local')->exists($file->path));
        $this->assertSame(64, strlen($file->checksum));

        $this->actingAsUser($reader);
        $this->getJson("/api/projects/{$project->id}/files")->assertOk()->assertJsonCount(1, 'data');
        $this->get("/api/files/{$file->id}/download")->assertOk();
        $this->assertSame(1, $file->fresh()->downloads_count);
        $this->getJson("/api/files/{$file->id}/preview")->assertOk();
    }

    public function test_reader_cannot_upload_nor_delete(): void
    {
        $project = $this->makeProject();
        $reader = $this->makeUser('member');
        $this->addMember($project, $reader, 'reader');
        $this->actingAsUser($reader);

        $this->postJson("/api/projects/{$project->id}/files", ['file' => $this->pdf()])->assertForbidden();

        $file = ProjectFile::create(['project_id' => $project->id, 'name' => 'x', 'original_name' => 'x.pdf', 'path' => 'x', 'disk' => 'local']);
        $this->deleteJson("/api/files/{$file->id}")->assertForbidden();
    }

    public function test_forbidden_extensions_are_rejected(): void
    {
        $project = $this->makeProject();
        $this->actingAsUser($this->makeUser('admin'));

        $this->postJson("/api/projects/{$project->id}/files", ['file' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_restricted_folder_hides_its_content_until_access_is_granted(): void
    {
        $project = $this->makeProject();
        $manager = $this->makeUser('project_manager');
        $reader = $this->makeUser('member');
        $this->addMember($project, $manager, 'manager');
        $this->addMember($project, $reader, 'reader');

        $this->actingAsUser($manager);
        $folderId = $this->postJson("/api/projects/{$project->id}/folders", ['name' => 'Contrat', 'is_restricted' => true])->assertCreated()->json('id');
        $this->postJson("/api/projects/{$project->id}/files", ['file' => $this->pdf('contrat.pdf'), 'folder_id' => $folderId])->assertCreated();
        $this->postJson("/api/projects/{$project->id}/files", ['file' => $this->pdf('public.pdf')])->assertCreated();
        $secret = ProjectFile::where('folder_id', $folderId)->first();

        // Le lecteur ne voit ni le dossier ni le fichier
        $this->actingAsUser($reader);
        $this->assertCount(0, $this->getJson("/api/projects/{$project->id}/folders")->json());
        $this->assertCount(1, $this->getJson("/api/projects/{$project->id}/files")->json('data'));
        $this->get("/api/files/{$secret->id}/download")->assertNotFound();
        $this->assertEmpty($this->getJson('/api/search?q=contrat')->json('files'));

        // Le gestionnaire lui donne accès
        $this->actingAsUser($manager);
        $this->putJson("/api/folders/{$folderId}/permissions", [
            'is_restricted' => true, 'permissions' => [['user_id' => $reader->id, 'can_view' => true]],
        ])->assertOk();

        $this->actingAsUser($reader);
        $visibleFolders = $this->getJson("/api/projects/{$project->id}/folders")->assertOk()->json();
        $this->assertCount(1, $visibleFolders);
        $this->assertTrue($visibleFolders[0]['my_permissions']['view']);
        $this->assertFalse($visibleFolders[0]['my_permissions']['upload']);
        $this->assertFalse($visibleFolders[0]['my_permissions']['edit']);
        $this->assertCount(2, $this->getJson("/api/projects/{$project->id}/files")->json('data'));
        $this->get("/api/files/{$secret->id}/download")->assertOk();
    }

    public function test_permissions_only_for_active_members(): void
    {
        $project = $this->makeProject();
        $folder = Folder::create(['project_id' => $project->id, 'name' => 'Privé']);
        $stranger = $this->makeUser('member');

        $this->actingAsUser($this->makeUser('admin'));
        $this->putJson("/api/folders/{$folder->id}/permissions", [
            'is_restricted' => true, 'permissions' => [['user_id' => $stranger->id]],
        ])->assertStatus(422);
    }

    public function test_new_version_keeps_history(): void
    {
        $project = $this->makeProject();
        $this->actingAsUser($this->makeUser('admin'));

        $id = $this->postJson("/api/projects/{$project->id}/files", ['file' => $this->pdf('cdc.pdf'), 'category' => 'specification'])->json('id');

        $v2 = $this->postJson("/api/files/{$id}/versions", ['file' => $this->pdf('cdc-v2.pdf')])->assertCreated();
        $this->assertSame(2, $v2->json('version_number'));

        $this->assertFalse(ProjectFile::find($id)->is_current);
        $this->assertCount(1, $this->getJson("/api/projects/{$project->id}/files")->json('data'));
        $this->assertCount(2, $this->getJson("/api/files/{$id}/versions")->json());
    }

    public function test_non_empty_folder_cannot_be_deleted(): void
    {
        $project = $this->makeProject();
        $this->actingAsUser($this->makeUser('admin'));
        $folder = Folder::create(['project_id' => $project->id, 'name' => 'Docs']);
        ProjectFile::create(['project_id' => $project->id, 'folder_id' => $folder->id, 'name' => 'x', 'original_name' => 'x.pdf', 'path' => 'x', 'disk' => 'local']);

        $this->deleteJson("/api/folders/{$folder->id}")->assertStatus(409);
    }
}
