<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Contracts;

interface WorkflowStateInterface
{
    /**
     * Get state identifier
     */
    public function value(): string;

    /**
     * Get human-readable label
     */
    public function label(): string;

    /**
     * Check if state is final
     */
    public function isFinal(): bool;

    /**
     * Check if state is initial
     */
    public function isInitial(): bool;

    /**
     * Get allowed transitions from this state
     */
    public function allowedTransitions(): array;
}
