<?php
/**
 * TeamFlow Uninstall Script
 * Path: uninstall.php
 * 
 * This file runs when the plugin is DELETED (not just deactivated)
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only proceed if user has admin rights
if (!current_user_can('activate_plugins')) {
    return;
}

global $wpdb;

// Option: Check if user wants to keep data
$keep_data = get_option('teamflow_keep_data_on_uninstall', false);

if ($keep_data) {
    // User wants to keep data, only remove scheduled events
    wp_clear_scheduled_hook('teamflow_daily_cleanup');
    wp_clear_scheduled_hook('teamflow_cleanup_screenshots');
    return;
}

// === Delete Database Tables ===
$tables = array(
    $wpdb->prefix . 'teamflow_time_entries',
    $wpdb->prefix . 'teamflow_screenshots',
    $wpdb->prefix . 'teamflow_activity_logs',
    $wpdb->prefix . 'teamflow_projects',
    $wpdb->prefix . 'teamflow_payroll',
    $wpdb->prefix . 'teamflow_user_settings',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// === Delete Options ===
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'teamflow_%'");

// === Delete User Meta ===
$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'teamflow_%'");

// === Remove Custom Roles ===
remove_role('team_manager');
remove_role('team_member');

// === Remove Capabilities from Admin ===
$admin = get_role('administrator');
if ($admin) {
    $admin->remove_cap('manage_team');
    $admin->remove_cap('view_timesheets');
    $admin->remove_cap('manage_payroll');
    $admin->remove_cap('view_monitoring');
    $admin->remove_cap('track_time');
    $admin->remove_cap('view_own_stats');
}

// === Clear Scheduled Events ===
wp_clear_scheduled_hook('teamflow_daily_cleanup');
wp_clear_scheduled_hook('teamflow_cleanup_screenshots');

// === Delete Uploaded Screenshots ===
$upload_dir = wp_upload_dir();
$screenshot_dir = $upload_dir['basedir'] . '/teamflow-screenshots';

if (is_dir($screenshot_dir)) {
    // Recursively delete directory
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($screenshot_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $delete_function = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $delete_function($fileinfo->getRealPath());
    }

    rmdir($screenshot_dir);
}

// === Delete Log Files ===
$log_file = WP_CONTENT_DIR . '/teamflow-debug.log';
if (file_exists($log_file)) {
    unlink($log_file);
}

// Delete log backups
$log_backups = glob(WP_CONTENT_DIR . '/teamflow-debug.log.*.bak');
foreach ($log_backups as $backup) {
    unlink($backup);
}

// === Clear Cache ===
wp_cache_flush();

// === Flush Rewrite Rules ===
flush_rewrite_rules();
