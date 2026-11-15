<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Suivi du temps : table prête, API prévue en version 1.1+.
class TimeEntry extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['entry_date' => 'date:Y-m-d', 'is_billable' => 'boolean', 'hourly_rate' => 'decimal:2'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
