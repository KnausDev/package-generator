<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Package Namespace
    |--------------------------------------------------------------------------
    |
    | This is the default namespace used for package generation.
    |
    */
    'namespace' => 'KnausDev',

    /*
    |--------------------------------------------------------------------------
    | Package Paths
    |--------------------------------------------------------------------------
    |
    | Define the paths where packages will be created.
    |
    */
    'paths' => [
        'composer' => base_path('vendor/{namespace}/{name}'),
        'domain' => base_path('domains/{namespace}/{name}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Field Validation Rules
    |--------------------------------------------------------------------------
    |
    | Default validation rules for each field type.
    |
    */
    'validation' => [
        'string' => 'required|string|max:255',
        'integer' => 'required|integer',
        'text' => 'required|string',
        'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        'float' => 'required|numeric|min:0|decimal:0,2',
        'boolean' => 'boolean',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API response format and versioning.
    |
    */
    'api' => [
        'version_prefix' => 'v',
        'response_format' => [
            'status' => 'success', // or 'error'
            'message' => '',
            'data' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory Structure Variant
    |--------------------------------------------------------------------------
    |
    | The directory structure to use for generated packages.
    | Options: 'standard', 'ddd' (domain-driven design)
    |
    */
    'structure_variant' => 'standard',

    /*
    |--------------------------------------------------------------------------
    | Skip Field Collection
    |--------------------------------------------------------------------------
    |
    | When set to true, the package generator will skip the interactive field
    | collection process and create an empty model. This is useful when you
    | want to define fields manually or when creating packages that don't
    | require database fields.
    |
    */
    'no_fields' => false,

    /*
    |--------------------------------------------------------------------------
    | API Only Mode
    |--------------------------------------------------------------------------
    |
    | When set to true, the package generator will create packages with only
    | API-related components (controllers, routes, resources) and skip
    | frontend assets like Vue components, views, and web routes. This is
    | useful for creating backend-only packages or microservices.
    |
    */
    'api_only' => false,

];
