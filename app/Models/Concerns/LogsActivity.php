<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Journal d'activité automatique (créations, modifications, suppressions).
 */
trait LogsActivity
{
    protected static array $activityIgnored = [
        'updated_at', 'remember_token', 'last_login_at', 'last_login_ip',
        'failed_login_attempts', 'locked_until', 'downloads_count',
    ];

    protected static function bootLogsActivity(): void
    {
        static::created(fn (Model $m) => static::writeActivity($m, 'created'));
        static::updated(fn (Model $m) => static::writeActivity($m, 'updated'));
        static::deleted(fn (Model $m) => static::writeActivity($m, 'deleted'));
    }

    protected static function writeActivity(Model $model, string $event): void
    {
        $properties = null;

        if ($event === 'updated') {
            $changes = array_diff_key($model->getChanges(), array_flip(static::$activityIgnored));
            if (empty($changes)) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            $properties = ['old' => static::maskSecrets($old), 'new' => static::maskSecrets($changes)];
        } elseif ($event === 'created') {
            $properties = ['new' => static::maskSecrets(array_diff_key($model->getAttributes(), array_flip(static::$activityIgnored)))];
        }

        $user = Auth::user();

        ActivityLog::create([
            'organization_id' => $model->organization_id ?? ($user->organization_id ?? null),
            'user_id' => $user->id ?? null,
            'project_id' => $model instanceof Project ? $model->id : ($model->project_id ?? null),
            'action' => $event,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'description' => class_basename($model).' #'.$model->getKey().' '.$event,
            'properties' => $properties,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    protected static function maskSecrets(array $values): array
    {
        foreach (['password', 'two_factor_secret', 'two_factor_recovery_codes'] as $secret) {
            if (array_key_exists($secret, $values)) {
                $values[$secret] = '***';
            }
        }

        return $values;
    }
}
