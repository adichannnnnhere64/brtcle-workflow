<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'definition',
        'version',
        'is_active',
    ];

    protected $casts = [
        'definition' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function transitions()
    {
        return $this->hasMany(WorkflowTransition::class, 'workflow_type', 'name');
    }
}
