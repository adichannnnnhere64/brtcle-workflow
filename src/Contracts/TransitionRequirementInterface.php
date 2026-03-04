<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Contracts;

interface TransitionRequirementInterface
{
    /**
     * Get the unique key/name for this requirement
     */
    public function getKey(): string;

    /**
     * Get the type of requirement (message, file, etc.)
     */
    public function getType(): string;

    /**
     * Get the human-readable label for this requirement
     */
    public function getLabel(): string;

    /**
     * Check if this requirement is required or optional
     */
    public function isRequired(): bool;

    /**
     * Validate the given value against this requirement
     *
     * @param  mixed  $value  The value from context to validate
     * @return array{valid: bool, errors: array<string>}
     */
    public function validate(mixed $value): array;

    /**
     * Get validation rules (Laravel validation format)
     */
    public function getRules(): array;

    /**
     * Get additional configuration/metadata for this requirement
     */
    public function getConfig(): array;

    /**
     * Convert the requirement to an array (for API/frontend)
     */
    public function toArray(): array;
}
