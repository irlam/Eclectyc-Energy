/**
 * eclectyc-energy/public/assets/js/app.js
 * Main JavaScript file for frontend interactions
 * Last updated: 06/11/2024 14:45:00
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Eclectyc Energy Platform Loaded');
    
    // Initialize components
    initializeHealthCheck();
    initializeDataTables();
    initializeCharts();
    initializeCarbonIntensity();
    initializeDeleteConfirmations();
});

/**
 * Initialize delete confirmations with "OK" typed confirmation
 */
function initializeDeleteConfirmations() {
    // Find all delete forms with data-confirm-delete attribute
    const deleteForms = document.querySelectorAll('form[data-confirm-delete]');
    
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const itemName = form.dataset.confirmDelete || 'this item';
            const warningMessage = form.dataset.confirmWarning || 
                'This will permanently delete ' + itemName + ' and all associated data. This action cannot be undone.';
            
            showDeleteConfirmation(itemName, warningMessage, () => {
                // User confirmed, submit the form
                form.submit();
            });
        });
    });
    
    // Also handle delete buttons with data-confirm-delete
    const deleteButtons = document.querySelectorAll('button[data-confirm-delete], a[data-confirm-delete]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemName = button.dataset.confirmDelete || 'this item';
            const warningMessage = button.dataset.confirmWarning || 
                'This will permanently delete ' + itemName + ' and all associated data. This action cannot be undone.';
            const deleteUrl = button.dataset.deleteUrl || button.href;
            
            showDeleteConfirmation(itemName, warningMessage, () => {
                // User confirmed, proceed with deletion
                if (deleteUrl) {
                    // Create a form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = deleteUrl;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
}

/**
 * Show delete confirmation modal requiring "OK" to be typed
 */
function showDeleteConfirmation(itemName, warningMessage, onConfirm) {
    // Remove any existing modal
    const existingModal = document.getElementById('delete-confirmation-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal HTML
    const modal = document.createElement('div');
    modal.id = 'delete-confirmation-modal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content modal-delete">
            <div class="modal-header">
                <h3>⚠️ Confirm Deletion</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="delete-warning">
                    <p><strong>Warning:</strong> ${warningMessage}</p>
                </div>
                <div class="delete-confirm-input">
                    <label for="delete-confirm-text">Type <strong>OK</strong> to confirm deletion:</label>
                    <input type="text" id="delete-confirm-text" class="delete-input" autocomplete="off" />
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" id="confirm-delete-btn" disabled>Delete</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focus the input
    const input = document.getElementById('delete-confirm-text');
    const confirmBtn = document.getElementById('confirm-delete-btn');
    
    setTimeout(() => input.focus(), 100);
    
    // Enable/disable confirm button based on input
    input.addEventListener('input', function() {
        if (this.value.trim() === 'OK') {
            confirmBtn.disabled = false;
            confirmBtn.classList.add('btn-danger-enabled');
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.remove('btn-danger-enabled');
        }
    });
    
    // Handle Enter key in input
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && this.value.trim() === 'OK') {
            confirmBtn.click();
        }
    });
    
    // Handle confirm button click
    confirmBtn.addEventListener('click', function() {
        if (input.value.trim() === 'OK') {
            closeDeleteModal();
            onConfirm();
        }
    });
    
    // Close on background click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeDeleteModal();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function escapeHandler(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    });
}

/**
 * Close delete confirmation modal
 */
function closeDeleteModal() {
    const modal = document.getElementById('delete-confirmation-modal');
    if (modal) {
        modal.classList.add('modal-closing');
        setTimeout(() => modal.remove(), 200);
    }
}

// Make closeDeleteModal globally accessible for inline event handlers
window.closeDeleteModal = closeDeleteModal;

/**
 * Health Check Monitor
 */
function initializeHealthCheck() {
    const healthElement = document.getElementById('health-status');
    if (!healthElement) return;
    
    // Create a refresh button for manual health checks
    const refreshButton = document.createElement('button');
    refreshButton.className = 'health-refresh-btn';
    refreshButton.innerHTML = '↻';
    refreshButton.title = 'Refresh health status';
    refreshButton.style.cssText = `
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: inherit;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 8px;
        vertical-align: middle;
        transition: all 0.2s ease;
    `;
    
    // Insert refresh button after health element
    healthElement.parentNode.insertBefore(refreshButton, healthElement.nextSibling);
    
    // Make health status clickable to navigate to details
    healthElement.style.cursor = 'pointer';
    healthElement.addEventListener('click', () => {
        window.location.href = '/tools/system-health';
    });
    
    // Set initial status
    healthElement.className = 'status';
    healthElement.textContent = 'Click ↻ to check';
    healthElement.title = 'Click refresh button to check system health';
    
    // Manual health check on button click
    refreshButton.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent triggering parent click
        checkHealth();
    });
    
    refreshButton.addEventListener('mouseenter', () => {
        refreshButton.style.background = 'rgba(255, 255, 255, 0.2)';
    });
    
    refreshButton.addEventListener('mouseleave', () => {
        refreshButton.style.background = 'rgba(255, 255, 255, 0.1)';
    });
    
    async function checkHealth() {
        // Show loading state
        refreshButton.style.animation = 'spin 1s linear infinite';
        refreshButton.disabled = true;
        healthElement.textContent = 'Checking...';
        
        try {
            const response = await fetch('/api/health');
            const data = await response.json();
            
            if (data.status === 'healthy') {
                healthElement.className = 'status status-success';
                healthElement.textContent = 'System Healthy';
                healthElement.title = 'All systems operational. Click for details.';
            } else {
                healthElement.className = 'status status-warning';
                
                // Build degraded message with reasons
                let message = 'System Degraded';
                let reasons = [];
                
                if (data.checks) {
                    if (data.checks.database && data.checks.database.status !== 'healthy') {
                        reasons.push('Database');
                    }
                    if (data.checks.imports && data.checks.imports.status !== 'healthy') {
                        reasons.push('Recent imports stale');
                    }
                    if (data.checks.exports && data.checks.exports.status !== 'healthy') {
                        reasons.push('Recent exports stale');
                    }
                    if (data.checks.disk_space && data.checks.disk_space.status !== 'healthy') {
                        reasons.push('Low disk space');
                    }
                }
                
                if (reasons.length > 0) {
                    message += ': ' + reasons.join(', ');
                    healthElement.title = 'Issues detected: ' + reasons.join(', ') + '. Click for full diagnostics.';
                } else {
                    healthElement.title = 'Some checks are degraded. Click for full diagnostics.';
                }
                
                healthElement.textContent = message;
            }
            
            // Update timestamp
            const timestampElement = document.getElementById('last-check');
            if (timestampElement) {
                const ukTime = new Date().toLocaleString('en-GB', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
                timestampElement.textContent = `Last checked: ${ukTime}`;
            }
        } catch (error) {
            console.error('Health check failed:', error);
            healthElement.className = 'status status-danger';
            healthElement.textContent = 'System Offline';
            healthElement.title = 'Unable to reach health check endpoint. Click to retry.';
        } finally {
            // Remove loading state
            refreshButton.style.animation = '';
            refreshButton.disabled = false;
        }
    }
}

/**
 * Initialize data tables with sorting
 */
function initializeDataTables() {
    const tables = document.querySelectorAll('.sortable-table');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(table, index);
            });
        });
    });
}

/**
 * Sort table by column
 */
function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Toggle sort direction
    const isAscending = table.dataset.sortColumn == column && table.dataset.sortOrder === 'asc';
    table.dataset.sortColumn = column;
    table.dataset.sortOrder = isAscending ? 'desc' : 'asc';
    
    // Sort rows
    rows.sort((a, b) => {
        const aValue = a.children[column].textContent.trim();
        const bValue = b.children[column].textContent.trim();
        
        // Try to parse as number
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        }
        
        // Sort as string
        return isAscending 
            ? bValue.localeCompare(aValue)
            : aValue.localeCompare(bValue);
    });
    
    // Re-append sorted rows
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Initialize charts (placeholder for future chart implementation)
 */
function initializeCharts() {
    const chartElements = document.querySelectorAll('.chart-container');
    
    chartElements.forEach(element => {
        // TODO: Implement chart rendering with Chart.js or D3.js
        console.log('Chart container found:', element.id);
    });
}

/**
 * Format UK date
 */
function formatUKDate(date) {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    }).format(date);
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    
    const container = document.getElementById('notifications') || document.body;
    container.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Handle AJAX forms
 */
document.addEventListener('submit', function(e) {
    if (e.target.classList.contains('ajax-form')) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Operation successful', 'success');
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                showNotification(data.message || 'Operation failed', 'danger');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            showNotification('An error occurred', 'danger');
        });
    }
});

/**
 * Carbon Intensity Management
 */
function initializeCarbonIntensity() {
    const carbonCard = document.querySelector('.energy-card-carbon');
    if (!carbonCard) return;
    
    // Add refresh capability to carbon intensity card
    const refreshButton = createRefreshButton();
    const carbonHeader = carbonCard.querySelector('.energy-header');
    if (carbonHeader) {
        carbonHeader.appendChild(refreshButton);
    }
    
    // Auto-refresh carbon intensity every 15 minutes
    setInterval(refreshCarbonIntensity, 15 * 60 * 1000);
}

function createRefreshButton() {
    const button = document.createElement('button');
    button.className = 'carbon-refresh-btn';
    button.innerHTML = '↻';
    button.title = 'Refresh carbon intensity data';
    button.style.cssText = `
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: var(--muted);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    `;
    
    button.addEventListener('click', refreshCarbonIntensity);
    
    button.addEventListener('mouseenter', () => {
        button.style.background = 'rgba(255, 255, 255, 0.2)';
        button.style.color = 'var(--ink)';
    });
    
    button.addEventListener('mouseleave', () => {
        button.style.background = 'rgba(255, 255, 255, 0.1)';
        button.style.color = 'var(--muted)';
    });
    
    return button;
}

async function refreshCarbonIntensity() {
    const carbonCard = document.querySelector('.energy-card-carbon');
    const refreshBtn = document.querySelector('.carbon-refresh-btn');
    
    if (!carbonCard) return;
    
    // Show loading state
    if (refreshBtn) {
        refreshBtn.style.animation = 'spin 1s linear infinite';
        refreshBtn.disabled = true;
    }
    
    try {
        const response = await fetch('/api/carbon-intensity/refresh', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh the page to show new data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
            
            showNotification('Carbon intensity data updated successfully', 'success');
        } else {
            showNotification('Failed to update carbon intensity data', 'error');
        }
    } catch (error) {
        console.error('Carbon intensity refresh error:', error);
        showNotification('Error updating carbon intensity data', 'error');
    } finally {
        // Remove loading state
        if (refreshBtn) {
            refreshBtn.style.animation = '';
            refreshBtn.disabled = false;
        }
    }
}

// Add CSS animation for refresh button
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Export functions for use in other scripts
window.EclectycEnergy = {
    formatUKDate,
    showNotification,
    checkHealth: initializeHealthCheck,
    refreshCarbonIntensity
};