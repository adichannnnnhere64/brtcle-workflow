<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Listeners;

use Adichan\WorkflowEngine\Events\WorkflowTransitioned;
use Illuminate\Support\Facades\Log;

class LogWorkflowTransition
{
    public function handle(WorkflowTransitioned $event): void
    {
        Log::info('Workflow transition', [
            'model' => get_class($event->model),
            'model_id' => $event->model->id,
            'transition' => $event->transition,
            'from_state' => $event->fromState,
            'to_state' => $event->toState,
            'performed_by' => auth()->id(),
            'context' => $event->context,
        ]);
    }
}
