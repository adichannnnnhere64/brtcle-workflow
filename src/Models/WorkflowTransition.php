<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Adichan\WorkflowEngine\Contracts\WorkflowTransitionInterface;

class WorkflowTransition extends Model implements WorkflowTransitionInterface
{
    protected $fillable = [
        'workflow_type',
        'model_type',
        'model_id',
        'transition',
        'from_state',
        'to_state',
        'context',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'context' => 'array',
        'metadata' => 'array',
        'performed_at' => 'datetime',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function performer()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'performed_by');
    }

    // Interface implementation
    public function getFromState(): string
    {
        return $this->from_state;
    }

    public function getToState(): string
    {
        return $this->to_state;
    }

    public function getTransitionName(): string
    {
        return $this->transition;
    }

    public function getContext(): array
    {
        return $this->context ?? [];
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getPerformedBy(): ?Model
    {
        return $this->performer;
    }

    public function getPerformedAt(): \DateTimeInterface
    {
        return $this->performed_at;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }
}
