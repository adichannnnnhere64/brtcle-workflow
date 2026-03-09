<?php

namespace Adichan\WorkflowEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowSignoff extends Model
{
    protected $fillable = [
        'workflow_type',
        'model_type',
        'model_id',
        'signoff_type',
        'user_id',
        'signed_at',
        'comments',
        'metadata',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who signed off.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /**
     * Get the model that was signed off on.
     */
    public function model()
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * Check if a user has signed off on a model.
     */
    public static function hasSignedOff(
        Model $model,
        int $userId,
        string $signoffType,
        string $workflowType
    ): bool {
        return static::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('user_id', $userId)
            ->where('signoff_type', $signoffType)
            ->where('workflow_type', $workflowType)
            ->exists();
    }

    /**
     * Get all signoffs for a model of a specific type.
     */
    public static function getSignoffs(
        Model $model,
        string $signoffType,
        string $workflowType
    ) {
        return static::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('signoff_type', $signoffType)
            ->where('workflow_type', $workflowType)
            ->with('user')
            ->get();
    }

    /**
     * Get signoff user IDs for a model.
     */
    public static function getSignoffUserIds(
        Model $model,
        string $signoffType,
        string $workflowType
    ): array {
        return static::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('signoff_type', $signoffType)
            ->where('workflow_type', $workflowType)
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Record a signoff.
     */
    public static function recordSignoff(
        Model $model,
        int $userId,
        string $signoffType,
        string $workflowType,
        ?string $comments = null,
        array $metadata = []
    ): self {
        return static::create([
            'workflow_type' => $workflowType,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'signoff_type' => $signoffType,
            'user_id' => $userId,
            'signed_at' => now(),
            'comments' => $comments,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Remove a signoff.
     */
    public static function removeSignoff(
        Model $model,
        int $userId,
        string $signoffType,
        string $workflowType
    ): bool {
        return static::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('user_id', $userId)
            ->where('signoff_type', $signoffType)
            ->where('workflow_type', $workflowType)
            ->delete() > 0;
    }

    /**
     * Clear all signoffs for a model of a specific type.
     */
    public static function clearSignoffs(
        Model $model,
        string $signoffType,
        string $workflowType
    ): int {
        return static::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('signoff_type', $signoffType)
            ->where('workflow_type', $workflowType)
            ->delete();
    }
}
