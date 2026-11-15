<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Folder extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['is_restricted' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'folder_permissions')
            ->withPivot(['can_view', 'can_upload', 'can_edit', 'can_delete', 'granted_by'])
            ->withTimestamps();
    }

    /**
     * Identifiants des dossiers (du projet) que l'utilisateur ne doit pas voir :
     * dossier restreint, ou enfant d'un dossier restreint, sans permission explicite.
     *
     * @return array<int, int>
     */
    public static function deniedIdsFor(Project $project, User $user): array
    {
        if ($user->isAdmin() || $user->canOnProject($project, 'manage')) {
            return [];
        }

        $folders = static::where('project_id', $project->id)->get(['id', 'parent_id', 'is_restricted']);
        if ($folders->isEmpty()) {
            return [];
        }

        $byId = $folders->keyBy('id');
        $allowed = DB::table('folder_permissions')
            ->where('user_id', $user->id)
            ->where('can_view', true)
            ->whereIn('folder_id', $folders->pluck('id'))
            ->pluck('folder_id')
            ->all();

        $denied = [];
        foreach ($folders as $folder) {
            $cursor = $folder;
            while ($cursor) {
                if ($cursor->is_restricted && ! in_array($cursor->id, $allowed, true)) {
                    $denied[] = $folder->id;
                    break;
                }
                $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
            }
        }

        return $denied;
    }

    /** Abilities : view | upload | edit | delete */
    public function accessibleBy(User $user, string $ability = 'view'): bool
    {
        $project = $this->project;

        if (! $project || ! $user->canOnProject($project, 'view')) {
            return false;
        }

        if ($user->isAdmin() || $user->canOnProject($project, 'manage')) {
            return true;
        }

        if (in_array($this->id, static::deniedIdsFor($project, $user), true)) {
            return false;
        }

        if ($ability === 'view') {
            return true;
        }

        if ($this->is_restricted) {
            $row = DB::table('folder_permissions')->where('folder_id', $this->id)->where('user_id', $user->id)->first();

            return $row && (bool) $row->{'can_'.$ability};
        }

        return $user->canOnProject($project, $ability);
    }
}
