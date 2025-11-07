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
});

/**
 * Health Check Monitor
 */
function initializeHealthCheck() {
    const healthElement = document.getElementById('health-status');
    if (!healthElement) return;
    
    // Check health status every 30 seconds
    checkHealth();
    setInterval(checkHealth, 30000);
    
    async function checkHealth() {
        try {
            const response = await fetch('/api/health');
            const data = await response.json();
            
            if (data.status === 'healthy') {
                healthElement.className = 'status status-success';
                healthElement.textContent = 'System Healthy';
            } else {
                healthElement.className = 'status status-warning';
                healthElement.textContent = 'System Degraded';
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