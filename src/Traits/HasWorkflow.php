<?php

namespace Adichan\WorkflowEngine\Traits;

use Adichan\WorkflowEngine\States\StateMachine;
use Adichan\WorkflowEngine\Models\WorkflowApproval;

trait HasWorkflow
{
    private ?StateMachine $workflow = null;
    
    protected string $stateColumn = 'status';

    public function workflow(): StateMachine
    {
        if (!$this->workflow) {
            $workflowName = $this->getWorkflowName();
            
            // Get config from the correct location
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
        // Check if property exists and is set
        if (property_exists($this, 'workflowName') && !empty($this->workflowName)) {
            return $this->workflowName;
        }
        
        // Check if we have a attribute set
        if (method_exists($this, 'getAttribute') && $this->getAttribute('workflowName')) {
            return $this->getAttribute('workflowName');
        }
        
        // Default to class basename
        return class_basename($this);
    }

    /**
     * Set the workflow name for this model
     */
    public function setWorkflowName(string $name): self
    {
        // Set as property
        $this->workflowName = $name;
        
        // Also set in attributes for Eloquent models
        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute('workflowName', $name);
        }
        
        // Reset workflow instance to force reload with new name
        $this->workflow = null;
        
        return $this;
    }

    /**
     * Alias for setWorkflowName - allows fluent interface
     */
    public function withWorkflow(string $name): self
    {
        return $this->setWorkflowName($name);
    }

    public function getStateColumn(): string
    {
        if (property_exists($this, 'stateColumn')) {
            return $this->stateColumn;
        }
        
        return 'status';
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

    // Multi-approval methods
    public function pendingApprovals(): array
    {
        return $this->workflow()->getPendingApprovals($this);
    }

    public function approveLevel(string $levelName, array $context = [])
    {
        $transition = $this->determineTransitionForLevel($levelName);
        
        return $this->workflow()->applyWithApproval(
            $this,
            $transition,
            $levelName,
            $context
        );
    }

    public function canApproveLevel(string $levelName): bool
    {
        return $this->workflow()->canUserApproveLevel(
            auth()->user(),
            $levelName,
            $this
        );
    }

    public function isApprovalCompleted(string $levelName): bool
    {
        return $this->workflow()->isApprovalCompleted($this, $levelName);
    }

    public function getApprovalHistory(): array
    {
        return WorkflowApproval::whereHas('transition', function ($query) {
                $query->where('model_type', get_class($this))
                      ->where('model_id', $this->id);
            })
            ->with('approver')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    protected function determineTransitionForLevel(string $levelName): ?string
    {
        $definition = $this->workflow()->getDefinition();
        $config = $definition['approval_levels'][$levelName] ?? null;
        
        // If transition is explicitly defined, use it
        if ($config && isset($config['transition'])) {
            return $config['transition'];
        }
        
        // Try to infer from state configuration
        $currentState = $this->workflow()->getState($this);
        $transitions = $this->workflow()->getAvailableTransitions($this);
        
        // Return first available transition if not specified
        return !empty($transitions) ? array_key_first($transitions) : null;
    }
}
