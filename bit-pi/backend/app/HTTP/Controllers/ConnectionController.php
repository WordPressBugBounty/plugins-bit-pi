<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\HTTP\Requests\ConnectionIndexRequest;
use BitApps\Pi\HTTP\Requests\ConnectionStoreRequest;
use BitApps\Pi\HTTP\Requests\ConnectionUpdateRequest;
use BitApps\Pi\Model\Connection;

final class ConnectionController
{
    public function index(ConnectionIndexRequest $request)
    {
        $query = Connection::where('status', Connection::STATUS['verified']);

        if (!empty($request['appSlug'])) {
            $query->where('app_slug', $request['appSlug']);
        }

        $connections = $query->desc()->get(['id', 'app_slug', 'auth_type', 'connection_name', 'auth_details', 'created_at']);

        return Response::success($connections);
    }

    public function store(ConnectionStoreRequest $request)
    {
        $reqData = $request->validated();
        $appSlug = $reqData['app_slug'] ?? '';

        $encrypt_keys = $request->get('encrypt_keys', []);
        $reqData['encrypt_keys'] = implode(',', $encrypt_keys);
        $authDetails = $reqData['auth_details'];
        $authDetails['generated_at'] = time();
        $authDetails = apply_filters(Config::VAR_PREFIX . 'before_store_connection_auth_details', $authDetails, $appSlug);

        if (\count($encrypt_keys) > 0) {
            foreach ($encrypt_keys as $value) {
                if (empty($authDetails[$value])) {
                    continue;
                }

                $authDetails[$value] = Hash::encrypt($authDetails[$value]);
            }
        }

        $reqData['auth_details'] = JSON::maybeEncode($authDetails);

        $connection = Connection::insert($reqData);

        return Response::success($connection);
    }

    public function update(ConnectionUpdateRequest $request)
    {
        $validated = $request->validated();

        if (empty(trim($validated['connection_name']))) {
            return Response::error('Connection name is required');
        }

        $connection = Connection::findOne(['id' => $validated['connection']]);
        if (!$connection) {
            return Response::error('Connection not found');
        }

        $result = $connection->update($validated);
        if (!$result) {
            return Response::error('Failed to update connection');
        }

        return Response::success($connection);
    }

    public function destroy(Connection $connection)
    {
        // delete() on a row that is gone builds no WHERE clause and throws instead of returning.
        if (!$connection->exists()) {
            return Response::error('Connection not found.');
        }

        $connection->delete();

        // Route binding fills in the key alone, and it arrives as text.
        return Response::success((int) $connection->id);
    }
}
