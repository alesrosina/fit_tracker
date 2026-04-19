<?php

declare(strict_types=1);

return [
    'routes' => [
        // Page
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Activities API
        ['name' => 'activity#index',       'url' => '/api/activities',                  'verb' => 'GET'],
        ['name' => 'activity#show',        'url' => '/api/activities/{id}',             'verb' => 'GET'],
        ['name' => 'activity#destroy',     'url' => '/api/activities/{id}',             'verb' => 'DELETE'],
        ['name' => 'activity#trackpoints', 'url' => '/api/activities/{id}/trackpoints', 'verb' => 'GET'],
        ['name' => 'activity#laps',        'url' => '/api/activities/{id}/laps',        'verb' => 'GET'],
        ['name' => 'activity#photos', 'url' => '/api/activities/{id}/photos', 'verb' => 'GET'],

        // Sleep API
        ['name' => 'sleep#index',   'url' => '/api/sleep',             'verb' => 'GET'],
        ['name' => 'sleep#show',    'url' => '/api/sleep/{id}',        'verb' => 'GET'],
        ['name' => 'sleep#stages',  'url' => '/api/sleep/{id}/stages', 'verb' => 'GET'],
        ['name' => 'sleep#destroy', 'url' => '/api/sleep/{id}',        'verb' => 'DELETE'],

        // Config
        ['name' => 'config#get_config', 'url' => '/api/config', 'verb' => 'GET'],
        ['name' => 'config#set_config', 'url' => '/api/config', 'verb' => 'POST'],
        ['name' => 'config#debug_fit',  'url' => '/api/debug',  'verb' => 'GET'],
    ],
];
