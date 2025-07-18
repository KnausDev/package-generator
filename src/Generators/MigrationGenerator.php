<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;

class MigrationGenerator extends BaseGenerator
{
    /**
     * Generate the migration.
     */
    public function generate(): bool
    {
        $migrationsDirectory = $this->getSourceDirectory() . '/database/migrations';
        $this->createDirectory($migrationsDirectory);

        $filename = $this->generateMigrationFilename();
        $migrationPath = $migrationsDirectory . '/' . $filename;

        $stub = $this->getStub('migration');
        $content = $this->populateStub($stub);

        return $this->writeFile($migrationPath, $content);
    }

    /**
     * Generate the migration filename.
     */
    protected function generateMigrationFilename(): string
    {
        $timestamp = date('Y_m_d_His');
        $tableName = $this->getTableName();

        return "{$timestamp}_create_{$this->namespace}_{$tableName}_table.php";
    }

    /**
     * Populate the migration stub with the package data.
     */
    protected function populateStub(string $stub): string
    {
        return $this->replaceTemplate($stub, [
            'tableName' => $this->getTableName(),
            'schema' => $this->generateSchema(),
        ]);
    }

    /**
     * Generate the schema for the migration.
     */
    protected function generateSchema(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $schema = array_map(function (BaseFieldType $field) {
            return "            " . $field->getMigrationColumnDefinition();
        }, $this->fields);

        return implode("\n", $schema);
    }
}
