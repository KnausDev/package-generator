<?php

namespace KnausDev\PackageGenerator\FieldTypes;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class FieldTypeFactory
{
    /**
     * Create a field type from an array definition.
     */
    public static function createFromArray(array $definition): BaseFieldType
    {
        // Validate required field information
        if (!isset($definition['name']) || !isset($definition['type'])) {
            throw new InvalidArgumentException('Field definition must include name and type');
        }

        $name = $definition['name'];
        $type = $definition['type'];
        $nullable = $definition['nullable'] ?? false;
        $default = $definition['default'] ?? null;
        $description = $definition['description'] ?? null;

        // Parse validation rules
        $validationRules = [];
        if (isset($definition['validation'])) {
            $validationRules = is_array($definition['validation'])
                ? $definition['validation']
                : explode('|', $definition['validation']);
        }

        // Create appropriate field type instance
        switch ($type) {
            case 'string':
                $maxLength = $definition['maxLength'] ?? 255;
                return new StringField($name, $nullable, $default, $description, $validationRules, $maxLength);

            case 'integer':
                $min = $definition['min'] ?? null;
                $max = $definition['max'] ?? null;
                return new IntegerField($name, $nullable, $default, $description, $validationRules, $min, $max);

            case 'text':
                return new TextField($name, $nullable, $default, $description, $validationRules);

            case 'boolean':
                return new BooleanField($name, $nullable, $default, $description, $validationRules);

            case 'float':
                $decimals = $definition['decimals'] ?? 2;
                return new FloatField($name, $nullable, $default, $description, $validationRules, $decimals);

            case 'file':
                $maxSize = $definition['maxSize'] ?? 10240; // 10MB default
                $allowedTypes = $definition['allowedTypes'] ?? ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
                return new FileField($name, $nullable, $default, $description, $validationRules, $maxSize, $allowedTypes);

            default:
                throw new InvalidArgumentException("Unsupported field type: {$type}");
        }
    }

    /**
     * Create a field type instance from a JSON definition.
     */
    public static function createFromJson(string $json): BaseFieldType
    {
        $definition = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return static::createFromArray($definition);
    }

    /**
     * Create multiple field types from an array of definitions.
     */
    public static function createMultipleFromArray(array $definitions): array
    {
        return array_map(function ($definition) {
            return static::createFromArray($definition);
        }, $definitions);
    }

    /**
     * Create multiple field types from a JSON array.
     */
    public static function createMultipleFromJson(string $json): array
    {
        $definitions = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        // Handle either a direct array of fields or a fields property
        $fieldDefinitions = isset($definitions['fields']) ? $definitions['fields'] : $definitions;

        return static::createMultipleFromArray($fieldDefinitions);
    }
}
