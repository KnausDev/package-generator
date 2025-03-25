<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;

class ModelGenerator extends BaseGenerator
{
    /**
     * Generate the model.
     */
    public function generate(): bool
    {
        $modelDirectory = $this->getSourceDirectory() . '/Models';
        $this->createDirectory($modelDirectory);

        $modelPath = $modelDirectory . '/' . $this->modelName . '.php';

        // Check if the model already exists
        if ($this->filesystem->exists($modelPath) && !$this->confirmOverwrite($modelPath)) {
            $this->info("Skipped: {$modelPath}");
            return false;
        }

        $stub = $this->getStub('model');
        $content = $this->populateStub($stub);

        return $this->writeFile($modelPath, $content);
    }

    /**
     * Populate the model stub with the package data.
     */
    protected function populateStub(string $stub): string
    {
        return $this->replaceTemplate($stub, [
            'namespace' => $this->getModelNamespace(),
            'class' => $this->modelName,
            'fillable' => $this->generateFillableArray(),
            'casts' => $this->generateCastsArray(),
            'tableName' => $this->getTableName(),
        ]);
    }

    /**
     * Get the model namespace.
     */
    protected function getModelNamespace(): string
    {
        if ($this->packageType === 'domain') {
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev

            return $domainBaseNamespace . '\\' . $this->packageName . '\\Models';
        }

        return $this->namespace . ($this->packageType === 'composer' ? '\\' : '\\Models');
    }

    /**
     * Generate the fillable array for the model.
     */
    protected function generateFillableArray(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $fillable = array_map(function (BaseFieldType $field) {
            return "        '{$field->getName()}'";
        }, $this->fields);

        return implode(",\n", $fillable);
    }

    /**
     * Generate the casts array for the model.
     */
    protected function generateCastsArray(): string
    {
        $casts = [];

        foreach ($this->fields as $field) {
            $cast = $field->getModelCast();

            if ($cast !== null) {
                $casts[] = "        '{$field->getName()}' => '{$cast}'";
            }
        }

        if (empty($casts)) {
            return '';
        }

        return implode(",\n", $casts);
    }

    /**
     * Confirm overwriting an existing file.
     */
    protected function confirmOverwrite(string $path): bool
    {
        // This would ideally prompt the user in a console environment
        // For now, we'll return true to always overwrite
        return true;
    }
}
