<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
require_once '../includes/govt_id_formatter.php';

// Enforce HR role (3)
if (!validateSession($conn, 3)) { exit; }

// Get current HR user's name
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 3 AND status = 'Active' AND User_ID = ?");
$hrStmt->execute([$_SESSION['user_id']]);
$hrData = $hrStmt->fetch(PDO::FETCH_ASSOC);
$hrName = $hrData ? $hrData['First_Name'] . ' ' . $hrData['Last_Name'] : "HR Staff";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $hrProfile = $profileData['Profile_Pic'];
} else {
    $hrProfile = '../images/default_profile.png';
}

// Fetch Roles for filters
try {
    $rolesStmt = $conn->prepare("SELECT Role_ID, Role_Name FROM roles ORDER BY Role_Name");
    $rolesStmt->execute();
    $allRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allRoles = [];
}

// Department options limited to: Super Admin(1), Admin(2), HR(3), Accounting(4)
$desiredDeptIds = [1,2,3,4,5];
$allRolesById = [];
foreach ($allRoles as $r) { $allRolesById[(int)$r['Role_ID']] = $r; }
$deptRoles = [];
foreach ($desiredDeptIds as $rid) { if (isset($allRolesById[$rid])) { $deptRoles[] = $allRolesById[$rid]; } }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masterlist - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/masterlist.css">
    
    <!-- Additional Styles for New Features -->
    <style>
        .role-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .role-card.border-success {
            border-color: #28a745 !important;
            background-color: #f8fff9;
        }
        .step-content {
            min-height: 300px;
        }
        .table img {
            border: 2px solid #dee2e6;
        }
        .btn-group .btn {
            margin-right: 0.25rem;
        }
        .modal-xl {
            max-width: 1200px;
        }
        
        /* Required field asterisk styling */
        .form-label .required {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Validation error styling */
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        
        /* Success validation styling */
        .form-control.is-valid,
        .form-select.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #28a745;
        }
        
        /* Employee type specific styling */
        .govt-id-input.bg-light {
            background-color: #f8f9fa !important;
            cursor: not-allowed;
        }
        
        /* Government ID indicator styling */
        .badge .material-icons {
            vertical-align: middle;
        }
    </style>
    
    <!-- Print Styles -->
    <style media="print">
        @page {
            size: landscape;
        }
        .sidebar, .header, .mobile-nav, .card-header, .dataTables_filter, 
        .dataTables_length, .dataTables_paginate, .dataTables_info,
        .modal, .btn, .no-print {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        body {
            background-color: white !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .card-body {
            padding: 0 !important;
        }
        table {
            width: 100% !important;
        }
        /* Add a header for print */
        body::before {
            content: "Green Meadows Security Agency - Employee Masterlist";
            display: block;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        /* Add a timestamp for print */
        body::after {
            content: "Generated on: " attr(data-print-date);
            display: block;
            text-align: center;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

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
                <a href="hr_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Requests">
                    <span class="material-icons">event_note</span>
                    <span>Leave Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="recruitment.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Recruitment">
                    <span class="material-icons">person_add</span>
                    <span>Recruitment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="masterlist.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">assignment</span>
                    <span>Masterlist</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">assessment</span>
                    <span>Performance Evaluation</span>
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
            <a href="profile.php" class="user-profile" id="userProfile" style="color:black; text-decoration:none;">
                <span><?php echo htmlspecialchars($hrName); ?></span>
                <img src="<?php echo $hrProfile; ?>" alt="User Profile">
            </a>
        </div>

        <!-- Main Content Area -->
        <div class="container-fluid mt-4">
            <h1 class="mb-4">Employee Masterlist</h1>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="material-icons align-middle me-1">list</i>
                            <span class="align-middle">Complete Employee List</span>
                        </div>
                        <div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success me-2" id="addEmployeeBtn">
                                    <i class="material-icons align-middle me-1">person_add</i> Add Employee
                                </button>
                                <button class="btn btn-sm btn-light" id="printBtn">
                                    <i class="material-icons align-middle me-1">print</i> Print
                                </button>
                                <button class="btn btn-sm btn-light" id="exportPdfBtn">
                                    <i class="material-icons align-middle me-1">picture_as_pdf</i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-md-3">
                            <label for="filterDepartment" class="form-label mb-1">Department</label>
                            <select id="filterDepartment" class="form-select form-select-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($deptRoles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['Role_Name']); ?>">
                                        <?php echo htmlspecialchars($role['Role_Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="filterStatus" class="form-label mb-1">Status</label>
                            <select id="filterStatus" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="filterGovtID" class="form-label mb-1">Government ID</label>
                            <select id="filterGovtID" class="form-select form-select-sm">
                                <option value="">All Employees</option>
                                <option value="complete">With Complete Gov't IDs</option>
                                <option value="incomplete">Missing Gov't IDs</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button id="resetFilters" class="btn btn-outline-secondary btn-sm ms-auto mt-auto">Reset Filters</button>
                        </div>
                    </div>
                    

                    <div class="table-responsive">
                        <table id="masterlistTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Length of Service</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get all employees with government details
                                $query = "SELECT u.User_ID, u.employee_id, u.First_Name, u.middle_name, u.Last_Name, u.name_extension, 
                                                 u.phone_number, u.status, r.Role_Name, u.Profile_Pic, u.Role_ID,
                                                 u.hired_date, u.created_at,
                                                 g.sss_number, g.tin_number, g.philhealth_number, g.pagibig_number
                                          FROM users u
                                          JOIN roles r ON u.Role_ID = r.Role_ID
                                          LEFT JOIN govt_details g ON u.User_ID = g.user_id
                                          WHERE u.archived_at IS NULL
                                          ORDER BY u.Last_Name";
                                $stmt = $conn->prepare($query);
                                $stmt->execute();
                                
                                // Function to check if a government ID is a placeholder (all zeros or common patterns)
                                function isPlaceholderGovtId($id) {
                                    if (empty($id)) return true;
                                    
                                    // Remove all non-numeric characters for checking
                                    $numericOnly = preg_replace('/\D/', '', $id);
                                    
                                    // Check if it's all zeros or empty
                                    return empty($numericOnly) || ctype_digit($numericOnly) && intval($numericOnly) === 0;
                                }
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    // Format name with middle initial
                                    $middleInitial = !empty($row['middle_name']) ? ' ' . substr($row['middle_name'], 0, 1) . '.' : '';
                                    $extension = !empty($row['name_extension']) ? ' ' . $row['name_extension'] : '';
                                    $fullName = $row['First_Name'] . $middleInitial . ' ' . $row['Last_Name'] . $extension;
                                    
                                    // Get profile picture
                                    $profilePic = '../images/default_profile.png';
                                    if (!empty($row['Profile_Pic']) && file_exists($row['Profile_Pic'])) {
                                        $profilePic = $row['Profile_Pic'];
                                    }
                                    
                                    // Determine status color
                                    $statusClass = $row['status'] === 'Active' ? 'bg-success' : 'bg-danger';
                                    
                                    // Calculate length of service
                                    $serviceDate = null;
                                    
                                    // Use hired_date for length of service calculation
                                    if (!empty($row['hired_date'])) {
                                        $serviceDate = new DateTime($row['hired_date']);
                                    } 
                                    // Fall back to created_at if hired_date is not available
                                    else if (!empty($row['created_at'])) {
                                        $serviceDate = new DateTime($row['created_at']);
                                    }
                                    // If no date is available, use a placeholder
                                    
                                    // Calculate service length
                                    $lengthOfService = "Not available";
                                    if ($serviceDate) {
                                        $now = new DateTime();
                                        $interval = $serviceDate->diff($now);
                                        
                                        if ($interval->y > 0) {
                                            $lengthOfService = $interval->y . " year" . ($interval->y > 1 ? "s" : "");
                                            if ($interval->m > 0) {
                                                $lengthOfService .= ", " . $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                                            }
                                        } else if ($interval->m > 0) {
                                            $lengthOfService = $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                                            if ($interval->d > 0) {
                                                $lengthOfService .= ", " . $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                                            }
                                        } else {
                                            $lengthOfService = $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                                        }
                                        
                                        // Add hire date in parentheses
                                        $hireDate = $serviceDate->format('M j, Y');
                                        $lengthOfService .= "<br><small class='text-muted'>(Since " . $hireDate . ")</small>";
                                    }
                                    
                                    // Check government ID completeness - handle various placeholder patterns
                                    
                                    $hasSSS = !isPlaceholderGovtId($row['sss_number']);
                                    $hasTIN = !isPlaceholderGovtId($row['tin_number']);
                                    $hasPhilHealth = !isPlaceholderGovtId($row['philhealth_number']);
                                    $hasPagIBIG = !isPlaceholderGovtId($row['pagibig_number']);
                                    $govtIDComplete = $hasSSS && $hasTIN && $hasPhilHealth && $hasPagIBIG;
                                    
                                    echo "<tr data-govt-complete='" . ($govtIDComplete ? 'true' : 'false') . "'>";
                                    echo "<td><img src='" . htmlspecialchars($profilePic) . "' alt='Profile' class='rounded-circle' width='40' height='40' style='object-fit: cover;'></td>";
                                    echo "<td>" . htmlspecialchars($row['employee_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($fullName) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['Role_Name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                                    echo "<td>" . $lengthOfService . "</td>";
                                    
                                    // Add government ID indicator next to status
                                    $govtIDIndicator = $govtIDComplete ? 
                                        "<small class='text-success ms-1' title='Government IDs Complete'><i class='material-icons' style='font-size: 14px;'>check_circle</i></small>" : 
                                        "<small class='text-warning ms-1' title='Missing Government IDs'><i class='material-icons' style='font-size: 14px;'>warning</i></small>";
                                    
                                    echo "<td><span class='badge {$statusClass}'>" . htmlspecialchars($row['status']) . "</span>" . $govtIDIndicator . "</td>";
                                    echo "<td>
                                            <button class='btn btn-sm btn-primary view-details me-1' data-id='" . $row['User_ID'] . "' title='View Details'>
                                                <i class='material-icons'>visibility</i>
                                            </button>
                                            <button class='btn btn-sm btn-info edit-employee me-1' data-id='" . $row['User_ID'] . "' title='Edit Employee'>
                                                <i class='material-icons'>edit</i>
                                            </button>
                                            <button class='btn btn-sm btn-warning archive-employee' data-id='" . $row['User_ID'] . "' data-name='" . htmlspecialchars($fullName) . "' title='Archive Employee'>
                                                <i class='material-icons'>archive</i>
                                            </button>
                                        </td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="employeeDetailsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="editEmployeeContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Employee Modal -->
    <div class="modal fade" id="archiveEmployeeModal" tabindex="-1" aria-labelledby="archiveEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="archiveEmployeeModalLabel">Archive Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="archiveEmployeeForm" method="POST" action="employee_management.php">
                        <input type="hidden" name="action" value="archive_employee">
                        <input type="hidden" id="archiveEmployeeId" name="employee_id">
                        <p>Are you sure you want to archive <strong id="archiveEmployeeName"></strong>?</p>
                        <div class="mb-3">
                            <label for="archiveReason" class="form-label">Reason for archiving (Optional):</label>
                            <textarea class="form-control" id="archiveReason" name="reason" rows="3" placeholder="Enter reason for archiving this employee..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Archive Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addEmployeeModalLabel">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Step Progress Indicator -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="progress" style="height: 3px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 33%;" id="progressBar"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-success" id="step1Label"><strong>Step 1: Role Selection</strong></small>
                                <small class="text-muted" id="step2Label">Step 2: Personal Information</small>
                                <small class="text-muted" id="step3Label">Step 3: Account Details</small>
                            </div>
                        </div>
                    </div>

                    <form id="addEmployeeForm" method="POST" action="employee_management.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_employee">
                        <!-- Step 1: Role Selection -->
                        <div class="step-content" id="step1">
                            <h6 class="mb-3">Select Employee Role</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="card role-card" data-role="3" data-role-name="Accounting">
                                        <div class="card-body text-center">
                                            <i class="material-icons mb-2" style="font-size: 48px; color: #28a745;">calculate</i>
                                            <h6>Accounting</h6>
                                            <p class="text-muted small">Manages financial records and payroll</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card role-card" data-role="4" data-role-name="Human Resource">
                                        <div class="card-body text-center">
                                            <i class="material-icons mb-2" style="font-size: 48px; color: #17a2b8;">people</i>
                                            <h6>Human Resource</h6>
                                            <p class="text-muted small">Manages employee relations and recruitment</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card role-card" data-role="5" data-role-name="Security Guard">
                                        <div class="card-body text-center">
                                            <i class="material-icons mb-2" style="font-size: 48px; color: #ffc107;">security</i>
                                            <h6>Security Guard</h6>
                                            <p class="text-muted small">Provides security services at client locations</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="selectedRole" name="role_id">
                            <input type="hidden" id="selectedRoleName" name="role_name">
                        </div>

                        <!-- Step 2: Personal Information -->
                        <div class="step-content d-none" id="step2">
                            <h6 class="mb-3">Personal Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label">First Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label">Last Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="middleName" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middleName" name="middle_name">
                                </div>
                                <div class="col-md-6">
                                    <label for="nameExtension" class="form-label">Name Extension</label>
                                    <select class="form-select" id="nameExtension" name="name_extension">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="sex" class="form-label">Sex <span class="required">*</span></label>
                                    <select class="form-select" id="sex" name="sex" required>
                                        <option value="">Select Sex</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="civilStatus" class="form-label">Marital Status <span class="required">*</span></label>
                                    <select class="form-select" id="civilStatus" name="civil_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Separated">Separated (Annulled)</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="phoneNumber" class="form-label">Phone Number <span class="required">*</span></label>
                                    <input type="tel" class="form-control" id="phoneNumber" name="phone_number" required 
                                           placeholder="0956 562 1232" maxlength="13">
                                    <div class="form-text">Must be in Philippine format: 09XX XXX XXXX</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="emailAddress" class="form-label">Email Address <span class="required">*</span></label>
                                    <input type="email" class="form-control" id="emailAddress" name="email" required 
                                           placeholder="sample@gmail.com">
                                    <div class="form-text">Must be a valid email from trusted domains</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="birthDate" class="form-label">Birth Date <span class="required">*</span></label>
                                    <input type="date" class="form-control" id="birthDate" name="birth_date" required>
                                    <div class="form-text">Employee must be at least 18 years old</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="hireDate" class="form-label">Hire Date <span class="required">*</span></label>
                                    <input type="date" class="form-control" id="hireDate" name="hire_date" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-12" id="locationSection" style="display: none;">
                                    <label for="guardLocation" class="form-label">Guard Location <span class="required">*</span></label>
                                    <select class="form-select" id="guardLocation" name="guard_location">
                                        <option value="">Select Location</option>
                                        <!-- Options will be loaded dynamically -->
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>

                            </div>
                        </div>

                        <!-- Step 3: Account Details -->
                        <div class="step-content d-none" id="step3">
                            <h6 class="mb-3">Account Details & Government ID Numbers</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="employeeType" class="form-label">Employee Type <span class="required">*</span></label>
                                    <select class="form-select" id="employeeType" name="employee_type" required>
                                        <option value="">Select Employee Type</option>
                                        <option value="new">New Employee</option>
                                        <option value="old">Existing Employee</option>
                                    </select>
                                    <div class="form-text text-muted">Select if this is a new employee or someone rejoining</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="employeeId" class="form-label">Employee ID <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="employeeId" name="employee_id" required>
                                    <div class="form-text text-muted">Enter a unique employee ID (e.g., EMP001, GUARD001, etc.)</div>
                                    <div id="employeeIdFeedback" class="feedback-message"></div>
                                </div>
                                
                                <!-- Government Details Section -->
                                <div class="col-12 mt-4">
                                    <h6 class="text-primary mb-3"><i class="material-icons align-middle me-1">credit_card</i>Government ID Numbers <span class="required">*</span></h6>
                                    
                                    <!-- New Employee Alert -->
                                    <div class="alert alert-warning d-none" id="newEmployeeAlert">
                                        <i class="material-icons align-middle me-1">info</i>
                                        <strong>New Employee:</strong> Default placeholder values will be used. These can be updated later when the employee provides their actual government ID numbers.
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="sssNumber" class="form-label">SSS Number <span class="required">*</span></label>
                                            <input type="text" class="form-control govt-id-input" id="sssNumber" name="sss_number" 
                                                   placeholder="34-1234567-8" maxlength="12" data-format="sss" required>
                                            <div class="form-text">Format: ##-#######-# (10 digits)</div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="tinNumber" class="form-label">TIN Number <span class="required">*</span></label>
                                            <input type="text" class="form-control govt-id-input" id="tinNumber" name="tin_number" 
                                                   placeholder="123-456-789-000" maxlength="15" data-format="tin" required>
                                            <div class="form-text">Format: ###-###-###-### (12 digits with branch code)</div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="philhealthNumber" class="form-label">PhilHealth Number <span class="required">*</span></label>
                                            <input type="text" class="form-control govt-id-input" id="philhealthNumber" name="philhealth_number" 
                                                   placeholder="12-123456789-0" maxlength="14" data-format="philhealth" required>
                                            <div class="form-text">Format: ##-#########-# (12 digits)</div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="pagibigNumber" class="form-label">Pag-IBIG Number <span class="required">*</span></label>
                                            <input type="text" class="form-control govt-id-input" id="pagibigNumber" name="pagibig_number" 
                                                   placeholder="1234-5678-9012" maxlength="14" data-format="pagibig" required>
                                            <div class="form-text">Format: ####-####-#### (12 digits)</div>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="alert alert-info">
                                            <i class="material-icons align-middle me-1">info</i>
                                            <strong>Account Setup:</strong> Login credentials will be automatically generated and sent to the employee's email address.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer with Navigation -->
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-outline-secondary d-none" id="prevBtn">Previous</button>
                            <button type="button" class="btn btn-success" id="nextBtn">Next</button>
                            <button type="submit" class="btn btn-success d-none" id="submitBtn">Create Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="hr_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="recruitment.php" class="mobile-nav-item">
                <span class="material-icons">person_add</span>
                <span class="mobile-nav-text">Recruitment</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item active">
                <span class="material-icons">list</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="performance_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">assessment</span>
                <span class="mobile-nav-text">Performance Evaluation</span>
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

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <script>
        // Set the print date attribute when the page loads
        document.body.setAttribute('data-print-date', new Date().toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        }));
        
        // Set today's date for reference
        const TODAY = new Date('July 18, 2025');
        
        $(document).ready(function() {
            // ===== Debug instrumentation helper =====
            const DEBUG_ADD_EMP = true; // toggle to enable/disable verbose logging
            function dbg(...args){ if(DEBUG_ADD_EMP) console.log('[AddEmployee]', ...args); }

            dbg('Masterlist JS loaded');
            // Helper functions using Bootstrap 5 native API (avoid jQuery plugin to prevent aria-hidden focus issues)
            function showBsModal(id){
                const el=document.getElementById(id); if(!el) return; const inst=bootstrap.Modal.getOrCreateInstance(el); inst.show();
            }
            function hideBsModal(id){
                const el=document.getElementById(id); if(!el) return; const inst=bootstrap.Modal.getInstance(el); if(inst) inst.hide();
            }
            // Initialize DataTable
            const table = $('#masterlistTable').DataTable({
                responsive: true,
                columnDefs: [
                    { responsivePriority: 1, targets: [0, 2, 5, 6, 7] }, // Profile, Name, Length of Service, Status, Actions
                    { orderable: false, targets: [0, 7] } // Profile and Actions columns
                ]
            });

            // Custom filtering combining Department, Status, and Government ID
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.getAttribute('id') !== 'masterlistTable') return true;
                // Columns: 0 Profile, 1 EmpID, 2 Name, 3 Role, 4 Phone, 5 Length, 6 Status, 7 Actions
                const roleCell = (data[3] || '').toString();
                const statusCellRaw = (data[6] || '').toString();
                const statusCell = statusCellRaw.replace(/<[^>]*>/g, '').trim();

                const deptSel = ($('#filterDepartment').val() || '').trim();
                const statusSel = ($('#filterStatus').val() || '').trim();
                const govtIDSel = ($('#filterGovtID').val() || '').trim();

                // Department targets the Role column
                if (deptSel && roleCell !== deptSel) return false;
                if (statusSel && statusCell !== statusSel) return false;
                
                // Government ID filtering
                if (govtIDSel) {
                    const row = settings.aoData[dataIndex].nTr;
                    const govtComplete = row ? row.getAttribute('data-govt-complete') === 'true' : false;
                    
                    if (govtIDSel === 'complete' && !govtComplete) return false;
                    if (govtIDSel === 'incomplete' && govtComplete) return false;
                }
                
                return true;
            });

            function triggerFilter() { table.draw(); }
            $('#filterDepartment, #filterStatus, #filterGovtID').on('change', triggerFilter);
            $('#resetFilters').on('click', function(e){
                e.preventDefault();
                $('#filterDepartment').val('');
                $('#filterStatus').val('');
                $('#filterGovtID').val('');
                table.draw();
            });
            
            // Handle Print Button
            $('#printBtn').on('click', function() {
                window.print();
            });
            
            // Handle PDF Export
            $('#exportPdfBtn').on('click', function() {
                // Create a hidden DataTable buttons instance for PDF export
                const exportTable = new $.fn.dataTable.Buttons(table, {
                    buttons: [
                        {
                            extend: 'pdfHtml5',
                            text: 'Export to PDF',
                            title: 'Employee Masterlist - Green Meadows Security Agency',
                            filename: 'employee_masterlist_' + new Date().toISOString().slice(0, 10),
                            orientation: 'landscape',
                            pageSize: 'A4',
                            customize: function(doc) {
                                doc.defaultStyle.fontSize = 10;
                                doc.styles.tableHeader.fontSize = 12;
                                doc.styles.title.fontSize = 14;
                                doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                                
                                // Add a footer with datetime
                                const now = new Date();
                                const dateStr = now.toLocaleDateString('en-US', { 
                                    weekday: 'long', 
                                    year: 'numeric', 
                                    month: 'long', 
                                    day: 'numeric' 
                                });
                                const timeStr = now.toLocaleTimeString('en-US');
                                doc['footer'] = function(currentPage, pageCount) {
                                    return { 
                                        text: 'Generated on: ' + dateStr + ' ' + timeStr + ' | Page ' + currentPage.toString() + ' of ' + pageCount, 
                                        alignment: 'center' 
                                    };
                                };
                            }
                        }
                    ]
                });
                
                // Execute the PDF export
                exportTable.container().find('button').trigger('click');
            });
            
            // Date and time update
            function updateDateTime() {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                $('#current-date').text(now.toLocaleDateString('en-US', options));
                
                let hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'
                const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
                $('#current-time').text(timeStr);
            }
            
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Toggle sidebar
            $('#toggleSidebar').on('click', function() {
                $('#sidebar').toggleClass('collapsed');
                $('#main-content').toggleClass('expanded');
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Government ID formatting functions
            function formatGovtId(value, format) {
                // Remove all non-digits
                const digits = value.replace(/\D/g, '');
                
                switch(format) {
                    case 'sss':
                        if (digits.length <= 2) return digits;
                        if (digits.length <= 9) return digits.substring(0, 2) + '-' + digits.substring(2);
                        return digits.substring(0, 2) + '-' + digits.substring(2, 9) + '-' + digits.substring(9, 10);
                        
                    case 'tin':
                        if (digits.length <= 3) return digits;
                        if (digits.length <= 6) return digits.substring(0, 3) + '-' + digits.substring(3);
                        if (digits.length <= 9) return digits.substring(0, 3) + '-' + digits.substring(3, 6) + '-' + digits.substring(6);
                        return digits.substring(0, 3) + '-' + digits.substring(3, 6) + '-' + digits.substring(6, 9) + '-' + digits.substring(9, 12);
                        
                    case 'philhealth':
                        if (digits.length <= 2) return digits;
                        if (digits.length <= 11) return digits.substring(0, 2) + '-' + digits.substring(2);
                        return digits.substring(0, 2) + '-' + digits.substring(2, 11) + '-' + digits.substring(11, 12);
                        
                    case 'pagibig':
                        if (digits.length <= 4) return digits;
                        if (digits.length <= 8) return digits.substring(0, 4) + '-' + digits.substring(4);
                        return digits.substring(0, 4) + '-' + digits.substring(4, 8) + '-' + digits.substring(8, 12);
                        
                    default:
                        return value;
                }
            }
            
            // Handle government ID input formatting
            $(document).on('input', '.govt-id-input', function() {
                const format = $(this).data('format');
                const cursorPos = this.selectionStart;
                const oldValue = $(this).val();
                const newValue = formatGovtId(oldValue, format);
                
                if (oldValue !== newValue) {
                    $(this).val(newValue);
                    // Adjust cursor position
                    const newCursorPos = cursorPos + (newValue.length - oldValue.length);
                    this.setSelectionRange(newCursorPos, newCursorPos);
                }
                
                // Validate government ID on input
                validateGovtIdField(this);
            });
            
            // Handle employee ID input with duplicate checking
            let employeeIdTimeout;
            $(document).on('input', '#employeeId', function() {
                const employeeId = $(this).val().trim();
                
                // Clear previous timeout
                clearTimeout(employeeIdTimeout);
                
                // Debounce the API call to avoid too many requests
                employeeIdTimeout = setTimeout(function() {
                    checkEmployeeIdDuplicate(employeeId);
                }, 300);
            });
            
            // Handle edit employee ID input with duplicate checking
            let editEmployeeIdTimeout;
            $(document).on('input', '#editEmployeeId', function() {
                const employeeId = $(this).val().trim();
                const excludeUserId = $('input[name="user_id"]').val(); // Get current user ID
                
                // Clear previous timeout
                clearTimeout(editEmployeeIdTimeout);
                
                // Debounce the API call to avoid too many requests
                editEmployeeIdTimeout = setTimeout(function() {
                    checkEmployeeIdDuplicate(employeeId, excludeUserId, '#editEmployeeIdFeedback', '#editEmployeeId');
                }, 300);
            });
            
            // Philippine phone number formatting (0956 529 9470 format)
            $(document).on('input', '#phoneNumber', function() {
                let value = this.value.replace(/\D/g, '');
                
                if (value.length > 0) {
                    if (value.length > 4 && value.length <= 7) {
                        // Format: 0956 529
                        value = value.substring(0, 4) + ' ' + value.substring(4);
                    } else if (value.length > 7) {
                        // Format: 0956 529 9470
                        value = value.substring(0, 4) + ' ' + 
                               value.substring(4, 7) + ' ' + 
                               value.substring(7, 11);
                    }
                }
                
                this.value = value.trim();
                validatePhoneNumber(this);
            });
            
            // Email validation on input
            $(document).on('input blur', '#emailAddress', function() {
                validateEmail(this);
            });
            
            // Birth date validation
            $(document).on('change', '#birthDate', function() {
                validateAge(this);
            });
            
            // Password confirmation validation
            $(document).on('keyup blur', '#confirmPassword', function() {
                validatePasswordMatch();
            });
            
            // Username validation
            $(document).on('blur', '#username', function() {
                validateUsername(this);
            });

            // Validation Functions
            function validatePhoneNumber(input) {
                const $input = $(input);
                const value = $input.val().replace(/\D/g, '');
                const feedback = $input.next('.invalid-feedback');
                
                // Clear previous validation
                $input.removeClass('is-valid is-invalid');
                
                if (!value) {
                    // Required field validation will handle empty values
                    return true;
                }
                
                if (!/^09\d{9}$/.test(value)) {
                    $input.addClass('is-invalid');
                    feedback.text('Phone number must be in Philippine format: 09XX XXX XXXX (11 digits starting with 09)');
                    return false;
                } else {
                    $input.addClass('is-valid');
                    feedback.text('');
                    return true;
                }
            }
            
            function validateEmail(input) {
                const $input = $(input);
                const value = $input.val().trim().toLowerCase();
                const feedback = $input.next('.invalid-feedback');
                
                // Clear previous validation
                $input.removeClass('is-valid is-invalid');
                
                if (!value) {
                    // Required field validation will handle empty values
                    return true;
                }
                
                // Basic email regex
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!emailRegex.test(value)) {
                    $input.addClass('is-invalid');
                    feedback.text('Please enter a valid email address');
                    return false;
                }
                
                // Domain validation - allow common trusted domains
                const allowedDomains = [
                    'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 
                    'live.com', 'icloud.com', 'protonmail.com', 'aol.com',
                    'mail.com', 'yandex.com', 'zoho.com'
                ];
                
                const domain = value.split('@')[1];
                
                if (!allowedDomains.includes(domain)) {
                    $input.addClass('is-invalid');
                    feedback.text('Please use a valid email from trusted domains (gmail.com, yahoo.com, outlook.com, etc.)');
                    return false;
                }
                
                // Check for malformed addresses
                if (value.includes('..') || value.startsWith('.') || value.endsWith('.') || 
                    value.includes('@.') || value.includes('.@')) {
                    $input.addClass('is-invalid');
                    feedback.text('Invalid email format detected');
                    return false;
                }
                
                $input.addClass('is-valid');
                feedback.text('');
                return true;
            }
            
            function validateAge(input) {
                const $input = $(input);
                const value = $input.val();
                const feedback = $input.next('.invalid-feedback');
                
                // Clear previous validation
                $input.removeClass('is-valid is-invalid');
                
                if (!value) {
                    // Birth date is now required
                    $input.addClass('is-invalid');
                    feedback.text('Birth date is required');
                    return false;
                }
                
                const birthDate = new Date(value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                // Adjust age if birthday hasn't occurred this year
                const actualAge = (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) 
                                  ? age - 1 : age;
                
                if (actualAge < 18) {
                    $input.addClass('is-invalid');
                    feedback.text('Employee must be at least 18 years old');
                    return false;
                } else {
                    $input.addClass('is-valid');
                    feedback.text('');
                    return true;
                }
            }
            
            function validatePasswordMatch() {
                const password = $('#password').val();
                const confirmPassword = $('#confirmPassword').val();
                const $confirmInput = $('#confirmPassword');
                const feedback = $confirmInput.next('.invalid-feedback');
                
                // Clear previous validation
                $confirmInput.removeClass('is-valid is-invalid');
                
                if (!confirmPassword) {
                    return true; // Required field validation will handle this
                }
                
                if (password !== confirmPassword) {
                    $confirmInput.addClass('is-invalid');
                    feedback.text('Passwords do not match');
                    return false;
                } else {
                    $confirmInput.addClass('is-valid');
                    feedback.text('');
                    return true;
                }
            }
            
            function validateUsername(input) {
                const $input = $(input);
                const value = $input.val().trim();
                const feedback = $input.next('.invalid-feedback');
                
                // Clear previous validation
                $input.removeClass('is-valid is-invalid');
                
                if (!value) {
                    return true; // Required field validation will handle this
                }
                
                // Check username availability via AJAX
                $.ajax({
                    url: 'employee_management.php',
                    type: 'POST',
                    data: { action: 'check_username', username: value },
                    dataType: 'json',
                    success: function(response) {
                        if (response.available) {
                            $input.addClass('is-valid');
                            feedback.text('');
                        } else {
                            $input.addClass('is-invalid');
                            feedback.text('Username already exists');
                        }
                    }
                });
                
                return true;
            }
            
            function validateGovtIdField(input) {
                const $input = $(input);
                const format = $input.data('format');
                const value = $input.val();
                const feedback = $input.next('.invalid-feedback');
                
                // Clear previous validation
                $input.removeClass('is-valid is-invalid');
                
                if (!value) {
                    return true; // Optional fields
                }
                
                if (!validateGovtId(value, format)) {
                    $input.addClass('is-invalid');
                    let message = '';
                    switch(format) {
                        case 'sss':
                            message = 'SSS number must be in format ##-#######-# (10 digits)';
                            break;
                        case 'tin':
                            message = 'TIN number must be in format ###-###-###-### (9 or 12 digits)';
                            break;
                        case 'philhealth':
                            message = 'PhilHealth number must be in format ##-#########-# (12 digits)';
                            break;
                        case 'pagibig':
                            message = 'Pag-IBIG number must be in format ####-####-#### (12 digits)';
                            break;
                    }
                    feedback.text(message);
                    return false;
                } else {
                    // Mark as valid first, then check for duplicates via AJAX
                    $input.addClass('is-valid');
                    feedback.text('');
                    
                    // Check for duplicates via AJAX (non-blocking)
                    $.ajax({
                        url: 'employee_management.php',
                        type: 'POST',
                        data: { 
                            action: 'check_govt_id_duplicate',
                            id_type: format,
                            id_value: value.replace(/\D/g, '')
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.duplicate) {
                                $input.removeClass('is-valid').addClass('is-invalid');
                                feedback.text(`This ${format.toUpperCase()} number is already registered to another employee`);
                            }
                        },
                        error: function() {
                            // If AJAX fails, don't mark as invalid - just log the error
                            console.log('Failed to check duplicate for ' + format + ' number');
                        }
                    });
                    return true;
                }
            }
            
            // Validate government IDs
            function validateGovtId(value, format) {
                const digits = value.replace(/\D/g, '');
                
                switch(format) {
                    case 'sss':
                        return digits.length === 10;
                    case 'tin':
                        return digits.length === 9 || digits.length === 12;
                    case 'philhealth':
                        return digits.length === 12;
                    case 'pagibig':
                        return digits.length === 12;
                    default:
                        return true;
                }
            }
            

            // Password confirmation validation for edit form
            $(document).on('keyup blur', '#editConfirmPassword', function() {
                const password = $('#editPassword').val();
                const confirmPassword = $(this).val();
                
                if (password && confirmPassword) {
                    if (password !== confirmPassword) {
                        $(this).addClass('is-invalid');
                        if (!$(this).next('.invalid-feedback').length) {
                            $(this).after('<div class="invalid-feedback">Passwords do not match</div>');
                        }
                    } else {
                        $(this).removeClass('is-invalid').addClass('is-valid');
                        $(this).next('.invalid-feedback').remove();
                    }
                }
            });
            
            // View employee details - using delegated event binding for DataTables pagination
            $(document).on('click', '.view-details', function() {
                const userId = $(this).data('id');
                $('#employeeDetailsContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                $('#viewDetailsModal').modal('show');
                
                // Load employee details
                $.ajax({
                    url: 'employee_management.php',
                    type: 'GET',
                    data: { action: 'get_details', user_id: userId },
                    success: function(response) {
                        $('#employeeDetailsContent').html(response);
                    },
                    error: function() {
                        $('#employeeDetailsContent').html('<div class="alert alert-danger">Error loading employee details.</div>');
                    }
                });
            });

            // Edit employee - using delegated event binding for DataTables pagination
            $(document).on('click', '.edit-employee', function() {
                const userId = $(this).data('id');
                $('#editEmployeeContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                $('#editEmployeeModal').modal('show');
                
                // Load employee edit form
                $.ajax({
                    url: 'employee_management.php',
                    type: 'GET',
                    data: { action: 'get_edit_form', user_id: userId },
                    success: function(response) {
                        $('#editEmployeeContent').html(response);
                    },
                    error: function() {
                        $('#editEmployeeContent').html('<div class="alert alert-danger">Error loading employee edit form.</div>');
                    }
                });
            });

            // Archive employee - using delegated event binding for DataTables pagination
            $(document).on('click', '.archive-employee', function() {
                const userId = $(this).data('id');
                const userName = $(this).data('name');
                
                $('#archiveEmployeeId').val(userId);
                $('#archiveEmployeeName').text(userName);
                $('#archiveEmployeeModal').modal('show');
            });

            // Handle archive form submission
            $('#archiveEmployeeForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'employee_management.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#archiveEmployeeModal').modal('hide');
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message,
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while archiving the employee.',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });

            // Handle edit employee form submission
            $(document).on('submit', '#editEmployeeFormInner', function(e) {
                e.preventDefault();
                console.log('[EditEmployee] Submit intercepted');
                
                // Check if employee ID has validation errors
                if ($('#editEmployeeId').hasClass('is-invalid') || $('#editEmployeeIdFeedback').hasClass('text-danger')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Validation Error',
                        text: 'Please fix the employee ID error before submitting.',
                        confirmButtonColor: '#ffc107'
                    });
                    return;
                }
                
                var formData = new FormData(this);
                // Log all form entries
                const entries = {};
                formData.forEach((v,k)=>{ entries[k]=v; });
                console.log('[EditEmployee] FormData snapshot', entries);
                // Explicitly check required fields client-side mirroring backend
                const required = ['action','user_id','employee_id','first_name','last_name','phone_number','email','hire_date','username'];
                const missing = required.filter(f=>!formData.get(f) || String(formData.get(f)).trim()==='');
                if(missing.length){
                    console.warn('[EditEmployee] Missing required fields before AJAX', missing);
                } else {
                    console.log('[EditEmployee] All required fields present');
                }
                console.log('[EditEmployee] Sending AJAX request');
                $.ajax({
                    url: 'employee_management.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        console.log('[EditEmployee] AJAX success response', response);
                        if (response.success) {
                            hideBsModal('editEmployeeModal');
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                location.reload(); // Refresh to show updated data
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message,
                                confirmButtonColor: '#dc3545'
                            });
                            console.warn('[EditEmployee] Server validation failed', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[EditEmployee] AJAX error', {status, error, responseText: xhr.responseText});
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while updating the employee.',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });

            // Add Employee Modal
            $('#addEmployeeBtn').on('click', function() {
                dbg('Add Employee button clicked');
                resetAddEmployeeForm();
                showBsModal('addEmployeeModal');
                dbg('Modal shown, step reset to 1');
            });

            // Multi-step form variables
            let currentStep = 1;
            const totalSteps = 3;

            // Employee Type selection handling
            $('#employeeType').on('change', function() {
                const employeeType = $(this).val();
                dbg('Employee type selected:', employeeType);
                
                if (employeeType === 'new') {
                    // Show alert for new employee
                    $('#newEmployeeAlert').removeClass('d-none');
                    
                    // Set placeholder values for government IDs
                    $('#sssNumber').val('00-0000000-0');
                    $('#tinNumber').val('000-000-000-000');
                    $('#philhealthNumber').val('00-000000000-0');
                    $('#pagibigNumber').val('0000-0000-0000');
                    
                    // Make fields read-only for new employees (they can be updated later)
                    $('.govt-id-input').prop('readonly', true);
                    $('.govt-id-input').addClass('bg-light');
                    
                    dbg('Set placeholder values for new employee');
                } else if (employeeType === 'old') {
                    // Hide alert and make fields editable
                    $('#newEmployeeAlert').addClass('d-none');
                    
                    // Clear values and make fields editable
                    $('#sssNumber').val('');
                    $('#tinNumber').val('');
                    $('#philhealthNumber').val('');
                    $('#pagibigNumber').val('');
                    
                    $('.govt-id-input').prop('readonly', false);
                    $('.govt-id-input').removeClass('bg-light');
                    
                    dbg('Cleared values for existing employee');
                } else {
                    // No type selected - hide alert and clear fields
                    $('#newEmployeeAlert').addClass('d-none');
                    $('#sssNumber').val('');
                    $('#tinNumber').val('');
                    $('#philhealthNumber').val('');
                    $('#pagibigNumber').val('');
                    $('.govt-id-input').prop('readonly', false);
                    $('.govt-id-input').removeClass('bg-light');
                }
            });

            // Role selection
            $('.role-card').on('click', function() {
                dbg('Role card clicked');
                $('.role-card').removeClass('border-success');
                $(this).addClass('border-success');
                
                const roleId = $(this).data('role');
                const roleName = $(this).data('role-name');
                
                $('#selectedRole').val(roleId);
                $('#selectedRoleName').val(roleName);
                dbg('Selected role', {roleId, roleName});
                
                // Show/hide location section for guards
                if (roleId == 5) {
                    dbg('Guard role selected; showing location section');
                    $('#locationSection').show();
                    $('#guardLocation').prop('required', true);
                    loadGuardLocations();
                } else {
                    dbg('Non-guard role selected; hiding location section');
                    $('#locationSection').hide();
                    $('#guardLocation').prop('required', false);
                }
            });

            // Next button
            $('#nextBtn').on('click', function() {
                dbg('Next button clicked from step', currentStep);
                if (validateCurrentStep()) {
                    dbg('Current step valid');
                    if (currentStep < totalSteps) {
                        currentStep++;
                        dbg('Advancing to step', currentStep);
                        showStep(currentStep);
                    }
                } else {
                    dbg('Current step invalid, staying on step', currentStep);
                }
            });

            // Previous button
            $('#prevBtn').on('click', function() {
                dbg('Previous button clicked from step', currentStep);
                if (currentStep > 1) {
                    currentStep--;
                    dbg('Moving back to step', currentStep);
                    showStep(currentStep);
                }
            });

            // Form submission
            $('#addEmployeeForm').on('submit', function(e) {
                e.preventDefault();
                dbg('Submit clicked at step', currentStep);
                
                if (!validateCurrentStep()) {
                    dbg('Submission blocked – current step invalid');
                    return;
                }
                dbg('All steps validated; preparing FormData');
                
                const formData = new FormData(this);
                // Log key fields (excluding sensitive password)
                dbg('FormData snapshot', {
                    role_id: formData.get('role_id'),
                    employee_id: formData.get('employee_id'),
                    first_name: formData.get('first_name'),
                    middle_name: formData.get('middle_name'),
                    last_name: formData.get('last_name'),
                    phone_number: formData.get('phone_number'),
                    email: formData.get('email'),
                    birth_date: formData.get('birth_date'),
                    hire_date: formData.get('hire_date'),
                    guard_location: formData.get('guard_location'),
                    sss_number: formData.get('sss_number'),
                    tin_number: formData.get('tin_number'),
                    philhealth_number: formData.get('philhealth_number'),
                    pagibig_number: formData.get('pagibig_number')
                });
                
                $.ajax({
                    url: 'employee_management.php',
                    type: 'POST',
                    beforeSend: function(){ dbg('AJAX add_employee sending'); },
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        dbg('AJAX success response', response);
                        if (response.success) {
                            hideBsModal('addEmployeeModal');
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message,
                                confirmButtonColor: '#dc3545'
                            });
                            dbg('Server reported failure', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        dbg('AJAX network/error', {status, error, responseText: xhr.responseText});
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while creating the employee.',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            });

            // Helper functions
            function showStep(step) {
                dbg('Showing step', step);
                $('.step-content').addClass('d-none');
                $(`#step${step}`).removeClass('d-none');
                
                // Update progress bar
                const progress = (step / totalSteps) * 100;
                $('#progressBar').css('width', progress + '%');
                
                // Update step labels
                for (let i = 1; i <= totalSteps; i++) {
                    if (i < step) {
                        $(`#step${i}Label`).removeClass('text-muted text-success').addClass('text-success');
                    } else if (i === step) {
                        $(`#step${i}Label`).removeClass('text-muted text-success').addClass('text-success').html(`<strong>Step ${i}: ${getStepTitle(i)}</strong>`);
                    } else {
                        $(`#step${i}Label`).removeClass('text-success').addClass('text-muted').html(`Step ${i}: ${getStepTitle(i)}`);
                    }
                }
                
                // Show/hide navigation buttons
                $('#prevBtn').toggleClass('d-none', step === 1);
                $('#nextBtn').toggleClass('d-none', step === totalSteps);
                $('#submitBtn').toggleClass('d-none', step !== totalSteps);
                dbg('Step UI updated');
            }

            function getStepTitle(step) {
                const titles = {
                    1: 'Role Selection',
                    2: 'Personal Information',
                    3: 'Account Details'
                };
                return titles[step];
            }

            function validateCurrentStep() {
                dbg('Validating current step', currentStep);
                let isValid = true;
                
                if (currentStep === 1) {
                    dbg('Step1 validation start');
                    if (!$('#selectedRole').val()) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Selection Required',
                            text: 'Please select an employee role.',
                            confirmButtonColor: '#ffc107'
                        });
                        return false;
                    }
                    dbg('Step1 validation ok');
                } else if (currentStep === 2) {
                    dbg('Step2 validation start');
                    // Validate all required fields
                    const requiredFields = [
                        { id: 'firstName', name: 'First Name' },
                        { id: 'lastName', name: 'Last Name' },
                        { id: 'sex', name: 'Sex' },
                        { id: 'civilStatus', name: 'Marital Status' },
                        { id: 'phoneNumber', name: 'Phone Number' },
                        { id: 'emailAddress', name: 'Email Address' },
                        { id: 'birthDate', name: 'Birth Date' },
                        { id: 'hireDate', name: 'Hire Date' }
                    ];
                    
                    if ($('#selectedRole').val() == 5) {
                        dbg('Guard role requires location field');
                        requiredFields.push({ id: 'guardLocation', name: 'Guard Location' });
                    }
                    
                    // Check required fields
                    for (let field of requiredFields) {
                        const $input = $(`#${field.id}`);
                        const value = $input.val().trim();
                        dbg('Checking required field', field.id, 'value:', value);
                        
                        if (!value) {
                            $input.addClass('is-invalid');
                            $input.next('.invalid-feedback').text(`${field.name} is required`);
                            isValid = false;
                        } else {
                            $input.removeClass('is-invalid');
                        }
                    }
                    
                    // Validate specific fields
                    if ($('#phoneNumber').val()) {
                        if (!validatePhoneNumber($('#phoneNumber')[0])) {
                            dbg('Phone number invalid');
                            isValid = false;
                        }
                    }
                    
                    if ($('#emailAddress').val()) {
                        if (!validateEmail($('#emailAddress')[0])) {
                            dbg('Email invalid');
                            isValid = false;
                        }
                    }
                    
                    if ($('#birthDate').val()) {
                        if (!validateAge($('#birthDate')[0])) {
                            dbg('Birth date invalid (<18)');
                            isValid = false;
                        }
                    }
                    
                    if (!isValid) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validation Error',
                            text: 'Please fix the highlighted errors before proceeding.',
                            confirmButtonColor: '#ffc107'
                        });
                        return false;
                    }
                    
                    dbg('Step2 validation ok');
                } else if (currentStep === 3) {
                    dbg('Step3 validation start');
                    const requiredFields = [
                        { id: 'employeeType', name: 'Employee Type' },
                        { id: 'employeeId', name: 'Employee ID' },
                        { id: 'sssNumber', name: 'SSS Number' },
                        { id: 'tinNumber', name: 'TIN Number' },
                        { id: 'philhealthNumber', name: 'PhilHealth Number' },
                        { id: 'pagibigNumber', name: 'Pag-IBIG Number' }
                    ];
                    
                    // Check required fields including employee ID
                    for (let field of requiredFields) {
                        const $input = $(`#${field.id}`);
                        const value = $input.val().trim();
                        dbg('Checking required field', field.id, 'value length:', value.length);
                        
                        if (!value) {
                            $input.addClass('is-invalid');
                            if (field.id === 'employeeId') {
                                $('#employeeIdFeedback').removeClass('text-success').addClass('text-danger').text('❌ Employee ID is required');
                            } else {
                                $input.next('.invalid-feedback').text(`${field.name} is required`);
                            }
                            isValid = false;
                        } else {
                            if (field.id !== 'employeeId') {
                                $input.removeClass('is-invalid');
                            }
                        }
                    }
                    
                    // Check if employee ID has duplicate error
                    if ($('#employeeId').hasClass('is-invalid') || $('#employeeIdFeedback').hasClass('text-danger')) {
                        dbg('Employee ID has validation errors');
                        isValid = false;
                    }
                    
                    // Validate government IDs format and duplicates
                    $('.govt-id-input').each(function() {
                        if ($(this).val()) {
                            dbg('Validating govt id field format/duplicate', this.id, $(this).val());
                            validateGovtIdField(this);
                        }
                    });
                    
                    // Check for any validation errors (but ignore AJAX-pending validations)
                    let hasFormatErrors = false;
                    $('.govt-id-input').each(function() {
                        const $input = $(this);
                        const value = $input.val().trim();
                        const format = $input.data('format');
                        dbg('Rechecking format only', this.id, value);
                        
                        if (value && !validateGovtId(value, format)) {
                            hasFormatErrors = true;
                        }
                    });
                    
                    if (hasFormatErrors) {
                        dbg('Format errors detected');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validation Error',
                            text: 'Please fix the highlighted errors before submitting.',
                            confirmButtonColor: '#ffc107'
                        });
                        return false;
                    }
                    dbg('Step3 validation ok');
                }
                
                dbg('validateCurrentStep result', isValid);
                return true;
            }

            function resetAddEmployeeForm() {
                dbg('Resetting add employee form');
                currentStep = 1;
                $('#addEmployeeForm')[0].reset();
                $('.role-card').removeClass('border-success');
                $('#selectedRole').val('');
                $('#selectedRoleName').val('');
                $('#employeeId').val('');
                $('#employeeIdFeedback').removeClass('text-success text-danger').text('');
                $('#employeeId').removeClass('is-invalid');
                $('#locationSection').hide();
                $('#guardLocation').prop('required', false);
                
                // Reset employee type and government ID fields
                $('#employeeType').val('');
                $('#newEmployeeAlert').addClass('d-none');
                $('#sssNumber').val('');
                $('#tinNumber').val('');
                $('#philhealthNumber').val('');
                $('#pagibigNumber').val('');
                $('.govt-id-input').prop('readonly', false);
                $('.govt-id-input').removeClass('bg-light');
                
                showStep(1);
            }

            // Employee ID duplicate checking
            function checkEmployeeIdDuplicate(employeeId, excludeUserId = null, feedbackSelector = '#employeeIdFeedback', inputSelector = '#employeeId') {
                if (!employeeId.trim()) {
                    $(feedbackSelector).removeClass('text-success text-danger').text('');
                    return;
                }
                
                const data = { 
                    action: 'check_employee_id_duplicate', 
                    employee_id: employeeId 
                };
                
                if (excludeUserId) {
                    data.exclude_user_id = excludeUserId;
                }
                
                $.ajax({
                    url: 'employee_management.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        const feedback = $(feedbackSelector);
                        if (response.duplicate) {
                            feedback.removeClass('text-success').addClass('text-danger').text('❌ ' + response.message);
                            $(inputSelector).addClass('is-invalid');
                        } else {
                            feedback.removeClass('text-danger').addClass('text-success').text('✅ ' + response.message);
                            $(inputSelector).removeClass('is-invalid');
                        }
                    },
                    error: function() {
                        $(feedbackSelector).removeClass('text-success').addClass('text-danger').text('❌ Error checking employee ID');
                        $(inputSelector).addClass('is-invalid');
                    }
                });
            }

            // Employee ID generation (kept for backwards compatibility but not used)
            function generateEmployeeId(roleId) {
        dbg('Generating employee ID for role', roleId);
                $.ajax({
                    url: 'employee_management.php',
                    type: 'POST',
                    data: { action: 'generate_employee_id', role_id: roleId },
                    dataType: 'json',
                    success: function(response) {
            dbg('Employee ID response', response);
                        if (response.success) {
                            $('#employeeId').val(response.employee_id);
                            // Trigger duplicate check for generated ID
                            checkEmployeeIdDuplicate(response.employee_id);
                        }
                    }
                });
            }

            function loadGuardLocations() {
        dbg('Loading guard locations');
                $.ajax({
                    url: 'employee_management.php',
                    type: 'GET',
                    data: { action: 'get_locations' },
                    dataType: 'json',
                    success: function(response) {
            dbg('Guard locations response', response);
                        if (response.success) {
                            let options = '<option value="">Select Location</option>';
                            response.locations.forEach(function(location) {
                                options += `<option value="${location.location_name}">${location.location_name}</option>`;
                            });
                            $('#guardLocation').html(options);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
