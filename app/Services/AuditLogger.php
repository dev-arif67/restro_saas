<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Log an action to the audit log.
     *
     * @param string $action The action performed (created, updated, deleted, login, impersonated, etc.)
     * @param Model|null $subject The model being acted upon
     * @param array|null $oldValues Previous values (for updates)
     * @param array|null $newValues New values (for creates/updates)
     * @return AuditLog
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log a model creation.
     */
    public static function logCreated(Model $model): AuditLog
    {
        return self::log('created', $model, null, $model->toArray());
    }

    /**
     * Log a model update.
     */
    public static function logUpdated(Model $model, array $originalValues): AuditLog
    {
        $changedValues = array_intersect_key($model->toArray(), $model->getDirty());
        $originalChanged = array_intersect_key($originalValues, $model->getDirty());

        return self::log('updated', $model, $originalChanged, $changedValues);
    }

    /**
     * Log a model deletion.
     */
    public static function logDeleted(Model $model): AuditLog
    {
        return self::log('deleted', $model, $model->toArray(), null);
    }

    /**
     * Log a user login.
     */
    public static function logLogin(): AuditLog
    {
        $user = Auth::user();
        return self::log('login', $user);
    }

    /**
     * Log an impersonation action.
     */
    public static function logImpersonation(Model $targetUser): AuditLog
    {
        return self::log('impersonated', $targetUser, null, [
            'impersonated_user_id' => $targetUser->id,
            'impersonated_tenant_id' => $targetUser->tenant_id,
        ]);
    }

    /**
     * Log a custom action.
     */
    public static function logAction(string $action, ?Model $subject = null, ?array $data = null): AuditLog
    {
        return self::log($action, $subject, null, $data);
    }
}
