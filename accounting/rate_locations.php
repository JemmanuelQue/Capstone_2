<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);

// Database connection
require_once '../db_connection.php'; // assumes $conn is PDO

// Get current Accounting user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 4 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'] : "Accounting";

// Get superadmin's profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate per Locations - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/rate_locations.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <a href="rate_locations.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Users List">
                    <span class="material-icons">attach_money</span>
                    <span>Rate per Locations</span>
                </a>
            </li>
             <li class="nav-item">
                <a href="calendar.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
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
                    <img src="<?php echo $superadminProfile; ?>" alt="User Profile">
                </div>
        </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Update Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add method and enctype for file uploads -->
                    <form id="updateProfileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="profilePic" class="form-label">Profile Image</label>
                            <div class="text-center mb-3">
                                <!-- Current profile image -->
                                <img id="currentProfileImage" src="<?php echo !empty($superadminProfile) ? $superadminProfile : '../assets/images/profile.jpg'; ?>" 
                                    alt="Current Profile" class="rounded-circle" width="100" height="100">
                                    
                                <!-- Preview container (initially hidden) -->
                                <div id="imagePreviewContainer" style="display: none;" class="mt-3">
                                    <p id="previewText" class="text-muted mb-2"></p>
                                    <img id="imagePreview" src="#" alt="Image Preview" class="rounded-circle" width="100" height="100">
                                </div>
                            </div>
                            <input type="file" class="form-control" id="profilePic" name="profilePic" 
                                accept=".jpg,.jpeg,.png,.avif">
                            <small class="form-text text-muted">Accepted file types: JPG, PNG, AVIF. Max size: 5MB</small>
                        </div>
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" 
                                value="<?php echo $superadminData['First_Name']; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" 
                                value="<?php echo $superadminData['Last_Name']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SWAL Alerts for Profile Picture -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($_SESSION['profilepic_success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo $_SESSION['profilepic_success']; ?>',
                    confirmButtonColor: '#2a7d4f'
                });
                <?php unset($_SESSION['profilepic_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['profilepic_error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $_SESSION['profilepic_error']; ?>',
                    confirmButtonColor: '#dc3545'
                });
                <?php unset($_SESSION['profilepic_error']); ?>
            <?php endif; ?>
        });
    </script>

    <!-- Profile Picture Preview Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the file input and preview image elements
            const profilePicInput = document.getElementById('profilePic');
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewImage = document.getElementById('imagePreview');
            const currentImage = document.getElementById('currentProfileImage');
            
            // Listen for file selection
            profilePicInput.addEventListener('change', function() {
                const file = this.files[0];
                
                // Check if a file was selected
                if (file) {
                    // Show the preview container
                    previewContainer.style.display = 'block';
                    
                    // Hide the current image
                    if (currentImage) {
                        currentImage.style.display = 'none';
                    }
                    
                    // Create a FileReader to read the image
                    const reader = new FileReader();
                    
                    // Set up the FileReader onload event
                    reader.onload = function(e) {
                        // Set the preview image source to the loaded data URL
                        previewImage.src = e.target.result;
                    }
                    
                    // Read the file as a data URL
                    reader.readAsDataURL(file);
                    
                } else {
                    // If no file selected or selection canceled, show current image
                    previewContainer.style.display = 'none';
                    if (currentImage) {
                        currentImage.style.display = 'block';
                    }
                }
            });
        });
    </script>

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- Custom JS -->
    <script src="js/accounting_dashboard.js"></script>
    <script src="js/rate_locations.js"></script>

    <!-- SweetAlert for messages -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if(isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?php echo $_SESSION['success_message']; ?>',
                confirmButtonColor: '#2a7d4f'
            });
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo $_SESSION['error_message']; ?>',
                confirmButtonColor: '#dc3545'
            });
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    });
    </script>

    <!-- Mobile Bottom Navigation -->
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
                <span class="material-icons">assignment</span>
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


    <!-- Main content after the header -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="material-icons align-middle me-2">attach_money</i> Location Rate Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="material-icons align-middle me-2">info</i>
                                    Set daily rates for each location. These rates will be used for payroll calculations and will be applied to all guards assigned to these locations.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Location Rates Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="locationRatesTable">
                                <thead class="table-success">
                                    <tr>
                                        <th>Location Name</th>
                                        <th>Current Daily Rate (₱)</th>
                                        <th>Guards Assigned</th>
                                        <th>Last Updated</th>
                                        <th>Last Update Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all unique locations with their current rates and latest update reason
                                    $locationQuery = $conn->query("
                                        SELECT 
                                            gl.location_name,
                                            MAX(gl.daily_rate) as current_rate,
                                            COUNT(DISTINCT gl.user_id) as guard_count,
                                            MAX(gl.updated_at) as last_updated,
                                            (SELECT 
                                                SUBSTRING_INDEX(SUBSTRING_INDEX(al.Action, 'Reason: ', -1), ' | ', 1) 
                                                FROM audit_logs al 
                                                WHERE al.Action LIKE CONCAT('%location rate for %', gl.location_name, '%') 
                                                AND al.Action LIKE '%Reason:%' 
                                                ORDER BY al.Timestamp DESC 
                                                LIMIT 1
                                            ) as last_reason
                                        FROM guard_locations gl
                                        WHERE gl.is_primary = 1 AND gl.is_active = 1
                                        GROUP BY gl.location_name
                                        ORDER BY gl.location_name
                                    ");
                                    
                                    while ($location = $locationQuery->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($location['location_name']) . '</td>';
                                        echo '<td class="text-end"><span class="currency-value">₱' . number_format(abs($location['current_rate']), 2) . '</span></td>';
                                        echo '<td class="text-center">' . $location['guard_count'] . '</td>';
                                        echo '<td>' . date('M d, Y h:i A', strtotime($location['last_updated'])) . '</td>';
                                        echo '<td>' . ($location['last_reason'] ? htmlspecialchars($location['last_reason']) : '<span class="text-muted">No reason recorded</span>') . '</td>';
                                        echo '<td class="text-center">
                                                <button class="btn btn-sm btn-primary edit-rate-btn" 
                                                    data-location="' . htmlspecialchars($location['location_name']) . '" 
                                                    data-rate="' . $location['current_rate'] . '">
                                                    <i class="material-icons">edit</i> Edit Rate
                                                </button>
                                              </td>';
                                        echo '</tr>';
                                    }
                                    
                                    // If no locations found
                                    if ($locationQuery->rowCount() == 0) {
                                        echo '<tr><td colspan="6" class="text-center">No locations found. Locations will appear here once assigned to guards.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Rate Modal -->
    <div class="modal fade" id="editRateModal" tabindex="-1" aria-labelledby="editRateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="editRateModalLabel">Update Location Rate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateRateForm" method="POST" action="update_location_rate.php">
                        <input type="hidden" id="locationName" name="locationName">
                        
                        <div class="mb-3">
                            <label for="locationDisplay" class="form-label">Location:</label>
                            <input type="text" class="form-control" id="locationDisplay" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dailyRate" class="form-label">Daily Rate (₱):</label>
                            <input type="number" class="form-control" id="dailyRate" name="dailyRate" step="0.01" min="0" required>
                            <div class="form-text text-muted">Enter the new daily rate for this location.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="updateReason" class="form-label">Reason for Update:</label>
                            <textarea class="form-control" id="updateReason" name="updateReason" rows="3" required placeholder="Please specify the reason for this rate change..."></textarea>
                            <div class="form-text text-muted">Provide a clear reason for this rate update for audit purposes.</div>
                        </div>
                        
                        <!-- Checkbox removed as requested -->
                        <input type="hidden" name="updateAllGuards" value="1">
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="material-icons align-middle">save</i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>