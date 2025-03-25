<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;

class RouteGenerator extends BaseGenerator
{
    /**
     * Generate the routes.
     */
    public function generate(): bool
    {
        $routesDirectory = $this->getSourceDirectory() . '/routes';
        $this->createDirectory($routesDirectory);

        // Generate API routes
        $apiRoutesPath = $routesDirectory . '/api.php';
        $apiStub = $this->getStub('routes/api');
        $apiContent = $this->populateStub($apiStub, true);
        $apiResult = $this->writeFile($apiRoutesPath, $apiContent);

        // Generate web routes if not API only
        $webResult = true;
        if (!$this->isApiOnly) {
            $webRoutesPath = $routesDirectory . '/web.php';
            $webStub = $this->getStub('routes/web');
            $webContent = $this->populateStub($webStub, false);
            $webResult = $this->writeFile($webRoutesPath, $webContent);
        }

        return $apiResult && $webResult;
    }

    /**
     * Populate the route stub with the package data.
     */
    protected function populateStub(string $stub, bool $isApi): string
    {
        $modelVariable = Str::snake(Str::pluralStudly($this->modelName));
        $controllerName = $this->modelName . 'Controller';

        // Build the correct namespace for domain packages
        $namespace = '';
        if ($this->packageType === 'domain') {
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev
            $namespace = $domainBaseNamespace . '\\' . $this->packageName . '\\Http\\Controllers';
        } else {
            $namespace = $this->namespace . '\\Http\\Controllers';
        }

        return $this->replaceTemplate($stub, [
            'namespace' => $namespace,
            'controllerName' => $controllerName,
            'modelVariable' => $modelVariable,
            'apiVersion' => $this->apiVersion,
        ]);
    }
}
