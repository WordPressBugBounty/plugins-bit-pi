<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\Mysql\MysqlHelper;

Route::group(
    function () {
        Route::post('mysql/get-tables', [MysqlHelper::class, 'getTables']);
        Route::post('mysql/get-columns', [MysqlHelper::class, 'getColumns']);
    }
)->middleware('nonce:admin');
