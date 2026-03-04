<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Contracts\TransitionRequirementInterface;
use Adichan\WorkflowEngine\Exceptions\RequirementValidationException;

class RequirementValidator
{
    /**
     * @var array<TransitionRequirementInterface>
     */
    protected array $requirements;

    /**
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * @param  array<TransitionRequirementInterface>  $requirements
     */
    public function __construct(array $requirements = [])
    {
        $this->requirements = $requirements;
    }

    /**
     * Create from array configurations
     *
     * @param  array<array>  $configs
     */
    public static function fromConfig(array $configs): static
    {
        $requirements = RequirementFactory::makeMany($configs);

        return new static($requirements);
    }

    /**
     * Validate context against all requirements
     *
     * @return array{valid: bool, errors: array<string, array<string>>}
     */
    public function validate(array $context): array
    {
        $this->errors = [];
        $valid = true;

        foreach ($this->requirements as $requirement) {
            $key = $requirement->getKey();
            $value = $context[$key] ?? null;

            $result = $requirement->validate($value);

            if (! $result['valid']) {
                $valid = false;
                $this->errors[$key] = $result['errors'];
            }
        }

        return [
            'valid' => $valid,
            'errors' => $this->errors,
        ];
    }

    /**
     * Validate and throw exception on failure
     *
     * @throws RequirementValidationException
     */
    public function validateOrFail(array $context): void
    {
        $result = $this->validate($context);

        if (! $result['valid']) {
            throw new RequirementValidationException(
                'Transition requirements validation failed',
                $result['errors']
            );
        }
    }

    /**
     * Check if context passes validation
     */
    public function passes(array $context): bool
    {
        return $this->validate($context)['valid'];
    }

    /**
     * Check if context fails validation
     */
    public function fails(array $context): bool
    {
        return ! $this->passes($context);
    }

    /**
     * Get the last validation errors
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all requirements
     *
     * @return array<TransitionRequirementInterface>
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    /**
     * Get required requirements only
     *
     * @return array<TransitionRequirementInterface>
     */
    public function getRequired(): array
    {
        return array_filter(
            $this->requirements,
            fn (TransitionRequirementInterface $r) => $r->isRequired()
        );
    }

    /**
     * Get optional requirements only
     *
     * @return array<TransitionRequirementInterface>
     */
    public function getOptional(): array
    {
        return array_filter(
            $this->requirements,
            fn (TransitionRequirementInterface $r) => ! $r->isRequired()
        );
    }

    /**
     * Check if there are any requirements
     */
    public function hasRequirements(): bool
    {
        return ! empty($this->requirements);
    }

    /**
     * Check if there are required requirements
     */
    public function hasRequired(): bool
    {
        return ! empty($this->getRequired());
    }

    /**
     * Convert all requirements to array
     *
     * @return array<array>
     */
    public function toArray(): array
    {
        return array_map(
            fn (TransitionRequirementInterface $r) => $r->toArray(),
            $this->requirements
        );
    }
}
