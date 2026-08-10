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
    | Maximum Tree Depth
    |--------------------------------------------------------------------------
    |
    | How many parent -> child edges "ancestors()" and "descendants()" will
    | follow before giving up with a TagDepthExceededException.
    |
    | The walks are recursive CTEs, and a recursive CTE over an adjacency list
    | has no natural stopping point: a cycle in "parent_id" is a perfectly valid
    | set of rows, and the walk over one runs until something kills the
    | statement — holding a connection while it does. moveTo() refuses to create
    | a cycle, but nothing stops a raw UPDATE, so the walks bound themselves.
    |
    | Raise it if your trees are genuinely deeper than this.
    |
    */
    'max_tree_depth' => 64,

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
