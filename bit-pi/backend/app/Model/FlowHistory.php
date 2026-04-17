<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class FlowHistory extends Model
{
    public const STATUS = [
        'SUCCESS'         => 'success',
        'PROCESSING'      => 'processing',
        'FAILED'          => 'failed',
        'PARTIAL_SUCCESS' => 'partial-success'
    ];

    protected $prefix = Config::VAR_PREFIX;

    protected $table = 'flow_histories';

    protected $casts = [
        'id'                => 'int',
        'parent_history_id' => 'int',
        'flow_id'           => 'int',
    ];

    protected $fillable = [
        'flow_id',
        'parent_history_id',
        'status',
    ];

    public function logs()
    {
        return $this->hasMany(FlowLog::class, 'flow_history_id', 'id');
    }

    public function flow()
    {
        return $this->hasOne(Flow::class, 'id', 'flow_id');
    }
}
