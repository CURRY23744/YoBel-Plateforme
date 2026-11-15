<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
