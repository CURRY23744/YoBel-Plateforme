<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use LogsActivity;

    /** Jeux de droits prédéfinis pour simplifier l'attribution. */
    public const PRESETS = [
        'reader' => ['can_view' => true, 'can_comment' => false, 'can_upload' => false, 'can_edit' => false, 'can_delete' => false, 'can_manage' => false],
        'contributor' => ['can_view' => true, 'can_comment' => true, 'can_upload' => true, 'can_edit' => false, 'can_delete' => false, 'can_manage' => false],
        'editor' => ['can_view' => true, 'can_comment' => true, 'can_upload' => true, 'can_edit' => true, 'can_delete' => false, 'can_manage' => false],
        'manager' => ['can_view' => true, 'can_comment' => true, 'can_upload' => true, 'can_edit' => true, 'can_delete' => true, 'can_manage' => true],
    ];

    public const NO_RIGHTS = ['can_view' => false, 'can_comment' => false, 'can_upload' => false, 'can_edit' => false, 'can_delete' => false, 'can_manage' => false];

    protected $guarded = ['id'];

    protected $casts = [
        'can_view' => 'boolean', 'can_comment' => 'boolean', 'can_upload' => 'boolean',
        'can_edit' => 'boolean', 'can_delete' => 'boolean', 'can_manage' => 'boolean',
        'joined_at' => 'datetime', 'left_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Donne tous les droits à un utilisateur sur un projet (créateur, responsable). */
    public static function grantFull(Project $project, User $user, string $role = 'manager', ?int $addedBy = null): self
    {
        return static::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $user->id],
            self::PRESETS['manager'] + ['project_role' => $role, 'left_at' => null, 'joined_at' => now(), 'added_by' => $addedBy]
        );
    }
}
