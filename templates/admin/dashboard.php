<?php
/**
 * Dashboard Template
 * Path: templates/admin/dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$stats_today = TeamFlow_Database::get_team_stats('today');
$total_hours_today = 0;
$active_members = 0;
$avg_activity = 0;

if ($stats_today) {
    foreach ($stats_today as $stat) {
        $total_hours_today += $stat->total_seconds / 3600;
        if ($stat->total_seconds > 0) $active_members++;
        $avg_activity += $stat->avg_activity;
    }
    $avg_activity = count($stats_today) > 0 ? $avg_activity / count($stats_today) : 0;
}

$total_team_members = count(get_users(array('role__in' => array('team_member', 'team_manager'))));
?>

<div class="wrap teamflow-wrap" id="tf-dashboard">
    <div class="tf-header">
        <h1><i class="fas fa-chart-line"></i> TeamFlow Dashboard</h1>
        <p class="tf-subtitle">Welcome back, <?php echo esc_html($current_user->display_name); ?>!</p>
    </div>

    <!-- Stats Cards -->
    <div class="tf-stats-grid">
        <div class="tf-stat-card tf-card-blue">
            <div class="tf-stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Total Hours Today</h3>
                <p class="tf-stat-value"><?php echo round($total_hours_today, 1); ?>h</p>
                <span class="tf-stat-change positive">+8%</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-green">
            <div class="tf-stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Active Team Members</h3>
                <p class="tf-stat-value"><?php echo $active_members; ?>/<?php echo $total_team_members; ?></p>
                <span class="tf-stat-change"><?php echo round(($active_members/$total_team_members)*100); ?>%</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-purple">
            <div class="tf-stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Avg Activity Rate</h3>
                <p class="tf-stat-value"><?php echo round($avg_activity); ?>%</p>
                <span class="tf-stat-change positive">+5%</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-orange">
            <div class="tf-stat-icon">
                <i class="fas fa-camera"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Screenshots Today</h3>
                <p class="tf-stat-value">234</p>
                <span class="tf-stat-change positive">+12</span>
            </div>
        </div>
    </div>

    <div class="tf-grid-2">
        <!-- Team Activity -->
        <div class="tf-card">
            <div class="tf-card-header">
                <h2><i class="fas fa-users"></i> Team Activity Today</h2>
            </div>
            <div class="tf-card-body">
                <div id="tf-team-list" class="tf-team-list">
                    <?php
                    foreach ($stats_today as $stat) {
                        $user = get_userdata($stat->user_id);
                        if (!$user) continue;
                        
                        $hours = round($stat->total_seconds / 3600, 1);
                        $activity = round($stat->avg_activity);
                        $initials = '';
                        $name_parts = explode(' ', $user->display_name);
                        foreach ($name_parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        ?>
                        <div class="tf-team-member">
                            <div class="tf-member-avatar"><?php echo esc_html($initials); ?></div>
                            <div class="tf-member-info">
                                <h4><?php echo esc_html($user->display_name); ?></h4>
                                <p class="tf-member-role"><?php echo esc_html(implode(', ', $user->roles)); ?></p>
                            </div>
                            <div class="tf-member-stats">
                                <div class="tf-stat">
                                    <span class="tf-label">Hours</span>
                                    <span class="tf-value"><?php echo $hours; ?>h</span>
                                </div>
                                <div class="tf-stat">
                                    <span class="tf-label">Activity</span>
                                    <span class="tf-value"><?php echo $activity; ?>%</span>
                                </div>
                            </div>
                            <div class="tf-activity-bar">
                                <div class="tf-activity-fill" style="width: <?php echo $activity; ?>%"></div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Weekly Overview Chart -->
        <div class="tf-card">
            <div class="tf-card-header">
                <h2><i class="fas fa-chart-bar"></i> Weekly Hours Overview</h2>
            </div>
            <div class="tf-card-body">
                <canvas id="tf-weekly-chart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-history"></i> Recent Activity</h2>
            <a href="<?php echo admin_url('admin.php?page=teamflow-timesheets'); ?>" class="tf-btn-link">View All</a>
        </div>
        <div class="tf-card-body">
            <div class="tf-activity-feed">
                <?php
                $recent_entries = TeamFlow_Database::get_time_entries(array(
                    'limit' => 10,
                    'status' => 'completed'
                ));
                
                foreach ($recent_entries as $entry) {
                    $user = get_userdata($entry->user_id);
                    if (!$user) continue;
                    
                    $time_ago = human_time_diff(strtotime($entry->start_time), current_time('timestamp'));
                    $icon_class = 'fa-play';
                    $activity_class = 'start';
                    
                    if ($entry->status === 'completed') {
                        $icon_class = 'fa-check-circle';
                        $activity_class = 'complete';
                    } elseif ($entry->status === 'paused') {
                        $icon_class = 'fa-pause';
                        $activity_class = 'pause';
                    }
                    ?>
                    <div class="tf-activity-item tf-activity-<?php echo $activity_class; ?>">
                        <div class="tf-activity-icon">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                        </div>
                        <div class="tf-activity-content">
                            <p><strong><?php echo esc_html($user->display_name); ?></strong> 
                               <?php echo $entry->status === 'completed' ? 'completed' : 'worked on'; ?> 
                               <?php echo esc_html($entry->task_name); ?></p>
                            <span class="tf-activity-time"><?php echo $time_ago; ?> ago</span>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        </div>
        <div class="tf-card-body">
            <div class="tf-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=teamflow-team'); ?>" class="tf-quick-action">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Team Member</span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=teamflow-timesheets'); ?>" class="tf-quick-action">
                    <i class="fas fa-file-export"></i>
                    <span>Export Timesheets</span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=teamflow-payroll'); ?>" class="tf-quick-action">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Process Payroll</span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=teamflow-monitoring'); ?>" class="tf-quick-action">
                    <i class="fas fa-desktop"></i>
                    <span>Live Monitoring</span>
                </a>
            </div>
        </div>
    </div>
</div>
                