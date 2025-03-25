<?php

namespace KnausDev\PackageGenerator\Support;

use Illuminate\Filesystem\Filesystem;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;
use KnausDev\PackageGenerator\FieldTypes\FieldTypeFactory;

class FieldDefinition
{
    /**
     * The filesystem instance.
     */
    protected Filesystem $filesystem;

    /**
     * The package path.
     */
    protected string $packagePath;

    /**
     * The model name.
     */
    protected string $modelName;

    /**
     * Create a new field definition instance.
     */
    public function __construct(string $packagePath, string $modelName)
    {
        $this->filesystem = new Filesystem();
        $this->packagePath = $packagePath;
        $this->modelName = $modelName;
    }

    /**
     * Save field definitions to a JSON file.
     */
    public function saveFields(array $fields): bool
    {
        $definitionsDirectory = $this->getDefinitionsDirectory();
        $this->createDirectory($definitionsDirectory);

        $fieldData = [
            'model' => $this->modelName,
            'fields' => array_map(function (BaseFieldType $field) {
                return $field->toArray();
            }, $fields)
        ];

        $filePath = $this->getDefinitionFilePath();

        return $this->filesystem->put(
            $filePath,
            json_encode($fieldData, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Load field definitions from a JSON file.
     */
    public function loadFields(): array
    {
        $filePath = $this->getDefinitionFilePath();

        if (!$this->filesystem->exists($filePath)) {
            return [];
        }

        $jsonContent = $this->filesystem->get($filePath);
        $fieldData = json_decode($jsonContent, true);

        if (!isset($fieldData['fields']) || !is_array($fieldData['fields'])) {
            return [];
        }

        return FieldTypeFactory::createMultipleFromArray($fieldData['fields']);
    }

    /**
     * Check if field definitions exist.
     */
    public function exists(): bool
    {
        return $this->filesystem->exists($this->getDefinitionFilePath());
    }

    /**
     * Add a new field to existing definitions.
     */
    public function addField(BaseFieldType $field): bool
    {
        $fields = $this->loadFields();

        // Check if the field already exists
        foreach ($fields as $existingField) {
            if ($existingField->getName() === $field->getName()) {
                return false; // Field with this name already exists
            }
        }

        // Add the new field
        $fields[] = $field;

        return $this->saveFields($fields);
    }

    /**
     * Update an existing field.
     */
    public function updateField(string $fieldName, BaseFieldType $updatedField): bool
    {
        $fields = $this->loadFields();
        $updated = false;

        foreach ($fields as $key => $field) {
            if ($field->getName() === $fieldName) {
                $fields[$key] = $updatedField;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            return false; // Field not found
        }

        return $this->saveFields($fields);
    }

    /**
     * Remove a field.
     */
    public function removeField(string $fieldName): bool
    {
        $fields = $this->loadFields();
        $originalCount = count($fields);

        $fields = array_filter($fields, function (BaseFieldType $field) use ($fieldName) {
            return $field->getName() !== $fieldName;
        });

        if (count($fields) === $originalCount) {
            return false; // No field was removed
        }

        return $this->saveFields(array_values($fields));
    }

    /**
     * Get the definitions directory.
     */
    protected function getDefinitionsDirectory(): string
    {
        return $this->packagePath . '/.definitions';
    }

    /**
     * Get the definition file path.
     */
    protected function getDefinitionFilePath(): string
    {
        return $this->getDefinitionsDirectory() . '/' . strtolower($this->modelName) . '.json';
    }

    /**
     * Create directory if it doesn't exist.
     */
    protected function createDirectory(string $path): void
    {
        if (!$this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path, 0755, true);
        }
    }
}
