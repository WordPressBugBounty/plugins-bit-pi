<?php

namespace BitApps\Pi\Model;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Model;

class Connection extends Model
{
    public const STATUS = [
        'verified' => 1,
        'pending'  => 2,
        'failed'   => 3,
    ];

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id'           => 'int',
        'auth_details' => 'object',
        'status'       => 'int',
    ];

    protected $fillable = [
        'app_slug',
        'auth_type',
        'connection_name',
        'account_name',
        'encrypt_keys',
        'auth_details',
        'status',
    ];
}
