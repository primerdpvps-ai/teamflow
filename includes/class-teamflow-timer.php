<?php
/**
 * TeamFlow Timer Management
 * Path: includes/class-teamflow-timer.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_Timer {
    
    public function __construct() {
        // Timer management actions are handled in AJAX class
        // This class provides helper methods for timer operations
    }
    
    /**
     * Get active timer for user
     */
    public static function get_active_timer($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND status IN ('active', 'paused')
             ORDER BY id DESC 
             LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Calculate elapsed time for entry
     */
    public static function calculate_elapsed_time($entry) {
        if (!$entry) return 0;
        
        $start = strtotime($entry->start_time);
        $now = current_time('timestamp');
        
        if ($entry->status === 'completed' && $entry->end_time) {
            $end = strtotime($entry->end_time);
            return $end - $start;
        }
        
        return $now - $start + $entry->elapsed_seconds;
    }
    
    /**
     * Format time in HH:MM:SS
     */
    public static function format_time($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    
    /**
     * Get user's daily summary
     */
    public static function get_daily_summary($user_id, $date = null) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as session_count,
                SUM(elapsed_seconds) as total_seconds,
                SUM(idle_seconds) as total_idle,
                AVG(activity_level) as avg_activity
             FROM $table 
             WHERE user_id = %d 
             AND DATE(start_time) = %s 
             AND status IN ('completed', 'paused', 'active')",
            $user_id,
            $date
        ));
        
        return $summary;
    }
    
    /**
     * Get user's weekly summary
     */
    public static function get_weekly_summary($user_id) {
        $start_of_week = date('Y-m-d', strtotime('monday this week'));
        $end_of_week = date('Y-m-d', strtotime('sunday this week'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as session_count,
                SUM(elapsed_seconds) as total_seconds,
                AVG(activity_level) as avg_activity
             FROM $table 
             WHERE user_id = %d 
             AND DATE(start_time) BETWEEN %s AND %s 
             AND status IN ('completed', 'paused')",
            $user_id,
            $start_of_week,
            $end_of_week
        ));
        
        return $summary;
    }
    
    /**
     * Get daily breakdown for chart
     */
    public static function get_weekly_breakdown($user_id) {
        $start_of_week = date('Y-m-d', strtotime('monday this week'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DAYNAME(start_time) as day_name,
                DATE(start_time) as date,
                SUM(elapsed_seconds) as total_seconds
             FROM $table 
             WHERE user_id = %d 
             AND DATE(start_time) >= %s 
             AND status IN ('completed', 'paused')
             GROUP BY DATE(start_time)
             ORDER BY DATE(start_time)",
            $user_id,
            $start_of_week
        ));
        
        return $breakdown;
    }
    
    /**
     * Get project distribution
     */
    public static function get_project_distribution($user_id, $period = 'month') {
        $date_condition = '';
        
        switch ($period) {
            case 'week':
                $date_condition = "AND DATE(start_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "AND DATE(start_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $date_condition = "AND DATE(start_time) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
        }
        
        global $wpdb;
        $entries_table = $wpdb->prefix . 'teamflow_time_entries';
        $projects_table = $wpdb->prefix . 'teamflow_projects';
        
        $distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.name as project_name,
                SUM(te.elapsed_seconds) as total_seconds,
                COUNT(te.id) as entry_count
             FROM $entries_table te
             LEFT JOIN $projects_table p ON te.project_id = p.id
             WHERE te.user_id = %d 
             AND te.status IN ('completed', 'paused')
             $date_condition
             GROUP BY te.project_id
             ORDER BY total_seconds DESC",
            $user_id
        ));
        
        return $distribution;
    }
    
    /**
     * Check for idle time alerts
     */
    public static function check_idle_alerts($user_id) {
        $active_timer = self::get_active_timer($user_id);
        
        if (!$active_timer || $active_timer->status !== 'active') {
            return false;
        }
        
        $threshold = get_option('teamflow_inactivity_threshold', 60);
        $last_activity = strtotime($active_timer->updated_at);
        $now = current_time('timestamp');
        $idle_seconds = $now - $last_activity;
        
        return $idle_seconds >= $threshold;
    }
}
