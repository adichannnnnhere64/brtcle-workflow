<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine;

use Illuminate\Database\Eloquent\Model;
use Adichan\WorkflowEngine\Contracts\WorkflowInterface;
use Adichan\WorkflowEngine\States\StateMachine;

/**
 * Base workflow class for concrete workflow implementations
 */
abstract class Workflow implements WorkflowInterface
{
    protected StateMachine $stateMachine;

    public function __construct()
    {
        $this->stateMachine = new StateMachine(
            $this->getName(),
            $this->getDefinition()
        );
    }

    /**
     * Get the workflow name
     */
    abstract public function getName(): string;

    /**
     * Get the workflow definition
     */
    abstract public function getDefinition(): array;

    /**
     * Get all available transitions for the current state
     */
    public function getAvailableTransitions(Model $model): array
    {
        return $this->stateMachine->getAvailableTransitions($model);
    }

    /**
     * Apply a transition to the model
     */
    public function apply(
        Model $model,
        string $transition,
        array $context = []
    ): Contracts\WorkflowTransitionInterface {
        return $this->stateMachine->apply($model, $transition, $context);
    }

    /**
     * Check if a transition can be applied
     */
    public function can(
        Model $model,
        string $transition,
        array $context = []
    ): bool {
        return $this->stateMachine->can($model, $transition, $context);
    }

    /**
     * Get the current state
     */
    public function getState(Model $model): string
    {
        return $this->stateMachine->getState($model);
    }

    /**
     * Get the state history
     */
    public function getHistory(Model $model): array
    {
        return $this->stateMachine->getHistory($model);
    }
}
