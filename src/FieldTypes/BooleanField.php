<?php

namespace KnausDev\PackageGenerator\FieldTypes;

class BooleanField extends BaseFieldType
{
    public function __construct(
        string $name,
        bool $nullable = false,
               $default = false,
        ?string $description = null,
        array $validationRules = []
    ) {
        parent::__construct($name, $nullable, $default, $description, $validationRules);

        // Add boolean validation if not already present
        if (!in_array('boolean', $this->validationRules)) {
            $this->validationRules[] = 'boolean';
        }
    }

    /**
     * Get migration column definition.
     */
    public function getMigrationColumnDefinition(): string
    {
        $column = "\$table->boolean('{$this->name}')";

        if ($this->nullable) {
            $column .= '->nullable()';
        }

        // Convert boolean to actual boolean for default value
        if ($this->default !== null) {
            $defaultValue = $this->default ? 'true' : 'false';
            $column .= "->default({$defaultValue})";
        }

        return $column . ';';
    }

    /**
     * Get model cast type if needed.
     */
    public function getModelCast(): ?string
    {
        return 'boolean';
    }

    /**
     * Get Vue form input component.
     */
    public function getVueFormInput(): string
    {
        return <<<HTML
        <div class="mb-4">
            <div class="flex items-center">
                <input
                    id="{$this->name}"
                    v-model="form.{$this->name}"
                    type="checkbox"
                    name="{$this->name}"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                >
                <label for="{$this->name}" class="ml-2 block text-gray-700 text-sm font-medium">
                    {$this->getFormattedName()}
                </label>
            </div>
            <div v-if="errors.{$this->name}" class="text-red-500 text-xs italic mt-1">{{ errors.{$this->name}[0] }}</div>
        </div>
        HTML;
    }

    /**
     * Get the field type.
     */
    public function getFieldType(): string
    {
        return 'boolean';
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
