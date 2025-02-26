/**
 * Mining Buddy Main JavaScript
 * Handles client-side functionality including real-time updates
 */

// Main application object
const MiningBuddy = {
    
    // Configuration
    config: {
        // Update intervals in milliseconds
        updateIntervals: {
            participantStatus: 10000, // 10 seconds
            miningData: 600000,       // 10 minutes
            marketPrices: 1800000,    // 30 minutes
            tokenCheck: 300000        // 5 minutes
        },
        
        // API endpoints
        api: {
            operationStatus: 'api/operation_status.php',
            miningData: 'api/mining_data.php',
            marketPrices: 'api/market_prices.php',
            tokenRefresh: 'api/token_refresh.php',
            participantAction: 'api/participant_action.php'
        },
        
        // Timers and intervals
        timers: {
            operationTimer: null,
            participantUpdater: null,
            miningDataUpdater: null,
            tokenChecker: null
        }
    },
    
    // Current operation data
    operation: {
        id: null,
        startTime: null,
        status: 'active',
        lastUpdate: null,
        participants: {},
        isAdmin: false,
        isDirector: false
    },
    
    // Token status
    token: {
        status: 'valid', // valid, refreshing, error
        lastRefresh: null
    },
    
    /**
     * Initialize the application
     */
    init: function() {
        // Get operation ID if on operation page
        const operationIdElem = document.getElementById('operation-id');
        if (operationIdElem) {
            this.operation.id = operationIdElem.value;
            this.operation.startTime = document.getElementById('operation-start-time').value;
            this.operation.isAdmin = document.getElementById('is-admin').value === '1';
            this.operation.isDirector = document.getElementById('is-director').value === '1';
            
            // Initialize operation page
            this.initOperationPage();
        }
        
        // Initialize tooltips
        this.initTooltips();
        
        // Initialize forms
        this.initForms();
    },
    
    /**
     * Initialize Bootstrap tooltips
     */
    initTooltips: function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },
    
    /**
     * Initialize form handlers
     */
    initForms: function() {
        // Join operation form
        const joinForm = document.getElementById('join-operation-form');
        if (joinForm) {
            joinForm.addEventListener('submit', function(e) {
                // Form will submit normally
            });
        }
        
        // Create operation form
        const createForm = document.getElementById('create-operation-form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                // Form will submit normally
            });
        }
    },
    
    /**
     * Initialize operation page functionality
     */
    initOperationPage: function() {
        if (!this.operation.id) {
            return;
        }
        
        // Update operation timer
        this.updateOperationTimer();
        this.config.timers.operationTimer = setInterval(() => {
            this.updateOperationTimer();
        }, 1000);
        
        // Start participant status updates
        this.getParticipantStatus();
        this.config.timers.participantUpdater = setInterval(() => {
            this.getParticipantStatus();
        }, this.config.updateIntervals.participantStatus);
        
        // Start mining data updates
        this.getMiningData();
        this.config.timers.miningDataUpdater = setInterval(() => {
            this.getMiningData();
        }, this.config.updateIntervals.miningData);
        
        // Start token checker
        this.checkTokenStatus();
        this.config.timers.tokenChecker = setInterval(() => {
            this.checkTokenStatus();
        }, this.config.updateIntervals.tokenCheck);
        
        // Initialize admin action handlers
        if (this.operation.isAdmin) {
            this.initAdminActions();
        }
        
        // Initialize leave operation handler
        const leaveBtn = document.getElementById('leave-operation-btn');
        if (leaveBtn) {
            leaveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (confirm('Are you sure you want to leave this operation?')) {
                    this.performParticipantAction('leave');
                }
            });
        }
        
        // Update next refresh countdowns
        this.updateNextRefreshCountdowns();
        setInterval(() => {
            this.updateNextRefreshCountdowns();
        }, 1000);
    },
    
    /**
     * Initialize admin action handlers
     */
    initAdminActions: function() {
        // Kick participant
        document.querySelectorAll('.kick-participant-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const userId = btn.getAttribute('data-user-id');
                const userName = btn.getAttribute('data-user-name');
                
                if (confirm(`Are you sure you want to kick ${userName} from this operation?`)) {
                    this.performAdminAction('kick', userId);
                }
            });
        });
        
        // Ban participant
        document.querySelectorAll('.ban-participant-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const userId = btn.getAttribute('data-user-id');
                const userName = btn.getAttribute('data-user-name');
                
                if (confirm(`Are you sure you want to ban ${userName} from this operation?`)) {
                    this.performAdminAction('ban', userId);
                }
            });
        });
        
        // Promote participant
        document.querySelectorAll('.promote-participant-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const userId = btn.getAttribute('data-user-id');
                const userName = btn.getAttribute('data-user-name');
                
                if (confirm(`Are you sure you want to promote ${userName} to Mining Director? You will no longer be the director.`)) {
                    this.performAdminAction('promote', userId);
                }
            });
        });
        
        // End operation
        const endOpBtn = document.getElementById('end-operation-btn');
        if (endOpBtn) {
            endOpBtn.addEventListener('click', (e) => {
                e.preventDefault();
                
                if (confirm('Are you sure you want to end this mining operation?')) {
                    this.performAdminAction('end');
                }
            });
        }
    },
    
    /**
     * Update the operation timer
     */
    updateOperationTimer: function() {
        const timerElem = document.getElementById('operation-timer');
        if (!timerElem || !this.operation.startTime) {
            return;
        }
        
        const startTime = new Date(this.operation.startTime);
        const now = new Date();
        const diff = Math.floor((now - startTime) / 1000); // difference in seconds
        
        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;
        
        timerElem.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    },
    
    /**
     * Update next refresh countdowns
     */
    updateNextRefreshCountdowns: function() {
        const miningCountdown = document.getElementById('mining-data-countdown');
        const participantCountdown = document.getElementById('participant-status-countdown');
        
        if (miningCountdown && this.operation.lastMiningUpdate) {
            const nextUpdate = new Date(this.operation.lastMiningUpdate);
            nextUpdate.setSeconds(nextUpdate.getSeconds() + this.config.updateIntervals.miningData / 1000);
            
            const now = new Date();
            let diff = Math.floor((nextUpdate - now) / 1000);
            
            if (diff < 0) {
                diff = 0;
            }
            
            const minutes = Math.floor(diff / 60);
            const seconds = diff % 60;
            
            miningCountdown.textContent = `${minutes}m ${seconds}s`;
        }
        
        if (participantCountdown && this.operation.lastParticipantUpdate) {
            const nextUpdate = new Date(this.operation.lastParticipantUpdate);
            nextUpdate.setSeconds(nextUpdate.getSeconds() + this.config.updateIntervals.participantStatus / 1000);
            
            const now = new Date();
            let diff = Math.floor((nextUpdate - now) / 1000);
            
            if (diff < 0) {
                diff = 0;
            }
            
            const seconds = diff;
            
            participantCountdown.textContent = `${seconds}s`;
        }
    },
    
    /**
     * Get participant status updates
     */
    getParticipantStatus: function() {
        if (!this.operation.id) {
            return;
        }
        
        fetch(this.config.api.operationStatus + '?operation_id=' + this.operation.id)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                this.operation.lastParticipantUpdate = new Date();
                
                // Update operation status
                this.operation.status = data.operation.status;
                
                // Handle operation ended
                if (data.operation.status === 'ending') {
                    this.handleOperationEnding(data.operation.countdown);
                } else if (data.operation.status === 'syncing') {
                    this.handleOperationSyncing(data.operation.countdown);
                } else if (data.operation.status === 'ended') {
                    this.handleOperationEnded();
                }
                
                // Update participant list
                this.updateParticipantList(data.participants);
                
                // Update total ISK
                this.updateTotalIsk(data.total_isk);
                
                // Update last update time
                document.getElementById('last-participant-update').textContent = new Date().toLocaleTimeString();
            })
            .catch(error => {
                console.error('Error fetching participant status:', error);
            });
    },
    
    /**
     * Get mining data updates
     */
    getMiningData: function() {
        if (!this.operation.id) {
            return;
        }
        
        const loadingIndicator = document.getElementById('mining-data-loading');
        if (loadingIndicator) {
            loadingIndicator.style.display = 'inline-block';
        }
        
        fetch(this.config.api.miningData + '?operation_id=' + this.operation.id)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                this.operation.lastMiningUpdate = new Date();
                
                // Update mining data for each participant
                for (const [userId, miningData] of Object.entries(data.mining_data)) {
                    this.updateParticipantMiningData(userId, miningData);
                }
                
                // Update market prices
                if (data.market_prices) {
                    this.updateMarketPrices(data.market_prices);
                }
                
                // Update last update time
                document.getElementById('last-mining-update').textContent = new Date().toLocaleTimeString();
                
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching mining data:', error);
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
            });
    },
    
    /**
     * Update participant list
     * @param {Array} participants - Participant data
     */
    updateParticipantList: function(participants) {
        const tbody = document.querySelector('#participant-table tbody');
        if (!tbody) {
            return;
        }
        
        // Keep track of existing rows
        const existingRows = {};
        
        // Update or add rows for participants
        participants.forEach(participant => {
            let row = document.getElementById(`participant-${participant.user_id}`);
            
            // Create new row if it doesn't exist
            if (!row) {
                row = document.createElement('tr');
                row.id = `participant-${participant.user_id}`;
                tbody.appendChild(row);
            }
            
            // Set row class based on status
            row.className = '';
            if (participant.status === 'active') {
                if (participant.is_admin) {
                    row.classList.add('participant-admin');
                } else {
                    row.classList.add('participant-active');
                }
            } else if (participant.status === 'left' || participant.status === 'kicked') {
                row.classList.add('participant-left');
            }
            
            // Update row content
            row.innerHTML = `
                <td>
                    <div class="eve-character">
                        <img src="${participant.avatar_url}" class="eve-character-avatar" alt="${participant.character_name}">
                        <a href="#" class="eve-character-name">${participant.character_name}</a>
                    </div>
                </td>
                <td>
                    <div class="eve-corporation">
                        <img src="${participant.corporation_logo_url}" class="eve-corporation-logo" alt="${participant.corporation_name}">
                        <span class="eve-corporation-name">${participant.corporation_name}</span>
                    </div>
                </td>
                <td class="ores-mined-col" id="ores-${participant.user_id}">
                    <div class="text-muted">Waiting for data...</div>
                </td>
                <td class="isk-value-col" id="isk-value-${participant.user_id}">
                    <span class="isk-value">0.00 ISK</span>
                </td>
                <td class="actions-col">
                    ${this.getParticipantActionButtons(participant)}
                </td>
            `;
            
            // Mark as existing
            existingRows[participant.user_id] = true;
            
            // Update local participant data
            this.operation.participants[participant.user_id] = participant;
        });
        
        // Remove rows for participants who are no longer in the list
        document.querySelectorAll('#participant-table tbody tr').forEach(row => {
            const userId = row.id.replace('participant-', '');
            if (!existingRows[userId]) {
                row.remove();
                delete this.operation.participants[userId];
            }
        });
        
        // Reinitialize admin action handlers
        if (this.operation.isAdmin) {
            this.initAdminActions();
        }
    },
    
    /**
     * Get action buttons for a participant
     * @param {Object} participant - Participant data
     * @returns {string} HTML for action buttons
     */
    getParticipantActionButtons: function(participant) {
        // If current user is this participant, show leave button
        if (document.getElementById('current-user-id').value == participant.user_id) {
            return `<button class="btn btn-sm btn-outline-danger" id="leave-operation-btn">Leave</button>`;
        }
        
        // If current user is admin, show admin buttons
        if (this.operation.isAdmin) {
            // Don't show admin buttons for users who have left
            if (participant.status !== 'active') {
                return '';
            }
            
            // Don't show admin buttons for other admins if not director
            if (participant.is_admin && !this.operation.isDirector) {
                return '';
            }
            
            // Don't show admin buttons for self
            if (document.getElementById('current-user-id').value == participant.user_id) {
                return '';
            }
            
            let buttons = `
                <button class="btn btn-sm btn-outline-warning kick-participant-btn" 
                        data-user-id="${participant.user_id}" 
                        data-user-name="${participant.character_name}"
                        data-bs-toggle="tooltip" 
                        title="Kick from operation">
                    <i class="fas fa-user-times"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger ban-participant-btn" 
                        data-user-id="${participant.user_id}" 
                        data-user-name="${participant.character_name}"
                        data-bs-toggle="tooltip" 
                        title="Ban from operation">
                    <i class="fas fa-ban"></i>
                </button>
            `;
            
            // Only director can promote others
            if (this.operation.isDirector) {
                buttons += `
                    <button class="btn btn-sm btn-outline-primary promote-participant-btn" 
                            data-user-id="${participant.user_id}" 
                            data-user-name="${participant.character_name}"
                            data-bs-toggle="tooltip" 
                            title="Promote to Mining Director">
                        <i class="fas fa-crown"></i>
                    </button>
                `;
            }
            
            return buttons;
        }
        
        return '';
    },
    
    /**
     * Update participant mining data
     * @param {string} userId - User ID
     * @param {Object} miningData - Mining data
     */
    updateParticipantMiningData: function(userId, miningData) {
        const oresCell = document.getElementById(`ores-${userId}`);
        const iskValueCell = document.getElementById(`isk-value-${userId}`);
        
        if (!oresCell || !iskValueCell) {
            return;
        }
        
        let oresHtml = '';
        let totalIsk = 0;
        
        // Create ore list
        if (miningData.ores && miningData.ores.length > 0) {
            miningData.ores.forEach(ore => {
                oresHtml += `
                    <div class="ore-item">
                        ${ore.icon_url ? `<img src="${ore.icon_url}" class="ore-icon" alt="${ore.name}">` : ''}
                        <span class="ore-name">${ore.name}</span>
                        <span class="ore-quantity">${this.formatNumber(ore.quantity)}</span>
                        <span class="ore-value">${this.formatIsk(ore.value)}</span>
                    </div>
                `;
                
                totalIsk += parseFloat(ore.value);
            });
        } else {
            oresHtml = '<div class="text-muted">No ores mined</div>';
        }
        
        // Update cells
        oresCell.innerHTML = oresHtml;
        iskValueCell.innerHTML = `<span class="isk-value">${this.formatIsk(totalIsk)}</span>`;
    },
    
    /**
     * Update market prices
     * @param {Object} prices - Market prices
     */
    updateMarketPrices: function(prices) {
        // Update any price displays if needed
    },
    
    /**
     * Update total ISK display
     * @param {number} totalIsk - Total ISK value
     */
    updateTotalIsk: function(totalIsk) {
        const totalIskElem = document.getElementById('total-isk-value');
        if (totalIskElem) {
            totalIskElem.textContent = this.formatIsk(totalIsk);
        }
    },
    
    /**
     * Handle operation ending countdown
     * @param {number} countdown - Seconds remaining
     */
    handleOperationEnding: function(countdown) {
        // Show countdown message
        let countdownElem = document.getElementById('operation-ending-countdown');
        if (!countdownElem) {
            countdownElem = document.createElement('div');
            countdownElem.id = 'operation-ending-countdown';
            countdownElem.className = 'operation-ending';
            document.querySelector('.operation-container').prepend(countdownElem);
        }
        
        countdownElem.textContent = `Operation ending in ${countdown} seconds`;
        
        // If countdown is 0, reload page but with protection against infinite loops
        if (countdown <= 0) {
            // Check when the last reload happened to prevent tight loops
            const lastReload = localStorage.getItem('last_reload_time');
            const now = Date.now();
            
            if (!lastReload || (now - parseInt(lastReload)) > 2000) { // Only reload if >2 seconds since last reload
                // Set the last reload time
                localStorage.setItem('last_reload_time', now.toString());
                window.location.reload();
            } else {
                // If we're in a potential loop, redirect to dashboard instead
                console.error('Detected potential reload loop, redirecting to dashboard');
                window.location.href = 'dashboard.php';
            }
        }
    },
    
    /**
     * Handle operation syncing
     * @param {number} countdown - Seconds remaining
     */
    handleOperationSyncing: function(countdown) {
        // Show syncing message
        let syncElem = document.getElementById('operation-syncing');
        if (!syncElem) {
            syncElem = document.createElement('div');
            syncElem.id = 'operation-syncing';
            syncElem.className = 'data-sync-countdown';
            document.querySelector('.operation-container').prepend(syncElem);
        }
        
        syncElem.textContent = `Data syncing in progress. Final data will be available in ${countdown} seconds.`;
        
        // If countdown is 0, reload page
        if (countdown <= 0) {
            window.location.reload();
        }
    },
    
    /**
     * Handle operation ended
     */
    handleOperationEnded: function() {
        // Show ended message
        let endedElem = document.getElementById('operation-ended');
        if (!endedElem) {
            endedElem = document.createElement('div');
            endedElem.id = 'operation-ended';
            endedElem.className = 'operation-ending';
            document.querySelector('.operation-container').prepend(endedElem);
        }
        
        endedElem.textContent = `Operation has ended.`;
        
        // Disable action buttons
        document.querySelectorAll('.kick-participant-btn, .ban-participant-btn, .promote-participant-btn, #leave-operation-btn, #end-operation-btn').forEach(btn => {
            btn.disabled = true;
        });
        
        // Stop update timers
        this.stopUpdateTimers();
    },
    
    /**
     * Stop all update timers
     */
    stopUpdateTimers: function() {
        clearInterval(this.config.timers.participantUpdater);
        clearInterval(this.config.timers.miningDataUpdater);
        clearInterval(this.config.timers.tokenChecker);
    },
    
    /**
     * Perform participant action (leave)
     * @param {string} action - Action to perform
     */
    performParticipantAction: function(action) {
        if (!this.operation.id) {
            return;
        }
        
        fetch(this.config.api.participantAction, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `operation_id=${this.operation.id}&action=${action}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (action === 'leave') {
                    // Redirect to dashboard
                    window.location.href = 'dashboard.php';
                }
            } else {
                alert(data.message || 'Error performing action');
            }
        })
        .catch(error => {
            console.error('Error performing participant action:', error);
            alert('An error occurred while performing action');
        });
    },
    
    /**
     * Perform admin action
     * @param {string} action - Action to perform
     * @param {string} userId - Target user ID (optional)
     */
    performAdminAction: function(action, userId = null) {
        if (!this.operation.id) {
            return;
        }
        
        let formData = `operation_id=${this.operation.id}&action=${action}`;
        if (userId) {
            formData += `&user_id=${userId}`;
        }
        
        fetch(this.config.api.participantAction, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (action === 'end') {
                    // Operation will start ending countdown
                    // The next status update will handle UI changes
                } else {
                    // Update participant list on next update
                    this.getParticipantStatus();
                }
            } else {
                alert(data.message || 'Error performing action');
            }
        })
        .catch(error => {
            console.error('Error performing admin action:', error);
            alert('An error occurred while performing action');
        });
    },
    
    /**
     * Check token status
     */
    checkTokenStatus: function() {
        const tokenStatusElem = document.getElementById('token-status');
        if (!tokenStatusElem) {
            return;
        }
        
        fetch(this.config.api.tokenRefresh + '?check=1')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                this.token.status = data.status;
                
                // Update token status indicator
                const statusIndicator = document.querySelector('.token-status');
                statusIndicator.className = 'token-status';
                
                if (data.status === 'valid') {
                    statusIndicator.classList.add('token-status-valid');
                    tokenStatusElem.textContent = 'Valid';
                } else if (data.status === 'refreshing') {
                    statusIndicator.classList.add('token-status-refreshing');
                    tokenStatusElem.textContent = 'Refreshing...';
                } else if (data.status === 'error') {
                    statusIndicator.classList.add('token-status-error');
                    tokenStatusElem.textContent = 'Error';
                    
                    // If token has a persistent error, show message
                    if (data.message) {
                        alert('Token error: ' + data.message + ' Please login again.');
                        window.location.href = 'logout.php';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking token status:', error);
            });
    },
    
    /**
     * Format ISK value
     * @param {number} value - ISK value
     * @returns {string} Formatted ISK value
     */
    formatIsk: function(value) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value) + ' ISK';
    },
    
    /**
     * Format number with commas
     * @param {number} value - Number to format
     * @returns {string} Formatted number
     */
    formatNumber: function(value) {
        return new Intl.NumberFormat('en-US').format(value);
    }
};

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    MiningBuddy.init();
});