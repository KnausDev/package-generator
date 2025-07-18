<?php

namespace KnausDev\PackageGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use KnausDev\PackageGenerator\FieldTypes\StringField;
use KnausDev\PackageGenerator\FieldTypes\IntegerField;
use KnausDev\PackageGenerator\FieldTypes\TextField;
use KnausDev\PackageGenerator\FieldTypes\BooleanField;
use KnausDev\PackageGenerator\FieldTypes\FloatField;
use KnausDev\PackageGenerator\FieldTypes\FileField;
use KnausDev\PackageGenerator\Generators\ModelGenerator;
use KnausDev\PackageGenerator\Generators\MigrationGenerator;
use KnausDev\PackageGenerator\Generators\ControllerGenerator;
use KnausDev\PackageGenerator\Generators\ServiceGenerator;
use KnausDev\PackageGenerator\Generators\RequestGenerator;
use KnausDev\PackageGenerator\Generators\ResourceGenerator;
use KnausDev\PackageGenerator\Generators\RouteGenerator;
use KnausDev\PackageGenerator\Generators\VueGenerator;
use KnausDev\PackageGenerator\Generators\DomainComposerGenerator;
use KnausDev\PackageGenerator\Support\FieldDefinition;

class MakePackageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knausdev:make-package
                            {name : The name of the package}
                            {--type=domain : Package type (composer or domain)}
                            {--namespace= : The namespace of the package (default from config)}
                            {--path= : Optional custom path for package}
                            {--model= : Optional model name (default derives from package name)}
                            {--api-only : Whether the package is API only (no frontend)}
                            {--api-version=v1 : API version to use}
                            {--no-fields : Skip field collection and create empty model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new package with KnausDev structure and components';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Package properties.
     */
    protected string $packageName;
    protected string $packageType;
    protected string $namespace;
    protected string $modelName;
    protected string $packagePath;
    protected array $fields = [];
    protected bool $isApiOnly;
    protected string $apiVersion;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->packageName = $this->argument('name');
        $this->packageType = $this->option('type');
        $this->namespace = $this->option('namespace') ?: config('package-generator.namespace', 'KnausDev');
        $this->modelName = $this->option('model') ?: Str::singular(Str::studly($this->packageName));
        $this->isApiOnly = $this->option('api-only') || config('package-generator.api_only', false);
        $this->apiVersion = $this->option('api-version');

        // Validate package type
        if (!in_array($this->packageType, ['composer', 'domain'])) {
            $this->error('Package type must be either "composer" or "domain"');
            return Command::FAILURE;
        }

        // Set package path based on type
        $this->packagePath = $this->getPackagePath();

        // Confirm creation
        if (!$this->confirmCreation()) {
            return Command::FAILURE;
        }

        // Create package structure
        $this->createPackageStructure();

        // Collect field definitions
        $this->collectFields();

        // Generate files
        $this->generateFiles();

        // Save field definitions
        $this->saveFieldDefinitions();

        // Provide next steps
        $this->showNextSteps();

        return Command::SUCCESS;
    }

    /**
     * Get the package path based on type and options.
     */
    protected function getPackagePath(): string
    {
        if ($path = $this->option('path')) {
            return $path;
        }

        if ($this->packageType === 'composer') {
            return base_path('packages/' . Str::slug($this->namespace) . '/' . Str::slug($this->packageName));
        }

        // For domain packages, create proper namespace directories
        // For example: domains/KnausDev/User/
        return base_path('domains/' . str_replace('\\', '/', $this->namespace) . '/' . $this->packageName);
    }

    /**
     * Confirm package creation.
     */
    protected function confirmCreation(): bool
    {
        $this->info('Package Generator');
        $this->info('=================');
        $this->info("Creating a new {$this->packageType} package:");
        $this->info("- Name: {$this->packageName}");
        $this->info("- Namespace: {$this->namespace}");
        $this->info("- Model: {$this->modelName}");
        $this->info("- Path: {$this->packagePath}");
        $this->info("- API Only: " . ($this->isApiOnly ? 'Yes' : 'No'));
        $this->info("- API Version: {$this->apiVersion}");

        if ($this->filesystem->exists($this->packagePath)) {
            $this->warn("The package directory already exists: {$this->packagePath}");

            if (!$this->confirm('Do you want to continue anyway?')) {
                return false;
            }
        }

        return $this->confirm('Do you want to continue with package creation?', true);
    }

    /**
     * Create the package directory structure.
     */
    protected function createPackageStructure(): void
    {
        $this->info('Creating package structure...');

        // Base directories
        $baseDirectories = [
            $this->packagePath,
            $this->packagePath . '/Models',
            $this->packagePath . '/Http/Controllers',
            $this->packagePath . '/Http/Requests',
            $this->packagePath . '/Http/Resources',
            $this->packagePath . '/Services',
            $this->packagePath . '/database/migrations',
            $this->packagePath . '/database/seeders',
            $this->packagePath . '/routes',
        ];

        // Add resources directories if not API only
        if (!$this->isApiOnly) {
            $baseDirectories[] = $this->packagePath . '/resources/js/components';
            $baseDirectories[] = $this->packagePath . '/resources/views';
        }

        // Add src directory for composer packages
        if ($this->packageType === 'composer') {
            array_walk($baseDirectories, function (&$dir) {
                if ($dir !== $this->packagePath) {
                    $dir = preg_replace('/^' . preg_quote($this->packagePath, '/') . '/', $this->packagePath . '/src', $dir);
                }
            });
        }

        // Create directories
        foreach ($baseDirectories as $directory) {
            if (!$this->filesystem->isDirectory($directory)) {
                $this->filesystem->makeDirectory($directory, 0755, true);
                $this->info("Created directory: {$directory}");
            }
        }

        // Create .definitions directory for storing field definitions
        $definitionsDir = $this->packagePath . '/.definitions';
        if (!$this->filesystem->isDirectory($definitionsDir)) {
            $this->filesystem->makeDirectory($definitionsDir, 0755, true);
        }
    }

    /**
     * Collect field definitions.
     */
    protected function collectFields(): void
    {
        if ($this->option('no-fields') || config('package-generator.no_fields', false)) {
            $this->info('Skipping field collection as --no-fields option is set');
            return;
        }

        $this->info('Define the fields for your model (Enter empty name to finish):');

        $fieldTypes = [
            'string' => 'String (max 255 characters)',
            'integer' => 'Integer',
            'text' => 'Text (long text)',
            'boolean' => 'Boolean (true/false)',
            'float' => 'Float (decimal)',
            'file' => 'File',
        ];

        $continue = true;

        while ($continue) {
            $fieldName = $this->ask('Field name');

            if (empty($fieldName)) {
                $continue = false;
                continue;
            }

            // Validate field name
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $fieldName)) {
                $this->error('Field name must start with a lowercase letter and contain only lowercase letters, numbers, and underscores');
                continue;
            }

            // Check for duplicate field
            foreach ($this->fields as $field) {
                if ($field->getName() === $fieldName) {
                    $this->error("A field with name '{$fieldName}' already exists");
                    continue 2;
                }
            }

            $fieldType = $this->choice(
                'Field type',
                $fieldTypes,
                0
            );

            $nullable = $this->confirm('Is this field nullable?', false);

            $description = $this->ask('Field description (optional)');

            // Get default validation rules for the field type
            $validationRules = config("package-generator.validation.{$fieldType}", '');
            $validationRules = $this->ask('Validation rules (comma separated)', $validationRules);

            // Create the appropriate field type
            switch ($fieldType) {
                case 'string':
                    $maxLength = $this->ask('Maximum length', 255);
                    $this->fields[] = new StringField(
                        $fieldName,
                        $nullable,
                        null,
                        $description,
                        explode(',', $validationRules),
                        (int) $maxLength
                    );
                    break;

                case 'integer':
                    $min = $this->ask('Minimum value (optional)');
                    $max = $this->ask('Maximum value (optional)');
                    $this->fields[] = new IntegerField(
                        $fieldName,
                        $nullable,
                        null,
                        $description,
                        explode(',', $validationRules),
                        !empty($min) ? (int) $min : null,
                        !empty($max) ? (int) $max : null
                    );
                    break;

                case 'text':
                    $richEditor = $this->confirm('Use rich text editor?', false);
                    $this->fields[] = new TextField(
                        $fieldName,
                        $nullable,
                        null,
                        $description,
                        explode(',', $validationRules),
                        $richEditor
                    );
                    break;

                case 'boolean':
                    $default = $this->confirm('Default value', false);
                    $this->fields[] = new BooleanField(
                        $fieldName,
                        $nullable,
                        $default,
                        $description,
                        explode(',', $validationRules)
                    );
                    break;

                case 'float':
                    $decimals = $this->ask('Number of decimal places', 2);
                    $min = $this->ask('Minimum value (optional)');
                    $max = $this->ask('Maximum value (optional)');
                    $this->fields[] = new FloatField(
                        $fieldName,
                        $nullable,
                        null,
                        $description,
                        explode(',', $validationRules),
                        (int) $decimals,
                        !empty($min) ? (float) $min : null,
                        !empty($max) ? (float) $max : null
                    );
                    break;

                case 'file':
                    $maxSize = $this->ask('Maximum file size in KB', 10240);
                    $allowedTypes = $this->ask('Allowed file types (comma separated)', 'pdf,doc,docx,xls,xlsx');
                    $this->fields[] = new FileField(
                        $fieldName,
                        $nullable,
                        null,
                        $description,
                        explode(',', $validationRules),
                        (int) $maxSize,
                        explode(',', $allowedTypes)
                    );
                    break;
            }

            $this->info("Field '{$fieldName}' added");
        }
    }

    /**
     * Generate all files for the package.
     */
    protected function generateFiles(): void
    {
        $this->info('Generating package files...');

        // Generate composer.json if it's a composer package
        if ($this->packageType === 'composer') {
            $this->generateComposerJson();
        } else {
            // For domain packages, use the domain composer generator
            $domainComposerGenerator = new DomainComposerGenerator(
                $this->packagePath,
                $this->namespace,
                $this->modelName,
                $this->fields,
                $this->isApiOnly,
                $this->apiVersion,
                $this->packageType
            );
            $domainComposerGenerator->generate();
        }

        // Generate service provider
        $this->generateServiceProvider();

        // Get source directory based on package type
        $sourceDir = $this->packageType === 'composer' ? $this->packagePath . '/src' : $this->packagePath;

        // Generate model
        $modelGenerator = new ModelGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $modelGenerator->generate();

        // Generate migration
        $migrationGenerator = new MigrationGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $migrationGenerator->generate();

        // Generate service
        $serviceGenerator = new ServiceGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $serviceGenerator->generate();

        // Generate controller
        $controllerGenerator = new ControllerGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $controllerGenerator->generate();

        // Generate request
        $requestGenerator = new RequestGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $requestGenerator->generate();

        // Generate resource
        $resourceGenerator = new ResourceGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $resourceGenerator->generate();

        // Generate routes
        $routeGenerator = new RouteGenerator(
            $this->packagePath,
            $this->namespace,
            $this->modelName,
            $this->fields,
            $this->isApiOnly,
            $this->apiVersion,
            $this->packageType
        );
        $routeGenerator->generate();

        // Generate Vue components if not API only
        if (!$this->isApiOnly) {
            $vueGenerator = new VueGenerator(
                $this->packagePath,
                $this->namespace,
                $this->modelName,
                $this->fields,
                $this->isApiOnly,
                $this->apiVersion,
                $this->packageType
            );
            $vueGenerator->generate();
        }
    }

    /**
     * Generate composer.json for composer packages.
     */
    protected function generateComposerJson(): void
    {
        $composerJsonPath = $this->packagePath . '/composer.json';

        // For domain packages, determine the correct namespace
        $psr4Namespace = $this->namespace . '\\';
        $packageName = Str::slug($this->packageName);

        if ($this->packageType === 'domain') {
            $baseNamespace = explode('\\', $this->namespace);
            $domainBaseNamespace = $baseNamespace[0]; // e.g., KnausDev
            $psr4Namespace = $domainBaseNamespace . '\\' . $this->packageName . '\\';
            $packageName = Str::slug($domainBaseNamespace) . '/' . Str::slug($this->packageName);
        } else {
            $packageName = Str::slug($this->namespace) . '/' . Str::slug($this->packageName);
        }

        $composerJson = [
            'name' => $packageName,
            'description' => 'A package for ' . $this->packageName,
            'type' => 'library',
            'license' => 'MIT',
            'autoload' => [
                'psr-4' => [
                    $psr4Namespace => $this->packageType === 'composer' ? 'src/' : ''
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
                'php' => '^8.1',
                'illuminate/support' => '^11.0'
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true
        ];

        $this->filesystem->put(
            $composerJsonPath,
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Created: {$composerJsonPath}");
    }

    /**
     * Generate service provider.
     */
    protected function generateServiceProvider(): void
    {
        $providerName = Str::studly($this->packageName) . 'ServiceProvider';

        if ($this->packageType === 'composer') {
            $providerPath = $this->packagePath . '/src/' . $providerName . '.php';
        } else {
            $providerPath = $this->packagePath . '/' . $providerName . '.php';
        }

        $content = <<<EOT
<?php

namespace {$this->namespace};

use Illuminate\Support\ServiceProvider;

class {$providerName} extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        \$this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        \$this->loadRoutesFrom(__DIR__ . '/routes/api.php');

EOT;

        if (!$this->isApiOnly) {
            $content .= <<<EOT
        \$this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        \$this->loadViewsFrom(__DIR__ . '/resources/views', '{$this->packageName}');

        \$this->publishes([
            __DIR__ . '/resources/js' => resource_path('js/vendor/{$this->packageName}'),
        ], '{$this->packageName}-assets');

EOT;
        }

        $content .= <<<EOT
    }
}
EOT;

        $this->filesystem->put($providerPath, $content);

        $this->info("Created: {$providerPath}");
    }

    /**
     * Save field definitions.
     */
    protected function saveFieldDefinitions(): void
    {
        $fieldDefinition = new FieldDefinition($this->packagePath, $this->modelName);
        $fieldDefinition->saveFields($this->fields);

        $this->info('Field definitions saved');
    }

    /**
     * Show next steps.
     */
    protected function showNextSteps(): void
    {
        $this->info('');
        $this->info('Package created successfully!');
        $this->info('');

        if ($this->packageType === 'composer') {
            $this->info('Next steps:');
            $this->info('1. Add your package to your main Laravel project\'s composer.json:');
            $this->info('   "require": {');
            $this->info('       "' . Str::slug($this->namespace) . '/' . Str::slug($this->packageName) . '": "*"');
            $this->info('   },');
            $this->info('   "repositories": [');
            $this->info('       {');
            $this->info('           "type": "path",');
            $this->info('           "url": "./packages/' . $this->namespace . '/' . $this->packageName . '"');
            $this->info('       }');
            $this->info('   ]');
            $this->info('');
            $this->info('2. Run composer update');
            $this->info('');
            $this->info('3. The package should now be installed and ready to use!');
        } else {
            $this->info('Next steps:');
            $this->info('1. Add the service provider to your config/app.php or bootstrap/providers.php (depends on your Laravel version):');
            $this->info('   ' . $this->namespace . '\\' . Str::studly($this->packageName) . 'ServiceProvider::class,');
            $this->info('');
            $this->info('2. Make sure your composer.json has the Wikimedia Merge Plugin configured:');
            $this->info('   "extra": {');
            $this->info('       "merge-plugin": {');
            $this->info('           "include": [');
            $this->info('               "domains/*/composer.json"');
            $this->info('           ],');
            $this->info('           "recurse": true,');
            $this->info('           "replace": false,');
            $this->info('           "merge-dev": true,');
            $this->info('           "merge-extra": true');
            $this->info('       }');
            $this->info('   }');
            $this->info('');
            $this->info('3. Run the migrations:');
            $this->info('   php artisan migrate');
        }
    }
}
