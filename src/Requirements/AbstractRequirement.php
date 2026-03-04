<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Contracts\TransitionRequirementInterface;
use Illuminate\Support\Facades\Validator;

abstract class AbstractRequirement implements TransitionRequirementInterface
{
    protected string $key;

    protected string $label;

    protected bool $required;

    protected array $config;

    public function __construct(
        string $key,
        string $label,
        bool $required = false,
        array $config = []
    ) {
        $this->key = $key;
        $this->label = $label;
        $this->required = $required;
        $this->config = $config;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    abstract public function getType(): string;

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function validate(mixed $value): array
    {
        $rules = $this->getRules();

        if (empty($rules)) {
            return ['valid' => true, 'errors' => []];
        }

        $validator = Validator::make(
            [$this->key => $value],
            [$this->key => $rules],
            $this->getMessages()
        );

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->get($this->key),
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    protected function getMessages(): array
    {
        return [];
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->getType(),
            'label' => $this->label,
            'required' => $this->required,
            'rules' => $this->getRules(),
            'config' => $this->config,
        ];
    }

    /**
     * Create a requirement from array configuration
     */
    public static function fromArray(array $config): static
    {
        return new static(
            key: $config['key'],
            label: $config['label'],
            required: $config['required'] ?? false,
            config: $config['config'] ?? []
        );
    }
}
