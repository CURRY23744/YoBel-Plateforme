<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiInteraction extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected $casts = ['context' => 'array', 'cost' => 'decimal:6'];

    public function suggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class, 'interaction_id');
    }
}
