<?php

namespace KnausDev\PackageGenerator\FieldTypes;

class IntegerField extends BaseFieldType
{
    protected ?int $min;
    protected ?int $max;

    public function __construct(
        string $name,
        bool $nullable = false,
               $default = null,
        ?string $description = null,
        array $validationRules = [],
        ?int $min = null,
        ?int $max = null
    ) {
        parent::__construct($name, $nullable, $default, $description, $validationRules);
        $this->min = $min;
        $this->max = $max;

        // Add type validation if not already present
        if (!in_array('integer', $this->validationRules)) {
            $this->validationRules[] = 'integer';
        }

        // Add min/max validation if specified
        if ($min !== null && !$this->hasMinValidation()) {
            $this->validationRules[] = "min:{$min}";
        }

        if ($max !== null && !$this->hasMaxValidation()) {
            $this->validationRules[] = "max:{$max}";
        }
    }

    /**
     * Get migration column definition.
     */
    public function getMigrationColumnDefinition(): string
    {
        $column = "\$table->integer('{$this->name}')";

        if ($this->nullable) {
            $column .= '->nullable()';
        }

        if ($this->default !== null) {
            $column .= "->default({$this->default})";
        }

        return $column . ';';
    }

    /**
     * Get model cast type if needed.
     */
    public function getModelCast(): ?string
    {
        return 'integer';
    }

    /**
     * Get Vue form input component.
     */
    public function getVueFormInput(): string
    {
        $required = in_array('required', $this->validationRules) ? 'required' : '';
        $min = $this->min !== null ? "min=\"{$this->min}\"" : '';
        $max = $this->max !== null ? "max=\"{$this->max}\"" : '';

        // Handle required indicator
        $requiredIndicator = $required ? '<span class="text-red-500">*</span>' : '';

        return <<<HTML
        <div class="mb-4">
            <label for="{$this->name}" class="block text-gray-700 text-sm font-bold mb-2">
                {$this->getFormattedName()} {$requiredIndicator}
            </label>
            <input
                id="{$this->name}"
                v-model="form.{$this->name}"
                type="number"
                name="{$this->name}"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                {$min}
                {$max}
                {$required}
            >
            <div v-if="errors.{$this->name}" class="text-red-500 text-xs italic mt-1">{{ errors.{$this->name}[0] }}</div>
        </div>
        HTML;
    }

    /**
     * Get the field type.
     */
    public function getFieldType(): string
    {
        return 'integer';
    }

    /**
     * Get formatted field name for display.
     */
    protected function getFormattedName(): string
    {
        if ($this->description) {
            return $this->description;
        }

        return ucwords(str_replace('_', ' ', $this->name));
    }

    /**
     * Check if validation rules already include min.
     */
    protected function hasMinValidation(): bool
    {
        foreach ($this->validationRules as $rule) {
            if (strpos($rule, 'min:') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if validation rules already include max.
     */
    protected function hasMaxValidation(): bool
    {
        foreach ($this->validationRules as $rule) {
            if (strpos($rule, 'max:') === 0) {
                return true;
            }
        }
        return false;
    }
}
