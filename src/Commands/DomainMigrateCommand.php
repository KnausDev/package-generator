<?php

namespace KnausDev\PackageGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DomainMigrateCommand extends Command
{
    protected $signature = 'knausdev:domain-migrate
                           {domain? : Specific domain to migrate (runs all domains if not specified)}
                           {--fresh : Wipe the database and run all migrations}
                           {--seed : Seed the database after migration}';

    protected $description = 'Run migrations for domain packages';

    public function handle()
    {
        $domainPath = base_path('domains');

        if (!File::isDirectory($domainPath)) {
            $this->error("Domains directory not found at: {$domainPath}");
            return Command::FAILURE;
        }

        $specificDomain = $this->argument('domain');
        $migrationPaths = [];

        if ($specificDomain) {
            // Run migrations for a specific domain
            $domainDirectory = $domainPath . '/' . $specificDomain;

            if (!File::isDirectory($domainDirectory)) {
                $this->error("Domain not found: {$specificDomain}");
                return Command::FAILURE;
            }

            $this->gatherMigrationPaths($domainDirectory, $migrationPaths);
        } else {
            // Run migrations for all domains
            $domains = File::directories($domainPath);

            foreach ($domains as $domain) {
                $this->gatherMigrationPaths($domain, $migrationPaths);
            }
        }

        if (empty($migrationPaths)) {
            $this->info('No migration paths found.');
            return Command::SUCCESS;
        }

        // Prepare the migrate command
        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        if ($this->option('seed')) {
            $command .= ' --seed';
        }

        // Execute the command
        $this->info("Running: {$command}");
        if ($this->option('fresh')) {
            $this->call('migrate:fresh');
        }
        $this->call('migrate');
        $this->call('migrate', ['--path' => $migrationPaths]);

        return Command::SUCCESS;
    }

    /**
     * Gather migration paths from a domain directory.
     */
    protected function gatherMigrationPaths(string $domainDirectory, array &$migrationPaths): void
    {
        $domainName = basename($domainDirectory);
        $this->info("Checking domain: {$domainName}");

        // For domain/database/migrations (domain-specific migrations)
        // The domain structure should be: domains/KnausDev/User/ where User is the actual domain
        $migrationPath = $domainDirectory . '/database/migrations';

        if (File::isDirectory($migrationPath)) {
            $relativePath = Str::replaceFirst(base_path() . '/', '', $migrationPath);
            $migrationPaths[] = $relativePath;
            $this->info("Found migrations in: {$relativePath}");
        }

        // Check for package-specific migrations within a domain
        // This would handle domains/KnausDev/User/SomePackage/database/migrations
        $packages = File::directories($domainDirectory);

        foreach ($packages as $package) {
            $packageName = basename($package);
            $packageMigrationPath = $package . '/database/migrations';

            if (File::isDirectory($packageMigrationPath)) {
                $relativePath = Str::replaceFirst(base_path() . '/', '', $packageMigrationPath);
                $migrationPaths[] = $relativePath;
                $this->info("Found migrations in: {$relativePath}");
            }
        }
    }
}
