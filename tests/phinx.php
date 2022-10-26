<?php

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/migrations'
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => '127.0.0.1',
            'name' => 'api-resources-test',
            'user' => 'root',
            'pass' => 'root',
            'port' => '23306',
            'charset' => 'utf8'
        ]
    ],

    'version_order' => 'creation'
];
