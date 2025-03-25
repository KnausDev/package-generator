<?php

namespace KnausDev\PackageGenerator\FieldTypes;

class TextField extends BaseFieldType
{
    protected bool $useRichEditor;

    public function __construct(
        string $name,
        bool $nullable = false,
               $default = null,
        ?string $description = null,
        array $validationRules = [],
        bool $useRichEditor = false
    ) {
        parent::__construct($name, $nullable, $default, $description, $validationRules);
        $this->useRichEditor = $useRichEditor;
    }

    /**
     * Get migration column definition.
     */
    public function getMigrationColumnDefinition(): string
    {
        $column = "\$table->text('{$this->name}')";

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
        // Text fields don't typically need casting in Laravel
        return null;
    }

    /**
     * Get Vue form input component.
     */
    public function getVueFormInput(): string
    {
        $required = in_array('required', $this->validationRules) ? 'required' : '';

        // Handle required indicator
        $requiredIndicator = $required ? '<span class="text-red-500">*</span>' : '';

        if ($this->useRichEditor) {
            // Rich editor component
            return <<<HTML
            <div class="mb-4">
                <label for="{$this->name}" class="block text-gray-700 text-sm font-bold mb-2">
                    {$this->getFormattedName()} {$requiredIndicator}
                </label>
                <rich-editor
                    id="{$this->name}"
                    v-model="form.{$this->name}"
                    name="{$this->name}"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    {$required}
                ></rich-editor>
                <div v-if="errors.{$this->name}" class="text-red-500 text-xs italic mt-1">{{ errors.{$this->name}[0] }}</div>
            </div>
            HTML;
        }

        // Regular textarea
        return <<<HTML
        <div class="mb-4">
            <label for="{$this->name}" class="block text-gray-700 text-sm font-bold mb-2">
                {$this->getFormattedName()} {$requiredIndicator}
            </label>
            <textarea
                id="{$this->name}"
                v-model="form.{$this->name}"
                name="{$this->name}"
                rows="4"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                {$required}
            ></textarea>
            <div v-if="errors.{$this->name}" class="text-red-500 text-xs italic mt-1">{{ errors.{$this->name}[0] }}</div>
        </div>
        HTML;
    }

    /**
     * Get the field type.
     */
    public function getFieldType(): string
    {
        return 'text';
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
     * Check if the field uses rich editor.
     */
    public function usesRichEditor(): bool
    {
        return $this->useRichEditor;
    }
}
