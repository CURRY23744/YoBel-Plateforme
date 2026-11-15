<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFile extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'project_files';

    protected $guarded = ['id'];

    protected $hidden = ['path', 'disk'];

    protected $casts = ['is_current' => 'boolean', 'size' => 'integer', 'version_number' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function folderAllows(User $user, string $ability = 'view'): bool
    {
        return ! $this->folder || $this->folder->accessibleBy($user, $ability);
    }
}
