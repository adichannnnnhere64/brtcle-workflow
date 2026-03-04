<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Enums\RequirementType;

class FileRequirement extends AbstractRequirement
{
    public function getType(): string
    {
        return RequirementType::FILE->value;
    }

    public function getRules(): array
    {
        $rules = [];

        if ($this->required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'file';

        if (isset($this->config['max_size'])) {
            $rules[] = 'max:'.$this->config['max_size'];
        }

        if (isset($this->config['mimes'])) {
            $mimes = is_array($this->config['mimes'])
                ? implode(',', $this->config['mimes'])
                : $this->config['mimes'];
            $rules[] = 'mimes:'.$mimes;
        }

        return $rules;
    }

    protected function getMessages(): array
    {
        return [
            "{$this->key}.required" => "The {$this->label} is required.",
            "{$this->key}.file" => "The {$this->label} must be a valid file.",
            "{$this->key}.max" => "The {$this->label} must not exceed :max kilobytes.",
            "{$this->key}.mimes" => "The {$this->label} must be a file of type: :values.",
        ];
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'max_size' => $this->config['max_size'] ?? null,
            'mimes' => $this->config['mimes'] ?? null,
            'accept' => $this->getAcceptAttribute(),
        ]);
    }

    protected function getAcceptAttribute(): ?string
    {
        if (! isset($this->config['mimes'])) {
            return null;
        }

        $mimes = is_array($this->config['mimes'])
            ? $this->config['mimes']
            : explode(',', $this->config['mimes']);

        return implode(',', array_map(fn ($m) => '.'.$m, $mimes));
    }
}
