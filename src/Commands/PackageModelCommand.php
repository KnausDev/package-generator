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
use KnausDev\PackageGenerator\Support\FieldDefinition;
use KnausDev\PackageGenerator\Support\PackageAnalyzer;

class PackageModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knausdev:package-model
                            {package : Package name}
                            {model : Model name}
                            {--type=composer : Package type (composer or domain)}
                            {--namespace= : Package namespace (default from config)}
                            {--path= : Optional custom path for package}
                            {--api-only : Whether the model is API only (no frontend)}
                            {--api-version=v1 : API version to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new model to an existing package';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Command properties.
     */
    protected string $packageName;
    protected string $modelName;
    protected string $packageType;
    protected string $namespace;
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
        $this->packageName = $this->argument('package');
        $this->modelName = $this->argument('model');
        $this->packageType = $this->option('type');
        $this->namespace = $this->option('namespace') ?: config('package-generator.namespace', 'KnausDev');
        $this->isApiOnly = $this->option('api-only');
        $this->apiVersion = $this->option('api-version');

        // Validate package type
        if (!in_array($this->packageType, ['composer', 'domain'])) {
            $this->error('Package type must be either "composer" or "domain"');
            return Command::FAILURE;
        }

        // Set package path based on type
        $this->packagePath = $this->getPackagePath();

        // Check if package exists
        if (!$this->filesystem->exists($this->packagePath)) {
            $this->error("Package does not exist at: {$this->packagePath}");
            return Command::FAILURE;
        }

        // Analyze the package
        $analyzer = new PackageAnalyzer($this->packagePath, $this->packageType);

        // Check if the namespace option was provided or try to detect it
        if (!$this->namespace) {
            $detectedNamespace = $analyzer->getNamespace();

            if (!$detectedNamespace) {
                $this->error('Could not determine package namespace. Please specify using --namespace option.');
                return Command::FAILURE;
            }

            $this->namespace = $detectedNamespace;
        }

        // Check if the model already exists
        if ($analyzer->modelExists($this->modelName)) {
            $this->error("Model '{$this->modelName}' already exists in this package");
            return Command::FAILURE;
        }

        // Confirm creation
        if (!$this->confirmCreation()) {
            return Command::FAILURE;
        }

        // Collect field definitions
        $this->collectFields();

        // Generate files
        $this->generateFiles();

        // Save field definitions
        $this->saveFieldDefinitions();

        $this->info("Model '{$this->modelName}' added successfully to package '{$this->packageName}'");

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
     * Confirm model creation.
     */
    protected function confirmCreation(): bool
    {
        $this->info('Package Model Generator');
        $this->info('======================');
        $this->info("Adding a new model to package:");
        $this->info("- Package: {$this->packageName}");
        $this->info("- Model: {$this->modelName}");
        $this->info("- Namespace: {$this->namespace}");
        $this->info("- Path: {$this->packagePath}");
        $this->info("- API Only: " . ($this->isApiOnly ? 'Yes' : 'No'));
        $this->info("- API Version: {$this->apiVersion}");

        return $this->confirm('Do you want to continue?', true);
    }

    /**
     * Collect field definitions.
     */
    protected function collectFields(): void
    {
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
                        (int)$maxLength
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
                        !empty($min) ? (int)$min : null,
                        !empty($max) ? (int)$max : null
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
                        (int)$decimals,
                        !empty($min) ? (float)$min : null,
                        !empty($max) ? (float)$max : null
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
                        (int)$maxSize,
                        explode(',', $allowedTypes)
                    );
                    break;
            }

            $this->info("Field '{$fieldName}' added");
        }
    }

    /**
     * Generate all files for the model.
     */
    protected function generateFiles(): void
    {
        $this->info('Generating model files...');

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
     * Save field definitions.
     */
    protected function saveFieldDefinitions(): void
    {
        $fieldDefinition = new FieldDefinition($this->packagePath, $this->modelName);
        $fieldDefinition->saveFields($this->fields);

        $this->info('Field definitions saved');
    }
}
