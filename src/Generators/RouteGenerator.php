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
        $apiResult = $this->appendToRouteFile($apiRoutesPath, $apiContent);

        // Generate web routes if not API only
        $webResult = true;
        if (!$this->isApiOnly) {
            $webRoutesPath = $routesDirectory . '/web.php';
            $webStub = $this->getStub('routes/web');
            $webContent = $this->populateStub($webStub, false);
            $webResult = $this->appendToRouteFile($webRoutesPath, $webContent);
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

    /**
     * Extract the route group from the full content, removing PHP tags and use statements.
     */
    protected function extractRouteGroup(string $content): string
    {
        // Remove PHP opening tag and use statements
        $lines = explode("\n", $content);
        $routeLines = [];
        $inRouteSection = false;

        foreach ($lines as $line) {
            // Skip PHP opening tag and use statements
            if (strpos($line, '<?php') === 0 || strpos($line, 'use ') === 0) {
                continue;
            }

            // Skip comments and empty lines at the beginning
            if (empty(trim($line)) || strpos(trim($line), '/*') === 0 || strpos(trim($line), '|') === 0 || strpos(trim($line), '*/') === 0) {
                if (!$inRouteSection) {
                    continue;
                }
            }

            // Start collecting lines when we hit the actual route content
            if (strpos($line, 'Route::') !== false || $inRouteSection) {
                $inRouteSection = true;
                $routeLines[] = $line;
            }
        }

        return implode("\n", $routeLines);
    }

    /**
     * Append routes to existing route file or create new one if it doesn't exist.
     */
    protected function appendToRouteFile(string $filePath, string $content): bool
    {
        // If file doesn't exist, create it with the basic PHP opening tag and header
        if (!file_exists($filePath)) {
            $header = "<?php\n\nuse Illuminate\Support\Facades\Route;\n";
            if (!$this->writeFile($filePath, $header)) {
                return false;
            }
        }

        // Read existing content
        $existingContent = file_get_contents($filePath);

        // Extract only the route group part from the new content (remove PHP opening tag and use statements)
        $routeGroupContent = $this->extractRouteGroup($content);

        // Check if this route group already exists to avoid duplicates
        if (strpos($existingContent, $routeGroupContent) !== false) {
            return true; // Already exists, no need to append
        }

        // Append the new route group
        $updatedContent = $existingContent . "\n" . $routeGroupContent;

        return $this->writeFile($filePath, $updatedContent);
    }


}
