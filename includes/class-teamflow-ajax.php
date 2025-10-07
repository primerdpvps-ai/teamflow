<?php
/**
 * TeamFlow AJAX Handler - HARDENED VERSION
 * Path: includes/class-teamflow-ajax.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_AJAX {
    
    public function __construct() {
        // Timer actions
        add_action('wp_ajax_teamflow_start_timer', array($this, 'start_timer'));
        add_action('wp_ajax_teamflow_pause_timer', array($this, 'pause_timer'));
        add_action('wp_ajax_teamflow_stop_timer', array($this, 'stop_timer'));
        add_action('wp_ajax_teamflow_update_activity', array($this, 'update_activity'));
        
        // Screenshot actions
        add_action('wp_ajax_teamflow_upload_screenshot', array($this, 'upload_screenshot'));
        add_action('wp_ajax_teamflow_get_screenshots', array($this, 'get_screenshots'));
        
        // Data retrieval actions
        add_action('wp_ajax_teamflow_get_team_data', array($this, 'get_team_data'));
        add_action('wp_ajax_teamflow_get_user_stats', array($this, 'get_user_stats'));
        add_action('wp_ajax_teamflow_get_timesheets', array($this, 'get_timesheets'));
        add_action('wp_ajax_teamflow_get_payroll', array($this, 'get_payroll'));
        
        // Settings actions
        add_action('wp_ajax_teamflow_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_teamflow_get_projects', array($this, 'get_projects'));
    }
    
    private function verify_nonce() {
        if (!check_ajax_referer('teamflow_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        return true;
    }
    
    public function start_timer() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }
        
        // Check capability
        if (!current_user_can('track_time')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        // Check for existing active timer with FOR UPDATE lock
        $wpdb->query('START TRANSACTION');
        
        $active_timer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND status IN ('active', 'paused') ORDER BY id DESC LIMIT 1 FOR UPDATE",
            $user_id
        ));
        
        if ($active_timer) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Timer already running', 'entry_id' => $active_timer->id));
        }
        
        // Sanitize and validate input
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $task_name = isset($_POST['task_name']) ? sanitize_text_field(trim($_POST['task_name'])) : '';
        
        if (empty($task_name)) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Task name is required'));
        }
        
        if (strlen($task_name) > 255) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Task name too long (max 255 characters)'));
        }
        
        // Validate project exists if provided
        if ($project_id) {
            $project_table = $wpdb->prefix . 'teamflow_projects';
            $project_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $project_table WHERE id = %d AND status = 'active'",
                $project_id
            ));
            
            if (!$project_exists) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => 'Invalid project'));
            }
        }
        
        $entry_id = TeamFlow_Database::insert_time_entry(array(
            'user_id' => $user_id,
            'project_id' => $project_id,
            'task_name' => $task_name,
            'start_time' => current_time('mysql'),
            'status' => 'active',
        ));
        
        if ($entry_id) {
            $wpdb->query('COMMIT');
            
            // Clear cache
            wp_cache_delete("teamflow_stats_{$user_id}_today", 'teamflow');
            
            wp_send_json_success(array(
                'message' => 'Timer started',
                'entry_id' => $entry_id,
                'start_time' => current_time('mysql'),
            ));
        } else {
            $wpdb->query('ROLLBACK');
            TeamFlow_Logger::error("Failed to start timer for user $user_id: " . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Failed to start timer'));
        }
    }
    
    public function pause_timer() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        if (!$entry_id) {
            wp_send_json_error(array('message' => 'No entry ID provided'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        // Get current entry with lock
        $wpdb->query('START TRANSACTION');
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d FOR UPDATE",
            $entry_id,
            $user_id
        ));
        
        if (!$entry) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Entry not found'));
        }
        
        if ($entry->status !== 'active') {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Timer is not active'));
        }
        
        // SERVER-SIDE TIME CALCULATION
        $start = strtotime($entry->start_time);
        $now = current_time('timestamp');
        $elapsed = $now - $start + intval($entry->elapsed_seconds);
        
        $result = TeamFlow_Database::update_time_entry($entry_id, array(
            'status' => 'paused',
            'elapsed_seconds' => $elapsed,
        ));
        
        if ($result !== false) {
            $wpdb->query('COMMIT');
            
            // Clear cache
            wp_cache_delete("teamflow_stats_{$user_id}_today", 'teamflow');
            
            wp_send_json_success(array(
                'message' => 'Timer paused',
                'elapsed_seconds' => $elapsed,
            ));
        } else {
            $wpdb->query('ROLLBACK');
            TeamFlow_Logger::error("Failed to pause timer $entry_id: " . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Failed to pause timer'));
        }
    }
    
    public function stop_timer() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        if (!$entry_id) {
            wp_send_json_error(array('message' => 'No entry ID provided'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $wpdb->query('START TRANSACTION');
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d FOR UPDATE",
            $entry_id,
            $user_id
        ));
        
        if (!$entry) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Entry not found'));
        }
        
        // SERVER-SIDE TIME VALIDATION
        $start = strtotime($entry->start_time);
        $now = current_time('timestamp');
        $max_possible_elapsed = $now - $start;
        
        // Calculate elapsed with existing paused time
        $current_session = ($entry->status === 'paused') ? 0 : ($now - $start);
        $total_elapsed = $current_session + intval($entry->elapsed_seconds);
        
        // Validate: reject if exceeds server time by more than 5 minutes (300 seconds)
        if ($total_elapsed > ($max_possible_elapsed + 300)) {
            TeamFlow_Logger::error("Time manipulation detected: user $user_id, entry $entry_id, claimed: $total_elapsed, max: $max_possible_elapsed");
            $total_elapsed = $max_possible_elapsed; // Use server time
        }
        
        // Ensure minimum of 1 second
        if ($total_elapsed < 1) {
            $total_elapsed = 1;
        }
        
        $result = TeamFlow_Database::update_time_entry($entry_id, array(
            'status' => 'completed',
            'end_time' => current_time('mysql'),
            'elapsed_seconds' => $total_elapsed,
        ));
        
        if ($result !== false) {
            $wpdb->query('COMMIT');
            
            // Clear all stat caches for this user
            wp_cache_delete("teamflow_stats_{$user_id}_today", 'teamflow');
            wp_cache_delete("teamflow_stats_{$user_id}_week", 'teamflow');
            wp_cache_delete("teamflow_stats_{$user_id}_month", 'teamflow');
            
            wp_send_json_success(array(
                'message' => 'Timer stopped',
                'elapsed_seconds' => $total_elapsed,
            ));
        } else {
            $wpdb->query('ROLLBACK');
            TeamFlow_Logger::error("Failed to stop timer $entry_id: " . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Failed to stop timer'));
        }
    }
    
    public function update_activity() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        $activity_level = isset($_POST['activity_level']) ? intval($_POST['activity_level']) : 0;
        $idle_seconds = isset($_POST['idle_seconds']) ? intval($_POST['idle_seconds']) : 0;
        
        if (!$entry_id) {
            wp_send_json_error(array('message' => 'No entry ID provided'));
        }
        
        // Validate activity level range
        $activity_level = max(0, min(100, $activity_level));
        $idle_seconds = max(0, $idle_seconds);
        
        // Verify entry belongs to user
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        $entry = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE id = %d AND user_id = %d AND status = 'active'",
            $entry_id,
            $user_id
        ));
        
        if (!$entry) {
            wp_send_json_error(array('message' => 'Invalid entry'));
        }
        
        TeamFlow_Database::update_time_entry($entry_id, array(
            'activity_level' => $activity_level,
            'idle_seconds' => $idle_seconds,
        ));
        
        // Log activity (optional, can be disabled for performance)
        if (get_option('teamflow_log_activity_updates', false)) {
            $activity_table = $wpdb->prefix . 'teamflow_activity_logs';
            $wpdb->insert($activity_table, array(
                'time_entry_id' => $entry_id,
                'user_id' => $user_id,
                'activity_type' => 'activity_update',
                'activity_data' => wp_json_encode(array(
                    'activity_level' => $activity_level,
                    'idle_seconds' => $idle_seconds,
                )),
                'recorded_at' => current_time('mysql'),
            ));
        }
        
        wp_send_json_success(array('message' => 'Activity updated'));
    }
    
    public function upload_screenshot() {
        $this->verify_nonce();
        
        if (!isset($_FILES['screenshot'])) {
            wp_send_json_error(array('message' => 'No screenshot provided'));
        }
        
        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        // FILE VALIDATION
        $file = $_FILES['screenshot'];
        
        // Check file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'File too large (max 5MB)'));
        }
        
        // Validate file type
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        
        if (!$filetype['type'] || !in_array($filetype['type'], array('image/jpeg', 'image/png'))) {
            wp_send_json_error(array('message' => 'Invalid file type. Only JPEG and PNG allowed'));
        }
        
        // Validate actual image
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            wp_send_json_error(array('message' => 'File is not a valid image'));
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            TeamFlow_Logger::error("Screenshot upload failed: " . $upload['error']);
            wp_send_json_error(array('message' => $upload['error']));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_screenshots';
        
        $result = $wpdb->insert($table, array(
            'time_entry_id' => $entry_id,
            'user_id' => $user_id,
            'file_path' => esc_url_raw($upload['url']),
            'activity_level' => isset($_POST['activity_level']) ? intval($_POST['activity_level']) : 0,
            'captured_at' => current_time('mysql'),
        ));
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Screenshot uploaded',
                'screenshot_id' => $wpdb->insert_id,
                'url' => esc_url($upload['url']),
            ));
        } else {
            TeamFlow_Logger::error("Screenshot DB insert failed: " . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Failed to save screenshot'));
        }
    }
    
    public function get_screenshots() {
        $this->verify_nonce();
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        // Check permissions
        if ($user_id != get_current_user_id() && !current_user_can('view_monitoring')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_screenshots';
        
        $where = array('user_id = %d');
        $params = array($user_id);
        
        if ($entry_id) {
            $where[] = 'time_entry_id = %d';
            $params[] = $entry_id;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $screenshots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY captured_at DESC LIMIT 50",
            $params
        ));
        
        wp_send_json_success(array('screenshots' => $screenshots));
    }
    
    public function get_team_data() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_team')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'today';
        $allowed_periods = array('today', 'week', 'month', 'year');
        
        if (!in_array($period, $allowed_periods)) {
            $period = 'today';
        }
        
        $stats = TeamFlow_Database::get_team_stats($period);
        
        $team_data = array();
        foreach ($stats as $stat) {
            $user = get_userdata($stat->user_id);
            if ($user) {
                $team_data[] = array(
                    'id' => absint($stat->user_id),
                    'name' => esc_html($user->display_name),
                    'email' => sanitize_email($user->user_email),
                    'role' => esc_html(implode(', ', $user->roles)),
                    'hours' => round($stat->total_seconds / 3600, 1),
                    'activity' => round($stat->avg_activity),
                    'entries' => absint($stat->total_entries),
                );
            }
        }
        
        wp_send_json_success(array('team' => $team_data));
    }
    
    public function get_user_stats() {
        $this->verify_nonce();
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        
        // Check permissions
        if ($user_id != get_current_user_id() && !current_user_can('view_timesheets')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $periods = array('today', 'week', 'month');
        $data = array();
        
        foreach ($periods as $period) {
            $stats = TeamFlow_Database::get_user_stats($user_id, $period);
            $data[$period] = array(
                'hours' => $stats ? round($stats->total_seconds / 3600, 1) : 0,
                'idle_minutes' => $stats ? round($stats->total_idle / 60, 1) : 0,
                'activity' => $stats ? round($stats->avg_activity) : 0,
                'entries' => $stats ? absint($stats->total_entries) : 0,
            );
        }
        
        wp_send_json_success(array('stats' => $data));
    }
    
    public function get_timesheets() {
        $this->verify_nonce();
        
        if (!current_user_can('view_timesheets')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        
        $entries = TeamFlow_Database::get_time_entries(array(
            'user_id' => $user_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'limit' => 100,
        ));
        
        $formatted_entries = array();
        foreach ($entries as $entry) {
            $user = get_userdata($entry->user_id);
            $project = $entry->project_id ? get_project_name($entry->project_id) : 'No Project';
            
            $formatted_entries[] = array(
                'id' => absint($entry->id),
                'user_name' => $user ? esc_html($user->display_name) : 'Unknown',
                'project' => esc_html($project),
                'task' => esc_html($entry->task_name),
                'start_time' => esc_html($entry->start_time),
                'hours' => round($entry->elapsed_seconds / 3600, 2),
                'activity' => absint($entry->activity_level),
                'idle_minutes' => round($entry->idle_seconds / 60, 1),
                'status' => esc_html($entry->status),
            );
        }
        
        wp_send_json_success(array('entries' => $formatted_entries));
    }
    
    public function get_payroll() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_payroll')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_payroll';
        
        $payroll_records = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY period_end DESC LIMIT 50"
        );
        
        $formatted_records = array();
        foreach ($payroll_records as $record) {
            $user = get_userdata($record->user_id);
            
            $formatted_records[] = array(
                'id' => absint($record->id),
                'employee_name' => $user ? esc_html($user->display_name) : 'Unknown',
                'period_start' => esc_html($record->period_start),
                'period_end' => esc_html($record->period_end),
                'hours_worked' => floatval($record->hours_worked),
                'hourly_rate' => floatval($record->hourly_rate),
                'gross_pay' => floatval($record->gross_pay),
                'deductions' => floatval($record->deductions),
                'net_pay' => floatval($record->net_pay),
                'status' => esc_html($record->status),
            );
        }
        
        wp_send_json_success(array('payroll' => $formatted_records));
    }
    
    public function save_settings() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        $allowed_settings = array(
            'screenshot_interval', 'screenshot_quality', 'recording_fps',
            'inactivity_threshold', 'session_type', 'blur_sensitive',
            'auto_resume', 'track_keyboard', 'track_mouse', 'track_apps',
            'track_urls', 'pay_period', 'tax_rate', 'auto_overtime',
            'low_activity_alerts', 'idle_notifications', 'daily_summary',
            'payroll_reminders'
        );
        
        foreach ($settings as $key => $value) {
            if (!in_array($key, $allowed_settings)) {
                continue; // Skip unknown settings
            }
            
            $option_key = 'teamflow_' . sanitize_key($key);
            update_option($option_key, sanitize_text_field($value));
        }
        
        wp_send_json_success(array('message' => 'Settings saved'));
    }
    
    public function get_projects() {
        $this->verify_nonce();
        
        $projects = TeamFlow_Database::get_projects();
        
        $formatted_projects = array();
        foreach ($projects as $project) {
            $formatted_projects[] = array(
                'id' => absint($project->id),
                'name' => esc_html($project->name),
                'description' => esc_html($project->description),
                'client_name' => esc_html($project->client_name),
                'hourly_rate' => floatval($project->hourly_rate),
                'status' => esc_html($project->status),
            );
        }
        
        wp_send_json_success(array('projects' => $formatted_projects));
    }
}

// Helper function
function get_project_name($project_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'teamflow_projects';
    $project = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table WHERE id = %d", $project_id));
    return $project ? $project->name : 'Unknown Project';
}
