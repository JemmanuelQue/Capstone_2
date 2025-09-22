/**
 * Location Rate Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable for better user experience
    $('#locationRatesTable').DataTable({
        responsive: true,
        "order": [[0, "asc"]],
        "columnDefs": [
            {
                // Format currency values to 2 decimal places and ensure positive values
                "targets": [1],
                "render": function(data, type, row) {
                    if (type === 'display') {
                        // Remove any non-numeric characters and ensure positive value
                        let value = parseFloat(data.replace(/[^\d.-]/g, ''));
                        value = Math.abs(value); // Ensure positive
                        return '₱' + value.toFixed(2);
                    }
                    return data;
                }
            }
        ]
    });
    
    // Edit Rate Button Click Handler
    document.querySelectorAll('.edit-rate-btn').forEach(button => {
        button.addEventListener('click', function() {
            const locationName = this.getAttribute('data-location');
            let currentRate = this.getAttribute('data-rate');
            
            // Ensure positive value for the rate
            currentRate = Math.abs(parseFloat(currentRate));
            
            // Set form values
            document.getElementById('locationName').value = locationName;
            document.getElementById('locationDisplay').value = locationName;
            document.getElementById('dailyRate').value = currentRate.toFixed(2);
            
            // Show the modal
            const editModal = new bootstrap.Modal(document.getElementById('editRateModal'));
            editModal.show();
        });
    });
    
    // Update current date and time
    function updateDateTime() {
        const now = new Date();
        document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    
    // Initialize date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
});