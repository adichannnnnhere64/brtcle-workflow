<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Enums;

enum RequirementType: string
{
    case MESSAGE = 'message';
    case FILE = 'file';
    case FILES = 'files';
    case CHECKBOX = 'checkbox';
    case SELECT = 'select';
    case DATE = 'date';
    case NUMBER = 'number';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::MESSAGE => 'Message/Comment',
            self::FILE => 'Single File Upload',
            self::FILES => 'Multiple File Uploads',
            self::CHECKBOX => 'Checkbox Confirmation',
            self::SELECT => 'Selection',
            self::DATE => 'Date',
            self::NUMBER => 'Number',
            self::CUSTOM => 'Custom',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MESSAGE => 'message-square',
            self::FILE => 'file',
            self::FILES => 'files',
            self::CHECKBOX => 'check-square',
            self::SELECT => 'list',
            self::DATE => 'calendar',
            self::NUMBER => 'hash',
            self::CUSTOM => 'settings',
        };
    }
}
