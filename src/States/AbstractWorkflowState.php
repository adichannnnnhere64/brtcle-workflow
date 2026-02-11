<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\States;

use Adichan\WorkflowEngine\Contracts\WorkflowStateInterface;
use Adichan\WorkflowEngine\Enums\WorkflowGuard;

abstract class AbstractWorkflowState implements WorkflowStateInterface
{
    protected array $transitions = [];
    protected array $guards = [];

    public function __construct(
        protected readonly string $state,
        protected readonly string $label,
        protected readonly bool $isInitial = false,
        protected readonly bool $isFinal = false
    ) {}

    public function value(): string
    {
        return $this->state;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isFinal(): bool
    {
        return $this->isFinal;
    }

    public function isInitial(): bool
    {
        return $this->isInitial;
    }

    public function allowedTransitions(): array
    {
        return $this->transitions;
    }

    public function addTransition(
        string $toState,
        string $transitionName,
        WorkflowGuard $guard,
        ?callable $validator = null
    ): void {
        $this->transitions[$transitionName] = [
            'to' => $toState,
            'guard' => $guard,
            'validator' => $validator,
        ];
    }

    public function getGuard(string $transitionName): ?WorkflowGuard
    {
        return $this->transitions[$transitionName]['guard'] ?? null;
    }

    public function validateTransition(string $transitionName, array $context = []): bool
    {
        $validator = $this->transitions[$transitionName]['validator'] ?? null;

        if ($validator && is_callable($validator)) {
            return $validator($context);
        }

        return true;
    }
}
