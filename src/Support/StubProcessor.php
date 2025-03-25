<?php

namespace KnausDev\PackageGenerator\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class StubProcessor
{
    /**
     * The filesystem instance.
     */
    protected Filesystem $filesystem;

    /**
     * Create a new stub processor instance.
     */
    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Get stub path.
     *
     * Looks for published stubs first, then falls back to package stubs.
     */
    public function getStubPath(string $stubName): string
    {
        // Check for published stubs first
        $publishedStubPath = base_path("stubs/vendor/knausdev/package-generator/{$stubName}.stub");

        if ($this->filesystem->exists($publishedStubPath)) {
            return $publishedStubPath;
        }

        // Fall back to package stubs
        return __DIR__ . "/../../stubs/{$stubName}.stub";
    }

    /**
     * Get stub contents.
     */
    public function getStub(string $stubName): string
    {
        return $this->filesystem->get($this->getStubPath($stubName));
    }

    /**
     * Replace template variables in stub.
     */
    public function processStub(string $stub, array $replacements): string
    {
        foreach ($replacements as $search => $replace) {
            $stub = str_replace('{{ ' . $search . ' }}', $replace, $stub);
        }

        return $stub;
    }

    /**
     * Process a stub file and write it to the destination.
     */
    public function processStubToFile(string $stubName, string $destination, array $replacements): bool
    {
        $stub = $this->getStub($stubName);
        $content = $this->processStub($stub, $replacements);

        $this->ensureDirectoryExists(dirname($destination));

        return $this->filesystem->put($destination, $content);
    }

    /**
     * Ensure directory exists.
     */
    public function ensureDirectoryExists(string $directory): void
    {
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Publish stubs for customization.
     */
    public function publishStubs(string $destination): bool
    {
        $sourcePath = __DIR__ . '/../../stubs';

        if (!$this->filesystem->isDirectory($sourcePath)) {
            return false;
        }

        $this->ensureDirectoryExists($destination);

        $files = $this->filesystem->allFiles($sourcePath);

        foreach ($files as $file) {
            $targetPath = $destination . '/' . $file->getRelativePathname();
            $this->ensureDirectoryExists(dirname($targetPath));
            $this->filesystem->copy($file->getPathname(), $targetPath);
        }

        return true;
    }
}
