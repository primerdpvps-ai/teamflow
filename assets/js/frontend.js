/**
 * TeamFlow Frontend JavaScript
 * Path: assets/js/frontend.js
 */

(function($) {
    'use strict';
    
    const TeamFlowFrontend = {
        timer: {
            entryId: null,
            startTime: null,
            elapsedSeconds: 0,
            isRunning: false,
            isPaused: false,
            intervalId: null,
            activityCheckId: null,
            lastActivity: Date.now(),
            inactivitySeconds: 0,
            
            init: function() {
                this.bindEvents();
                this.checkExistingTimer();
                this.startActivityMonitoring();
            },
            
            bindEvents: function() {
                $(document).on('click', '.tf-start-timer', this.start.bind(this));
                $(document).on('click', '.tf-pause-timer', this.pause.bind(this));
                $(document).on('click', '.tf-resume-timer', this.resume.bind(this));
                $(document).on('click', '.tf-stop-timer', this.stop.bind(this));
                
                // Activity detection
                $(document).on('mousemove keydown click', this.recordActivity.bind(this));
            },
            
            checkExistingTimer: function() {
                const entryId = $('#tf-active-entry-id').val();
                if (entryId) {
                    this.entryId = parseInt(entryId);
                    this.isRunning = true;
                    this.startCounter();
                    this.updateUI();
                }
            },
            
            start: function(e) {
                e.preventDefault();
                
                const projectId = $('#tf-project-select').val();
                const taskName = $('#tf-task-input').val();
                
                if (!taskName || taskName.trim() === '') {
                    this.showNotification('Please enter a task description', 'warning');
                    $('#tf-task-input').focus();
                    return;
                }
                
                const $btn = $(e.currentTarget);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Starting...');
                
                $.ajax({
                    url: teamflow.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'teamflow_start_timer',
                        nonce: teamflow.nonce,
                        project_id: projectId,
                        task_name: taskName
                    },
                    success: (response) => {
                        if (response.success) {
                            this.entryId = response.data.entry_id;
                            this.startTime = new Date();
                            this.elapsedSeconds = 0;
                            this.isRunning = true;
                            this.isPaused = false;
                            
                            this.startCounter();
                            this.updateUI();
                            
                            this.showNotification('Timer started successfully', 'success');
                        } else {
                            this.showNotification(response.data.message || 'Failed to start timer', 'error');
                        }
                    },
                    error: () => {
                        this.showNotification('Network error. Please try again.', 'error');
                    },
                    complete: () => {
                        $btn.prop('disabled', false).html('<i class="fas fa-play"></i> Start Timer');
                    }
                });
            },
            
            pause: function(e) {
                e.preventDefault();
                
                if (!this.entryId) return;
                
                const $btn = $(e.currentTarget);
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: teamflow.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'teamflow_pause_timer',
                        nonce: teamflow.nonce,
                        entry_id: this.entryId
                    },
                    success: (response) => {
                        if (response.success) {
                            this.isPaused = true;
                            this.stopCounter();
                            this.updateUI();
                            this.showNotification('Timer paused', 'info');
                        }
                    },
                    complete: () => {
                        $btn.prop('disabled', false);
                    }
                });
            },
            
            resume: function(e) {
                e.preventDefault();
                
                this.isPaused = false;
                this.startCounter();
                this.updateUI();
                this.showNotification('Timer resumed', 'success');
            },
            
            stop: function(e) {
                e.preventDefault();
                
                if (!this.entryId) return;
                
                if (!confirm('Stop timer and save this time entry?')) {
                    return;
                }
                
                const $btn = $(e.currentTarget);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Stopping...');
                
                $.ajax({
                    url: teamflow.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'teamflow_stop_timer',
                        nonce: teamflow.nonce,
                        entry_id: this.entryId
                    },
                    success: (response) => {
                        if (response.success) {
                            const hours = (response.data.elapsed_seconds / 3600).toFixed(2);
                            this.reset();
                            this.showNotification(`Timer stopped. Total time: ${hours} hours`, 'success');
                            
                            // Reload page after 2 seconds to show updated data
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    },
                    error: () => {
                        this.showNotification('Failed to stop timer', 'error');
                        $btn.prop('disabled', false).html('<i class="fas fa-stop"></i> Stop & Save');
                    }
                });
            },
            
            reset: function() {
                this.entryId = null;
                this.startTime = null;
                this.elapsedSeconds = 0;
                this.isRunning = false;
                this.isPaused = false;
                this.stopCounter();
                this.updateUI();
                $('#tf-task-input').val('');
                $('#tf-project-select').val('');
            },
            
            startCounter: function() {
                if (this.intervalId) return;
                
                this.intervalId = setInterval(() => {
                    if (!this.isPaused) {
                        this.elapsedSeconds++;
                        this.updateDisplay();
                        
                        // Update activity every 30 seconds
                        if (this.elapsedSeconds % 30 === 0) {
                            this.updateActivity();
                        }
                    }
                }, 1000);
            },
            
            stopCounter: function() {
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                    this.intervalId = null;
                }
            },
            
            startActivityMonitoring: function() {
                this.activityCheckId = setInterval(() => {
                    if (this.isRunning && !this.isPaused) {
                        const timeSinceActivity = Math.floor((Date.now() - this.lastActivity) / 1000);
                        this.inactivitySeconds = timeSinceActivity;
                        
                        const threshold = parseInt($('#tf-inactivity-threshold').val()) || 60;
                        
                        if (timeSinceActivity >= threshold) {
                            this.pause({ preventDefault: () => {}, currentTarget: $('.tf-pause-timer') });
                            this.showNotification('Timer auto-paused due to inactivity', 'warning');
                        }
                        
                        this.updateDisplay();
                    }
                }, 1000);
            },
            
            recordActivity: function() {
                this.lastActivity = Date.now();
                this.inactivitySeconds = 0;
            },
            
            updateActivity: function() {
                if (!this.entryId) return;
                
                const activityLevel = this.calculateActivityLevel();
                
                $.ajax({
                    url: teamflow.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'teamflow_update_activity',
                        nonce: teamflow.nonce,
                        entry_id: this.entryId,
                        activity_level: activityLevel,
                        idle_seconds: this.inactivitySeconds
                    }
                });
            },
            
            calculateActivityLevel: function() {
                const recentActivity = Date.now() - this.lastActivity;
                if (recentActivity < 5000) return 95;
                if (recentActivity < 15000) return 80;
                if (recentActivity < 30000) return 60;
                if (recentActivity < 60000) return 40;
                return 20;
            },
            
            updateDisplay: function() {
                const display = this.formatTime(this.elapsedSeconds);
                $('#tf-timer-display, .tf-timer-display').text(display);
                
                if (this.inactivitySeconds > 10 && this.isRunning && !this.isPaused) {
                    const inactiveDisplay = this.formatTime(this.inactivitySeconds);
                    $('#tf-inactive-time, .tf-inactive-time').text(`Inactive: ${inactiveDisplay}`).show();
                } else {
                    $('#tf-inactive-time, .tf-inactive-time').hide();
                }
            },
            
            updateUI: function() {
                if (this.isRunning) {
                    $('.tf-start-timer').hide();
                    
                    if (this.isPaused) {
                        $('.tf-pause-timer').hide();
                        $('.tf-resume-timer').show();
                        $('.tf-timer-status').text('Paused').removeClass().addClass('tf-timer-status paused');
                    } else {
                        $('.tf-pause-timer').show();
                        $('.tf-resume-timer').hide();
                        $('.tf-timer-status').text('Active').removeClass().addClass('tf-timer-status active');
                    }
                    
                    $('.tf-stop-timer').show();
                } else {
                    $('.tf-start-timer').show();
                    $('.tf-pause-timer, .tf-resume-timer, .tf-stop-timer').hide();
                    $('.tf-timer-status').text('Not Tracking').removeClass().addClass('tf-timer-status stopped');
                }
            },
            
            formatTime: function(seconds) {
                const hrs = Math.floor(seconds / 3600);
                const mins = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            },
            
            showNotification: function(message, type = 'info') {
                TeamFlowFrontend.notifications.show(message, type);
            }
        },
        
        stats: {
            init: function() {
                this.initCharts();
                this.bindPeriodSelector();
            },
            
            bindPeriodSelector: function() {
                $('.tf-period-btn').on('click', function() {
                    const period = $(this).data('period');
                    
                    $('.tf-period-btn').removeClass('active');
                    $(this).addClass('active');
                    
                    TeamFlowFrontend.stats.loadStats(period);
                });
            },
            
            loadStats: function(period) {
                $.ajax({
                    url: teamflow.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'teamflow_get_user_stats',
                        nonce: teamflow.nonce,
                        user_id: teamflow.current_user_id
                    },
                    success: function(response) {
                        if (response.success && response.data.stats[period]) {
                            const stats = response.data.stats[period];
                            
                            $('#tf-total-hours').text(stats.hours + 'h');
                            $('#tf-avg-activity').text(stats.activity + '%');
                            $('#tf-total-sessions').text(stats.entries);
                            $('#tf-idle-time').text(stats.idle_minutes + 'm');
                        }
                    }
                });
            },
            
            initCharts: function() {
                // Weekly hours chart
                if ($('#tf-weekly-hours-chart').length) {
                    const ctx = document.getElementById('tf-weekly-hours-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                            datasets: [{
                                label: 'Hours Worked',
                                data: [7.5, 8.2, 7.8, 8.0, 6.5, 0, 0],
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value + 'h';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Activity pie chart
                if ($('#tf-activity-pie-chart').length) {
                    const ctx = document.getElementById('tf-activity-pie-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Active Work', 'Meetings', 'Breaks', 'Idle'],
                            datasets: [{
                                data: [65, 20, 10, 5],
                                backgroundColor: [
                                    'rgba(16, 185, 129, 0.8)',
                                    'rgba(59, 130, 246, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(239, 68, 68, 0.8)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            }
        },
        
        notifications: {
            show: function(message, type = 'info') {
                const iconMap = {
                    success: 'fa-check-circle',
                    error: 'fa-times-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };
                
                const icon = iconMap[type] || iconMap.info;
                
                const notification = $(`
                    <div class="tf-notification tf-notification-${type}">
                        <i class="fas ${icon}"></i>
                        <span>${message}</span>
                    </div>
                `);
                
                $('body').append(notification);
                
                setTimeout(() => {
                    notification.addClass('show');
                }, 10);
                
                setTimeout(() => {
                    notification.removeClass('show');
                    setTimeout(() => notification.remove(), 300);
                }, 4000);
            }
        },
        
        dashboard: {
            init: function() {
                this.loadRecentEntries();
            },
            
            loadRecentEntries: function() {
                // Already loaded from server, but could refresh via AJAX if needed
            }
        },
        
        init: function() {
            $(document).ready(() => {
                // Initialize timer if timer widget exists
                if ($('.tf-timer-widget').length || $('#tf-timer-display').length) {
                    this.timer.init();
                }
                
                // Initialize stats page
                if ($('.tf-period-selector').length) {
                    this.stats.init();
                }
                
                // Initialize dashboard
                if ($('.tf-frontend-dashboard').length) {
                    this.dashboard.init();
                }
                
                // Initialize Chart.js if available
                if (typeof Chart !== 'undefined') {
                    this.stats.initCharts();
                }
            });
        }
    };
    
    // Initialize
    TeamFlowFrontend.init();
    
    // Export to global scope
    window.TeamFlowFrontend = TeamFlowFrontend;
    
})(jQuery);
