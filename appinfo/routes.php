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
        ['name' => 'activity#repair_sport', 'url' => '/api/activities/repair-sport', 'verb' => 'POST'],
        ['name' => 'activity#reimport',     'url' => '/api/activities/{id}/reimport', 'verb' => 'POST'],
        ['name' => 'activity#reimport_all', 'url' => '/api/reimport', 'verb' => 'POST'],
        ['name' => 'activity#update_meta', 'url' => '/api/activities/{id}/meta',    'verb' => 'PUT'],
        ['name' => 'activity#weather',     'url' => '/api/activities/{id}/weather', 'verb' => 'GET'],

        // Public links
        ['name' => 'share#index',        'url' => '/api/shares',                  'verb' => 'GET'],
        ['name' => 'share#for_activity', 'url' => '/api/activities/{id}/shares',  'verb' => 'GET'],
        ['name' => 'share#create',       'url' => '/api/activities/{id}/shares',  'verb' => 'POST'],
        ['name' => 'share#destroy',      'url' => '/api/shares/{id}',             'verb' => 'DELETE'],
        ['name' => 'public#show',        'url' => '/s/{token}',                   'verb' => 'GET'],

        // Stats
        ['name' => 'stats#analyze', 'url' => '/api/stats/analyze', 'verb' => 'POST'],
        ['name' => 'stats#load',    'url' => '/api/stats/load',    'verb' => 'GET'],
        ['name' => 'stats#records', 'url' => '/api/stats/records', 'verb' => 'GET'],
        ['name' => 'stats#heatmap', 'url' => '/api/stats/heatmap', 'verb' => 'GET'],
        ['name' => 'stats#photos',  'url' => '/api/stats/photos',  'verb' => 'GET'],

        // Goals
        ['name' => 'goal#index',   'url' => '/api/goals',      'verb' => 'GET'],
        ['name' => 'goal#create',  'url' => '/api/goals',      'verb' => 'POST'],
        ['name' => 'goal#update',  'url' => '/api/goals/{id}', 'verb' => 'PUT'],
        ['name' => 'goal#destroy', 'url' => '/api/goals/{id}', 'verb' => 'DELETE'],

        // Gear
        ['name' => 'gear#index',   'url' => '/api/gear',      'verb' => 'GET'],
        ['name' => 'gear#create',  'url' => '/api/gear',      'verb' => 'POST'],
        ['name' => 'gear#update',  'url' => '/api/gear/{id}', 'verb' => 'PUT'],
        ['name' => 'gear#destroy', 'url' => '/api/gear/{id}', 'verb' => 'DELETE'],

        // Sleep API
        ['name' => 'sleep#index',   'url' => '/api/sleep',             'verb' => 'GET'],
        ['name' => 'sleep#show',    'url' => '/api/sleep/{id}',        'verb' => 'GET'],
        ['name' => 'sleep#stages',  'url' => '/api/sleep/{id}/stages', 'verb' => 'GET'],
        ['name' => 'sleep#destroy', 'url' => '/api/sleep/{id}',        'verb' => 'DELETE'],
        ['name' => 'sleep#reimport', 'url' => '/api/sleep/{id}/reimport', 'verb' => 'POST'],

        // Config
        ['name' => 'config#get_config', 'url' => '/api/config', 'verb' => 'GET'],
        ['name' => 'config#set_config', 'url' => '/api/config', 'verb' => 'POST'],
        ['name' => 'config#debug_fit',  'url' => '/api/debug',  'verb' => 'GET'],
        ['name' => 'config#calendars',       'url' => '/api/calendars',       'verb' => 'GET'],
        ['name' => 'config#export_calendar', 'url' => '/api/calendars/export', 'verb' => 'POST'],
    ],
];
