<?php

namespace KnausDev\PackageGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'knausdev:install
                           {--force : Force overwrite of existing files}';

    protected $description = 'Set up your project to work with KnausDev domain packages';

    public function handle()
    {
        $this->info('Installing KnausDev Package Generator...');

        // Create directories
        $this->createDirectories();

        // Update composer.json for domain package support
        $this->updateComposerConfig();

        // Publish configuration
        $this->call('vendor:publish', [
            '--provider' => 'KnausDev\\PackageGenerator\\PackageGeneratorServiceProvider',
            '--tag' => 'package-generator-config',
            '--force' => $this->option('force'),
        ]);

        $this->info('Installation complete!');
        $this->info('You can now use the following commands:');
        $this->line('php artisan knausdev:make-package PackageName');
        $this->line('php artisan knausdev:package-model PackageName ModelName');
        $this->line('php artisan knausdev:package-field add|update|remove PackageName ModelName');
        $this->line('php artisan knausdev:domain-migrate');
        $this->line('php artisan knausdev:domain-routes');

        return Command::SUCCESS;
    }

    /**
     * Create necessary directories.
     */
    protected function createDirectories(): void
    {
        $directories = [
            base_path('packages'),
            base_path('domains'),
            // Create the base KnausDev directory to maintain namespace structure
            base_path('domains/KnausDev'),
        ];

        foreach ($directories as $directory) {
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->info("Created directory: {$directory}");
            }
        }
    }

    /**
     * Update composer.json to add merge-plugin configuration.
     */
    protected function updateComposerConfig(): void
    {
        $composerJsonPath = base_path('composer.json');

        if (!File::exists($composerJsonPath)) {
            $this->error("composer.json not found in project root.");
            return;
        }

        $composerJson = json_decode(File::get($composerJsonPath), true);

        // Backup original file
        File::copy($composerJsonPath, $composerJsonPath . '.backup');
        $this->info("Backed up original composer.json to: {$composerJsonPath}.backup");

        // Add merge-plugin configuration if not already present
        if (!isset($composerJson['extra']['merge-plugin'])) {
            if (!isset($composerJson['extra'])) {
                $composerJson['extra'] = [];
            }

            $composerJson['extra']['merge-plugin'] = [
                'include' => [
                    'domains/*/composer.json',
                    'domains/*/*/composer.json'
                ],
                'recurse' => true,
                'replace' => false,
                'merge-dev' => true,
                'merge-extra' => true
            ];

            // Ensure wikimedia/composer-merge-plugin is in require
            if (!isset($composerJson['require']['wikimedia/composer-merge-plugin'])) {
                if (!isset($composerJson['require'])) {
                    $composerJson['require'] = [];
                }

                $composerJson['require']['wikimedia/composer-merge-plugin'] = "^2.0";
            }

            // Write updated composer.json
            File::put(
                $composerJsonPath,
                json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->info("Updated composer.json with merge-plugin configuration.");
            $this->info("Run 'composer update' to apply changes.");
        } else {
            $this->info("composer.json already has merge-plugin configuration.");
        }
    }
}
