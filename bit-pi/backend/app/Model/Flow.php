<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class Flow extends Model
{
    public const IS_HOOK_CAPTURED = 1;

    public const LISTENER_TYPE = [
        'NONE'     => 0,
        'CAPTURE'  => 1, // Capture only trigger data
        'RUN_ONCE' => 2  // Flow run once
    ];

    public const STATUS = [
        'ACTIVE'    => 1,
        'IN_ACTIVE' => 0
    ];

    // public const triggerType = [
    //     'WP_HOOK'  => 1,
    //     'webhook'  => 2,
    //     'SCHEDULE' => 3
    // ];

    public const TOOLS = 'tools';

    public const DEFAULT_SETTINGS = ['onNodeFail' => 'continue'];

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'              => 'int',
        'run_count'       => 'int',
        'is_hook_capture' => 'int',
        'trigger_type'    => 'int',
        'listener_type'   => 'int',
        'is_active'       => 'int',
        'map'             => 'object',
        'data'            => 'object',
        'settings'        => 'array',

    ];

    protected $fillable = [
        'title',
        'run_count',
        'is_active',
        'map',
        'data',
        'tag',
        'tag_id',
        'trigger_type',
        'listener_type',
        'is_hook_capture',
        'settings',
    ];

    public function logs()
    {
        return $this->hasOne(FlowLog::class, 'flow_id');
    }

    public function nodes()
    {
        return $this->hasMany(FlowNode::class, 'flow_id', 'id');
    }

    public function nodesCount()
    {
        return $this
            ->hasMany(FlowNode::class, 'flow_id', 'id')
            ->where('app_slug', '!=', static::TOOLS)
            ->groupBy('flow_id')
            ->select('flow_id')
            ->withCount();
    }
}
