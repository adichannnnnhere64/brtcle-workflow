<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Enums\RequirementType;

class MessageRequirement extends AbstractRequirement
{
    public function getType(): string
    {
        return RequirementType::MESSAGE->value;
    }

    public function getRules(): array
    {
        $rules = [];

        if ($this->required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'string';

        if (isset($this->config['min_length'])) {
            $rules[] = 'min:'.$this->config['min_length'];
        }

        if (isset($this->config['max_length'])) {
            $rules[] = 'max:'.$this->config['max_length'];
        }

        return $rules;
    }

    protected function getMessages(): array
    {
        return [
            "{$this->key}.required" => "The {$this->label} is required.",
            "{$this->key}.min" => "The {$this->label} must be at least :min characters.",
            "{$this->key}.max" => "The {$this->label} must not exceed :max characters.",
        ];
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'placeholder' => $this->config['placeholder'] ?? "Enter {$this->label}...",
            'min_length' => $this->config['min_length'] ?? null,
            'max_length' => $this->config['max_length'] ?? null,
            'multiline' => $this->config['multiline'] ?? true,
        ]);
    }
}
