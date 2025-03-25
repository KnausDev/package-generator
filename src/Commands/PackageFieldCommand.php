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
use KnausDev\PackageGenerator\Generators\RequestGenerator;
use KnausDev\PackageGenerator\Generators\ResourceGenerator;
use KnausDev\PackageGenerator\Generators\VueGenerator;
use KnausDev\PackageGenerator\Support\FieldDefinition;

class PackageFieldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knausdev:package-field
                            {action : Action to perform (add, update, remove)}
                            {package : Package name}
                            {model : Model name}
                            {--type=composer : Package type (composer or domain)}
                            {--namespace= : Package namespace (default from config)}
                            {--path= : Optional custom path for package}
                            {--field= : Field name (required for update and remove actions)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add, update, or remove fields from an existing package';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Command properties.
     */
    protected string $action;
    protected string $packageName;
    protected string $modelName;
    protected string $packageType;
    protected string $namespace;
    protected string $packagePath;
    protected ?string $fieldName;
    protected array $existingFields = [];
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
        $this->action = $this->argument('action');
        $this->packageName = $this->argument('package');
        $this->modelName = $this->argument('model');
        $this->packageType = $this->option('type');
        $this->namespace = $this->option('namespace') ?: config('package-generator.namespace', 'KnausDev');
        $this->fieldName = $this->option('field');

        // Validate action
        if (!in_array($this->action, ['add', 'update', 'remove'])) {
            $this->error('Action must be one of: add, update, remove');
            return Command::FAILURE;
        }

        // Validate package type
        if (!in_array($this->packageType, ['composer', 'domain'])) {
            $this->error('Package type must be either "composer" or "domain"');
            return Command::FAILURE;
        }

        // Check if field name is provided for update and remove actions
        if (in_array($this->action, ['update', 'remove']) && !$this->fieldName) {
            $this->error('Field name is required for update and remove actions (use --field option)');
            return Command::FAILURE;
        }

        // Set package path based on type
        $this->packagePath = $this->getPackagePath();

        // Check if package exists
        if (!$this->filesystem->exists($this->packagePath)) {
            $this->error("Package does not exist at: {$this->packagePath}");
            return Command::FAILURE;
        }

        // Load existing fields
        $fieldDefinition = new FieldDefinition($this->packagePath, $this->modelName);

        if (!$fieldDefinition->exists() && in_array($this->action, ['update', 'remove'])) {
            $this->error("No field definitions found for model: {$this->modelName}");
            return Command::FAILURE;
        }

        $this->existingFields = $fieldDefinition->loadFields();

        // Detect API only and version from package structure
        $this->detectPackageSettings();

        // Perform requested action
        switch ($this->action) {
            case 'add':
                return $this->addField($fieldDefinition);

            case 'update':
                return $this->updateField($fieldDefinition);

            case 'remove':
                return $this->removeField($fieldDefinition);
        }

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
     * Detect package settings like API only and API version.
     */
    protected function detectPackageSettings(): void
    {
        // Check for API only by looking for web routes
        $sourceDir = $this->packageType === 'composer' ? $this->packagePath . '/src' : $this->packagePath;
        $webRoutesPath = $sourceDir . '/routes/web.php';

        $this->isApiOnly = !$this->filesystem->exists($webRoutesPath);

        // Try to detect API version from routes
        $apiRoutesPath = $sourceDir . '/routes/api.php';
        if ($this->filesystem->exists($apiRoutesPath)) {
            $apiRoutesContent = $this->filesystem->get($apiRoutesPath);

            if (preg_match('/prefix\s*\(\s*[\'"]api\/([^\'"]+)[\'"]\s*\)/', $apiRoutesContent, $matches)) {
                $this->apiVersion = $matches[1];
            } else {
                $this->apiVersion = 'v1'; // Default value
            }
        }
    }

    /**
     * Add a new field to the package.
     */
    protected function addField(FieldDefinition $fieldDefinition): int
    {
        // Get field information
        $this->info('Adding a new field to model: ' . $this->modelName);

        $fieldName = $this->fieldName ?: $this->ask('Field name');

        // Validate field name
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $fieldName)) {
            $this->error('Field name must start with a lowercase letter and contain only lowercase letters, numbers, and underscores');
            return Command::FAILURE;
        }

        // Check for duplicate field
        foreach ($this->existingFields as $field) {
            if ($field->getName() === $fieldName) {
                $this->error("A field with name '{$fieldName}' already exists");
                return Command::FAILURE;
            }
        }

        $fieldTypes = [
            'string' => 'String (max 255 characters)',
            'integer' => 'Integer',
            'text' => 'Text (long text)',
            'boolean' => 'Boolean (true/false)',
            'float' => 'Float (decimal)',
            'file' => 'File',
        ];

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
        $field = null;

        switch ($fieldType) {
            case 'string':
                $maxLength = $this->ask('Maximum length', 255);
                $field = new StringField(
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
                $field = new IntegerField(
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
                $field = new TextField(
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
                $field = new BooleanField(
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
                $field = new FloatField(
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
                $field = new FileField(
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

        if (!$field) {
            $this->error('Failed to create field');
            return Command::FAILURE;
        }

        // Add field to existing fields
        $this->existingFields[] = $field;

        // Save field definition
        if (!$fieldDefinition->saveFields($this->existingFields)) {
            $this->error('Failed to save field definition');
            return Command::FAILURE;
        }

        // Update package files
        if (!$this->updatePackageFiles()) {
            $this->error('Failed to update package files');
            return Command::FAILURE;
        }

        $this->info("Field '{$fieldName}' added successfully");

        return Command::SUCCESS;
    }

    /**
     * Update an existing field in the package.
     */
    protected function updateField(FieldDefinition $fieldDefinition): int
    {
        // Find the field to update
        $fieldToUpdate = null;
        $fieldIndex = -1;

        foreach ($this->existingFields as $index => $field) {
            if ($field->getName() === $this->fieldName) {
                $fieldToUpdate = $field;
                $fieldIndex = $index;
                break;
            }
        }

        if (!$fieldToUpdate) {
            $this->error("Field '{$this->fieldName}' not found");
            return Command::FAILURE;
        }

        $this->info("Updating field: {$this->fieldName}");

        // Get current field type and properties
        $currentType = $fieldToUpdate->getFieldType();

        $fieldTypes = [
            'string' => 'String (max 255 characters)',
            'integer' => 'Integer',
            'text' => 'Text (long text)',
            'boolean' => 'Boolean (true/false)',
            'float' => 'Float (decimal)',
            'file' => 'File',
        ];

        // Ask for new field properties
        $fieldType = $this->choice(
            'Field type',
            $fieldTypes,
            array_search($currentType, array_keys($fieldTypes))
        );

        $nullable = $this->confirm('Is this field nullable?', $fieldToUpdate->isNullable());

        $description = $this->ask('Field description (optional)', $fieldToUpdate->getDescription() ?: '');

        // Get current validation rules
        $currentRules = $fieldToUpdate->getValidationRulesString();
        $validationRules = $this->ask('Validation rules (comma separated)', $currentRules);

        // Create the updated field
        $updatedField = null;

        switch ($fieldType) {
            case 'string':
                $maxLength = 255;
                if ($currentType === 'string' && $fieldToUpdate instanceof StringField) {
                    // Try to get the current max length
                    $reflectionClass = new \ReflectionClass($fieldToUpdate);
                    if ($reflectionClass->hasProperty('maxLength')) {
                        $property = $reflectionClass->getProperty('maxLength');
                        $property->setAccessible(true);
                        $maxLength = $property->getValue($fieldToUpdate);
                    }
                }

                $maxLength = $this->ask('Maximum length', $maxLength);
                $updatedField = new StringField(
                    $this->fieldName,
                    $nullable,
                    $fieldToUpdate->getDefault(),
                    $description,
                    explode(',', $validationRules),
                    (int)$maxLength
                );
                break;

            case 'integer':
                $min = null;
                $max = null;

                if ($currentType === 'integer' && $fieldToUpdate instanceof IntegerField) {
                    // Try to get current min/max values
                    $reflectionClass = new \ReflectionClass($fieldToUpdate);
                    if ($reflectionClass->hasProperty('min')) {
                        $property = $reflectionClass->getProperty('min');
                        $property->setAccessible(true);
                        $min = $property->getValue($fieldToUpdate);
                    }

                    if ($reflectionClass->hasProperty('max')) {
                        $property = $reflectionClass->getProperty('max');
                        $property->setAccessible(true);
                        $max = $property->getValue($fieldToUpdate);
                    }
                }

                $min = $this->ask('Minimum value (optional)', $min);
                $max = $this->ask('Maximum value (optional)', $max);

                $updatedField = new IntegerField(
                    $this->fieldName,
                    $nullable,
                    $fieldToUpdate->getDefault(),
                    $description,
                    explode(',', $validationRules),
                    !empty($min) ? (int)$min : null,
                    !empty($max) ? (int)$max : null
                );
                break;

            case 'text':
                $richEditor = false;

                if ($currentType === 'text' && $fieldToUpdate instanceof TextField) {
                    // Try to get rich editor setting
                    $reflectionClass = new \ReflectionClass($fieldToUpdate);
                    if ($reflectionClass->hasProperty('useRichEditor')) {
                        $property = $reflectionClass->getProperty('useRichEditor');
                        $property->setAccessible(true);
                        $richEditor = $property->getValue($fieldToUpdate);
                    }
                }

                $richEditor = $this->confirm('Use rich text editor?', $richEditor);

                $updatedField = new TextField(
                    $this->fieldName,
                    $nullable,
                    $fieldToUpdate->getDefault(),
                    $description,
                    explode(',', $validationRules),
                    $richEditor
                );
                break;

            case 'boolean':
                $default = false;

                if ($currentType === 'boolean' && $fieldToUpdate instanceof BooleanField) {
                    $default = $fieldToUpdate->getDefault();
                }

                $default = $this->confirm('Default value', $default);

                $updatedField = new BooleanField(
                    $this->fieldName,
                    $nullable,
                    $default,
                    $description,
                    explode(',', $validationRules)
                );
                break;

            case 'float':
                $decimals = 2;
                $min = null;
                $max = null;

                if ($currentType === 'float' && $fieldToUpdate instanceof FloatField) {
                    // Try to get current settings
                    $reflectionClass = new \ReflectionClass($fieldToUpdate);

                    if ($reflectionClass->hasProperty('decimals')) {
                        $property = $reflectionClass->getProperty('decimals');
                        $property->setAccessible(true);
                        $decimals = $property->getValue($fieldToUpdate);
                    }

                    if ($reflectionClass->hasProperty('min')) {
                        $property = $reflectionClass->getProperty('min');
                        $property->setAccessible(true);
                        $min = $property->getValue($fieldToUpdate);
                    }

                    if ($reflectionClass->hasProperty('max')) {
                        $property = $reflectionClass->getProperty('max');
                        $property->setAccessible(true);
                        $max = $property->getValue($fieldToUpdate);
                    }
                }

                $decimals = $this->ask('Number of decimal places', $decimals);
                $min = $this->ask('Minimum value (optional)', $min);
                $max = $this->ask('Maximum value (optional)', $max);

                $updatedField = new FloatField(
                    $this->fieldName,
                    $nullable,
                    $fieldToUpdate->getDefault(),
                    $description,
                    explode(',', $validationRules),
                    (int)$decimals,
                    !empty($min) ? (float)$min : null,
                    !empty($max) ? (float)$max : null
                );
                break;

            case 'file':
                $maxSize = 10240;
                $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

                if ($currentType === 'file' && $fieldToUpdate instanceof FileField) {
                    // Try to get current settings
                    $reflectionClass = new \ReflectionClass($fieldToUpdate);

                    if ($reflectionClass->hasProperty('maxSize')) {
                        $property = $reflectionClass->getProperty('maxSize');
                        $property->setAccessible(true);
                        $maxSize = $property->getValue($fieldToUpdate);
                    }

                    if ($reflectionClass->hasProperty('allowedTypes')) {
                        $property = $reflectionClass->getProperty('allowedTypes');
                        $property->setAccessible(true);
                        $allowedTypes = $property->getValue($fieldToUpdate);
                    }
                }

                $maxSize = $this->ask('Maximum file size in KB', $maxSize);
                $allowedTypes = $this->ask('Allowed file types (comma separated)', implode(',', $allowedTypes));

                $updatedField = new FileField(
                    $this->fieldName,
                    $nullable,
                    $fieldToUpdate->getDefault(),
                    $description,
                    explode(',', $validationRules),
                    (int)$maxSize,
                    explode(',', $allowedTypes)
                );
                break;
        }

        if (!$updatedField) {
            $this->error('Failed to update field');
            return Command::FAILURE;
        }

        // Update the field in the existing fields array
        $this->existingFields[$fieldIndex] = $updatedField;

        // Save updated field definitions
        if (!$fieldDefinition->saveFields($this->existingFields)) {
            $this->error('Failed to save field definition');
            return Command::FAILURE;
        }

        // Update package files
        if (!$this->updatePackageFiles()) {
            $this->error('Failed to update package files');
            return Command::FAILURE;
        }

        $this->info("Field '{$this->fieldName}' updated successfully");

        return Command::SUCCESS;
    }

    /**
     * Remove a field from the package.
     */
    protected function removeField(FieldDefinition $fieldDefinition): int
    {
        // Find the field to remove
        $fieldExists = false;

        foreach ($this->existingFields as $field) {
            if ($field->getName() === $this->fieldName) {
                $fieldExists = true;
                break;
            }
        }

        if (!$fieldExists) {
            $this->error("Field '{$this->fieldName}' not found");
            return Command::FAILURE;
        }

        if (!$this->confirm("Are you sure you want to remove the field '{$this->fieldName}'?", false)) {
            $this->info('Field removal canceled');
            return Command::SUCCESS;
        }

        // Remove the field from the array
        $this->existingFields = array_filter($this->existingFields, function ($field) {
            return $field->getName() !== $this->fieldName;
        });

        // Save updated field definitions
        if (!$fieldDefinition->saveFields(array_values($this->existingFields))) {
            $this->error('Failed to save field definition');
            return Command::FAILURE;
        }

        // Generate migration for removing the field
        $this->generateRemoveMigration();

        // Update package files
        if (!$this->updatePackageFiles()) {
            $this->error('Failed to update package files');
            return Command::FAILURE;
        }

        $this->info("Field '{$this->fieldName}' removed successfully");

        return Command::SUCCESS;
    }

    /**
     * Update package files after field changes.
     */
    protected function updatePackageFiles(): bool
    {
        try {
            $sourceDir = $this->packagePath;

            // Model
            $modelGenerator = new ModelGenerator(
                $this->packagePath,
                $this->namespace,
                $this->modelName,
                $this->existingFields,
                $this->isApiOnly,
                $this->apiVersion,
                $this->packageType
            );
            $modelGenerator->generate();

            // Form Request
            $requestGenerator = new RequestGenerator(
                $this->packagePath,
                $this->namespace,
                $this->modelName,
                $this->existingFields,
                $this->isApiOnly,
                $this->apiVersion,
                $this->packageType
            );
            $requestGenerator->generate();

            // API Resource
            $resourceGenerator = new ResourceGenerator(
                $this->packagePath,
                $this->namespace,
                $this->modelName,
                $this->existingFields,
                $this->isApiOnly,
                $this->apiVersion,
                $this->packageType
            );
            $resourceGenerator->generate();

            // Vue Components (if not API only)
            if (!$this->isApiOnly) {
                $vueGenerator = new VueGenerator(
                    $this->packagePath,
                    $this->namespace,
                    $this->modelName,
                    $this->existingFields,
                    $this->isApiOnly,
                    $this->apiVersion,
                    $this->packageType
                );
                $vueGenerator->generate();
            }

            // Generate migration for the field change
            $this->generateFieldMigration();

            return true;
        } catch (\Exception $e) {
            $this->error('Error updating package files: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a migration for field changes.
     */
    protected function generateFieldMigration(): void
    {
        if ($this->action === 'add') {
            // For new fields, we generate an 'add column' migration
            $this->generateAddMigration();
        } elseif ($this->action === 'update') {
            // For updated fields, we generate a 'modify column' migration
            $this->generateUpdateMigration();
        }
        // For 'remove', we use generateRemoveMigration() which is called separately
    }

    /**
     * Generate a migration to add a field.
     */
    protected function generateAddMigration(): void
    {
        // Get the newly added field
        $newField = null;
        foreach ($this->existingFields as $field) {
            if ($field->getName() === $this->fieldName) {
                $newField = $field;
                break;
            }
        }

        if (!$newField) {
            return;
        }

        $tableName = Str::snake(Str::pluralStudly($this->modelName));
        $migrationName = 'add_' . $newField->getName() . '_to_' . $tableName . '_table';
        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp . '_' . $migrationName . '.php';

        $sourceDir = $this->packageType === 'composer' ? $this->packagePath . '/src' : $this->packagePath;
        $migrationPath = $sourceDir . '/database/migrations/' . $fileName;

        $content = <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            {$newField->getMigrationColumnDefinition()}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->dropColumn('{$newField->getName()}');
        });
    }
};
EOT;

        $this->filesystem->put($migrationPath, $content);
        $this->info("Created migration: " . basename($migrationPath));
    }

    /**
     * Generate a migration to update a field.
     */
    protected function generateUpdateMigration(): void
    {
        // Get the updated field
        $updatedField = null;
        foreach ($this->existingFields as $field) {
            if ($field->getName() === $this->fieldName) {
                $updatedField = $field;
                break;
            }
        }

        if (!$updatedField) {
            return;
        }

        $tableName = Str::snake(Str::pluralStudly($this->modelName));
        $migrationName = 'update_' . $updatedField->getName() . '_in_' . $tableName . '_table';
        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp . '_' . $migrationName . '.php';

        $sourceDir = $this->packageType === 'composer' ? $this->packagePath . '/src' : $this->packagePath;
        $migrationPath = $sourceDir . '/database/migrations/' . $fileName;

        // Extract the column definition without the semicolon
        $columnDefinition = $updatedField->getMigrationColumnDefinition();
        $columnDefinition = rtrim($columnDefinition, ';');

        $content = <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->dropColumn('{$updatedField->getName()}');
        });

        Schema::table('{$tableName}', function (Blueprint \$table) {
            {$columnDefinition};
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not safely reversible.
        // If you need to revert, you should create a new migration.
    }
};
EOT;

        $this->filesystem->put($migrationPath, $content);
        $this->info("Created migration: " . basename($migrationPath));
    }

    /**
     * Generate a migration to remove a field.
     */
    protected function generateRemoveMigration(): void
    {
        $tableName = Str::snake(Str::pluralStudly($this->modelName));
        $migrationName = 'remove_' . $this->fieldName . '_from_' . $tableName . '_table';
        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp . '_' . $migrationName . '.php';

        $sourceDir = $this->packageType === 'composer' ? $this->packagePath . '/src' : $this->packagePath;
        $migrationPath = $sourceDir . '/database/migrations/' . $fileName;

        $content = <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->dropColumn('{$this->fieldName}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            // Add the column back if needed.
            // \$table->string('{$this->fieldName}')->nullable();
        });
    }
};
EOT;

        $this->filesystem->put($migrationPath, $content);
        $this->info("Created migration: " . basename($migrationPath));
    }
}
