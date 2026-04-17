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
class FlowTag extends Model
{
    protected $prefix = Config::VAR_PREFIX;

    protected $table = 'flow_tag';

    protected $casts = ['id' => 'int'];

    protected $fillable = [
        'flow_id',
        'tag_id',
    ];
}
