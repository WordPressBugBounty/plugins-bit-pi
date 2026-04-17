<?php

namespace BitApps\Pi\src\Queue;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use stdClass;

abstract class BackgroundProcessHandler extends AsyncRequest
{
    public const STATUS_CANCELLED = 1;

    public const STATUS_PAUSED = 2;

    protected $action = 'background_process';

    protected $startTime = 0;

    protected $cronHookIdentifier;

    protected $queueLockTime = 60;

    protected $cronIntervalIdentifier;

    public function __construct()
    {
        parent::__construct();

        $this->cronHookIdentifier = $this->identifier . '_cron';

        $this->cronIntervalIdentifier = $this->identifier . '_cron_interval';

        add_filter('cron_schedules', [$this, 'scheduleCronHealthCheck']);
    }

    /**
     * Schedule the cron healthCheck and dispatch an async request to start processing the queue.
     *
     * @return array|false|WP_Error HTTP Response array, WP_Error on failure, or false if not attempted
     */
    public function dispatch()
    {
        if ($this->isProcessing()) {
            return false;
        }

        $this->scheduleEvent();

        return parent::dispatch();
    }

    /**
     * Push to the queue.
     *
     * Note, save must be called in order to persist queued items to a batch for processing.
     *
     * @param mixed $data data
     *
     * @return $this
     */
    public function pushToQueue($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Save the queued items for future processing.
     *
     * @return $this
     */
    public function save()
    {
        $key = $this->generateKey();

        if ($this->data !== []) {
            update_site_option($key, $this->data);
        }

        $this->data = [];

        return $this;
    }

    /**
     * Update a batch's queued items.
     *
     * @param string $key  key
     * @param array  $data data
     *
     * @return $this
     */
    public function update($key, $data)
    {
        if (!empty($data)) {
            update_site_option($key, $data);
        }

        return $this;
    }

    /**
     * Delete a batch of queued items.
     *
     * @param string $key key
     *
     * @return $this
     */
    public function delete($key)
    {
        return delete_site_option($key);
    }

    /**
     * Delete entire job queue.
     */
    public function deleteAll()
    {
        $batches = $this->getBatches();

        foreach ($batches as $batch) {
            $this->delete($batch->key);
        }

        delete_site_option($this->getStatusKey());

        $this->cancelled();
    }

    /**
     * Cancel job on next batch.
     */
    public function cancel()
    {
        update_site_option($this->getStatusKey(), self::STATUS_CANCELLED);

        $this->dispatch();
    }

    /**
     * Has the process been cancelled?
     *
     * @return bool
     */
    public function isCancelled()
    {
        $status = get_site_option($this->getStatusKey(), 0);

        return absint($status) === self::STATUS_CANCELLED;
    }

    /**
     * Pause job on next batch.
     */
    public function pause()
    {
        update_site_option($this->getStatusKey(), self::STATUS_PAUSED);
    }

    /**
     * Is the job paused?
     *
     * @return bool
     */
    public function isPaused()
    {
        $status = get_site_option($this->getStatusKey(), 0);

        return absint($status) === self::STATUS_PAUSED;
    }

    /**
     * Resume job.
     */
    public function resume()
    {
        delete_site_option($this->getStatusKey());

        $this->scheduleEvent();

        $this->dispatch();

        $this->resumed();
    }

    /**
     * Is queued?
     *
     * @return bool
     */
    public function isQueued()
    {
        return !$this->isQueueEmpty();
    }

    /**
     * Is the tool currently active, e.g. starting, working, paused or cleaning up?
     *
     * @return bool
     */
    public function isActive()
    {
        if ($this->isQueued()) {
            return true;
        }

        if ($this->isProcessing()) {
            return true;
        }

        if ($this->isPaused()) {
            return true;
        }

        return $this->isCancelled();
    }

    /**
     * Maybe process a batch of queued items.
     *
     * Checks whether data exists within the queue and that
     * the process is not already running.
     */
    public function maybeHandle()
    {
        // Don't lock up other requests while processing.
        session_write_close();

        if ($this->isProcessing()) {
            return $this->maybeWpDie();
        }

        if ($this->isCancelled()) {
            $this->clearScheduledEvent();
            $this->deleteAll();

            return $this->maybeWpDie();
        }

        if ($this->isPaused()) {
            $this->clearScheduledEvent();
            $this->paused();

            return $this->maybeWpDie();
        }

        if ($this->isQueueEmpty()) {
            // No data to process.
            return $this->maybeWpDie();
        }
        check_ajax_referer(Config::withPrefix('nonce'), '_ajax_nonce');
        $this->handle();

        return $this->maybeWpDie();
    }

    /**
     * Is the background process currently running?
     *
     * @return bool
     */
    public function isProcessing()
    {
        return (bool) (get_site_transient($this->identifier . '_process_lock'));
    }

    /**
     * Get batches.
     *
     * @param int        $limit number of batches to return, defaults to all
     *
     * @return array of stdClass
     */
    public function getBatches($limit = 0)
    {
        global $wpdb;

        if (empty($limit) || !\is_int($limit)) {
            $limit = 0;
        }

        $table = $wpdb->options;
        $column = 'option_name';
        $key_column = 'option_id';
        $value_column = 'option_value';

        if (is_multisite()) {
            $table = $wpdb->sitemeta;
            $column = 'meta_key';
            $key_column = 'meta_id';
            $value_column = 'meta_value';
        }

        $key = $wpdb->esc_like($this->identifier . '_batch_') . '%';

        // Table and column names come from $wpdb->options / $wpdb->sitemeta (WordPress core values).
        // They cannot be parameterized with $wpdb->prepare() as SQL identifiers don't support placeholders.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sql = '
			SELECT *
			FROM ' . $table . '
			WHERE ' . $column . ' LIKE %s
			ORDER BY ' . $key_column . ' ASC
			';

        $args = [$key];

        if ($limit !== 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $sql .= ' LIMIT %d';

            $args[] = $limit;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = $wpdb->get_results($wpdb->prepare($sql, $args));

        if (!empty($items)) {
            return array_map(
                function ($item) use ($column, $value_column) {
                    $batch = new stdClass();
                    $batch->key = $item->{$column};
                    $batch->data = maybe_unserialize($item->{$value_column});

                    return $batch;
                },
                $items
            );
        }

        return [];
    }

    /**
     * Schedule the cron healthcheck job.
     *
     * @param mixed $schedules schedules
     *
     * @return mixed
     */
    public function scheduleCronHealthCheck($schedules)
    {
        $interval = $this->getCronInterval();

        if ($interval === 1) {
            $display = __('Every Minute ( Bit Pi )', 'bit-pi');
        } else {
            // translators: %d: Number of minutes
            $display = \sprintf(__('Every %d Minutes ( Bit Pi )', 'bit-pi'), $interval);
        }

        // Adds an "Every NNN Minute(s)" schedule to the existing cron schedules.
        $schedules[$this->cronIntervalIdentifier] = [
            'interval' => MINUTE_IN_SECONDS * $interval,
            'display'  => $display,
        ];

        return $schedules;
    }

    /**
     * Handle cron healthcheck event.
     *
     * Restart the background process if not already running
     * and data exists in the queue.
     */
    public function checkQueueAndRestartBackgroundProcess()
    {
        if ($this->isProcessing()) {
            exit;
        }

        if ($this->isQueueEmpty()) {
            $this->clearScheduledEvent();

            exit;
        }

        $this->dispatch();
    }

    public function batchProcessHandle()
    {
        check_ajax_referer(Config::withPrefix('nonce'), '_ajax_nonce');

        $this->startTime = time();

        $batch = $this->getBatch();

        if (!isset($batch->key)) {
            return;
        }

        if (!$this->delete($batch->key)) {
            return;
        }

        if ($this->checkCpuLoad(70)) {
            sleep(5);
        }

        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $this->executeBatchTasks($batch);

        return $this->maybeWpDie();
    }

    /**
     * Get the cron healthcheck interval in minutes.
     *
     * Default is 5 minutes, minimum is 1 minute.
     *
     * @return int
     */
    public function getCronInterval()
    {
        $interval = apply_filters($this->cronIntervalIdentifier, 5); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

        return \is_int($interval) && $interval > 0 ? $interval : 5;
    }

    /**
     * Called when background process has been cancelled.
     */
    protected function cancelled()
    {
        do_action($this->identifier . '_cancelled'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
    }

    /**
     * Called when background process has been paused.
     */
    protected function paused()
    {
        do_action($this->identifier . '_paused'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
    }

    /**
     * Called when background process has been resumed.
     */
    protected function resumed()
    {
        do_action($this->identifier . '_resumed'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
    }

    /**
     * Generate key for a batch.
     *
     * Generates a unique key based on microtime. Queue items are
     * given a unique key so that they can be merged upon save.
     *
     * @param int    $length optional max length to trim key to, defaults to 64 characters
     * @param string $key    optional string to append to identifier before hash, defaults to "batch"
     *
     * @return string
     */
    protected function generateKey($length = 64, $key = 'batch')
    {
        $unique = md5(microtime() . wp_rand());

        $prepend = $this->identifier . '_' . $key . '_';

        return substr($prepend . $unique, 0, $length);
    }

    /**
     * Get the status key.
     *
     * @return string
     */
    protected function getStatusKey()
    {
        return $this->identifier . '_status';
    }

    /**
     * Is queue empty?
     *
     * @return bool
     */
    protected function isQueueEmpty()
    {
        return empty($this->getBatch());
    }

    /**
     * Lock process.
     *
     * Lock the process so that multiple instances can't run simultaneously.
     * Override if applicable, but the duration should be greater than that
     * defined in the timeExceeded() method.
     */
    protected function lockProcess()
    {
        $this->startTime = time(); // Set start time of current process.

        // $lock_duration = property_exists($this, 'queue_lock_time') ? $this->queue_lock_time : 60;  1 minute
        $lockDuration = apply_filters($this->identifier . '_queue_lock_time', $this->queueLockTime); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

        set_site_transient($this->identifier . '_process_lock', microtime(), $lockDuration);
    }

    /**
     * Unlock process.
     *
     * Unlock the process so that other instances can spawn.
     *
     * @return $this
     */
    protected function unlockProcess()
    {
        delete_site_transient($this->identifier . '_process_lock');

        return $this;
    }

    /**
     * Get batch.
     *
     * @return stdClass return the first batch of queued items
     */
    protected function getBatch()
    {
        return array_reduce(
            $this->getBatches(1),
            fn ($carry, $batch) => $batch,
            []
        );
    }

    /**
     * Handle a dispatched request.
     *
     * Pass each queue item to the task handler, while remaining
     * within server memory and time limit constraints.
     */
    protected function handle()
    {
        $this->lockProcess();

        do {
            $this->batchDispatch();
        } while (!$this->isQueueEmpty() && !$this->timeExceeded() && !$this->memoryExceeded());

        $this->unlockProcess();

        if (!$this->isQueueEmpty()) {
            $this->dispatch();
        } else {
            $this->complete();
        }

        return $this->maybeWpDie();
    }

    /**
     * Memory exceeded?
     *
     * Ensures the batch process never exceeds 90%
     * of the maximum WordPress memory.
     *
     * @return bool
     */
    protected function memoryExceeded()
    {
        $memory_limit = $this->getMemoryLimit() * 0.9; // 90% of max memory

        $current_memory = memory_get_usage(true);
        $return = false;

        if ($current_memory >= $memory_limit) {
            $return = true;
        }

        return apply_filters($this->identifier . '_memory_exceeded', $return); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
    }

    /**
     * Get memory limit in bytes.
     *
     * @return int
     */
    protected function getMemoryLimit()
    {
        $memory_limit = \function_exists('ini_get') ? \ini_get('memory_limit') : '512M';

        if (!$memory_limit || \intval($memory_limit) === -1) {
            // Unlimited, set to 32GB.
            $memory_limit = '32000M';
        }

        return wp_convert_hr_to_bytes($memory_limit);
    }

    /**
     * Time limit exceeded?
     *
     * Ensures the batch never exceeds a sensible time limit.
     * A timeout limit of 30s is common on shared hosting.
     *
     * @return bool
     */
    protected function timeExceeded()
    {
        if (defined('BACKGROUND_PROCESS_DISABLE') && BACKGROUND_PROCESS_DISABLE) {
            return false;
        }

        $finish = $this->startTime + apply_filters($this->identifier . '_default_time_limit', 20); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
        $return = false;

        if (time() >= $finish) {
            $return = true;
        }

        return apply_filters($this->identifier . '_time_exceeded', $return); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
    }

    /**
     * Complete processing.
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete()
    {
        delete_site_option($this->getStatusKey());

        // Remove the cron healthcheck job from the cron schedule.
        $this->clearScheduledEvent();

        $this->completed();
    }

    /**
     * Called when background process has completed.
     */
    protected function completed()
    {
        do_action($this->identifier . '_completed'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
    }

    /**
     * Schedule the cron healthcheck event.
     */
    protected function scheduleEvent()
    {
        if (!wp_next_scheduled($this->cronHookIdentifier)) {
            wp_schedule_event(time() + ($this->getCronInterval() * MINUTE_IN_SECONDS), $this->cronIntervalIdentifier, $this->cronHookIdentifier);
        }
    }

    /**
     * Clear scheduled cron healthcheck event.
     */
    protected function clearScheduledEvent()
    {
        $timestamp = wp_next_scheduled($this->cronHookIdentifier);

        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cronHookIdentifier);
        }
    }

    /**
     * Perform task with queued item.
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @return mixed
     */
    abstract protected function task();

    abstract protected function handleTaskTimeout();

    abstract protected function batchComplete();

    abstract protected function executeBatchTasks($batch);

    private function checkCpuLoad($threshold = 75)
    {
        $cpuLoad = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? $this->getCpuLoadWindows() : $this->getCpuLoadLinux();

        return $cpuLoad > $threshold;
    }

    private function getCpuLoadLinux()
    {
        if (file_exists('/proc/cpuinfo') && is_readable('/proc/cpuinfo')) {
            $cpuCount = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
        } elseif (\function_exists('shell_exec') && is_executable('/usr/bin/nproc')) {
            $cpuCount = (int) trim(shell_exec('nproc'));
        } else {
            $cpuCount = 1;
        }

        $lastOneMinuteLoad = $this->getLastOneMinuteResourceLoad();

        return ($lastOneMinuteLoad / max($cpuCount, 1)) * 100;
    }

    private function getLastOneMinuteResourceLoad()
    {
        $firstMinuteIndexPosition = 0;

        if (\function_exists('sys_getloadavg')) {
            $loadAvg = @sys_getloadavg();
            if ($loadAvg !== false && isset($loadAvg[$firstMinuteIndexPosition])) {
                return $loadAvg[$firstMinuteIndexPosition];
            }
        }

        if (is_readable('/proc/loadavg')) {
            $content = @file_get_contents('/proc/loadavg');
            if ($content !== false) {
                $loadAvg = preg_split('/\s+/', trim($content));
                if (isset($loadAvg[$firstMinuteIndexPosition])) {
                    return $loadAvg[$firstMinuteIndexPosition];
                }
            }
        }

        if (\function_exists('shell_exec')) {
            $uptime = @shell_exec('uptime');
            if ($uptime && preg_match('/load average[s]?:\s*([0-9\.]+)/i', $uptime, $matches)) {
                return str_replace(',', '.', $matches[1]);
            }
        }

        return 0;
    }

    private function getCpuLoadWindows()
    {
        $cpuLoad = trim(shell_exec('wmic cpu get loadpercentage'));

        if (preg_match('/\d+/', $cpuLoad, $matches)) {
            return (int) $matches[0];
        }

        return 0;
    }
}
