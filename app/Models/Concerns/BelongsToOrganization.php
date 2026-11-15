<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Cloisonnement par entreprise : toute requête est automatiquement limitée
 * à l'organisation de l'utilisateur connecté, et organization_id est renseigné à la création.
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (Auth::hasUser() && Auth::user()->organization_id) {
                $builder->where($builder->getModel()->getTable().'.organization_id', Auth::user()->organization_id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->organization_id) && Auth::hasUser()) {
                $model->organization_id = Auth::user()->organization_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
