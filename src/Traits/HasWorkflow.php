<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Traits;

use Adichan\WorkflowEngine\States\StateMachine;

trait HasWorkflow
{
    private ?StateMachine $workflow = null;

    public function workflow(): StateMachine
    {
        if (!$this->workflow) {
            $workflowName = $this->getWorkflowName();
            
            // FIX: Get config from the correct location
            $workflows = config('workflow.workflows', []);
            $definition = $workflows[$workflowName] ?? null;

            if (!$definition) {
                // Fallback to old config path for backward compatibility
                $definition = config("workflows.{$workflowName}");
                
                if (!$definition) {
                    throw new \RuntimeException("Workflow '{$workflowName}' not defined. Check your workflow configuration.");
                }
            }

            $this->workflow = new StateMachine($workflowName, $definition);
        }

        return $this->workflow;
    }

    public function getWorkflowName(): string
    {
        return property_exists($this, 'workflowName')
            ? $this->workflowName
            : class_basename($this);
    }

    public function getStateColumn(): string
    {
        return property_exists($this, 'stateColumn')
            ? $this->stateColumn
            : 'state';
    }

    public function availableTransitions(): array
    {
        return $this->workflow()->getAvailableTransitions($this);
    }

    public function canTransition(string $transition, array $context = []): bool
    {
        return $this->workflow()->can($this, $transition, $context);
    }

    public function transition(string $transition, array $context = [])
    {
        return $this->workflow()->apply($this, $transition, $context);
    }

    public function transitionHistory()
    {
        return $this->workflow()->getHistory($this);
    }
}
