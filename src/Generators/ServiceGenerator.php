<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;

class ServiceGenerator extends BaseGenerator
{
    /**
     * Generate the service.
     */
    public function generate(): bool
    {
        $serviceDirectory = $this->getSourceDirectory() . '/Services';
        $this->createDirectory($serviceDirectory);

        $serviceName = $this->modelName . 'Service';
        $servicePath = $serviceDirectory . '/' . $serviceName . '.php';

        // Check if the service already exists
        if ($this->filesystem->exists($servicePath) && !$this->confirmOverwrite($servicePath)) {
            $this->info("Skipped: {$servicePath}");
            return false;
        }

        $stub = $this->getStub('service');
        $content = $this->populateStub($stub);

        return $this->writeFile($servicePath, $content);
    }

    /**
     * Populate the service stub with the package data.
     */
    protected function populateStub(string $stub): string
    {
        $modelVariable = lcfirst($this->modelName);

        return $this->replaceTemplate($stub, [
            'namespace' => $this->getServiceNamespace(),
            'modelNamespace' => $this->getModelNamespace(),
            'class' => $this->modelName . 'Service',
            'model' => $this->modelName,
            'modelVariable' => $modelVariable,
            'pluralModelVariable' => Str::plural($modelVariable),
        ]);
    }

    /**
     * Get the service namespace.
     */
    protected function getServiceNamespace(): string
    {
        if ($this->packageType === 'domain') {
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev

            return $domainBaseNamespace . '\\' . $this->packageName . '\\Services';
        }

        return $this->namespace . '\\Services';
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

        return $this->namespace . '\\Models';
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
