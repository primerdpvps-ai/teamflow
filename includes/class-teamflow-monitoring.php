<?php
/**
 * TeamFlow Monitoring System
 * Path: includes/class-teamflow-monitoring.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_Monitoring {
    
    public function __construct() {
        // Screenshot upload handling
        add_action('wp_ajax_teamflow_capture_screenshot', array($this, 'handle_screenshot_upload'));
        
        // Activity logging
        add_action('wp_ajax_teamflow_log_activity', array($this, 'log_activity'));
        
        // Get monitoring data
        add_action('wp_ajax_teamflow_get_monitoring_data', array($this, 'get_monitoring_data'));
        
        // Schedule screenshot cleanup (remove old screenshots)
        if (!wp_next_scheduled('teamflow_cleanup_screenshots')) {
            wp_schedule_event(time(), 'daily', 'teamflow_cleanup_screenshots');
        }
        add_action('teamflow_cleanup_screenshots', array($this, 'cleanup_old_screenshots'));
    }
    
    /**
     * Handle screenshot upload from client
     */
    public function handle_screenshot_upload() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        if (!isset($_FILES['screenshot']) || !isset($_POST['entry_id'])) {
            wp_send_json_error(array('message' => 'Missing required data'));
        }
        
        $user_id = get_current_user_id();
        $entry_id = intval($_POST['entry_id']);
        $activity_level = isset($_POST['activity_level']) ? intval($_POST['activity_level']) : 0;
        
        // Verify entry belongs to user
        global $wpdb;
        $entry_table = $wpdb->prefix . 'teamflow_time_entries';
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $entry_table WHERE id = %d AND user_id = %d",
            $entry_id,
            $user_id
        ));
        
        if (!$entry) {
            wp_send_json_error(array('message' => 'Invalid entry'));
        }
        
        // Handle file upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
            )
        );
        
        $uploaded_file = wp_handle_upload($_FILES['screenshot'], $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error(array('message' => $uploaded_file['error']));
        }
        
        // Optionally blur sensitive content if enabled
        $blur_sensitive = get_option('teamflow_blur_sensitive', true);
        if ($blur_sensitive) {
            $this->blur_sensitive_content($uploaded_file['file']);
        }
        
        // Create thumbnail
        $thumbnail_path = $this->create_thumbnail($uploaded_file['file']);
        
        // Save to database
        $screenshot_table = $wpdb->prefix . 'teamflow_screenshots';
        $wpdb->insert($screenshot_table, array(
            'time_entry_id' => $entry_id,
            'user_id' => $user_id,
            'file_path' => $uploaded_file['url'],
            'thumbnail_path' => $thumbnail_path,
            'activity_level' => $activity_level,
            'captured_at' => current_time('mysql'),
        ));
        
        $screenshot_id = $wpdb->insert_id;
        
        wp_send_json_success(array(
            'screenshot_id' => $screenshot_id,
            'url' => $uploaded_file['url'],
            'thumbnail' => $thumbnail_path,
        ));
    }
    
    /**
     * Log activity (keyboard, mouse, app usage)
     */
    public function log_activity() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : '';
        $activity_data = isset($_POST['activity_data']) ? $_POST['activity_data'] : array();
        
        if (!$entry_id || !$activity_type) {
            wp_send_json_error(array('message' => 'Missing required data'));
        }
        
        // Check if this activity type is enabled
        $track_keyboard = get_option('teamflow_track_keyboard', true);
        $track_mouse = get_option('teamflow_track_mouse', true);
        $track_apps = get_option('teamflow_track_apps', true);
        $track_urls = get_option('teamflow_track_urls', false);
        
        $allowed = false;
        switch ($activity_type) {
            case 'keyboard':
                $allowed = $track_keyboard;
                break;
            case 'mouse':
                $allowed = $track_mouse;
                break;
            case 'app_usage':
                $allowed = $track_apps;
                break;
            case 'url_visit':
                $allowed = $track_urls;
                break;
        }
        
        if (!$allowed) {
            wp_send_json_error(array('message' => 'Activity tracking disabled for this type'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_activity_logs';
        
        $wpdb->insert($table, array(
            'time_entry_id' => $entry_id,
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'activity_data' => json_encode($activity_data),
            'recorded_at' => current_time('mysql'),
        ));
        
        wp_send_json_success(array('message' => 'Activity logged'));
    }
    
    /**
     * Get monitoring data for admin view
     */
    public function get_monitoring_data() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        if (!current_user_can('view_monitoring')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        $entry_table = $wpdb->prefix . 'teamflow_time_entries';
        $screenshot_table = $wpdb->prefix . 'teamflow_screenshots';
        
        // Get active users
        $active_entries = $wpdb->get_results(
            "SELECT * FROM $entry_table 
             WHERE status = 'active' 
             AND DATE(start_time) = CURDATE()
             ORDER BY start_time DESC"
        );
        
        $monitoring_data = array();
        
        foreach ($active_entries as $entry) {
            $user = get_userdata($entry->user_id);
            if (!$user) continue;
            
            // Get latest screenshot
            $latest_screenshot = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $screenshot_table 
                 WHERE time_entry_id = %d 
                 ORDER BY captured_at DESC LIMIT 1",
                $entry->id
            ));
            
            // Calculate current duration
            $start = strtotime($entry->start_time);
            $now = current_time('timestamp');
            $duration = ($now - $start + $entry->elapsed_seconds) / 3600;
            
            $monitoring_data[] = array(
                'user_id' => $entry->user_id,
                'user_name' => $user->display_name,
                'task' => $entry->task_name,
                'duration' => round($duration, 2),
                'activity_level' => $entry->activity_level,
                'latest_screenshot' => $latest_screenshot ? $latest_screenshot->thumbnail_path : null,
                'last_activity' => $latest_screenshot ? human_time_diff(strtotime($latest_screenshot->captured_at), $now) : 'N/A',
            );
        }
        
        wp_send_json_success(array('monitoring' => $monitoring_data));
    }
    
    /**
     * Create thumbnail from screenshot
     */
    private function create_thumbnail($file_path) {
        $image_editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($image_editor)) {
            return null;
        }
        
        $image_editor->resize(300, 200, true);
        $thumbnail_path = str_replace('.', '-thumb.', $file_path);
        $image_editor->save($thumbnail_path);
        
        return str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $thumbnail_path);
    }
    
    /**
     * Blur sensitive content in screenshots (basic implementation)
     */
    private function blur_sensitive_content($file_path) {
        // This is a placeholder - you would implement actual blur logic
        // using image processing libraries or external services
        // For example: blur text areas, password fields, etc.
        return $file_path;
    }
    
    /**
     * Cleanup old screenshots (runs daily)
     */
    public function cleanup_old_screenshots() {
        $retention_days = get_option('teamflow_screenshot_retention_days', 30);
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_screenshots';
        
        // Get old screenshots
        $old_screenshots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE captured_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
        
        foreach ($old_screenshots as $screenshot) {
            // Delete physical files
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $screenshot->file_path);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            if ($screenshot->thumbnail_path) {
                $thumb_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $screenshot->thumbnail_path);
                if (file_exists($thumb_path)) {
                    unlink($thumb_path);
                }
            }
            
            // Delete database record
            $wpdb->delete($table, array('id' => $screenshot->id));
        }
    }
    
    /**
     * Get activity heatmap data
     */
    public static function get_activity_heatmap($user_id, $date = null) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_activity_logs';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT activity_type, COUNT(*) as count 
             FROM $table 
             WHERE user_id = %d 
             AND DATE(recorded_at) = %s 
             GROUP BY activity_type",
            $user_id,
            $date
        ));
        
        $heatmap = array(
            'keyboard' => 0,
            'mouse' => 0,
            'app_usage' => 0,
            'url_visit' => 0,
        );
        
        foreach ($results as $result) {
            $heatmap[$result->activity_type] = $result->count;
        }
        
        return $heatmap;
    }
}
