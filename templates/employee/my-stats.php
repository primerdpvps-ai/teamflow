<?php
/**
 * Employee My Stats Template
 * Path: templates/employee/my-stats.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get stats for different periods
$stats_today = TeamFlow_Database::get_user_stats($user_id, 'today');
$stats_week = TeamFlow_Database::get_user_stats($user_id, 'week');
$stats_month = TeamFlow_Database::get_user_stats($user_id, 'month');

// Get weekly breakdown for chart
$weekly_breakdown = TeamFlow_Timer::get_weekly_breakdown($user_id);
?>

<div class="wrap teamflow-wrap">
    <div class="tf-header">
        <h1><i class="fas fa-chart-bar"></i> My Statistics</h1>
        <p class="tf-subtitle">Track your productivity and performance</p>
    </div>

    <!-- Period Selector -->
    <div class="tf-period-selector">
        <button class="tf-period-btn active" data-period="today">Today</button>
        <button class="tf-period-btn" data-period="week">This Week</button>
        <button class="tf-period-btn" data-period="month">This Month</button>
    </div>

    <!-- Main Stats Cards -->
    <div class="tf-stats-grid">
        <div class="tf-stat-card tf-card-blue">
            <div class="tf-stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Total Hours</h3>
                <p class="tf-stat-value" id="tf-total-hours">
                    <?php echo $stats_today ? round($stats_today->total_seconds / 3600, 1) : 0; ?>h
                </p>
                <span class="tf-stat-change">Today</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-green">
            <div class="tf-stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Average Activity</h3>
                <p class="tf-stat-value" id="tf-avg-activity">
                    <?php echo $stats_today ? round($stats_today->avg_activity) : 0; ?>%
                </p>
                <span class="tf-stat-change">Productivity level</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-purple">
            <div class="tf-stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Total Sessions</h3>
                <p class="tf-stat-value" id="tf-total-sessions">
                    <?php echo $stats_today ? $stats_today->total_entries : 0; ?>
                </p>
                <span class="tf-stat-change">Completed tasks</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-orange">
            <div class="tf-stat-icon">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Idle Time</h3>
                <p class="tf-stat-value" id="tf-idle-time">
                    <?php echo $stats_today ? round($stats_today->total_idle / 60) : 0; ?>m
                </p>
                <span class="tf-stat-change">Inactive periods</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="tf-charts-grid">
        <!-- Weekly Hours Chart -->
        <div class="tf-card">
            <div class="tf-card-header">
                <h2><i class="fas fa-chart-bar"></i> Weekly Hours</h2>
            </div>
            <div class="tf-card-body">
                <canvas id="tf-weekly-hours-chart" height="300"></canvas>
            </div>
        </div>

        <!-- Activity Distribution -->
        <div class="tf-card">
            <div class="tf-card-header">
                <h2><i class="fas fa-pie-chart"></i> Activity Distribution</h2>
            </div>
            <div class="tf-card-body">
                <canvas id="tf-activity-pie-chart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Stats Table -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-table"></i> Detailed Breakdown</h2>
        </div>
        <div class="tf-card-body">
            <table class="tf-stats-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Total Hours</th>
                        <th>Sessions</th>
                        <th>Avg Activity</th>
                        <th>Idle Time</th>
                        <th>Productive Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Today</strong></td>
                        <td><?php echo $stats_today ? round($stats_today->total_seconds / 3600, 2) : 0; ?>h</td>
                        <td><?php echo $stats_today ? $stats_today->total_entries : 0; ?></td>
                        <td>
                            <div class="tf-activity-badge" style="background: <?php echo $stats_today && $stats_today->avg_activity > 70 ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $stats_today && $stats_today->avg_activity > 70 ? '#166534' : '#92400e'; ?>">
                                <?php echo $stats_today ? round($stats_today->avg_activity) : 0; ?>%
                            </div>
                        </td>
                        <td><?php echo $stats_today ? round($stats_today->total_idle / 60) : 0; ?>m</td>
                        <td>
                            <?php 
                            if ($stats_today) {
                                $productive_hours = ($stats_today->total_seconds - $stats_today->total_idle) / 3600;
                                echo round($productive_hours, 2);
                            } else {
                                echo 0;
                            }
                            ?>h
                        </td>
                    </tr>
                    <tr>
                        <td><strong>This Week</strong></td>
                        <td><?php echo $stats_week ? round($stats_week->total_seconds / 3600, 2) : 0; ?>h</td>
                        <td><?php echo $stats_week ? $stats_week->total_entries : 0; ?></td>
                        <td>
                            <div class="tf-activity-badge" style="background: <?php echo $stats_week && $stats_week->avg_activity > 70 ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $stats_week && $stats_week->avg_activity > 70 ? '#166534' : '#92400e'; ?>">
                                <?php echo $stats_week ? round($stats_week->avg_activity) : 0; ?>%
                            </div>
                        </td>
                        <td><?php echo $stats_week ? round($stats_week->total_idle / 60) : 0; ?>m</td>
                        <td>
                            <?php 
                            if ($stats_week) {
                                $productive_hours = ($stats_week->total_seconds - $stats_week->total_idle) / 3600;
                                echo round($productive_hours, 2);
                            } else {
                                echo 0;
                            }
                            ?>h
                        </td>
                    </tr>
                    <tr>
                        <td><strong>This Month</strong></td>
                        <td><?php echo $stats_month ? round($stats_month->total_seconds / 3600, 2) : 0; ?>h</td>
                        <td><?php echo $stats_month ? $stats_month->total_entries : 0; ?></td>
                        <td>
                            <div class="tf-activity-badge" style="background: <?php echo $stats_month && $stats_month->avg_activity > 70 ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $stats_month && $stats_month->avg_activity > 70 ? '#166534' : '#92400e'; ?>">
                                <?php echo $stats_month ? round($stats_month->avg_activity) : 0; ?>%
                            </div>
                        </td>
                        <td><?php echo $stats_month ? round($stats_month->total_idle / 60) : 0; ?>m</td>
                        <td>
                            <?php 
                            if ($stats_month) {
                                $productive_hours = ($stats_month->total_seconds - $stats_month->total_idle) / 3600;
                                echo round($productive_hours, 2);
                            } else {
                                echo 0;
                            }
                            ?>h
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Performance Insights -->
    <div class="tf-card tf-insights-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-lightbulb"></i> Performance Insights</h2>
        </div>
        <div class="tf-card-body">
            <div class="tf-insights-grid">
                <?php
                $avg_activity = $stats_week ? round($stats_week->avg_activity) : 0;
                $total_hours_week = $stats_week ? round($stats_week->total_seconds / 3600, 1) : 0;
                ?>
                
                <div class="tf-insight">
                    <div class="tf-insight-icon tf-<?php echo $avg_activity > 70 ? 'success' : ($avg_activity > 40 ? 'warning' : 'danger'); ?>">
                        <i class="fas fa-<?php echo $avg_activity > 70 ? 'check-circle' : ($avg_activity > 40 ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                    </div>
                    <div class="tf-insight-content">
                        <h4>Activity Level</h4>
                        <p>
                            <?php if ($avg_activity > 70) : ?>
                                Excellent! You're maintaining high productivity levels.
                            <?php elseif ($avg_activity > 40) : ?>
                                Good work! Consider minimizing distractions to boost productivity.
                            <?php else : ?>
                                Your activity is low. Try to stay focused on active tasks.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="tf-insight">
                    <div class="tf-insight-icon tf-<?php echo $total_hours_week >= 35 ? 'success' : 'warning'; ?>">
                        <i class="fas fa-<?php echo $total_hours_week >= 35 ? 'check-circle' : 'clock'; ?>"></i>
                    </div>
                    <div class="tf-insight-content">
                        <h4>Work Hours</h4>
                        <p>
                            <?php if ($total_hours_week >= 40) : ?>
                                Great! You've met your weekly target hours.
                            <?php elseif ($total_hours_week >= 35) : ?>
                                You're on track! Just a few more hours to hit your target.
                            <?php else : ?>
                                You have <?php echo 40 - $total_hours_week; ?>h remaining to reach your weekly target.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tf-period-selector {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    padding: 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tf-period-btn {
    padding: 12px 24px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.tf-period-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.tf-period-btn:hover:not(.active) {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.tf-charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.tf-stats-table {
    width: 100%;
    border-collapse: collapse;
}

.tf-stats-table th {
    text-align: left;
    padding: 12px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}

.tf-stats-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

.tf-activity-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}

.tf-insights-card {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
    border: 2px solid #3b82f6;
}

.tf-insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.tf-insight {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: white;
    border-radius: 12px;
}

.tf-insight-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 