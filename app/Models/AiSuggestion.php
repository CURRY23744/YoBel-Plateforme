<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestion extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'validated_at' => 'datetime', 'expires_at' => 'datetime'];

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(AiInteraction::class, 'interaction_id');
    }
}
