<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class Webhook extends Model
{
    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'      => 'int',
        'flow_id' => 'int'
    ];

    protected $fillable = [
        'title',
        'flow_id',
        'app_slug',
        'webhook_slug',
    ];

    public function flow()
    {
        return $this->hasOne(Flow::class, 'id', 'flow_id');
    }
}
