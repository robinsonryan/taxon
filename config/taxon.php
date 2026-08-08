<?php

use RobinsonRyan\Taxon\Models\Tag;

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'tags' => 'tags',
        'taggables' => 'taggables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | Supported: "incrementing", "uuid7"
    |
    */
    'id_type' => 'incrementing',

    /*
    |--------------------------------------------------------------------------
    | Taggable Key Type
    |--------------------------------------------------------------------------
    |
    | "id_type" above governs Taxon's own primary keys. This one governs the
    | "taggables.taggable_id" column, which holds the primary keys of YOUR
    | models — and the two need not match. An application can quite reasonably
    | run uuid7 tags while its posts and users keep auto-incrementing ids, or
    | the reverse.
    |
    | Leave this null to follow "id_type", which is what a single-key-type
    | application wants. Set it only when your models are keyed differently
    | from your tags.
    |
    | Supported: "incrementing", "uuid7", null
    |
    */
    'taggable_id_type' => null,

    /*
    |--------------------------------------------------------------------------
    | Tag Model
    |--------------------------------------------------------------------------
    */
    'tag_model' => Tag::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Configuration
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        'enabled' => false,
        'column' => 'tenant_id',
        'resolver' => 'auth',
        'auth_attribute' => 'account_id',
        'callback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-create Tags
    |--------------------------------------------------------------------------
    */
    'auto_create' => true,

    /*
    |--------------------------------------------------------------------------
    | Morph Map
    |--------------------------------------------------------------------------
    */
    'morph_map' => [],
];
