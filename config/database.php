<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | StyleHub uses MySQL during normal application development.
    | Automated tests may override this connection with SQLite.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | StyleHub currently supports:
    |
    | - MySQL for the actual application database
    | - SQLite for isolated automated testing
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env(
                'DB_DATABASE',
                database_path('database.sqlite')
            ),
            'prefix' => '',
            'foreign_key_constraints' => env(
                'DB_FOREIGN_KEYS',
                true
            ),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),

            'host' => env(
                'DB_HOST',
                '127.0.0.1'
            ),

            'port' => env(
                'DB_PORT',
                '3306'
            ),

            'database' => env(
                'DB_DATABASE',
                'stylehub'
            ),

            'username' => env(
                'DB_USERNAME',
                'root'
            ),

            'password' => env(
                'DB_PASSWORD',
                ''
            ),

            'unix_socket' => env(
                'DB_SOCKET',
                ''
            ),

            /*
            |--------------------------------------------------------------------------
            | Character Encoding
            |--------------------------------------------------------------------------
            |
            | utf8mb4 provides full Unicode support.
            |
            */

            'charset' => env(
                'DB_CHARSET',
                'utf8mb4'
            ),

            'collation' => env(
                'DB_COLLATION',
                'utf8mb4_unicode_ci'
            ),

            'prefix' => '',
            'prefix_indexes' => true,

            /*
            |--------------------------------------------------------------------------
            | Strict SQL Mode
            |--------------------------------------------------------------------------
            |
            | Strict mode helps prevent invalid or silently truncated data
            | from being stored in the database.
            |
            */

            'strict' => true,

            /*
            |--------------------------------------------------------------------------
            | Storage Engine
            |--------------------------------------------------------------------------
            |
            | StyleHub explicitly requires InnoDB.
            |
            | The local WAMP MySQL installation currently defaults to
            | MyISAM. We intentionally override that default for this
            | application because StyleHub requires transactions,
            | foreign keys, and reliable concurrent writes.
            |
            */

            'engine' => env(
                'DB_ENGINE',
                'InnoDB'
            ),

            /*
            |--------------------------------------------------------------------------
            | PDO Options
            |--------------------------------------------------------------------------
            */

            'options' => extension_loaded('pdo_mysql')
                ? array_filter([
                    Mysql::ATTR_SSL_CA => env(
                        'MYSQL_ATTR_SSL_CA'
                    ),
                ])
                : [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository
    |--------------------------------------------------------------------------
    |
    | Laravel records executed migrations in this table.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    |
    | Redis is not required for the current local development phase,
    | but this configuration is retained for future caching or queue
    | requirements.
    |
    */

    'redis' => [

        'client' => env(
            'REDIS_CLIENT',
            'phpredis'
        ),

        'options' => [
            'cluster' => env(
                'REDIS_CLUSTER',
                'redis'
            ),

            'prefix' => env(
                'REDIS_PREFIX',
                Str::slug(
                    (string) env(
                        'APP_NAME',
                        'StyleHub'
                    )
                ).'-database-'
            ),

            'persistent' => env(
                'REDIS_PERSISTENT',
                false
            ),
        ],

        'default' => [
            'url' => env('REDIS_URL'),

            'host' => env(
                'REDIS_HOST',
                '127.0.0.1'
            ),

            'username' => env(
                'REDIS_USERNAME'
            ),

            'password' => env(
                'REDIS_PASSWORD'
            ),

            'port' => env(
                'REDIS_PORT',
                '6379'
            ),

            'database' => env(
                'REDIS_DB',
                '0'
            ),

            'max_retries' => env(
                'REDIS_MAX_RETRIES',
                3
            ),

            'backoff_algorithm' => env(
                'REDIS_BACKOFF_ALGORITHM',
                'decorrelated_jitter'
            ),

            'backoff_base' => env(
                'REDIS_BACKOFF_BASE',
                100
            ),

            'backoff_cap' => env(
                'REDIS_BACKOFF_CAP',
                1000
            ),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),

            'host' => env(
                'REDIS_HOST',
                '127.0.0.1'
            ),

            'username' => env(
                'REDIS_USERNAME'
            ),

            'password' => env(
                'REDIS_PASSWORD'
            ),

            'port' => env(
                'REDIS_PORT',
                '6379'
            ),

            'database' => env(
                'REDIS_CACHE_DB',
                '1'
            ),

            'max_retries' => env(
                'REDIS_MAX_RETRIES',
                3
            ),

            'backoff_algorithm' => env(
                'REDIS_BACKOFF_ALGORITHM',
                'decorrelated_jitter'
            ),

            'backoff_base' => env(
                'REDIS_BACKOFF_BASE',
                100
            ),

            'backoff_cap' => env(
                'REDIS_BACKOFF_CAP',
                1000
            ),
        ],

    ],

];