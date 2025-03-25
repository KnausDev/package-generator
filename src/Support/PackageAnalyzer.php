<?php

namespace KnausDev\PackageGenerator\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\FieldTypeFactory;

class PackageAnalyzer
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
     * The package type (composer or domain).
     */
    protected string $packageType;

    /**
     * Create a new package analyzer instance.
     */
    public function __construct(string $packagePath, string $packageType = 'composer')
    {
        $this->filesystem = new Filesystem();
        $this->packagePath = $packagePath;
        $this->packageType = $packageType;
    }

    /**
     * Check if a package exists.
     */
    public function exists(): bool
    {
        return $this->filesystem->exists($this->packagePath);
    }

    /**
     * Get the package source directory.
     */
    public function getSourceDirectory(): string
    {
        return $this->packageType === 'composer' ? $this->packagePath . '/src' : $this->packagePath;
    }

    /**
     * Get the package namespace.
     */
    public function getNamespace(): ?string
    {
        $sourceDir = $this->getSourceDirectory();

        // Try to determine from service provider
        $files = $this->filesystem->glob($sourceDir . '/*ServiceProvider.php');

        if (!empty($files)) {
            $serviceProviderPath = $files[0];
            $content = $this->filesystem->get($serviceProviderPath);

            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                return $matches[1];
            }
        }

        // Try to determine from composer.json
        if ($this->packageType === 'composer' && $this->filesystem->exists($this->packagePath . '/composer.json')) {
            $composerJson = json_decode($this->filesystem->get($this->packagePath . '/composer.json'), true);

            if (isset($composerJson['autoload']['psr-4']) && is_array($composerJson['autoload']['psr-4'])) {
                $psr4 = $composerJson['autoload']['psr-4'];
                $namespace = key($psr4);
                return rtrim($namespace, '\\');
            }
        }

        return null;
    }

    /**
     * Get available models in the package.
     */
    public function getModels(): array
    {
        $sourceDir = $this->getSourceDirectory();
        $modelsDir = $sourceDir . '/Models';

        if (!$this->filesystem->isDirectory($modelsDir)) {
            return [];
        }

        $models = [];
        $files = $this->filesystem->files($modelsDir);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $models[] = $file->getFilenameWithoutExtension();
            }
        }

        return $models;
    }

    /**
     * Check if a specific model exists.
     */
    public function modelExists(string $modelName): bool
    {
        $sourceDir = $this->getSourceDirectory();
        $modelPath = $sourceDir . '/Models/' . $modelName . '.php';

        return $this->filesystem->exists($modelPath);
    }

    /**
     * Get field definitions for a model.
     */
    public function getModelFields(string $modelName): array
    {
        $fieldDefinition = new FieldDefinition($this->packagePath, $modelName);

        if (!$fieldDefinition->exists()) {
            return [];
        }

        return $fieldDefinition->loadFields();
    }

    /**
     * Check if a package uses API only mode.
     */
    public function isApiOnly(): bool
    {
        $sourceDir = $this->getSourceDirectory();
        $webRoutesPath = $sourceDir . '/routes/web.php';

        return !$this->filesystem->exists($webRoutesPath);
    }

    /**
     * Get the API version used by the package.
     */
    public function getApiVersion(): ?string
    {
        $sourceDir = $this->getSourceDirectory();
        $apiRoutesPath = $sourceDir . '/routes/api.php';

        if (!$this->filesystem->exists($apiRoutesPath)) {
            return null;
        }

        $content = $this->filesystem->get($apiRoutesPath);

        if (preg_match('/prefix\s*\(\s*[\'"]api\/([^\'"]+)[\'"]\s*\)/', $content, $matches)) {
            return $matches[1];
        }

        return 'v1'; // Default value
    }

    /**
     * Get model dependencies and relationships.
     */
    public function getModelRelationships(string $modelName): array
    {
        $sourceDir = $this->getSourceDirectory();
        $modelPath = $sourceDir . '/Models/' . $modelName . '.php';

        if (!$this->filesystem->exists($modelPath)) {
            return [];
        }

        $content = $this->filesystem->get($modelPath);
        $relationships = [];

        // Extract relationship methods
        $relationTypes = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphMany', 'morphToMany'];

        foreach ($relationTypes as $relationType) {
            if (preg_match_all('/public\s+function\s+([a-zA-Z0-9_]+)\s*\(\s*\).*?' . $relationType . '\s*\(\s*([^,\)]+)/', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $relationName = $match[1];
                    $relatedModel = trim($match[2], '\'"');

                    if (Str::contains($relatedModel, '::class')) {
                        $parts = explode('::', $relatedModel);
                        $relatedModel = array_pop($parts);
                    }

                    $relationships[] = [
                        'name' => $relationName,
                        'type' => $relationType,
                        'related_model' => $relatedModel,
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Get package metadata.
     */
    public function getPackageMetadata(): array
    {
        $metadata = [
            'name' => basename($this->packagePath),
            'type' => $this->packageType,
            'namespace' => $this->getNamespace(),
            'models' => $this->getModels(),
            'api_only' => $this->isApiOnly(),
            'api_version' => $this->getApiVersion(),
        ];

        // Add model details
        $modelDetails = [];

        foreach ($metadata['models'] as $modelName) {
            $modelDetails[$modelName] = [
                'fields' => array_map(function ($field) {
                    return $field->toArray();
                }, $this->getModelFields($modelName)),
                'relationships' => $this->getModelRelationships($modelName),
            ];
        }

        $metadata['model_details'] = $modelDetails;

        return $metadata;
    }
}
