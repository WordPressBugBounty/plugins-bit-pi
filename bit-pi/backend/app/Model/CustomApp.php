<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class CustomApp extends Model
{
    public const APP_SLUG_PREFIX = 'custom-app-';

    public const APP_SLUG = 'customApp';

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'     => 'int',
        'status' => 'int',
    ];

    protected $fillable = [
        'name',
        'color',
        'slug',
        'description',
        'logo',
        'status',
    ];

    public function customMachines()
    {
        return $this->hasMany(CustomMachine::class, 'custom_app_id', 'id');
    }
}
