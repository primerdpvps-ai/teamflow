<?php
/**
 * TeamFlow Shortcodes
 * Path: includes/class-teamflow-shortcodes.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_Shortcodes {
    
    public function __construct() {
        add_shortcode('teamflow_timer', array($this, 'timer_shortcode'));
        add_shortcode('teamflow_stats', array($this, 'stats_shortcode'));
        add_shortcode('teamflow_timesheet', array($this, 'timesheet_shortcode'));
        add_shortcode('teamflow_dashboard', array($this, 'dashboard_shortcode'));
    }
    
    /**
     * Timer Widget Shortcode
     * Usage: [teamflow_timer]
     */
    public function timer_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to use the timer.</p>';
        }
        
        $atts = shortcode_atts(array(
            'show_projects' => 'yes',
            'show_stats' => 'yes',
        ), $atts);
        
        ob_start();
        include TEAMFLOW_PLUGIN_DIR . 'templates/shortcodes/timer-widget.php';
        return ob_get_clean();
    }
    
    /**
     * Stats Shortcode
     * Usage: [teamflow_stats period="week"]
     */
    public function stats_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view stats.</p>';
        }
        
        $atts = shortcode_atts(array(
            'period' => 'week',
            'user_id' => get_current_user_id(),
        ), $atts);
        
        $stats = TeamFlow_Database::get_user_stats($atts['user_id'], $atts['period']);
        
        if (!$stats) {
            return '<p>No stats available for this period.</p>';
        }
        
        ob_start();
        ?>
        <div class="tf-stats-widget">
            <h3>Your Stats (<?php echo esc_html(ucfirst($atts['period'])); ?>)</h3>
            <div class="tf-stats-grid">
                <div class="tf-stat-box">
                    <span class="tf-stat-label">Total Hours</span>
                    <span class="tf-stat-value"><?php echo round($stats->total_seconds / 3600, 1); ?>h</span>
                </div>
                <div class="tf-stat-box">
                    <span class="tf-stat-label">Activity Level</span>
                    <span class="tf-stat-value"><?php echo round($stats->avg_activity); ?>%</span>
                </div>
                <div class="tf-stat-box">
                    <span class="tf-stat-label">Idle Time</span>
                    <span class="tf-stat-value"><?php echo round($stats->total_idle / 60); ?>m</span>
                </div>
                <div class="tf-stat-box">
                    <span class="tf-stat-label">Sessions</span>
                    <span class="tf-stat-value"><?php echo $stats->total_entries; ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Timesheet Shortcode
     * Usage: [teamflow_timesheet limit="10"]
     */
    public function timesheet_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your timesheet.</p>';
        }
        
        $atts = shortcode_atts(array(
            'limit' => 10,
            'user_id' => get_current_user_id(),
        ), $atts);
        
        $entries = TeamFlow_Database::get_time_entries(array(
            'user_id' => $atts['user_id'],
            'limit' => $atts['limit'],
        ));
        
        if (empty($entries)) {
            return '<p>No time entries found.</p>';
        }
        
        ob_start();
        ?>
        <div class="tf-timesheet-widget">
            <h3>Recent Time Entries</h3>
            <table class="tf-timesheet-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Task</th>
                        <th>Duration</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) : 
                        $hours = round($entry->elapsed_seconds / 3600, 2);
                        $date = date('M d, Y', strtotime($entry->start_time));
                    ?>
                    <tr>
                        <td><?php echo esc_html($date); ?></td>
                        <td><?php echo esc_html($entry->task_name); ?></td>
                        <td><?php echo esc_html($hours); ?>h</td>
                        <td>
                            <div class="tf-activity-indicator">
                                <div class="tf-activity-bar-small">
                                    <div class="tf-activity-fill-small" style="width: <?php echo $entry->activity_level; ?>%"></div>
                                </div>
                                <span><?php echo $entry->activity_level; ?>%</span>
                            </div>
                        </td>
                        <td><span class="tf-status-badge tf-status-<?php echo $entry->status; ?>"><?php echo ucfirst($entry->status); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
