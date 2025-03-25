<?php

namespace KnausDev\PackageGenerator\FieldTypes;

class FileField extends BaseFieldType
{
    protected int $maxSize;
    protected array $allowedTypes;

    public function __construct(
        string $name,
        bool $nullable = false,
               $default = null,
        ?string $description = null,
        array $validationRules = [],
        int $maxSize = 10240, // Default to 10MB
        array $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx']
    ) {
        parent::__construct($name, $nullable, $default, $description, $validationRules);
        $this->maxSize = $maxSize;
        $this->allowedTypes = $allowedTypes;

        // Add file validation if not already present
        if (!in_array('file', $this->validationRules)) {
            $this->validationRules[] = 'file';
        }

        // Add max size validation if not already present
        $hasMaxValidation = false;
        foreach ($this->validationRules as $rule) {
            if (strpos($rule, 'max:') === 0) {
                $hasMaxValidation = true;
                break;
            }
        }

        if (!$hasMaxValidation) {
            $this->validationRules[] = "max:{$maxSize}";
        }

        // Add mimes validation if not already present
        $hasMimesValidation = false;
        foreach ($this->validationRules as $rule) {
            if (strpos($rule, 'mimes:') === 0) {
                $hasMimesValidation = true;
                break;
            }
        }

        if (!$hasMimesValidation && !empty($allowedTypes)) {
            $this->validationRules[] = 'mimes:' . implode(',', $allowedTypes);
        }
    }

    /**
     * Get migration column definition.
     */
    public function getMigrationColumnDefinition(): string
    {
        // For files, we typically store the file path or ID in the database
        $column = "\$table->string('{$this->name}', 255)";

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
        // No special casting needed for file path strings
        return null;
    }

    /**
     * Get Vue form input component.
     */
    public function getVueFormInput(): string
    {
        $required = in_array('required', $this->validationRules) ? 'required' : '';
        $acceptedTypes = implode(',', array_map(function($type) {
            return '.' . $type;
        }, $this->allowedTypes));

        // Handle required indicator
        $requiredIndicator = $required ? '<span class="text-red-500">*</span>' : '';

        return <<<HTML
        <div class="mb-4">
            <label for="{$this->name}" class="block text-gray-700 text-sm font-bold mb-2">
                {$this->getFormattedName()} {$requiredIndicator}
            </label>
            <div class="mt-1 flex items-center">
                <input
                    id="{$this->name}"
                    type="file"
                    name="{$this->name}"
                    @change="handleFileUpload"
                    class="block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-50 file:text-blue-700
                        hover:file:bg-blue-100"
                    accept="{$acceptedTypes}"
                    {$required}
                />
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Max file size: {$this->formatFileSize($this->maxSize)}. Allowed types: {$this->formatAllowedTypes()}
            </p>
            <div v-if="errors.{$this->name}" class="text-red-500 text-xs italic mt-1">{{ errors.{$this->name}[0] }}</div>
        </div>
        HTML;
    }

    /**
     * Get the field type.
     */
    public function getFieldType(): string
    {
        return 'file';
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
     * Format file size for display.
     */
    protected function formatFileSize(int $sizeKB): string
    {
        if ($sizeKB >= 1024) {
            return round($sizeKB / 1024, 2) . ' MB';
        }

        return $sizeKB . ' KB';
    }

    /**
     * Format allowed types for display.
     */
    protected function formatAllowedTypes(): string
    {
        return strtoupper(implode(', ', $this->allowedTypes));
    }
}
