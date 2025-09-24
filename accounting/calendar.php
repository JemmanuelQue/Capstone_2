<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);
require_once '../db_connection.php';

// Get current Accounting user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 4 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'] : "Accounting";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Calendar - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/rate_locations.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .fc-event {
            cursor: pointer;
            padding: 5px;
        }
        .fc-regular-holiday {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        .fc-special-non-working-holiday {
            background-color: #fd7e14 !important;
            border-color: #fd7e14 !important;
        }
        .fc-special-working-holiday {
            background-color: #0dcaf0 !important;
            border-color: #0dcaf0 !important;
        }
        .holiday-badge {
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 5px;
            border-radius: 3px;
        }
        .holiday-legend {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .holiday-legend-item {
            display: flex;
            align-items: center;
            margin: 0 15px;
        }
        #holidayTable_wrapper {
            margin-top: 20px;
        }

        /* Override FullCalendar button styles for arrows */
    .fc .fc-prev-button, .fc .fc-next-button {
        width: 40px !important;
        height: 40px !important;
        border-radius: 50% !important;
        background-color: #2a7d4f !important;
        border-color: #2a7d4f !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .fc .fc-prev-button:hover, .fc .fc-next-button:hover {
        background-color: #1e5c38 !important;
        border-color: #1e5c38 !important;
    }
    
    /* Replace text with arrows */
    .fc .fc-prev-button .fc-icon:before {
        content: "←" !important;
        font-size: 20px !important;
        font-weight: bold !important;
    }
    
    .fc .fc-next-button .fc-icon:before {
        content: "→" !important;
        font-size: 20px !important;
        font-weight: bold !important;
    }
    
    /* Remove today button */
    .fc .fc-today-button {
        display: none !important;
    }

    /* Add this CSS in the head or style section */
    <style>
        /* Better button styles for navigation */
        .fc .fc-button-primary {
            background-color: #2a7d4f;
            border-color: #2a7d4f;
            color: #fff;
            font-weight: bold;
            padding: 8px 15px;
        }
        
        .fc .fc-button-primary:hover {
            background-color: #206e3f;
            border-color: #206e3f;
        }
        
        /* Clear the existing arrow styles */
        .fc .fc-prev-button .fc-icon:before,
        .fc .fc-next-button .fc-icon:before {
            font-family: 'Material Icons';
            font-size: 24px !important;
        }
        
        .fc .fc-prev-button .fc-icon:before {
            content: "chevron_left" !important;
        }
        
        .fc .fc-next-button .fc-icon:before {
            content: "chevron_right" !important;
        }
        
        /* Add text label alongside icon */
        .fc .fc-prev-button:after {
            content: " Prev";
        }
        
        .fc .fc-next-button:after {
            content: " Next";
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div> SECURITY AGENCY</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a href="accounting_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="daily_time_record.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
                    <span class="material-icons">schedule</span>
                    <span>Daily Time Record</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rate_locations.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Rate Management">
                    <span class="material-icons">attach_money</span>
                    <span>Rate per Locations</span>
                </a>
            </li>
             <li class="nav-item">
                <a href="calendar.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Calendar">
                    <span class="material-icons">date_range</span>
                    <span>Calendar</span>
                </a>
            </li>
             <li class="nav-item">
                <a href="masterlist.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">assignment</span>
                    <span>Masterlist</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="archives.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Archives">
                    <span class="material-icons">archive</span>
                    <span>Archives</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logs">
                    <span class="material-icons">receipt_long</span>
                    <span>Logs</span>
                </a>
            </li>
            <li class="nav-item mt-5">
                <a href="../logout.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                    <span class="material-icons">logout</span>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Header -->
        <div class="header">
            <button class="toggle-sidebar" id="toggleSidebar">
                <span class="material-icons">menu</span>
            </button>
            <div class="current-datetime ms-3 d-none d-md-block">
                <span id="current-date"></span> | <span id="current-time"></span>
            </div>
            <div class="user-profile" id="userProfile" data-bs-toggle="modal" data-bs-target="#profileModal">
                <span><?php echo $superadminName; ?></span>
                <a href="profile.php"><img src="<?php echo $superadminProfile; ?>" alt="User Profile"></a>
            </div>
        </div>

        <!-- Calendar Content -->
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-success text-white text-center">
                            <div class="d-flex justify-content-center align-items-center">
                                <i class="material-icons me-2">date_range</i>
                                <h5 class="mb-0">Philippine Holiday Calendar</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Color coding legend -->
                            <div class="holiday-legend mb-4">
                                <div class="holiday-legend-item">
                                    <div class="holiday-badge fc-regular-holiday"></div>
                                    <div>Regular Holiday</div>
                                </div>
                                <div class="holiday-legend-item">
                                    <div class="holiday-badge fc-special-non-working-holiday"></div>
                                    <div>Special Non-Working Holiday</div>
                                </div>
                                <div class="holiday-legend-item">
                                    <div class="holiday-badge fc-special-working-holiday"></div>
                                    <div>Special Working Holiday</div>
                                </div>
                            </div>
                            
                            <!-- Instructions alert -->
                            <div class="alert alert-info mb-4" role="alert">
                                <i class="material-icons align-middle me-2">info</i>
                                Click on any day to add a holiday. Click on an existing holiday to edit or delete it.
                            </div>
                            
                            <!-- Calendar container -->
                            <div id="calendar" class="mb-4"></div>
                            

                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Holiday Modal -->
    <div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="holidayModalLabel">Add Holiday</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="holidayForm">
                        <input type="hidden" id="holidayId" name="holidayId">
                        
                        <div class="mb-3">
                            <label for="holidayDate" class="form-label">Holiday Date *</label>
                            <input type="date" class="form-control" id="holidayDate" name="holidayDate" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="holidayName" class="form-label">Holiday Name *</label>
                            <input type="text" class="form-control" id="holidayName" name="holidayName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="holidayType" class="form-label">Holiday Type *</label>
                            <select class="form-select" id="holidayType" name="holidayType" required>
                                <option value="">Select holiday type</option>
                                <option value="Regular">Regular Holiday</option>
                                <option value="Special Non-Working">Special Non-Working Holiday</option>
                                <option value="Special Working">Special Working Holiday</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveHoliday">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Holiday Modal -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addHolidayModalLabel">Add New Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addHolidayForm">
                        <!-- Make sure your form has these exact field IDs and attributes -->
                    <div class="mb-3">
                        <label for="holidayName" class="form-label">Holiday Name</label>
                        <input type="text" class="form-control" id="add_holidayName" name="holidayName" required>
                    </div>
                    <div class="mb-3">
                        <label for="holidayDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="add_holidayDate" name="holidayDate" required>
                    </div>
                    <div class="mb-3">
                        <label for="holidayType" class="form-label">Holiday Type</label>
                        <select class="form-select" id="add_holidayType" name="holidayType" required>
                            <option value="">Select holiday type</option>
                            <option value="Regular">Regular Holiday</option>
                            <option value="Special Non-Working">Special Non-Working Holiday</option>
                            <option value="Special Working">Special Working Holiday</option>
                        </select>
                    </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveHolidayBtn">Save Holiday</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Holiday Details Modal -->
    <div class="modal fade" id="holidayDetailsModal" tabindex="-1" aria-labelledby="holidayDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="holidayDetailsModalLabel">Holiday Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="holidayDetailsContent">
                        <p><strong>Name:</strong> <span id="detailHolidayName"></span></p>
                        <p><strong>Date:</strong> <span id="detailHolidayDate"></span></p>
                        <p><strong>Type:</strong> <span id="detailHolidayType"></span></p>
                    </div>
                </div>
                <!-- Update the holiday details modal footer -->
                <div class="modal-footer">
                    <input type="hidden" id="detailHolidayId" value="">
                    <button type="button" class="btn btn-danger" id="deleteHolidayBtn">
                        <i class="material-icons small me-1">delete</i> Delete Holiday
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/accounting_dashboard.js"></script>
    
    <!-- Calendar JS Script -->
    <!-- Calendar Functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize FullCalendar
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev',
                    center: 'title',
                    right: 'next'
                },
                themeSystem: 'bootstrap5',
                dayMaxEvents: true,
                eventDidMount: function(info) {
                    // Add holiday type as a class to help with styling
                    if (info.event.extendedProps.holidayType) {
                        info.el.classList.add('fc-' + info.event.extendedProps.holidayType.toLowerCase().replace(/\s+/g, '-') + '-holiday');
                    }
                },
                eventDisplay: 'block', // Make events more visible
                events: function(info, successCallback, failureCallback) {
                    // Fetch holiday data from database with better error handling
                    $.ajax({
                        url: 'get_holidays.php',
                        type: 'GET',
                        data: {
                            start: info.start.toISOString().split('T')[0],
                            end: info.end.toISOString().split('T')[0],
                            year: info.start.getFullYear()
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (!Array.isArray(response)) {
                                failureCallback({ message: 'Invalid response format from server' });
                                return;
                            }
                            
                            const events = response.map(holiday => {
                                // Set color based on holiday type (keep existing color scheme)
                                let color;
                                switch(holiday.holiday_type) {
                                    case 'Regular':
                                        color = '#dc3545'; // Red
                                        break;
                                    case 'Special Non-Working':
                                        color = '#fd7e14'; // Orange
                                        break;
                                    case 'Special Working':
                                        color = '#0dcaf0'; // Blue
                                        break;
                                    default:
                                        color = '#28a745'; // Green for custom
                                }
                                
                                // Add a lock icon to default holidays
                                const isDefault = holiday.is_default == 1;
                                const title = isDefault ? `${holiday.holiday_name} 🔒` : holiday.holiday_name;
                                
                                return {
                                    id: holiday.holiday_id,
                                    title: title,
                                    start: holiday.holiday_date,
                                    color: color,
                                    extendedProps: {
                                        holidayType: holiday.holiday_type,
                                        isDefault: isDefault
                                    }
                                };
                            });
                            
                            successCallback(events);
                        },
                        error: function(xhr, status, error) {
                            failureCallback({ message: 'There was an error while fetching holidays: ' + error });
                        }
                    });
                },
                dateClick: function(info) {
                    // Get the clicked date in the correct format
                    const clickedDate = info.dateStr;
                    
                    // Clear any previous values
                    $('#holidayName').val('');
                    $('#holidayType').val('Regular');
                    
                    // Set the date value properly and make it visible
                    $('#holidayDate').val(clickedDate);
                    
                    // Add a visual indication of the selected date
                    const formattedDate = new Date(clickedDate).toLocaleDateString('en-PH', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    // Show the modal
                    $('#addHolidayModal').modal('show');
                },
                eventClick: function(info) {
                    // Get basic info without the lock icon
                    const holidayName = info.event.title.replace(' 🔒', '');
                    const isDefault = info.event.extendedProps.isDefault;
                    
                    // Show holiday details
                    $('#detailHolidayName').text(holidayName);
                    $('#detailHolidayDate').text(new Date(info.event.start).toLocaleDateString('en-PH', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    }));
                    $('#detailHolidayType').text(info.event.extendedProps.holidayType);
                    
                    // Store holiday ID for deletion
                    $('#detailHolidayId').val(info.event.id);
                    
                    // Show/hide the default holiday warning
                    if (isDefault) {
                        $('#defaultHolidayWarning').show();
                        $('#deleteHolidayBtn').prop('disabled', true).addClass('disabled');
                    } else {
                        $('#defaultHolidayWarning').hide();
                        $('#deleteHolidayBtn').prop('disabled', false).removeClass('disabled');
                    }
                    
                    $('#holidayDetailsModal').modal('show');
                },
                datesSet: function(info) {
                    // When calendar view changes, check for holidays in new view
                    checkAndPopulateHolidays(info.view.currentStart.getFullYear());
                }
            });
            
            // Store calendar reference in window for global access
            window.holidayCalendar = calendar;
            
            calendar.render();

            // Function to check and populate holidays for a year
            function checkAndPopulateHolidays(year) {
                $.ajax({
                    url: 'populate_ph_holidays.php',
                    type: 'POST',
                    data: {
                        check_year: year
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.populated) {
                            // Refresh calendar if holidays were added
                            calendar.refetchEvents();
                        }
                    }
                });
            }

            // Check holidays for current year on page load
            checkAndPopulateHolidays(new Date().getFullYear());
            
            // Update date and time in real-time
            function updateDateTime() {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                const timeOptions = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };
                
                document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
                document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);
        });
    </script>
    <script>
        // Handle holiday form submission
        $('#saveHolidayBtn').click(function() {
            // Get form values SPECIFICALLY from the addHolidayModal
            const modal = $('#addHolidayModal');
            const holidayName = $('#add_holidayName').val().trim();
            const holidayDate = $('#add_holidayDate').val().trim();
            const holidayType = $('#add_holidayType').val();
            
            // Debug output
            console.log('Form Values from active modal:', {
                name: holidayName,
                date: holidayDate,
                type: holidayType
            });
            
            // Validate inputs
            if (!holidayName || holidayName === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please enter a holiday name',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }
            
            if (!holidayDate || holidayDate === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select a date',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }
            
            if (!holidayType || holidayType === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select a holiday type',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }
            
            // Format date properly - handle multiple possible formats
            let formattedDate = holidayDate;
            
            // Check if date is already in YYYY-MM-DD format
            if (!/^\d{4}-\d{2}-\d{2}$/.test(holidayDate)) {
                try {
                    // Try to parse the date
                    const dateParts = new Date(holidayDate);
                    if (!isNaN(dateParts.getTime())) {
                        formattedDate = dateParts.getFullYear() + '-' + 
                                       String(dateParts.getMonth() + 1).padStart(2, '0') + '-' + 
                                       String(dateParts.getDate()).padStart(2, '0');
                    } else {
                        throw new Error('Invalid date format');
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Invalid date format. Please use YYYY-MM-DD format.',
                        confirmButtonColor: '#2a7d4f'
                    });
                    return;
                }
            }
            
            // Debug - show what we're sending
            console.log('Sending to server:', {
                holidayName: holidayName,
                holidayDate: formattedDate,
                holidayType: holidayType
            });
            
            // Submit data via AJAX
            $.ajax({
                url: 'save_holiday.php',
                type: 'POST',
                data: {
                    holidayName: holidayName,
                    holidayDate: formattedDate,
                    holidayType: holidayType
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Server response:', response);
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Holiday saved successfully',
                            confirmButtonColor: '#2a7d4f'
                        });
                        
                        // Reset form and close modal
                        $('#addHolidayForm')[0].reset();
                        $('#addHolidayModal').modal('hide');
                        
                        // Refresh calendar
                        if (window.holidayCalendar) {
                            window.holidayCalendar.refetchEvents();
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to add holiday',
                            confirmButtonColor: '#2a7d4f'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while processing your request',
                        confirmButtonColor: '#2a7d4f'
                    });
                }
            });
        });
    </script>
    <script>
$(document).ready(function() {
    // Direct AJAX call to check for holidays
    $.ajax({
        url: 'get_holidays.php',
        type: 'GET',
        data: {
            start: '2025-06-01',
            end: '2025-06-30',
            year: 2025
        },
        dataType: 'json',
        success: function(response) {
            console.log('June 2025 Holidays:', response);
            
            if (Array.isArray(response) && response.length > 0) {
                console.log(`✅ Found ${response.length} holidays`);
                
                // Force calendar refresh
                if (window.holidayCalendar) {
                    console.log('Refreshing calendar events...');
                    window.holidayCalendar.refetchEvents();
                }
            } else {
                console.log('❌ No holidays found or invalid response format:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching holidays:', error);
            console.error('Response:', xhr.responseText);
        }
    });
});
</script>
<script>
$(document).ready(function() {
    // Holiday deletion handler
    $('#deleteHolidayBtn').click(function() {
        const holidayId = $('#detailHolidayId').val();
        const holidayName = $('#detailHolidayName').text();
        
        if (!holidayId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not identify the holiday to delete.',
                confirmButtonColor: '#2a7d4f'
            });
            return;
        }
        
        // Confirm deletion
        Swal.fire({
            icon: 'warning',
            title: 'Delete Holiday?',
            text: `Are you sure you want to delete "${holidayName}"? This action cannot be undone.`,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Process deletion via AJAX
                $.ajax({
                    url: 'delete_holiday.php',
                    type: 'POST',
                    data: {
                        holidayId: holidayId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'Holiday has been deleted successfully.',
                                confirmButtonColor: '#2a7d4f'
                            });
                            
                            // Close the modal
                            $('#holidayDetailsModal').modal('hide');
                            
                            // Refresh the calendar
                            if (window.holidayCalendar) {
                                window.holidayCalendar.refetchEvents();
                            }
                        } else {
                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to delete holiday.',
                                confirmButtonColor: '#2a7d4f'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while processing your request.',
                            confirmButtonColor: '#2a7d4f'
                        });
                    }
                });
            }
        });
    });
});
</script>
<!-- Add this to the modal body before the closing div -->
<div id="defaultHolidayWarning" class="alert alert-warning mt-3" style="display:none;">
    <i class="material-icons align-middle me-1">lock</i> 
    This is an official Philippine holiday and cannot be deleted to maintain payroll accuracy.
</div>

<div class="mobile-nav">
        <div class="mobile-nav-container">
            <!-- FIXED: Match sidebar links -->
            <a href="accounting_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item active">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <!-- FIXED: Add missing links to match sidebar -->
            <a href="rate_locations.php" class="mobile-nav-item">
                <span class="material-icons">attach_money</span>
                <span class="mobile-nav-text">Rate per Locations</span>
            </a>
            <a href="calendar.php" class="mobile-nav-item">
                <span class="material-icons">date_range</span>
                <span class="mobile-nav-text">Calendar</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item">
                <span class="material-icons">list_alt</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="archives.php" class="mobile-nav-item">
                <span class="material-icons">archive</span>
                <span class="mobile-nav-text">Archives</span>
            </a>
            <a href="logs.php" class="mobile-nav-item">
                <span class="material-icons">receipt_long</span>
                <span class="mobile-nav-text">Logs</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>
</body>
</html>