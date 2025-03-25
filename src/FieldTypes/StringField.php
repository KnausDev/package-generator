<?php

namespace KnausDev\PackageGenerator\FieldTypes;

class StringField extends BaseFieldType
{
    protected int $maxLength;

    public function __construct(
        string $name,
        bool $nullable = false,
               $default = null,
        ?string $description = null,
        array $validationRules = [],
        int $maxLength = 255
    ) {
        parent::__construct($name, $nullable, $default, $description, $validationRules);
        $this->maxLength = $maxLength;

        // Add max length validation if not already present
        $hasMaxValidation = false;
        foreach ($this->validationRules as $rule) {
            if (strpos($rule, 'max:') === 0) {
                $hasMaxValidation = true;
                break;
            }
        }

        if (!$hasMaxValidation) {
            $this->validationRules[] = "max:{$maxLength}";
        }
    }

    /**
     * Get migration column definition.
     */
    public function getMigrationColumnDefinition(): string
    {
        $column = "\$table->string('{$this->name}', {$this->maxLength})";

        if ($this->nullable) {
            $column .= '->nullable()';
        }

        if ($this->default !== null) {
            $column .= "->default('{$this->default}')";
        }

        return $column . ';';
    }

    /**
     * Get model cast type if needed.
     */
    public function getModelCast(): ?string
    {
        // String doesn't need casting in Laravel
        return null;
    }

    /**
     * Get Vue form input component.
     */
    public function getVueFormInput(): string
    {
        $required = in_array('required', $this->validationRules) ? 'required' : '';
        $maxLength = $this->maxLength;

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
                type="text"
                name="{$this->name}"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                maxlength="{$maxLength}"
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
        return 'string';
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
}
