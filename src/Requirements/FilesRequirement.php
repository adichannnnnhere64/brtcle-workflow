<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Enums\RequirementType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class FilesRequirement extends AbstractRequirement
{
    public function getType(): string
    {
        return RequirementType::FILES->value;
    }

    public function getRules(): array
    {
        $rules = [];

        if ($this->required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'array';

        if (isset($this->config['min_files'])) {
            $rules[] = 'min:'.$this->config['min_files'];
        }

        if (isset($this->config['max_files'])) {
            $rules[] = 'max:'.$this->config['max_files'];
        }

        return $rules;
    }

    public function validate(mixed $value): array
    {
        // First validate the array itself
        $arrayValidation = parent::validate($value);

        if (! $arrayValidation['valid']) {
            return $arrayValidation;
        }

        // If value is empty and not required, it's valid
        if (empty($value) && ! $this->required) {
            return ['valid' => true, 'errors' => []];
        }

        // Validate each file in the array
        $fileRules = $this->getFileRules();
        $errors = [];

        if (is_array($value)) {
            foreach ($value as $index => $file) {
                if ($file instanceof UploadedFile) {
                    $validator = Validator::make(
                        ['file' => $file],
                        ['file' => $fileRules],
                        $this->getFileMessages($index)
                    );

                    if ($validator->fails()) {
                        $errors = array_merge($errors, $validator->errors()->get('file'));
                    }

                    continue;
                }

                if (is_array($file)) {
                    $errors = array_merge($errors, $this->validateMetadataFile($file, $index));
                    continue;
                }

                $errors[] = $this->getFileMessages($index)['file.file'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function getFileRules(): array
    {
        $rules = ['file'];

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
            "{$this->key}.required" => "At least one file is required for {$this->label}.",
            "{$this->key}.array" => "The {$this->label} must be an array of files.",
            "{$this->key}.min" => "At least :min file(s) are required for {$this->label}.",
            "{$this->key}.max" => "The {$this->label} must not exceed :max files.",
        ];
    }

    protected function getFileMessages(int $index): array
    {
        return [
            'file.file' => "File #".($index + 1)." in {$this->label} must be a valid file.",
            'file.max' => "File #".($index + 1)." in {$this->label} must not exceed :max kilobytes.",
            'file.mimes' => "File #".($index + 1)." in {$this->label} must be a file of type: :values.",
        ];
    }

    /**
     * Validate file metadata arrays for stored attachments.
     *
     * @return array<int, string>
     */
    protected function validateMetadataFile(array $file, int $index): array
    {
        $errors = [];
        $messages = $this->getFileMessages($index);
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
            $errors[] = str_replace(':values', implode(', ', $allowed), $messages['file.mimes']);
        }

        if (isset($this->config['max_size'])) {
            $maxKb = (int) $this->config['max_size'];
            $sizeBytes = $file['size'] ?? null;

            if (! is_numeric($sizeBytes)) {
                $errors[] = $messages['file.file'];
            } elseif ((int) $sizeBytes > ($maxKb * 1024)) {
                $errors[] = str_replace(':max', (string) $maxKb, $messages['file.max']);
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'max_size' => $this->config['max_size'] ?? null,
            'mimes' => $this->config['mimes'] ?? null,
            'min_files' => $this->config['min_files'] ?? null,
            'max_files' => $this->config['max_files'] ?? null,
            'accept' => $this->getAcceptAttribute(),
            'multiple' => true,
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
