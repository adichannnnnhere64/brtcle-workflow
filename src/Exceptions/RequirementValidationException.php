<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Exceptions;

use Exception;

class RequirementValidationException extends Exception
{
    /**
     * @var array<string, array<string>>
     */
    protected array $errors;

    /**
     * @param  array<string, array<string>>  $errors
     */
    public function __construct(string $message, array $errors = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get all validation errors
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     *
     * @return array<string>
     */
    public function getErrorsFor(string $key): array
    {
        return $this->errors[$key] ?? [];
    }

    /**
     * Get the first error message
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (! empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }

        return null;
    }

    /**
     * Get all error messages as a flat array
     *
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        $messages = [];

        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }

        return $messages;
    }
}
