<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Enums\RequirementType;
use Illuminate\Http\UploadedFile;

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

    public function validate(mixed $value): array
    {
        if ($value instanceof UploadedFile || ! is_array($value)) {
            return parent::validate($value);
        }

        if (empty($value) && ! $this->required) {
            return ['valid' => true, 'errors' => []];
        }

        if (empty($value) && $this->required) {
            return [
                'valid' => false,
                'errors' => [$this->getMessages()["{$this->key}.required"]],
            ];
        }

        return $this->validateMetadataFile($value);
    }

    /**
     * Validate file metadata arrays for stored attachments.
     *
     * @return array{valid: bool, errors: array<int, string>}
     */
    protected function validateMetadataFile(array $file): array
    {
        $errors = [];
        $messages = $this->getMessages();
        $allowed = [];

        if (isset($this->config['mimes'])) {
            $allowed = is_array($this->config['mimes'])
                ? $this->config['mimes']
                : explode(',', $this->config['mimes']);
            $allowed = array_map('strtolower', $allowed);
        }

        $name = $file['name'] ?? $file['path'] ?? null;
        $ext = $name ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : null;

        if ($allowed && (! $ext || ! in_array($ext, $allowed, true))) {
            $errors[] = str_replace(':values', implode(', ', $allowed), $messages["{$this->key}.mimes"]);
        }

        if (isset($this->config['max_size'])) {
            $maxKb = (int) $this->config['max_size'];
            $sizeBytes = $file['size'] ?? null;

            if (! is_numeric($sizeBytes)) {
                $errors[] = $messages["{$this->key}.file"];
            } elseif ((int) $sizeBytes > ($maxKb * 1024)) {
                $errors[] = str_replace(':max', (string) $maxKb, $messages["{$this->key}.max"]);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
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
