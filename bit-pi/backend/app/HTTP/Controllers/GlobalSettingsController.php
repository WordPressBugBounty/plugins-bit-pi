<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\PiPro\Services\CronService;

class GlobalSettingsController
{
    private $defaultSettings = ['preserve_logs' => 7];

    public function getGlobalDefaultSettings()
    {
        return $this->defaultSettings;
    }

    public function getSettings()
    {
        $settings = Config::getOption('global_settings');

        $cronStatus = Config::isProActivated() ? CronService::getCloudCronStatus() : null;

        if (
            isset($cronStatus->success, $settings['use_cloud_cron'])
             && (bool) $cronStatus->success !== (bool) $settings['use_cloud_cron']
        ) {
            $settings['use_cloud_cron'] = $cronStatus->success;
            Config::updateOption('global_settings', $settings);
        }

        if (!$settings) {
            return Response::success($this->defaultSettings);
        }

        $settings = array_merge($this->defaultSettings, $settings);

        return Response::success($settings);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate(
            [
                'notify_user'        => ['nullable', 'boolean'],
                'notification_email' => ['nullable', 'email', 'max:255'],
                'preserve_logs'      => ['nullable', 'integer'],
                'use_cloud_cron'     => ['nullable', 'boolean'],
            ]
        );

        if (Config::isProActivated()) {
            if (\array_key_exists('use_cloud_cron', $validated)) {
                $res = CronService::createOrDeleteCloudCron($validated['use_cloud_cron']);

                if (!isset($res->success) || !$res->success) {
                    return Response::error(['use_cloud_cron' => [$res->response ?? 'Failed to update cron setting.']])->code('VALIDATION');
                }
            }
        }

        $globalSettings = Config::getOption('global_settings');

        $updated = Config::updateOption(
            'global_settings',
            \is_array($globalSettings) ? array_merge($globalSettings, $validated) : $validated
        );

        if ($updated) {
            return Response::success($validated);
        }

        return Response::error('Failed to update settings');
    }
}
