<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;

class RequestGenerator extends BaseGenerator
{
    /**
     * Generate the form request.
     */
    public function generate(): bool
    {
        $requestDirectory = $this->getSourceDirectory() . '/Http/Requests';
        $this->createDirectory($requestDirectory);

        $requestName = $this->modelName . 'Request';
        $requestPath = $requestDirectory . '/' . $requestName . '.php';

        // Check if the request already exists
        if ($this->filesystem->exists($requestPath) && !$this->confirmOverwrite($requestPath)) {
            $this->info("Skipped: {$requestPath}");
            return false;
        }

        $stub = $this->getStub('request');
        $content = $this->populateStub($stub);

        return $this->writeFile($requestPath, $content);
    }

    /**
     * Populate the request stub with the package data.
     */
    protected function populateStub(string $stub): string
    {
        return $this->replaceTemplate($stub, [
            'namespace' => $this->getRequestNamespace(),
            'class' => $this->modelName . 'Request',
            'rules' => $this->generateRules(),
            'messages' => $this->generateMessages(),
        ]);
    }

    /**
     * Get the request namespace.
     */
    protected function getRequestNamespace(): string
    {
        if ($this->packageType === 'domain') {
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev

            return $domainBaseNamespace . '\\' . $this->packageName . '\\Http\\Requests';
        }

        return $this->namespace . '\\Http\\Requests';
    }

    /**
     * Generate the validation rules.
     */
    protected function generateRules(): string
    {
        if (empty($this->fields)) {
            return "        //";
        }

        $rules = [];

        foreach ($this->fields as $field) {
            $validationRules = $field->getValidationRulesString();
            $rules[] = "        '{$field->getName()}' => '{$validationRules}',";
        }

        return implode("\n", $rules);
    }

    /**
     * Generate custom validation messages.
     */
    protected function generateMessages(): string
    {
        return "        //";
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
