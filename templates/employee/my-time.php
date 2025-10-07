<?php
/**
 * Employee My Time Template
 * Path: templates/employee/my-time.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get active timer
$active_timer = TeamFlow_Timer::get_active_timer($user_id);

// Get today's summary
$stats_today = TeamFlow_Database::get_user_stats($user_id, 'today');

// Get recent entries
$recent_entries = TeamFlow_Database::get_time_entries(array(
    'user_id' => $user_id,
    'limit' => 10,
));

// Get projects for dropdown
$projects = TeamFlow_Database::get_projects();
?>

<div class="wrap teamflow-wrap">
    <div class="tf-header">
        <h1><i class="fas fa-clock"></i> My Time Tracking</h1>
        <p class="tf-subtitle">Track your work hours and manage your time entries</p>
    </div>

    <!-- Today's Summary -->
    <div class="tf-stats-grid">
        <div class="tf-stat-card tf-card-blue">
            <div class="tf-stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Hours Today</h3>
                <p class="tf-stat-value">
                    <?php echo $stats_today ? round($stats_today->total_seconds / 3600, 1) : 0; ?>h
                </p>
            </div>
        </div>

        <div class="tf-stat-card tf-card-green">
            <div class="tf-stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Activity Level</h3>
                <p class="tf-stat-value">
                    <?php echo $stats_today ? round($stats_today->avg_activity) : 0; ?>%
                </p>
            </div>
        </div>

        <div class="tf-stat-card tf-card-purple">
            <div class="tf-stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Sessions</h3>
                <p class="tf-stat-value">
                    <?php echo $stats_today ? $stats_today->total_entries : 0; ?>
                </p>
            </div>
        </div>

        <div class="tf-stat-card tf-card-orange">
            <div class="tf-stat-icon">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Idle Time</h3>
                <p class="tf-stat-value">
                    <?php echo $stats_today ? round($stats_today->total_idle / 60) : 0; ?>m
                </p>
            </div>
        </div>
    </div>

    <!-- Timer Widget -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-stopwatch"></i> Time Tracker</h2>
            <?php if ($active_timer) : ?>
                <span class="tf-timer-status <?php echo $active_timer->status; ?>">
                    <?php echo ucfirst($active_timer->status); ?>
                </span>
            <?php else : ?>
                <span class="tf-timer-status stopped">Not Tracking</span>
            <?php endif; ?>
        </div>
        <div class="tf-card-body">
            <div class="tf-timer-widget">
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
                        value="<?php echo $active_timer ? esc_attr($active_timer->task_name) : ''; ?>"
                    />
                </div>

                <!-- Project Select -->
                <div class="tf-form-group">
                    <label class="tf-form-label" for="tf-project-select">
                        <i class="fas fa-folder"></i> Project (Optional)
                    </label>
                    <select id="tf-project-select" class="tf-form-select">
                        <option value="">No Project</option>
                        <?php foreach ($projects as $project) : ?>
                            <option value="<?php echo esc_attr($project->id); ?>" 
                                    <?php selected($active_timer && $active_timer->project_id == $project->id); ?>>
                                <?php echo esc_html($project->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Timer Display -->
                <div class="tf-timer-display-container">
                    <div class="tf-timer-display" id="tf-timer-display">
                        <?php 
                        if ($active_timer) {
                            $elapsed = TeamFlow_Timer::calculate_elapsed_time($active_timer);
                            echo TeamFlow_Timer::format_time($elapsed);
                        } else {
                            echo '00:00:00';
                        }
                        ?>
                    </div>
                    <div class="tf-inactive-time" id="tf-inactive-time" style="display: none;"></div>
                </div>

                <!-- Timer Controls -->
                <div class="tf-timer-controls">
                    <?php if (!$active_timer) : ?>
                        <button class="tf-btn tf-btn-primary tf-start-timer">
                            <i class="fas fa-play"></i> Start Timer
                        </button>
                    <?php else : ?>
                        <?php if ($active_timer->status === 'active') : ?>
                            <button class="tf-btn tf-btn-warning tf-pause-timer">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                        <?php else : ?>
                            <button class="tf-btn tf-btn-primary tf-resume-timer">
                                <i class="fas fa-play"></i> Resume
                            </button>
                        <?php endif; ?>
                        <button class="tf-btn tf-btn-danger tf-stop-timer">
                            <i class="fas fa-stop"></i> Stop & Save
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Time Entries -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-history"></i> Recent Time Entries</h2>
            <a href="<?php echo admin_url('admin.php?page=teamflow-my-stats'); ?>" class="tf-btn-link">
                View All Stats <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="tf-card-body">
            <?php if (empty($recent_entries)) : ?>
                <p class="tf-no-data">No time entries yet. Start tracking your time above!</p>
            <?php else : ?>
            <div class="tf-entries-list">
                <?php foreach ($recent_entries as $entry) : 
                    $project = $entry->project_id ? get_project_name($entry->project_id) : 'No Project';
                    $hours = round($entry->elapsed_seconds / 3600, 2);
                    $date = date('M d, Y', strtotime($entry->start_time));
                    $time = date('h:i A', strtotime($entry->start_time));
                ?>
                <div class="tf-entry-item">
                    <div class="tf-entry-header">
                        <div class="tf-entry-task">
                            <i class="fas fa-check-circle tf-entry-icon tf-entry-<?php echo $entry->status; ?>"></i>
                            <div>
                                <h4><?php echo esc_html($entry->task_name); ?></h4>
                                <p class="tf-entry-meta">
                                    <span class="tf-project-badge"><?php echo esc_html($project); ?></span>
                                    <span><?php echo $date; ?> at <?php echo $time; ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="tf-entry-stats">
                            <div class="tf-entry-stat">
                                <span class="tf-entry-stat-label">Duration</span>
                                <span class="tf-entry-stat-value"><?php echo $hours; ?>h</span>
                            </div>
                            <div class="tf-entry-stat">
                                <span class="tf-entry-stat-label">Activity</span>
                                <span class="tf-entry-stat-value"><?php echo $entry->activity_level; ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="tf-activity-bar">
                        <div class="tf-activity-fill" style="width: <?php echo $entry->activity_level; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden settings -->
<input type="hidden" id="tf-inactivity-threshold" value="<?php echo esc_attr(get_option('teamflow_inactivity_threshold', 60)); ?>" />
<?php if ($active_timer) : ?>
<input type="hidden" id="tf-active-entry-id" value="<?php echo $active_timer->id; ?>" />
<?php endif; ?>

<style>
.tf-timer-display-container {
    text-align: center;
    margin: 24px 0;
}

.tf-timer-controls {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.tf-timer-controls .tf-btn {
    flex: 1;
    max-width: 200px;
}

.tf-entries-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.tf-entry-item {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    transition: background 0.2s;
}

.tf-entry-item:hover {
    background: #f1f5f9;
}

.tf-entry-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.tf-entry-task {
    display: flex;
    gap: 16px;
    flex: 1;
}

.tf-entry-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.tf-entry-icon.tf-entry-completed {
    color: #10b981;
}

.tf-entry-icon.tf-entry-active {
    color: #3b82f6;
}

.tf-entry-icon.tf-entry-paused {
    color: #f59e0b;
}

.tf-entry-task h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #1e293b;
}

.tf-entry-meta {
    display: flex;
    gap: 12px;
    align-items: center;
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

.tf-entry-stats {
    display: flex;
    gap: 24px;
}

.tf-entry-stat {
    text-align: right;
}

.tf-entry-stat-label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 4px;
}

.tf-entry-stat-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.tf-no-data {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

@media (max-width: 768px) {
    .tf-entry-header {
        flex-direction: column;
        gap: 16px;
    }

    .tf-entry-stats {
        width: 100%;
        justify-content: space-around;
    }
}
</style>
