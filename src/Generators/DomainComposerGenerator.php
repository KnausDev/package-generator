<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;

class DomainComposerGenerator extends BaseGenerator
{
    /**
     * Generate the composer.json file for a domain package.
     */
    public function generate(): bool
    {
        $composerJsonPath = $this->packagePath . '/composer.json';

        // Determine the correct namespace for domain packages
        $baseNamespace = explode('\\', $this->namespace);
        $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev
        $psr4Namespace = $domainBaseNamespace . '\\' . $this->packageName . '\\';

        $composerJson = [
            'name' => Str::slug($domainBaseNamespace) . '/' . Str::slug($this->packageName),
            'description' => 'Domain package for ' . $this->packageName,
            'type' => 'library',
            'license' => 'MIT',
            'autoload' => [
                'psr-4' => [
                    $psr4Namespace => ''
                ]
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        $psr4Namespace . Str::studly($this->packageName) . 'ServiceProvider'
                    ]
                ]
            ],
            'require' => [
                'php' => '^8.1'
            ]
        ];

        $result = $this->filesystem->put(
            $composerJsonPath,
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($result) {
            $this->info("Created composer.json for domain package: {$composerJsonPath}");
        } else {
            $this->error("Failed to create composer.json for domain package.");
        }

        return $result;
    }
}
