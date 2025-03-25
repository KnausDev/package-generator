<?php

namespace KnausDev\PackageGenerator\FieldTypes;

abstract class BaseFieldType
{
    protected string $name;
    protected bool $nullable;
    protected $default;
    protected ?string $description;
    protected array $validationRules;

    public function __construct(
        string $name,
        bool $nullable = false,
               $default = null,
        ?string $description = null,
        array $validationRules = []
    ) {
        $this->name = $name;
        $this->nullable = $nullable;
        $this->default = $default;
        $this->description = $description;
        $this->validationRules = $validationRules;
    }

    /**
     * Get the field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if the field is nullable.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Get the field default value.
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Get the field description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get validation rules.
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Get validation rules as string.
     */
    public function getValidationRulesString(): string
    {
        return implode('|', $this->validationRules);
    }

    /**
     * Get migration column definition.
     */
    abstract public function getMigrationColumnDefinition(): string;

    /**
     * Get model cast type if needed.
     */
    abstract public function getModelCast(): ?string;

    /**
     * Get Vue form input component.
     */
    abstract public function getVueFormInput(): string;

    /**
     * Convert the field to an array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->getFieldType(),
            'nullable' => $this->nullable,
            'default' => $this->default,
            'description' => $this->description,
            'validation' => $this->getValidationRulesString(),
        ];
    }

    /**
     * Get the field type.
     */
    abstract public function getFieldType(): string;
}
