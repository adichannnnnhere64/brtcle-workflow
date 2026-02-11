<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Contracts;

use Illuminate\Database\Eloquent\Model;

interface WorkflowInterface
{
    /**
     * Get all available transitions for the current state
     */
    public function getAvailableTransitions(Model $model): array;

    /**
     * Apply a transition to the model
     */
    public function apply(
        Model $model,
        string $transition,
        array $context = []
    ): WorkflowTransitionInterface;

    /**
     * Check if a transition can be applied
     */
    public function can(
        Model $model,
        string $transition,
        array $context = []
    ): bool;

    /**
     * Get the current state
     */
    public function getState(Model $model): string;

    /**
     * Get the state history
     */
    public function getHistory(Model $model): array;

    /**
     * Get workflow definition
     */
    public function getDefinition(): array;
}
