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
];
