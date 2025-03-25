<?php

namespace KnausDev\PackageGenerator;

use Illuminate\Support\ServiceProvider;
use KnausDev\PackageGenerator\Commands\MakePackageCommand;
use KnausDev\PackageGenerator\Commands\PackageFieldCommand;
use KnausDev\PackageGenerator\Commands\PackageModelCommand;
use KnausDev\PackageGenerator\Commands\PublishStubsCommand;
use KnausDev\PackageGenerator\Commands\DomainMigrateCommand;
use KnausDev\PackageGenerator\Commands\DomainRoutesCommand;
use KnausDev\PackageGenerator\Commands\InstallCommand;

class PackageGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/package-generator.php', 'package-generator'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePackageCommand::class,
                PackageFieldCommand::class,
                PackageModelCommand::class,
                PublishStubsCommand::class,
                DomainMigrateCommand::class,
                DomainRoutesCommand::class,
                InstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/package-generator.php' => config_path('package-generator.php'),
            ], 'package-generator-config');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/vendor/knausdev/package-generator'),
            ], 'package-generator-stubs');
        }
    }
}
