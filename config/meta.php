<?php

use Javaabu\Meta\Models\Meta;

return [

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | The table meta values are stored in. A model can keep its meta in a table
    | of its own by setting a $meta_table property; create that table with the
    | same columns and indexes (see the published migration).
    |
    */

    'table' => 'meta',

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model of a meta row. Extend Javaabu\Meta\Models\Meta to
    | change it.
    |
    */

    'model' => Meta::class,

    /*
    |--------------------------------------------------------------------------
    | Record keys
    |--------------------------------------------------------------------------
    |
    | The type of the metable_id column the migration creates: 'id' for
    | auto-incrementing keys, or 'uuid' or 'ulid'. Change it before you migrate.
    |
    */

    'morph_key' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | Run the package's migration with your own. Turn it off when you publish
    | the migration (php artisan vendor:publish --tag=meta-migrations).
    |
    */

    'run_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Types
    |--------------------------------------------------------------------------
    |
    | Your own meta types, classes extending Javaabu\Meta\Types\MetaType. A type
    | with the name of a built-in one replaces it.
    |
    */

    'types' => [
        // App\Meta\ColorType::class,
    ],

];
