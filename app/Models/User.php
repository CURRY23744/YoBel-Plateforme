<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    protected $fillable = [
        'organization_id', 'first_name', 'last_name', 'email', 'password', 'role',
        'phone', 'job_title', 'department', 'employee_number', 'avatar_path', 'bio',
        'locale', 'timezone', 'is_active', 'must_change_password', 'password_changed_at',
        'last_login_at', 'last_login_ip', 'failed_login_attempts', 'locked_until',
        'notification_preferences', 'deactivated_at', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $appends = ['name'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => Role::class,
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    // ----- Relations -----

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['project_role', 'can_view', 'can_comment', 'can_upload', 'can_edit', 'can_delete', 'can_manage', 'joined_at', 'left_at'])
            ->wherePivotNull('left_at');
    }

    public function favoriteProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_favorites')->withTimestamps();
    }

    // ----- Rôles -----

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::ProjectManager;
    }

    /** Administrateur ou chef de projet. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function isGuest(): bool
    {
        return $this->role === Role::Guest;
    }

    public function canCreateProjects(): bool
    {
        return $this->isStaff();
    }

    // ----- Droits par projet -----

    public function membershipFor(Project $project): ?ProjectMember
    {
        return ProjectMember::where('project_id', $project->id)
            ->where('user_id', $this->id)
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Abilities : view | comment | upload | edit | delete | manage
     */
    public function canOnProject(Project $project, string $ability = 'view'): bool
    {
        if ($project->organization_id !== $this->organization_id) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        // Un invité reste en lecture seule, quels que soient les droits enregistrés.
        if ($this->isGuest() && $ability !== 'view') {
            return false;
        }

        $membership = $this->membershipFor($project);

        return $membership ? (bool) $membership->{'can_'.$ability} : false;
    }

    /** @return array<string, bool> */
    public function abilitiesOn(Project $project): array
    {
        $abilities = [];
        foreach (['view', 'comment', 'upload', 'edit', 'delete', 'manage'] as $ability) {
            $abilities[$ability] = $this->canOnProject($project, $ability);
        }

        return $abilities;
    }
}
