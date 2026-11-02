<?php

namespace BitApps\Pi\src;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPTelemetry\Telemetry\Telemetry;

/**
 * Telemetry lived in the pro plugin (options under bit_pi_pro_) before it moved back to the free
 * plugin (options under bit_pi_). Carry an existing opt-in/opt-out over so users are not asked again.
 */
final class TelemetryOptionMigration
{
    /**
     * Last free release where telemetry options were stored under the pro prefix; newer free
     * versions have nothing to migrate.
     */
    private const MAX_FREE_VERSION_FOR_MIGRATION = '1.32.0';

    private const OPTION_KEYS = [
        'allow_tracking',
        'tracking_last_sended_at',
        'tracking_notice_dismissed',
        'tracking_skipped',
    ];

    /**
     * Copy each telemetry option that exists under the pro prefix but not under the free prefix,
     * then remove the pro copy so there is a single source of truth.
     *
     * Must run after TelemetryConfig is set up: it re-schedules the weekly report under the free prefix.
     */
    public static function migrateFromProIfAbsent(): void
    {
        if (version_compare(Config::getOption('version', '0.0'), self::MAX_FREE_VERSION_FOR_MIGRATION, '>')) {
            return;
        }

        $migrated = false;

        foreach (self::OPTION_KEYS as $key) {
            $proKey = 'pro_' . $key;
            $proValue = Config::getOption($proKey);

            if ($proValue === false) {
                continue;
            }

            if (Config::getOption($key) === false) {
                Config::updateOption($key, $proValue);
            }

            Config::deleteOption($proKey);
            $migrated = true;
        }

        if (!$migrated) {
            return;
        }

        // The pro weekly event has no listener anymore.
        wp_clear_scheduled_hook(Config::VAR_PREFIX . 'pro_send_tracking_event');

        // Already opted in: trackingOptIn() only restores the missing weekly event, it sends nothing.
        if (Telemetry::report()->isTrackingAllowed()) {
            Telemetry::report()->trackingOptIn();
        }
    }
}
