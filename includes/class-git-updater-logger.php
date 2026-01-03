<?php
if (!defined('ABSPATH')) {
    exit;
}

class Git_Updater_Logger
{
    const OPTION_NAME = 'git_updater_debug_log';
    const MAX_LOGS = 1000;

    public static function log($message)
    {
        $logs = get_option(self::OPTION_NAME, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        $timestamp = current_time('mysql');
        $entry = "[$timestamp] $message";

        // Prepend new log
        array_unshift($logs, $entry);

        // Limit size
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, 0, self::MAX_LOGS);
        }

        update_option(self::OPTION_NAME, $logs);
    }

    public static function get_logs()
    {
        $logs = get_option(self::OPTION_NAME, array());
        return is_array($logs) ? $logs : array();
    }

    public static function clear()
    {
        delete_option(self::OPTION_NAME);
    }
}
