// OIC Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar toggle functionality
    const toggleSidebar = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    // Create overlay for mobile
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                // Mobile behavior
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                // Desktop behavior
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
                
                // Save sidebar state to localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            }
        });
    }
    
    // Close sidebar when clicking overlay (mobile)
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
    
    // Restore sidebar state from localStorage (desktop only)
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
    if (sidebarCollapsed === 'true' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            // Mobile: reset to default state
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('show');
            mainContent.classList.remove('sidebar-collapsed');
            overlay.classList.remove('show');
        } else {
            // Desktop: restore saved state
            overlay.classList.remove('show');
            sidebar.classList.remove('show');
            
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
            if (sidebarCollapsed === 'true') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
            }
        }
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh functionality for pending evaluations
    const refreshInterval = 300000; // 5 minutes
    let refreshTimer;
    
    function startAutoRefresh() {
        refreshTimer = setInterval(function() {
            // Only refresh if we're showing due/overdue evaluations
            const evaluationStatusSelect = document.getElementById('evaluation_status');
            if (evaluationStatusSelect && (evaluationStatusSelect.value === 'Due' || evaluationStatusSelect.value === 'Overdue')) {
                showRefreshNotification();
            }
        }, refreshInterval);
    }
    
    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
    }
    
    function showRefreshNotification() {
        // Show a subtle notification about new data availability
        if (!document.getElementById('refresh-notification')) {
            const notification = document.createElement('div');
            notification.id = 'refresh-notification';
            notification.className = 'alert alert-info position-fixed top-0 end-0 m-3';
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="material-icons me-2">refresh</i>
                    <span>Data may have been updated. <a href="#" onclick="location.reload()" class="alert-link">Refresh now</a></span>
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Auto-remove after 10 seconds
            setTimeout(function() {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 10000);
        }
    }
    
    // Start auto-refresh when page loads
    startAutoRefresh();
    
    // Stop auto-refresh when page is hidden/minimized
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
    
    // Highlight overdue evaluations with enhanced animation
    const overdueRows = document.querySelectorAll('.table-danger');
    overdueRows.forEach(function(row) {
        row.style.animation = 'highlightOverdue 3s ease-in-out infinite';
    });
    
    // Add CSS for overdue highlighting
    const style = document.createElement('style');
    style.textContent = `
        @keyframes highlightOverdue {
            0%, 100% { background-color: rgba(220, 53, 69, 0.1); }
            50% { background-color: rgba(220, 53, 69, 0.2); }
        }
    `;
    document.head.appendChild(style);
    
    // Enhanced search functionality
    const searchInput = document.getElementById('guardSearch');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.toLowerCase();
            
            // Add loading indicator
            const form = this.closest('form');
            const originalButton = form.querySelector('button[name="filter_submit"]');
            
            if (searchTerm.length >= 2) {
                searchTimeout = setTimeout(function() {
                    // Highlight matching text in table
                    highlightSearchResults(searchTerm);
                }, 300);
            } else {
                // Clear highlights
                clearSearchHighlights();
            }
        });
    }
    
    function highlightSearchResults(searchTerm) {
        const tableRows = document.querySelectorAll('#guardsTable tbody tr');
        
        tableRows.forEach(function(row) {
            const nameCell = row.querySelector('td:nth-child(2)');
            if (nameCell) {
                const originalText = nameCell.textContent;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                const highlightedText = originalText.replace(regex, '<mark>$1</mark>');
                
                if (originalText.toLowerCase().includes(searchTerm)) {
                    nameCell.innerHTML = highlightedText;
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }
    
    function clearSearchHighlights() {
        const tableRows = document.querySelectorAll('#guardsTable tbody tr');
        tableRows.forEach(function(row) {
            row.style.display = '';
            const nameCell = row.querySelector('td:nth-child(2)');
            if (nameCell) {
                nameCell.innerHTML = nameCell.textContent;
            }
        });
    }
    
    // Quick filter buttons
    addQuickFilterButtons();
    
    function addQuickFilterButtons() {
        const filterCard = document.querySelector('.card-body form');
        if (filterCard) {
            const quickFiltersDiv = document.createElement('div');
            quickFiltersDiv.className = 'mb-3';
            quickFiltersDiv.innerHTML = `
                <label class="form-label">Quick Filters:</label>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="setQuickFilter('overdue')">
                        <i class="material-icons">warning</i> Overdue Only
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="setQuickFilter('due')">
                        <i class="material-icons">schedule</i> Due Only
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="setQuickFilter('probationary')">
                        <i class="material-icons">person</i> Probationary Only
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="setQuickFilter('all')">
                        <i class="material-icons">all_inclusive</i> Show All
                    </button>
                </div>
            `;
            
            filterCard.insertBefore(quickFiltersDiv, filterCard.firstChild);
        }
    }
    
    // Add quick filter function to global scope
    window.setQuickFilter = function(filterType) {
        const evaluationStatusSelect = document.getElementById('evaluation_status');
        const employmentStatusSelect = document.getElementById('employment_status');
        const searchInput = document.getElementById('guardSearch');
        
        // Reset all filters first
        searchInput.value = '';
        employmentStatusSelect.value = 'all';
        evaluationStatusSelect.value = 'all';
        
        switch(filterType) {
            case 'overdue':
                evaluationStatusSelect.value = 'Overdue';
                break;
            case 'due':
                evaluationStatusSelect.value = 'Due';
                break;
            case 'probationary':
                employmentStatusSelect.value = 'Probationary';
                break;
            case 'all':
                // Already reset above
                break;
        }
        
        // Submit the form
        evaluationStatusSelect.closest('form').submit();
    };
    
    // Print functionality for individual evaluations
    window.printEvaluation = function(guardId) {
        const printWindow = window.open(`print_evaluation.php?guard_id=${guardId}`, '_blank');
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    };
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('guardSearch');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('guardSearch');
            if (searchInput && searchInput === document.activeElement) {
                searchInput.value = '';
                clearSearchHighlights();
            }
        }
    });
    
    // Add loading states for form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            }
        });
    });
    
    // Performance monitoring for large tables
    if (document.querySelectorAll('#guardsTable tbody tr').length > 100) {
        console.log('Large table detected, optimizing performance...');
        
        // Virtual scrolling for very large datasets could be implemented here
        // For now, we'll just add a notice
        const tableContainer = document.querySelector('.table-responsive');
        if (tableContainer) {
            const notice = document.createElement('div');
            notice.className = 'alert alert-info small mb-2';
            notice.innerHTML = '<i class="material-icons">info</i> Large dataset detected. Use filters to improve performance.';
            tableContainer.parentNode.insertBefore(notice, tableContainer);
        }
    }
});

// Update current date and time
function updateDateTime() {
    const now = new Date();
    const dateOptions = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'long'
    };
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true
    };
    
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    if (dateElement) {
        dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
    }
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
    }
}

// Update every second
setInterval(updateDateTime, 1000);
updateDateTime(); // Initial call