<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;

class ControllerGenerator extends BaseGenerator
{
    /**
     * Generate the controller.
     */
    public function generate(): bool
    {
        $controllerDirectory = $this->getSourceDirectory() . '/Http/Controllers';
        $this->createDirectory($controllerDirectory);

        $controllerName = $this->modelName . 'Controller';
        $controllerPath = $controllerDirectory . '/' . $controllerName . '.php';

        // Check if the controller already exists
        if ($this->filesystem->exists($controllerPath) && !$this->confirmOverwrite($controllerPath)) {
            $this->info("Skipped: {$controllerPath}");
            return false;
        }

        $stub = $this->getStub('controller');
        $content = $this->populateStub($stub);

        return $this->writeFile($controllerPath, $content);
    }

    /**
     * Populate the controller stub with the package data.
     */
    protected function populateStub(string $stub): string
    {
        $modelVariable = lcfirst($this->modelName);

        return $this->replaceTemplate($stub, [
            'namespace' => $this->getControllerNamespace(),
            'modelNamespace' => $this->getModelNamespace(),
            'requestNamespace' => $this->getRequestNamespace(),
            'resourceNamespace' => $this->getResourceNamespace(),
            'serviceNamespace' => $this->getServiceNamespace(),
            'class' => $this->modelName . 'Controller',
            'model' => $this->modelName,
            'modelVariable' => $modelVariable,
            'pluralModelVariable' => Str::plural($modelVariable),
            'formRequest' => $this->modelName . 'Request',
            'resource' => $this->modelName . 'Resource',
            'service' => $this->modelName . 'Service',
            'apiPrefix' => $this->apiVersion ? '/' . $this->apiVersion : '',
        ]);
    }

    /**
     * Get the controller namespace.
     */
    protected function getControllerNamespace(): string
    {
        if ($this->packageType === 'domain') {
            // For domain packages, the namespace should include the model name as a domain component
            // e.g., KnausDev\User\Http\Controllers
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev

            // The domain name is derived from the package name for domain packages
            return $domainBaseNamespace . '\\' . $this->packageName . '\\Http\\Controllers';
        }

        // For composer packages
        return $this->namespace . '\\Http\\Controllers';
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
     * Confirm overwriting an existing file.
     */
    protected function confirmOverwrite(string $path): bool
    {
        // This would ideally prompt the user in a console environment
        // For now, we'll return true to always overwrite
        return true;
    }
}
