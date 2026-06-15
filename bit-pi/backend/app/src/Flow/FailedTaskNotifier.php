<?php

namespace BitApps\Pi\src\Flow;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\Model\Flow as FlowModel;

final class FailedTaskNotifier
{
    /**
     * Email the configured admin that a flow node failed, if notifications are enabled.
     *
     * @param array $nodeInfo failed node info (app_slug, machine_slug, node_id)
     * @param mixed $flowId
     */
    public static function send($nodeInfo, $flowId)
    {
        $globalSettings = Config::getOption('global_settings');

        if (empty($globalSettings['notify_user']) || empty($globalSettings['notification_email'])) {
            return;
        }

        $emailBody = self::buildBody($flowId, $nodeInfo);

        Hooks::addFilter('wp_mail_content_type', (fn () => 'text/html; charset=UTF-8'));

        wp_mail(
            $globalSettings['notification_email'],
            'Failed Task Notification',
            $emailBody
        );

        remove_filter('wp_mail_content_type', (fn () => 'text/html; charset=UTF-8'));
    }

    /**
     * Build the HTML email body describing the failed flow node.
     *
     * @param mixed $flowId   flow id used to look up the title and build the details link
     * @param array $nodeInfo failed node info (app_slug, machine_slug, node_id)
     *
     * @return string the HTML email body
     */
    private static function buildBody($flowId, $nodeInfo)
    {
        $flowTitle = FlowModel::select('title')->findOne(['id' => $flowId])->title ?? '';

        $emailBody = '<p>Hello, </p>';

        $emailBody .= '<p>We hope you are doing well. We wanted to inform you that a task in your Flow [' . esc_html($flowTitle) . '], has failed to execute as expected.</p>';

        $emailBody .= '<h4>Failed Task Details</h4>';

        $emailBody .= '<ul>';

        $emailBody .= '<li>Task Name: ' . esc_html(ucfirst($nodeInfo['app_slug']) . '-' . self::convertMachineSlugToLabel($nodeInfo['machine_slug'])) . '</li>';

        $emailBody .= '<li>Node Id: ' . esc_html($nodeInfo['node_id']) . '</li>';

        $emailBody .= '</ul>';

        $emailBody .= '<p>You can review the full workflow details and take further action by visiting the workflow page<p>';

        $emailBody .= '<a href="' . esc_url(admin_url('admin.php?page=' . Config::SLUG . '#/flows/details/' . $flowId)) . '">Click here to view the Flow</a>';

        return $emailBody . '<p> Please check the Flow and take necessary actions to resolve the issue.</p>';
    }

    private static function convertMachineSlugToLabel($machineSlug)
    {
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $machineSlug);

        return ucwords($label);
    }
}
