<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;

class ResourceGenerator extends BaseGenerator
{
    /**
     * Generate the API resource.
     */
    public function generate(): bool
    {
        $resourceDirectory = $this->getSourceDirectory() . '/Http/Resources';
        $this->createDirectory($resourceDirectory);

        $resourceName = $this->modelName . 'Resource';
        $resourcePath = $resourceDirectory . '/' . $resourceName . '.php';

        // Check if the resource already exists
        if ($this->filesystem->exists($resourcePath) && !$this->confirmOverwrite($resourcePath)) {
            $this->info("Skipped: {$resourcePath}");
            return false;
        }

        $stub = $this->getStub('resource');
        $content = $this->populateStub($stub);

        return $this->writeFile($resourcePath, $content);
    }

    /**
     * Populate the resource stub with the package data.
     */
    protected function populateStub(string $stub): string
    {
        return $this->replaceTemplate($stub, [
            'namespace' => $this->getResourceNamespace(),
            'class' => $this->modelName . 'Resource',
            'attributes' => $this->generateAttributes(),
        ]);
    }

    /**
     * Get the resource namespace.
     */
    protected function getResourceNamespace(): string
    {
        if ($this->packageType === 'domain') {
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev

            return $domainBaseNamespace . '\\' . $this->packageName . '\\Http\\Resources';
        }

        return $this->namespace . '\\Http\\Resources';
    }

    /**
     * Generate the resource attributes.
     */
    protected function generateAttributes(): string
    {
        if (empty($this->fields)) {
            return "            //";
        }

        $attributes = [
            "            'id' => \$this->id,",
        ];

        foreach ($this->fields as $field) {
            $attributes[] = "            '{$field->getName()}' => \$this->{$field->getName()},";
        }

        $attributes[] = "            'created_at' => \$this->created_at,";
        $attributes[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $attributes);
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
