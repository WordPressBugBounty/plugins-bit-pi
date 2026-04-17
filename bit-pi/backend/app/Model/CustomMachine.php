<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class CustomMachine extends Model
{
    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'            => 'int',
        'config'        => 'array',
        'status'        => 'int',
        'custom_app_id' => 'int',
        'connection_id' => 'int',
    ];

    protected $fillable = [
        'custom_app_id',
        'connection_id',
        'name',
        'slug',
        'app_type',
        'trigger_type',
        'status',
        'config',
    ];
}
