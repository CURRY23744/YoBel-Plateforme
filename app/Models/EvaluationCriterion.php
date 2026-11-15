<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCriterion extends Model
{
    use BelongsToOrganization;

    protected $table = 'evaluation_criteria';

    protected $guarded = ['id'];

    protected $casts = ['weight' => 'decimal:2', 'is_active' => 'boolean'];

    public function evaluations(): HasMany
    {
        return $this->hasMany(ProjectEvaluation::class, 'criterion_id');
    }
}
