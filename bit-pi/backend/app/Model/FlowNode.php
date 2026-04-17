<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class FlowNode extends Model
{
    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'            => 'int',
        'flow_id'       => 'int',
        'count'         => 'int',
        'field_mapping' => 'object',
        'data'          => 'object',
        'variables'     => 'array',
    ];

    protected $fillable = [
        'node_id',
        'flow_id',
        'app_slug',
        'machine_slug',
        'field_mapping',
        'data',
        'variables',
    ];
}
