<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\HTTP\Controllers\AuthorizationController;
use BitApps\Pi\HTTP\Controllers\ConnectionController;
use BitApps\Pi\HTTP\Controllers\CustomAppController;
use BitApps\Pi\HTTP\Controllers\DashboardController;
use BitApps\Pi\HTTP\Controllers\FlowController;
use BitApps\Pi\HTTP\Controllers\FlowSettingsController;
use BitApps\Pi\HTTP\Controllers\GlobalSettingsController;
use BitApps\Pi\HTTP\Controllers\HistoryController;
use BitApps\Pi\HTTP\Controllers\HookListenerController;
use BitApps\Pi\HTTP\Controllers\NodeController;
use BitApps\Pi\HTTP\Controllers\PluginImprovementController;
use BitApps\Pi\HTTP\Controllers\ProxyController;
use BitApps\Pi\HTTP\Controllers\TagController;
use BitApps\Pi\src\Flow\FlowExecutor;

if (!defined('ABSPATH')) {
    exit;
}

if (!headers_sent()) {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    header('Access-Control-Allow-Origin: *');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);

        exit;
    }
}

Route::group(
    function (): void {
        Route::post('proxy/route', [ProxyController::class, 'proxyRequest']);
        Route::get('flows/{flow_id}', [FlowController::class, 'show']);
        Route::post('flows/save', [FlowController::class, 'store']);
        Route::post('flows/update', [FlowController::class, 'update']);
        Route::post('flows/search', [FlowController::class, 'search']);
        Route::post('flows/delete', [FlowController::class, 'destroy']);
        Route::get('flow/re-execute/{flow_id}/{history_id}', [FlowController::class, 'reExecuteFlow']);
        Route::get('flows/{flow_id}/variables', [FlowController::class, 'variables']);

        Route::get('flow-settings/{flow_id}', [FlowSettingsController::class, 'getSettings']);
        Route::post('flow-settings/update', [FlowSettingsController::class, 'updateSettings']);

        Route::get('global-settings', [GlobalSettingsController::class, 'getSettings']);
        Route::post('global-settings/update', [GlobalSettingsController::class, 'updateSettings']);

        Route::get('custom-apps', [CustomAppController::class, 'index']);
        Route::get('custom-apps-active', [CustomAppController::class, 'getActiveCustomApps']);
        Route::post('custom-apps/save', [CustomAppController::class, 'store']);
        Route::get('custom-apps/{customApp}', [CustomAppController::class, 'view']);
        Route::post('custom-apps/{customApp}/update', [CustomAppController::class, 'update']);
        Route::post('custom-apps/{customApp}/delete', [CustomAppController::class, 'destroy']);
        Route::post('custom-apps/{customApp}/update-status', [CustomAppController::class, 'updateStatus']);

        Route::get('node/{flow_id}/{node_id}', [NodeController::class, 'show']);
        Route::post('node/store', [NodeController::class, 'store']);
        Route::post('node/save', [NodeController::class, 'createOrUpdate']);
        Route::post('node/update', [NodeController::class, 'update']);
        Route::post('node/{node}/delete', [NodeController::class, 'destroy']);

        Route::get('tags', [TagController::class, 'index']);
        Route::post('tags/save', [TagController::class, 'store']);
        Route::post('tags/update', [TagController::class, 'update']);
        Route::post('tags/updateStatus', [TagController::class, 'updateStatus']);
        Route::post('tags/delete', [TagController::class, 'destroy']);

        Route::post('connections', [ConnectionController::class, 'index']);
        Route::post('connections/save', [ConnectionController::class, 'store']);
        Route::post('connections/{connection}/delete', [ConnectionController::class, 'destroy']);
        Route::post('refresh-token', [AuthorizationController::class, 'refreshToken']);

        Route::get('hook-capture/{flow_id}/{node_id}', [HookListenerController::class, 'captureResponse']);
        Route::post('stop-hook-listener', [HookListenerController::class, 'stopHookListener']);

        Route::get('histories/{flow_id}/{page_number}/{page_limit}', [HistoryController::class, 'index']);
        Route::get('histories/{history_id}', [HistoryController::class, 'show']);
        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('plugin-improvement', [PluginImprovementController::class, 'getOpt']);
        Route::post('plugin-improvement', [PluginImprovementController::class, 'updateOpt']);
    }
)->middleware('nonce', 'isAdmin');

Route::noAuth()->group(
    function (): void {
        Route::post('background_process_request', [FlowExecutor::class, 'maybeHandle']);
        Route::post('batch_background_process_request', [FlowExecutor::class, 'batchProcessHandle']);
    }
);
