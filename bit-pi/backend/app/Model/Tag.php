<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

/**
 * Undocumented class.
 */
class Tag extends Model
{
    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'     => 'int',
        'status' => 'boolean'
    ];

    protected $fillable = [
        'title',
        'slug',
        'filter',
        'status',
    ];
}
