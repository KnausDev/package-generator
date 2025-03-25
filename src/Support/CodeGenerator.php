<?php

namespace KnausDev\PackageGenerator\Support;

use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;

class CodeGenerator
{
    /**
     * Generate fillable array for a model.
     */
    public static function generateFillableArray(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $fillable = array_map(function (BaseFieldType $field) {
            return "        '{$field->getName()}'";
        }, $fields);

        return implode(",\n", $fillable);
    }

    /**
     * Generate casts array for a model.
     */
    public static function generateCastsArray(array $fields): string
    {
        $casts = [];

        foreach ($fields as $field) {
            $cast = $field->getModelCast();

            if ($cast !== null) {
                $casts[] = "        '{$field->getName()}' => '{$cast}'";
            }
        }

        if (empty($casts)) {
            return '';
        }

        return implode(",\n", $casts);
    }

    /**
     * Generate migration schema.
     */
    public static function generateMigrationSchema(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $schema = array_map(function (BaseFieldType $field) {
            return "            " . $field->getMigrationColumnDefinition();
        }, $fields);

        return implode("\n", $schema);
    }

    /**
     * Generate validation rules.
     */
    public static function generateValidationRules(array $fields): string
    {
        if (empty($fields)) {
            return "        //";
        }

        $rules = [];

        foreach ($fields as $field) {
            $validationRules = $field->getValidationRulesString();
            $rules[] = "        '{$field->getName()}' => '{$validationRules}',";
        }

        return implode("\n", $rules);
    }

    /**
     * Generate API resource attributes.
     */
    public static function generateResourceAttributes(array $fields): string
    {
        if (empty($fields)) {
            return "            //";
        }

        $attributes = [
            "            'id' => \$this->id,",
        ];

        foreach ($fields as $field) {
            $attributes[] = "            '{$field->getName()}' => \$this->{$field->getName()},";
        }

        $attributes[] = "            'created_at' => \$this->created_at,";
        $attributes[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $attributes);
    }

    /**
     * Generate form fields for Vue components.
     */
    public static function generateVueFormFields(array $fields): string
    {
        if (empty($fields)) {
            return '        <!-- No fields defined -->';
        }

        $formFields = [];

        foreach ($fields as $field) {
            $formFields[] = $field->getVueFormInput();
        }

        return implode("\n\n", $formFields);
    }

    /**
     * Generate form data initialization for Vue components.
     */
    public static function generateVueFormData(array $fields): string
    {
        if (empty($fields)) {
            return '        // No fields defined';
        }

        $formData = [];

        foreach ($fields as $field) {
            $default = $field->getDefault();

            if ($default === null) {
                $default = 'null';
            } elseif (is_bool($default)) {
                $default = $default ? 'true' : 'false';
            } elseif (is_string($default)) {
                $default = "'{$default}'";
            }

            $formData[] = "        {$field->getName()}: {$default},";
        }

        return implode("\n", $formData);
    }

    /**
     * Generate table headers for Vue list components.
     */
    public static function generateVueTableHeaders(array $fields, int $fieldLimit = 4): string
    {
        if (empty($fields)) {
            return '          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
        }

        $headers = [
            '          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>'
        ];

        // Add only the first few fields to avoid overly wide tables
        $fieldCount = 0;

        foreach ($fields as $field) {
            if ($fieldCount >= $fieldLimit) {
                break;
            }

            $headers[] = '          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' .
                self::getFormattedFieldName($field) .
                '</th>';

            $fieldCount++;
        }

        $headers[] = '          <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>';

        return implode("\n", $headers);
    }

    /**
     * Generate table rows for Vue list components.
     */
    public static function generateVueTableRows(array $fields, int $fieldLimit = 4): string
    {
        if (empty($fields)) {
            return '          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.id }}</td>';
        }

        $rows = [
            '          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.id }}</td>'
        ];

        // Add only the first few fields to match the headers
        $fieldCount = 0;

        foreach ($fields as $field) {
            if ($fieldCount >= $fieldLimit) {
                break;
            }

            $rows[] = '          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.' . $field->getName() . ' }}</td>';

            $fieldCount++;
        }

        return implode("\n", $rows);
    }

    /**
     * Generate detail fields for Vue show components.
     */
    public static function generateVueDetailFields(array $fields): string
    {
        if (empty($fields)) {
            return '        <!-- No fields defined -->';
        }

        $details = [];

        foreach ($fields as $field) {
            $fieldName = self::getFormattedFieldName($field);
            $details[] = <<<HTML
        <div class="mb-4">
          <h3 class="text-sm font-medium text-gray-500">{$fieldName}</h3>
          <p class="mt-1 text-sm text-gray-900">{{ {$field->getName()} }}</p>
        </div>
HTML;
        }

        return implode("\n\n", $details);
    }

    /**
     * Get formatted field name for display.
     */
    protected static function getFormattedFieldName(BaseFieldType $field): string
    {
        if ($field->getDescription()) {
            return $field->getDescription();
        }

        return ucwords(str_replace('_', ' ', $field->getName()));
    }

    /**
     * Generate method for a model relationship.
     */
    public static function generateRelationshipMethod(string $relationName, string $relationType, string $relatedModel, ?string $foreignKey = null, ?string $localKey = null): string
    {
        $methodName = Str::camel($relationName);
        $relationCode = "    /**\n";
        $relationCode .= "     * Get the {$relationName} relationship.\n";
        $relationCode .= "     */\n";
        $relationCode .= "    public function {$methodName}()\n";
        $relationCode .= "    {\n";
        $relationCode .= "        return \$this->{$relationType}({$relatedModel}::class";

        if ($foreignKey) {
            $relationCode .= ", '{$foreignKey}'";

            if ($localKey && in_array($relationType, ['hasOne', 'hasMany', 'belongsTo'])) {
                $relationCode .= ", '{$localKey}'";
            }
        }

        $relationCode .= ");\n";
        $relationCode .= "    }\n";

        return $relationCode;
    }

    /**
     * Generate a complete model class.
     */
    public static function generateModelClass(string $namespace, string $className, string $tableName, array $fields, array $relationships = []): string
    {
        $fillable = self::generateFillableArray($fields);
        $casts = self::generateCastsArray($fields);

        $relationshipMethods = '';

        if (!empty($relationships)) {
            $relationshipMethods = "\n";

            foreach ($relationships as $relationship) {
                $relationshipMethods .= self::generateRelationshipMethod(
                    $relationship['name'],
                    $relationship['type'],
                    $relationship['related_model'],
                    $relationship['foreign_key'] ?? null,
                    $relationship['local_key'] ?? null
                );
                $relationshipMethods .= "\n";
            }
        }

        $modelCode = <<<EOT
<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$className} extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected \$table = '{$tableName}';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected \$fillable = [
{$fillable}
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected \$casts = [
{$casts}
    ];{$relationshipMethods}
}
EOT;

        return $modelCode;
    }
}
