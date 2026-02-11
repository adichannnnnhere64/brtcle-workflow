<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Contracts;

use Illuminate\Database\Eloquent\Model;

interface WorkflowTransitionInterface
{
    public function getFromState(): string;
    public function getToState(): string;
    public function getTransitionName(): string;
    public function getContext(): array;
    public function getModel(): Model;
    public function getPerformedBy(): ?Model;
    public function getPerformedAt(): \DateTimeInterface;
    public function getMetadata(): array;
}
