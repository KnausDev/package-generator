# KnausDev Laravel Package Generator

A Laravel package generator that creates packages with a custom code style and structure. This tool helps you quickly scaffold Laravel packages with models, controllers, services, and frontend components.

## Features

- Generate composer packages or domain-specific implementations
- Create models with fillable fields and casts
- Generate migrations with proper schema definitions
- Create controllers with resource methods
- Implement service layer for business logic
- Generate form requests with validation rules
- Create API resources for response formatting
- Generate Vue components for frontend (optional)
- Support for field management (add, update, remove)
- Customizable templates and configurations

## Installation

You can install the package via composer:

```bash
composer require knausdev/package-generator
```

Or develop it locally:

```bash
# Create a packages directory in your Laravel project
mkdir -p packages/knausdev/package-generator

# Clone this repository into the directory or create files manually
git clone https://github.com/your-username/laravel-package-generator.git packages/knausdev/package-generator

# Add to composer.json repositories
"repositories": [
    {
        "type": "path",
        "url": "./packages/knausdev/package-generator"
    }
]

# Require the package
composer require knausdev/package-generator
```

After installation, run the setup command:

```bash
php artisan knausdev:install
```

This will:
- Create necessary directories (packages, domains)
- Generate route registration files
- Update main route files to include domain routes
- Publish configuration files

## Publishing Configuration

```bash
php artisan vendor:publish --provider="KnausDev\PackageGenerator\PackageGeneratorServiceProvider" --tag="package-generator-config"
```

## Customizing Templates

You can publish and customize the stub templates:

```bash
php artisan knausdev:publish-stubs
```

This will copy all stub templates to `stubs/vendor/knausdev/package-generator` where you can modify them to match your coding style.

## Usage

### Creating a New Package

```bash
php artisan knausdev:make-package YourPackageName
```

Options:
- `--type=composer` - Package type (composer or domain), default: composer
- `--namespace=YourNamespace` - The namespace of the package, default: KnausDev
- `--path=/custom/path` - Optional custom path for package
- `--model=CustomModel` - Optional model name (default derives from package name)
- `--api-only` - Whether the package is API only (no frontend)
- `--api-version=v2` - API version to use, default: v1

### Adding a Model to an Existing Package

```bash
php artisan knausdev:package-model YourPackageName NewModelName
```

Options are the same as for `make-package`.

### Managing Fields

```bash
# Add a field
php artisan knausdev:package-field add YourPackageName ModelName

# Update a field
php artisan knausdev:package-field update YourPackageName ModelName --field=fieldName

# Remove a field
php artisan knausdev:package-field remove YourPackageName ModelName --field=fieldName
```

### Running Migrations for Domain Packages

Domain packages don't use the standard Laravel migration discovery. Use our custom command to run migrations:

```bash
# Run migrations for all domains
php artisan knausdev:domain-migrate

# Run migrations for a specific domain
php artisan knausdev:domain-migrate YourDomainName

# Fresh migrations (wipes database)
php artisan knausdev:domain-migrate --fresh

# With seeding
php artisan knausdev:domain-migrate --seed
```

### Registering Routes from Domain Packages

Domain routes aren't automatically registered. Use our command to generate a registration file:

```bash
php artisan knausdev:domain-routes
```

This command scans all domain packages for route files and generates:
- `routes/domain_web.php` - For web routes
- `routes/domain_api.php` - For API routes

Then add these lines to your main route files:

In `routes/web.php`:
```php
require base_path('routes/domain_web.php');
```

In `routes/api.php`:
```php
require base_path('routes/domain_api.php');
```

## Domain Structure

This package supports two types of package structures:

### Composer Packages
```
packages/knausdev/package-name/
├── composer.json
├── src/
    ├── ...
```

### Domain Packages
```
domains/KnausDev/DomainName/
├── composer.json  # Automatically created to handle autoloading
├── Models/
├── Http/
├── ...
```

In domain packages, the namespace follows this structure:
- Domain: `KnausDev\DomainName`
- Controller: `KnausDev\DomainName\Http\Controllers\SomeController`

For example, a User domain would have the namespace `KnausDev\User\` and be located at `domains/KnausDev/User/`.

Each domain package automatically gets a `composer.json` file that configures PSR-4 autoloading for that domain. This autoloading is enabled by the Wikimedia Composer Merge Plugin, which is automatically configured when you run `php artisan knausdev:install`.

## Field Types

The generator supports the following field types:

- `string` - String with customizable length
- `integer` - Integer with optional min/max values
- `text` - Text field with optional rich editor
- `boolean` - Boolean (true/false) values
- `float` - Decimal numbers with configurable precision
- `file` - File uploads with customizable validation

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
