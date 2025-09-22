$(document).ready(function() {
    // Initialize tooltips for desktop sidebar with proper configuration
    function initializeTooltips() {
        if ($(window).width() > 991) {
            // First completely destroy any existing tooltips
            $('[data-bs-toggle="tooltip"]').tooltip('dispose');
            
            // Remove any remaining tooltip elements from the DOM
            $('.tooltip').remove();
            
            // Create new tooltip instances with a small delay to ensure DOM is ready
            setTimeout(function() {
                $('[data-bs-toggle="tooltip"]').tooltip({
                    boundary: 'window',
                    trigger: 'hover',
                    placement: 'right',
                    delay: {show: 100, hide: 100}
                });
                
                // Explicitly enable or disable based on sidebar state
                if ($("#sidebar").hasClass("collapsed")) {
                    $('[data-bs-toggle="tooltip"]').tooltip('enable');
                    // Force show tooltips on hover by adding mouseenter event
                    $('[data-bs-toggle="tooltip"]').on('mouseenter', function() {
                        $(this).tooltip('show');
                    });
                } else {
                    $('[data-bs-toggle="tooltip"]').tooltip('disable');
                }
            }, 100);
        }
    }
    
    // Toggle desktop sidebar
    $("#toggleSidebar").click(function() {
        // Toggle classes for sidebar and content
        $("#sidebar").toggleClass("collapsed");
        $("#main-content").toggleClass("expanded");
        
        // Get the new state immediately after toggle
        let isCollapsed = $("#sidebar").hasClass("collapsed");
        
        // Different handling based on new state
        if (isCollapsed) {
            // We just collapsed the sidebar, force tooltip setup
            setTimeout(function() {
                // Dispose any existing tooltips first
                $('[data-bs-toggle="tooltip"]').tooltip('dispose');
                $('.tooltip').remove();
                
                // Initialize new tooltips and force enable
                $('[data-bs-toggle="tooltip"]').tooltip({
                    boundary: 'window',
                    trigger: 'hover',
                    placement: 'right',
                    delay: {show: 300, hide: 100},
                    animation: true,
                    container: 'body'
                });
                
                // Force enable
                $('[data-bs-toggle="tooltip"]').tooltip('enable');
                
                // Add explicit mouseenter event
                $('[data-bs-toggle="tooltip"]').on('mouseenter', function() {
                    $(this).tooltip('show');
                });
            }, 150); // Slightly longer delay to ensure DOM update
        } else {
            // We just expanded the sidebar, disable tooltips
            $('[data-bs-toggle="tooltip"]').tooltip('disable');
        }
    });
    
    // Initialize tooltips on page load
    initializeTooltips();

    // Set the correct redirect page for profile forms
    function setRedirectPage() {
        // Get current page filename
        const currentPage = window.location.pathname.split('/').pop() || 'superadmin_dashboard.php';
        
        // Set the value of the hidden redirect_page input in all profile forms
        if (!$('input[name="redirect_page"]').length) {
            $('#updateProfileForm').append(`<input type="hidden" name="redirect_page" value="${currentPage}">`);
        } else {
            $('input[name="redirect_page"]').val(currentPage);
        }
    }
    
    // Call the function when the profile modal is shown
    $('#profileModal').on('show.bs.modal', function() {
        setRedirectPage();
    });

    // Rest of your existing functions...
    function updateDateTime() {
        const now = new Date();
        
        // Format date: May 11, 2025
        const options = { 
            weekday: 'long',
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        const dateStr = now.toLocaleDateString('en-US', options);
        
        // Format time with seconds: 3:45:27 PM
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert 0 to 12 for 12-hour format
        
        const timeStr = `${hours}:${minutes}:${seconds} ${ampm}`;
        
        $('#current-date').text(dateStr);
        $('#current-time').text(timeStr);
    }
    
    // Update time immediately and then every second
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Show active page in mobile nav
    const currentPage = window.location.pathname.split('/').pop();
    $('.mobile-nav-item').removeClass('active');
    $(`.mobile-nav-item[href="${currentPage}"]`).addClass('active');
    
    // Show profile modal on profile click
    $("#userProfile").click(function() {
        $("#profileModal").modal("show");
    });
    
    // Handle profile update
    $("#saveProfile").click(function() {
        // Here you would typically send an AJAX request to update the profile
        alert("Profile updated successfully!");
        $("#profileModal").modal("hide");
    });
    
    // Make sure datetime elements are visible on mobile
    if ($('#current-date').length && $('#current-time').length) {
        $('#current-date, #current-time').show();
        $('.current-datetime').show();
    }
});