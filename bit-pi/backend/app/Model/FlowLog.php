<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class FlowLog extends Model
{
    public const STATUS = [
        'SUCCESS'   => 'success',
        'ERROR'     => 'error',
        'PENDING'   => 'pending',
        'COMPLETED' => 'completed'
    ];

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'              => 'int',
        'flow_history_id' => 'int',
        'output'          => 'array',
        'input'           => 'array',
        'details'         => 'array'
    ];

    protected $fillable = [
        'flow_history_id',
        'node_id',
        'status',
        'messages',
        'input',
        'output',
        'details',
    ];
}
