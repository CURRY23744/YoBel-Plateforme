<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, BelongsToOrganization, LogsActivity, SoftDeletes;

    public const FINAL_STATUSES = ['completed', 'abandoned', 'archived'];

    protected $guarded = ['id'];

    protected $casts = [
        'technologies' => 'array',
        'settings' => 'array',
        'start_date' => 'date:Y-m-d',
        'planned_end_date' => 'date:Y-m-d',
        'actual_end_date' => 'date:Y-m-d',
        'decision_date' => 'date:Y-m-d',
        'archived_at' => 'datetime',
        'is_archived' => 'boolean',
        'progress' => 'integer',
        'budget_amount' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'priority_score' => 'decimal:2',
    ];

    // ----- Relations -----

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['project_role', 'can_view', 'can_comment', 'can_upload', 'can_edit', 'can_delete', 'can_manage', 'joined_at', 'left_at']);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'project_tag');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('due_date');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ProjectEvaluation::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // ----- Scopes -----

    /** Projets que l'utilisateur a le droit de voir. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('projects.organization_id', $user->organization_id);

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'projects.id',
            ProjectMember::query()->select('project_id')
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->where('can_view', true)
        );
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('planned_end_date')
            ->whereDate('planned_end_date', '<', now()->toDateString())
            ->whereNotIn('status', self::FINAL_STATUSES);
    }

    // ----- Helpers -----

    public static function nextReference(int $organizationId): string
    {
        $yearMonth = now()->format('Y-m');
        $count = static::withoutGlobalScopes()->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('reference', 'like', "PRJ-{$yearMonth}-%")
            ->count();

        return sprintf('PRJ-%s-%04d', $yearMonth, $count + 1);
    }

    /** Recalcule le score de priorité (0-100) à partir des évaluations et pondérations. */
    public function recomputePriorityScore(): ?float
    {
        $rows = ProjectEvaluation::where('project_id', $this->id)
            ->selectRaw('criterion_id, avg(score) as avg_score')
            ->groupBy('criterion_id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $weights = EvaluationCriterion::whereIn('id', $rows->pluck('criterion_id'))->pluck('weight', 'id');
        $totalWeight = (float) $weights->sum();

        if ($totalWeight <= 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) $row->avg_score * (float) ($weights[$row->criterion_id] ?? 0);
        }

        $score = round(($sum / $totalWeight) * 10, 2);
        $this->forceFill(['priority_score' => $score])->saveQuietly();

        return $score;
    }
}
