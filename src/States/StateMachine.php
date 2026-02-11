<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\States;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Adichan\WorkflowEngine\Contracts\WorkflowInterface;
use Adichan\WorkflowEngine\Contracts\WorkflowTransitionInterface;
use Adichan\WorkflowEngine\Enums\WorkflowGuard;
use Adichan\WorkflowEngine\Models\WorkflowTransition;
use Adichan\WorkflowEngine\Events\WorkflowTransitioned;

class StateMachine implements WorkflowInterface
{
    private array $states = [];

    public function __construct(
        protected string $workflowName,
        protected array $definition
    ) {
        $this->initializeStates();
    }

    private function initializeStates(): void
    {
        // FIX: Check if states key exists
        if (!isset($this->definition['states'])) {
            throw new \InvalidArgumentException("Workflow definition must contain 'states' key");
        }

        foreach ($this->definition['states'] as $stateConfig) {
            $state = new class(
                $stateConfig['value'],
                $stateConfig['label'],
                $stateConfig['is_initial'] ?? false,
                $stateConfig['is_final'] ?? false
            ) extends AbstractWorkflowState {};

            $this->states[$stateConfig['value']] = $state;
        }

        // FIX: Check if transitions key exists
        if (!isset($this->definition['transitions'])) {
            throw new \InvalidArgumentException("Workflow definition must contain 'transitions' key");
        }

        foreach ($this->definition['transitions'] as $transition) {
            $fromState = $this->states[$transition['from']] ?? null;
            
            if (!$fromState) {
                throw new \InvalidArgumentException(
                    "Transition '{$transition['name']}' references undefined state: {$transition['from']}"
                );
            }
            
            $fromState->addTransition(
                $transition['to'],
                $transition['name'],
                WorkflowGuard::from($transition['guard']),
                $transition['validator'] ?? null
            );
        }
    }

    public function getAvailableTransitions(Model $model): array
    {
        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentStateValue = $model->{$stateColumn} ?? 'draft';
        $currentState = $this->states[$currentStateValue] ?? null;

        if (!$currentState) {
            return [];
        }

        $available = [];

        foreach ($currentState->allowedTransitions() as $transitionName => $config) {
            if ($this->can($model, $transitionName)) {
                $available[$transitionName] = [
                    'to' => $config['to'],
                    'guard' => $config['guard'],
                ];
            }
        }

        return $available;
    }

    public function apply(
        Model $model,
        string $transition,
        array $context = []
    ): WorkflowTransitionInterface {
        if (!$this->can($model, $transition, $context)) {
            throw new \DomainException(
                "Transition '{$transition}' is not allowed from current state"
            );
        }

        $stateColumn = $this->definition['state_column'] ?? 'status';
        $currentState = $model->{$stateColumn};
        $state = $this->states[$currentState] ?? null;

        if (!$state) {
            throw new \DomainException("Invalid current state: {$currentState}");
        }

        $config = $state->allowedTransitions()[$transition] ?? null;

        if (!$config) {
            throw new \DomainException("Transition '{$transition}' not defined");
        }

        // FIX: Execute BEFORE hooks BEFORE checking if transition can be applied
        $this->executeBeforeHooks($transition, $model, $currentState, $config['to'], $context);

        return DB::transaction(function () use ($model, $transition, $config, $currentState, $context, $stateColumn) {
            $previousState = $currentState;
            $newState = $config['to'];

            // Update model state
            $model->{$stateColumn} = $newState;
            $model->save();

            // Create transition record
            $transitionRecord = WorkflowTransition::create([
                'workflow_type' => $this->workflowName,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'transition' => $transition,
                'from_state' => $previousState,
                'to_state' => $newState,
                'context' => $context,
                'performed_by' => auth()->id(),
                'metadata' => $context['metadata'] ?? [],
                'performed_at' => now(),
            ]);

            // Execute AFTER hooks
            $this->executeAfterHooks($transition, $model, $previousState, $newState, $context);

            // Dispatch event
            event(new WorkflowTransitioned(
                $model,
                $transition,
                $previousState,
                $newState,
                $context
            ));

            return $transitionRecord;
        });
    }

	// src/States/StateMachine.php (update the can() method)
public function can(
    Model $model,
    string $transition,
    array $context = []
): bool {
    $stateColumn = $this->definition['state_column'] ?? 'status';
    $currentState = $model->{$stateColumn};
    $state = $this->states[$currentState] ?? null;

    if (!$state) {
        return false;
    }

    $config = $state->allowedTransitions()[$transition] ?? null;

    if (!$config) {
        return false;
    }

    // Check guard
    $guardValue = $config['guard'] instanceof WorkflowGuard 
        ? $config['guard']->value 
        : $config['guard'];
        
    if (!Gate::allows($guardValue, $model)) {
        return false;
    }

    // Check validator
    if (isset($config['validator']) && is_callable($config['validator'])) {
        try {
            if (!$config['validator']($context)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    // Check custom conditions from definition
    if (isset($this->definition['conditions'][$transition])) {
        $condition = $this->definition['conditions'][$transition];
        if (is_callable($condition)) {
            try {
                if (!$condition($model, $context)) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    return true;
}


    public function getState(Model $model): string
    {
        $stateColumn = $this->definition['state_column'] ?? 'status';
        return $model->{$stateColumn};
    }

    public function getHistory(Model $model): array
    {
        return WorkflowTransition::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('workflow_type', $this->workflowName)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    private function executeBeforeHooks(
        string $transition,
        Model $model,
        string $from,
        string $to,
        array $context
    ): void {
        // Execute before hooks
        if (isset($this->definition['hooks']['before'][$transition])) {
            $this->definition['hooks']['before'][$transition]($model, $from, $to, $context);
        }
    }

    private function executeAfterHooks(
        string $transition,
        Model $model,
        string $from,
        string $to,
        array $context
    ): void {
        // Execute after hooks
        if (isset($this->definition['hooks']['after'][$transition])) {
            $this->definition['hooks']['after'][$transition]($model, $from, $to, $context);
        }
    }
}
