<?php
/**
 * Timer Widget Template
 * Path: templates/shortcodes/timer-widget.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$projects = TeamFlow_Database::get_projects();
$show_projects = isset($atts['show_projects']) && $atts['show_projects'] === 'yes';
$show_stats = isset($atts['show_stats']) && $atts['show_stats'] === 'yes';
?>

<div class="tf-timer-widget">
    <div class="tf-timer-header">
        <h3><i class="fas fa-clock"></i> Time Tracker</h3>
        <span class="tf-timer-status stopped">Stopped</span>
    </div>
    
    <div class="tf-timer-body">
        <!-- Task Input -->
        <div class="tf-form-group">
            <label class="tf-form-label" for="tf-task-input">
                <i class="fas fa-tasks"></i> What are you working on?
            </label>
            <input 
                type="text" 
                id="tf-task-input" 
                class="tf-form-input" 
                placeholder="Enter task description..."
            />
        </div>
        
        <?php if ($show_projects && !empty($projects)) : ?>
        <!-- Project Select -->
        <div class="tf-form-group">
            <label class="tf-form-label" for="tf-project-select">
                <i class="fas fa-folder"></i> Project
            </label>
            <select id="tf-project-select" class="tf-form-select">
                <option value="">Select a project...</option>
                <?php foreach ($projects as $project) : ?>
                    <option value="<?php echo esc_attr($project->id); ?>">
                        <?php echo esc_html($project->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Timer Display -->
        <div class="tf-timer-display-container">
            <div class="tf-timer-display">00:00:00</div>
            <div class="tf-inactive-time" style="display: none;"></div>
        </div>
        
        <!-- Timer Controls -->
        <div class="tf-timer-controls">
            <button class="tf-btn tf-btn-primary tf-start-timer">
                <i class="fas fa-play"></i> Start Timer
            </button>
            <button class="tf-btn tf-btn-warning tf-pause-timer" style="display: none;">
                <i class="fas fa-pause"></i> Pause
            </button>
            <button class="tf-btn tf-btn-danger tf-stop-timer" style="display: none;">
                <i class="fas fa-stop"></i> Stop
            </button>
        </div>
    </div>
    
    <?php if ($show_stats) : 
        $user_id = get_current_user_id();
        $stats_today = TeamFlow_Database::get_user_stats($user_id, 'today');
    ?>
    <!-- Today's Summary -->
    <div class="tf-timer-footer">
        <h4>Today's Summary</h4>
        <div class="tf-timer-stats">
            <div class="tf-timer-stat">
                <span class="tf-stat-icon"><i class="fas fa-clock"></i></span>
                <div>
                    <span class="tf-stat-label">Total Time</span>
                    <span class="tf-stat-value"><?php echo $stats_today ? round($stats_today->total_seconds / 3600, 1) : 0; ?>h</span>
                </div>
            </div>
            <div class="tf-timer-stat">
                <span class="tf-stat-icon"><i class="fas fa-chart-line"></i></span>
                <div>
                    <span class="tf-stat-label">Activity</span>
                    <span class="tf-stat-value"><?php echo $stats_today ? round($stats_today->avg_activity) : 0; ?>%</span>
                </div>
            </div>
            <div class="tf-timer-stat">
                <span class="tf-stat-icon"><i class="fas fa-pause-circle"></i></span>
                <div>
                    <span class="tf-stat-label">Idle Time</span>
                    <span class="tf-stat-value"><?php echo $stats_today ? round($stats_today->total_idle / 60) : 0; ?>m</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hidden settings -->
<input type="hidden" id="tf-inactivity-threshold" value="<?php echo esc_attr(get_option('teamflow_inactivity_threshold', 60)); ?>" />
