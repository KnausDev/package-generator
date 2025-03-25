<?php

namespace KnausDev\PackageGenerator\Generators;

use Illuminate\Support\Str;
use KnausDev\PackageGenerator\FieldTypes\BaseFieldType;

class VueGenerator extends BaseGenerator
{
    /**
     * Generate the Vue components.
     */
    public function generate(): bool
    {
        if ($this->isApiOnly) {
            $this->info("Skipping Vue components generation (API only)");
            return true;
        }

        $componentsDirectory = $this->getSourceDirectory() . '/resources/js/components/' . Str::kebab($this->modelName);
        $this->createDirectory($componentsDirectory);

        // Generate form component
        $formResult = $this->generateFormComponent($componentsDirectory);

        // Generate list component
        $listResult = $this->generateListComponent($componentsDirectory);

        // Generate show component
        $showResult = $this->generateShowComponent($componentsDirectory);

        return $formResult && $listResult && $showResult;
    }

    /**
     * Generate the form component.
     */
    protected function generateFormComponent(string $directory): bool
    {
        $componentName = Str::kebab($this->modelName) . '-form.vue';
        $componentPath = $directory . '/' . $componentName;

        $stub = $this->getStub('vue/form');
        $content = $this->populateFormStub($stub);

        return $this->writeFile($componentPath, $content);
    }

    /**
     * Generate the list component.
     */
    protected function generateListComponent(string $directory): bool
    {
        $componentName = Str::kebab($this->modelName) . '-list.vue';
        $componentPath = $directory . '/' . $componentName;

        $stub = $this->getStub('vue/list');
        $content = $this->populateListStub($stub);

        return $this->writeFile($componentPath, $content);
    }

    /**
     * Generate the show component.
     */
    protected function generateShowComponent(string $directory): bool
    {
        $componentName = Str::kebab($this->modelName) . '-view.vue';
        $componentPath = $directory . '/' . $componentName;

        $stub = $this->getStub('vue/show');
        $content = $this->populateShowStub($stub);

        return $this->writeFile($componentPath, $content);
    }

    /**
     * Populate the form component stub.
     */
    protected function populateFormStub(string $stub): string
    {
        $modelVariable = lcfirst($this->modelName);
        $pluralModelVariable = Str::plural($modelVariable);
        $kebabModelName = Str::kebab($this->modelName);

        return $this->replaceTemplate($stub, [
            'modelName' => $this->modelName,
            'modelVariable' => $modelVariable,
            'pluralModelVariable' => $pluralModelVariable,
            'kebabModelName' => $kebabModelName,
            'formFields' => $this->generateFormFields(),
            'formData' => $this->generateFormData(),
            'apiUrl' => $this->getApiUrl($pluralModelVariable),
        ]);
    }

    /**
     * Populate the list component stub.
     */
    protected function populateListStub(string $stub): string
    {
        $modelVariable = lcfirst($this->modelName);
        $pluralModelVariable = Str::plural($modelVariable);
        $kebabModelName = Str::kebab($this->modelName);

        return $this->replaceTemplate($stub, [
            'modelName' => $this->modelName,
            'modelVariable' => $modelVariable,
            'pluralModelVariable' => $pluralModelVariable,
            'kebabModelName' => $kebabModelName,
            'tableHeaders' => $this->generateTableHeaders(),
            'tableRows' => $this->generateTableRows(),
            'apiUrl' => $this->getApiUrl($pluralModelVariable),
        ]);
    }

    /**
     * Populate the show component stub.
     */
    protected function populateShowStub(string $stub): string
    {
        $modelVariable = lcfirst($this->modelName);
        $pluralModelVariable = Str::plural($modelVariable);
        $kebabModelName = Str::kebab($this->modelName);

        return $this->replaceTemplate($stub, [
            'modelName' => $this->modelName,
            'modelVariable' => $modelVariable,
            'pluralModelVariable' => $pluralModelVariable,
            'kebabModelName' => $kebabModelName,
            'detailFields' => $this->generateDetailFields(),
            'apiUrl' => $this->getApiUrl($pluralModelVariable),
        ]);
    }

    /**
     * Generate form fields HTML.
     */
    protected function generateFormFields(): string
    {
        if (empty($this->fields)) {
            return '        <!-- No fields defined -->';
        }

        $formFields = [];

        foreach ($this->fields as $field) {
            $formFields[] = $field->getVueFormInput();
        }

        return implode("\n\n", $formFields);
    }

    /**
     * Generate form data initialization.
     */
    protected function generateFormData(): string
    {
        if (empty($this->fields)) {
            return '        // No fields defined';
        }

        $formData = [];

        foreach ($this->fields as $field) {
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
     * Generate table headers.
     */
    protected function generateTableHeaders(): string
    {
        if (empty($this->fields)) {
            return '          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
        }

        $headers = [
            '          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>'
        ];

        // Add only the first few fields to avoid overly wide tables
        $fieldLimit = 4;
        $fieldCount = 0;

        foreach ($this->fields as $field) {
            if ($fieldCount >= $fieldLimit) {
                break;
            }

            $headers[] = '          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' .
                $this->getFormattedFieldName($field) .
                '</th>';

            $fieldCount++;
        }

        $headers[] = '          <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>';

        return implode("\n", $headers);
    }

    /**
     * Generate table rows.
     */
    protected function generateTableRows(): string
    {
        if (empty($this->fields)) {
            return '          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.id }}</td>';
        }

        $rows = [
            '          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.id }}</td>'
        ];

        // Add only the first few fields to match the headers
        $fieldLimit = 4;
        $fieldCount = 0;

        foreach ($this->fields as $field) {
            if ($fieldCount >= $fieldLimit) {
                break;
            }

            $rows[] = '          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.' . $field->getName() . ' }}</td>';

            $fieldCount++;
        }

        return implode("\n", $rows);
    }

    /**
     * Generate detail fields.
     */
    protected function generateDetailFields(): string
    {
        if (empty($this->fields)) {
            return '        <!-- No fields defined -->';
        }

        $details = [];

        foreach ($this->fields as $field) {
            $details[] = <<<HTML
        <div class="mb-4">
          <h3 class="text-sm font-medium text-gray-500">{$this->getFormattedFieldName($field)}</h3>
          <p class="mt-1 text-sm text-gray-900">{{ {$field->getName()} }}</p>
        </div>
HTML;
        }

        return implode("\n\n", $details);
    }

    /**
     * Get formatted field name for display.
     */
    protected function getFormattedFieldName(BaseFieldType $field): string
    {
        if ($field->getDescription()) {
            return $field->getDescription();
        }

        return ucwords(str_replace('_', ' ', $field->getName()));
    }

    /**
     * Get the API URL based on version.
     */
    protected function getApiUrl(string $pluralModelVariable): string
    {
        if ($this->apiVersion) {
            return "/api/{$this->apiVersion}/{$pluralModelVariable}";
        }

        return "/api/{$pluralModelVariable}";
    }
}
