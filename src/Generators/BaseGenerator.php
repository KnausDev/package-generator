<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseGenerator
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
     * The package namespace.
     */
    protected string $namespace;

    /**
     * The model name.
     */
    protected string $modelName;

    /**
     * The package name.
     */
    protected string $packageName;

    /**
     * The fields for the model.
     */
    protected array $fields;

    /**
     * Is this package for API only?
     */
    protected bool $isApiOnly;

    /**
     * The API version to use.
     */
    protected string $apiVersion;

    /**
     * The package type (composer or domain).
     */
    protected string $packageType;

    /**
     * Create a new generator instance.
     */
    public function __construct(
        string $packagePath,
        string $namespace,
        string $modelName,
        array $fields = [],
        bool $isApiOnly = false,
        string $apiVersion = 'v1',
        string $packageType = 'composer'
    ) {
        $this->filesystem = new Filesystem();
        $this->packagePath = $packagePath;
        $this->namespace = $namespace;
        $this->modelName = $modelName;
        $this->fields = $fields;
        $this->isApiOnly = $isApiOnly;
        $this->apiVersion = $apiVersion;
        $this->packageType = $packageType;

        // Extract the package/domain name from the package path
        $pathParts = explode('/', rtrim($packagePath, '/'));
        $this->packageName = end($pathParts);
    }

    /**
     * Get the table name from the model.
     */
    protected function getTableName(): string
    {
        return Str::snake(Str::pluralStudly($this->modelName));
    }

    /**
     * Get stub path.
     *
     * Looks for published stubs first, then falls back to package stubs.
     */
    protected function getStubPath(string $stubName): string
    {
        // Check for published stubs first
        $publishedStubPath = base_path("stubs/vendor/knausdev/package-generator/{$stubName}.stub");

        if (file_exists($publishedStubPath)) {
            return $publishedStubPath;
        }

        // Fall back to package stubs
        return __DIR__ . "/../../stubs/{$stubName}.stub";
    }

    /**
     * Get stub contents.
     */
    protected function getStub(string $stubName): string
    {
        return $this->filesystem->get($this->getStubPath($stubName));
    }

    /**
     * Replace template variables in stub.
     */
    protected function replaceTemplate(string $stub, array $replacements): string
    {
        foreach ($replacements as $search => $replace) {
            $stub = str_replace('{{ ' . $search . ' }}', $replace, $stub);
        }

        return $stub;
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

    /**
     * Write content to file.
     */
    protected function writeFile(string $path, string $content): bool
    {
        $this->createDirectory(dirname($path));

        $result = $this->filesystem->put($path, $content);

        if ($result) {
            $relativePath = str_replace(base_path() . '/', '', $path);
            $this->info("Created: {$relativePath}");
        } else {
            $this->error("Failed to create: {$path}");
        }

        return $result;
    }

    /**
     * Output an info message.
     */
    protected function info(string $message): void
    {
        // Can be overridden to use a logger or console output
        echo "[INFO] {$message}" . PHP_EOL;
    }

    /**
     * Output an error message.
     */
    protected function error(string $message): void
    {
        // Can be overridden to use a logger or console output
        echo "[ERROR] {$message}" . PHP_EOL;
    }

    /**
     * Get the package's source directory.
     */
    protected function getSourceDirectory(): string
    {
        return $this->packageType === 'composer' ? "{$this->packagePath}/src" : $this->packagePath;
    }

    /**
     * Generate the content.
     */
    abstract public function generate(): bool;
}
