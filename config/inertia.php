<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render the initial visits made to your application's pages.
    |
    | Locally leave INERTIA_SSR_ENABLED=false and keep using client-side
    | rendering. Production compose enables SSR and points at the ssr service.
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    'ensure_pages_exist' => false,

    'page_paths' => [

        resource_path('js/Pages'),

    ],

    'page_extensions' => [

        'js',
        'jsx',
        'svelte',
        'ts',
        'tsx',
        'vue',

    ],

    'use_script_element_for_initial_page' => (bool) env('INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE', false),

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [

            resource_path('js/Pages'),

        ],

        'page_extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

    'history' => [

        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),

    ],

];
