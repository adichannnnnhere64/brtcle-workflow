<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Requirements;

use Adichan\WorkflowEngine\Contracts\TransitionRequirementInterface;
use Adichan\WorkflowEngine\Enums\RequirementType;
use InvalidArgumentException;

class RequirementFactory
{
    /**
     * Registry of custom requirement types
     *
     * @var array<string, class-string<TransitionRequirementInterface>>
     */
    protected static array $customTypes = [];

    /**
     * Create a requirement from array configuration
     */
    public static function make(array $config): TransitionRequirementInterface
    {
        $type = $config['type'] ?? RequirementType::MESSAGE->value;

        // Check for custom registered types first
        if (isset(self::$customTypes[$type])) {
            $class = self::$customTypes[$type];

            return new $class(
                key: $config['key'],
                label: $config['label'],
                required: $config['required'] ?? false,
                config: $config['config'] ?? []
            );
        }

        // Built-in types
        return match ($type) {
            RequirementType::MESSAGE->value => new MessageRequirement(
                key: $config['key'],
                label: $config['label'],
                required: $config['required'] ?? false,
                config: $config['config'] ?? []
            ),
            RequirementType::FILE->value => new FileRequirement(
                key: $config['key'],
                label: $config['label'],
                required: $config['required'] ?? false,
                config: $config['config'] ?? []
            ),
            RequirementType::FILES->value => new FilesRequirement(
                key: $config['key'],
                label: $config['label'],
                required: $config['required'] ?? false,
                config: $config['config'] ?? []
            ),
            default => throw new InvalidArgumentException("Unknown requirement type: {$type}")
        };
    }

    /**
     * Create multiple requirements from array configuration
     *
     * @param  array<array>  $configs
     * @return array<TransitionRequirementInterface>
     */
    public static function makeMany(array $configs): array
    {
        return array_map(fn ($config) => self::make($config), $configs);
    }

    /**
     * Register a custom requirement type
     *
     * @param  class-string<TransitionRequirementInterface>  $class
     */
    public static function register(string $type, string $class): void
    {
        if (! is_subclass_of($class, TransitionRequirementInterface::class)) {
            throw new InvalidArgumentException(
                "Class {$class} must implement TransitionRequirementInterface"
            );
        }

        self::$customTypes[$type] = $class;
    }

    /**
     * Check if a type is registered
     */
    public static function hasType(string $type): bool
    {
        if (isset(self::$customTypes[$type])) {
            return true;
        }

        return in_array($type, [
            RequirementType::MESSAGE->value,
            RequirementType::FILE->value,
            RequirementType::FILES->value,
        ]);
    }

    /**
     * Get all registered types
     *
     * @return array<string>
     */
    public static function getTypes(): array
    {
        $builtIn = [
            RequirementType::MESSAGE->value,
            RequirementType::FILE->value,
            RequirementType::FILES->value,
        ];

        return array_merge($builtIn, array_keys(self::$customTypes));
    }
}
