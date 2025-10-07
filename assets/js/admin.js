/**
 * TeamFlow Frontend JavaScript
 * Path: assets/js/admin.js
 */

(function($) {
    'use strict';
    
    const TeamFlow = {
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
                $(document).on('click', '.tf-stop-timer', this.stop.bind(this));
                
                // Activity detection
                $(document).on('mousemove keydown click', this.recordActivity.bind(this));
            },
            
            checkExistingTimer: function() {
                const savedTimer = localStorage.getItem('teamflow_active_timer');
                if (savedTimer) {
                    const data = JSON.parse(savedTimer);
                    this.entryId = data.entryId;
                    this.startTime = new Date(data.startTime);
                    this.elapsedSeconds = data.elapsedSeconds || 0;
                    this.resume();
                }
            },
            
            start: function(e) {
                e.preventDefault();
                
                const projectId = $('#tf-project-select').val();
                const taskName = $('#tf-task-input').val();
                
                if (!taskName) {
                    alert('Please enter a task name');
                    return;
                }
                
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
                            
                            this.saveTimerState();
                            this.startCounter();
                            this.updateUI();
                            
                            TeamFlow.notifications.show('Timer started', 'success');
                        } else {
                            TeamFlow.notifications.show(response.data.message, 'error');
                        }
                    }
                });
            },
            
            pause: function(e) {
                e.preventDefault();
                
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
                            TeamFlow.notifications.show('Timer paused', 'info');
                        }
                    }
                });
            },
            
            resume: function() {
                this.isRunning = true;
                this.isPaused = false;
                this.startCounter();
                this.updateUI();
            },
            
            stop: function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to stop the timer?')) {
                    return;
                }
                
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
                            this.reset();
                            TeamFlow.notifications.show('Timer stopped', 'success');
                            
                            // Refresh data
                            if (typeof TeamFlow.dashboard !== 'undefined') {
                                TeamFlow.dashboard.refresh();
                            }
                        }
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
                localStorage.removeItem('teamflow_active_timer');
                this.updateUI();
                $('#tf-task-input').val('');
            },
            
            startCounter: function() {
                if (this.intervalId) return;
                
                this.intervalId = setInterval(() => {
                    if (!this.isPaused) {
                        this.elapsedSeconds++;
                        this.updateDisplay();
                        this.saveTimerState();
                        
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
                            this.pause({ preventDefault: () => {} });
                            TeamFlow.notifications.show('Timer paused due to inactivity', 'warning');
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
                // Simple activity calculation based on recent interactions
                const recentActivity = Date.now() - this.lastActivity;
                if (recentActivity < 5000) return 95;
                if (recentActivity < 15000) return 80;
                if (recentActivity < 30000) return 60;
                return 40;
            },
            
            updateDisplay: function() {
                const display = this.formatTime(this.elapsedSeconds);
                $('.tf-timer-display').text(display);
                
                if (this.inactivitySeconds > 0 && this.isRunning) {
                    const inactiveDisplay = this.formatTime(this.inactivitySeconds);
                    $('.tf-inactive-time').text(`Inactive for: ${inactiveDisplay}`).show();
                } else {
                    $('.tf-inactive-time').hide();
                }
            },
            
            updateUI: function() {
                if (this.isRunning) {
                    $('.tf-start-timer').hide();
                    $('.tf-pause-timer, .tf-stop-timer').show();
                    $('.tf-timer-status').text(this.isPaused ? 'Paused' : 'Tracking').removeClass().addClass('tf-timer-status ' + (this.isPaused ? 'paused' : 'active'));
                } else {
                    $('.tf-start-timer').show();
                    $('.tf-pause-timer, .tf-stop-timer').hide();
                    $('.tf-timer-status').text('Stopped').removeClass().addClass('tf-timer-status stopped');
                }
            },
            
            formatTime: function(seconds) {
                const hrs = Math.floor(seconds / 3600);
                const mins = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            },
            
            saveTimerState: function() {
                if (this.entryId) {
                    localStorage.setItem('teamflow_active_timer', JSON.stringify({
                        entryId: this.entryId,
                        startTime: this.startTime,
                        elapsedSeconds: this.elapsedSeconds
                    }));
                }
            }
        },
        
        dashboard: {
            init: function() {
                this.loadTeamData();
                this.initCharts();
                
                // Refresh every 60 seconds
                setInterval(() => this.refresh(), 60000);
            },
            
            loadTeamData: function() {
                $.ajax({
                    url: teamflow.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'teamflow_get_team_data',
                        nonce: teamflow.nonce,
                        period: 'today'
                    },
                    success: (response) => {
                        if (response.success) {
                            this.renderTeamData(response.data.team);
                        }
                    }
                });
            },
            
            renderTeamData: function(team) {
                const container = $('#tf-team-list');
                container.empty();
                
                team.forEach(member => {
                    const html = `
                        <div class="tf-team-member">
                            <div class="tf-member-avatar">${this.getInitials(member.name)}</div>
                            <div class="tf-member-info">
                                <h4>${member.name}</h4>
                                <p>${member.role}</p>
                            </div>
                            <div class="tf-member-stats">
                                <div class="tf-stat">
                                    <span class="label">Hours</span>
                                    <span class="value">${member.hours}h</span>
                                </div>
                                <div class="tf-stat">
                                    <span class="label">Activity</span>
                                    <span class="value">${member.activity}%</span>
                                </div>
                            </div>
                        </div>
                    `;
                    container.append(html);
                });
            },
            
            initCharts: function() {
                // Weekly hours chart
                if ($('#tf-weekly-chart').length) {
                    const ctx = document.getElementById('tf-weekly-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                            datasets: [{
                                label: 'Hours',
                                data: [8.5, 7.8, 8.2, 7.5, 6.5, 0, 0],
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                        }
                    });
                }
            },
            
            getInitials: function(name) {
                return name.split(' ').map(n => n[0]).join('').toUpperCase();
            },
            
            refresh: function() {
                this.loadTeamData();
            }
        },
        
        notifications: {
            show: function(message, type = 'info') {
                const notification = $(`
                    <div class="tf-notification tf-notification-${type}">
                        ${message}
                    </div>
                `);
                
                $('body').append(notification);
                
                setTimeout(() => {
                    notification.addClass('show');
                }, 10);
                
                setTimeout(() => {
                    notification.removeClass('show');
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        },
        
        init: function() {
            $(document).ready(() => {
                // Initialize timer if on appropriate page
                if ($('.tf-timer-widget').length) {
                    this.timer.init();
                }
                
                // Initialize dashboard if on dashboard page
                if ($('#tf-dashboard').length) {
                    this.dashboard.init();
                }
            });
        }
    };
    
    TeamFlow.init();
    
    // Export to global scope
    window.TeamFlow = TeamFlow;
    
})(jQuery);