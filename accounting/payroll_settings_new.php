<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);

// Database connection
require_once '../db_connection.php';

// Handle AJAX requests for saving settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_setting') {
        $user_id = intval($_POST['user_id']);
        $year = intval($_POST['year']);
        $month = intval($_POST['month']);
        $setting_type = $_POST['setting_type'];
        $value = floatval($_POST['value']);
        
        // Validate inputs
        if (!$user_id || !$year || !$month || !in_array($setting_type, ['sss_contribution', 'philhealth_contribution', 'pagibig_contribution', 'tax_withholding'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        // Validate value range (0 to 9999.99)
        if ($value < 0 || $value > 9999.99) {
            echo json_encode(['success' => false, 'message' => 'Value must be between 0 and 9999.99']);
            exit;
        }
        
        try {
            // Insert or update the setting using ON DUPLICATE KEY UPDATE
            $sql = "INSERT INTO payroll_monthly_settings (user_id, year, month, {$setting_type}, updated_by) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    {$setting_type} = VALUES({$setting_type}), 
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $year, $month, $value, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Setting saved successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Get current Accounting user's name
$accountingStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 4 AND status = 'Active' AND User_ID = ?");
$accountingStmt->execute([$_SESSION['user_id']]);
$accountingData = $accountingStmt->fetch(PDO::FETCH_ASSOC);
$accountingName = $accountingData ? $accountingData['First_Name'] . ' ' . $accountingData['Last_Name'] : "Accounting";

// Get accounting's profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $accountingProfile = $profileData['Profile_Pic'];
} else {
    $accountingProfile = '../images/default_profile.png';
}

// Get filter parameters - month, year, and department
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$department = isset($_GET['department']) ? $_GET['department'] : 'all';

// Get departments for filter dropdown
$deptQuery = "SELECT Role_ID, Role_Name FROM roles WHERE Role_ID IN (3, 4, 5) ORDER BY Role_Name";
$deptStmt = $conn->prepare($deptQuery);
$deptStmt->execute();
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Build employee query with department filter
$whereClause = "u.archived_at IS NULL AND u.Role_ID IN (3, 4, 5) AND u.status = 'Active'";
$params = [];

if ($department !== 'all') {
    $whereClause .= " AND u.Role_ID = ?";
    $params[] = $department;
}

// Get all employees (Accounting, HR, Guards only - excluding Admin and Super Admin)
$query = "
    SELECT 
        u.User_ID,
        u.employee_id,
        u.First_Name,
        u.middle_name,
        u.Last_Name,
        u.Email,
        u.phone_number,
        u.hired_date,
        u.Role_ID,
        r.Role_Name,
        gl.location_name,
        ps.sss_contribution,
        ps.philhealth_contribution,
        ps.pagibig_contribution,
        ps.tax_withholding
    FROM users u
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    LEFT JOIN payroll_monthly_settings ps ON u.User_ID = ps.user_id AND ps.year = ? AND ps.month = ?
    WHERE {$whereClause}
    ORDER BY r.Role_Name, u.Last_Name, u.First_Name";

array_unshift($params, $year, $month);
$stmt = $conn->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Settings - Green Meadows Security Agency</title>
    
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
    <link rel="stylesheet" href="css/payroll_settings.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div>GREEN MEADOWS</div>
                <div>SECURITY AGENCY</div>
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
                <a href="payroll_settings.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll Settings">
                    <span class="material-icons">settings</span>
                    <span>Payroll Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rate_locations.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Rate Locations">
                    <span class="material-icons">attach_money</span>
                    <span>Rate Locations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="masterlist.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">list</span>
                    <span>Masterlist</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calendar.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Calendar">
                    <span class="material-icons">date_range</span>
                    <span>Calendar</span>
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
                <img src="<?php echo $accountingProfile; ?>" alt="Profile Picture">
                <span><?php echo $accountingName; ?></span>
            </div>
        </div>

        <!-- Page Title -->
        <div class="page-title">
            <span class="material-icons">settings</span>
            Payroll Settings - Monthly Contributions
        </div>

        <!-- Filter Section -->
        <div class="container-fluid mb-3">
            <div class="filter-container">
                <form id="filterForm" method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-select" id="monthSelect">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($month == $m) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select" id="yearSelect">
                                <?php for ($y = 2020; $y <= 2030; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select" id="departmentSelect">
                                <option value="all" <?php echo ($department == 'all') ? 'selected' : ''; ?>>All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['Role_ID']; ?>" <?php echo ($department == $dept['Role_ID']) ? 'selected' : ''; ?>>
                                        <?php echo $dept['Role_Name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn filter-btn">
                                <span class="material-icons">filter_list</span>
                                Apply Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Settings Information Banner -->
        <div class="alert alert-info" role="alert">
            <span class="material-icons me-2">info</span>
            <strong>Payroll Settings for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></strong>
            <br>Set monthly contributions for SSS, PhilHealth, Pag-IBIG, and Tax Withholding. Changes will be automatically applied to the payroll calculations.
        </div>

        <!-- Payroll Settings Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <span class="material-icons align-middle me-2">people</span>
                    Employee Monthly Contributions
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="settingsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Location</th>
                                <th>SSS</th>
                                <th>PhilHealth</th>
                                <th>Pag-IBIG</th>
                                <th>Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <?php
                                $fullName = trim($employee['First_Name'] . ' ' . 
                                    (!empty($employee['middle_name']) ? $employee['middle_name'][0] . '. ' : '') . 
                                    $employee['Last_Name']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($fullName); ?></td>
                                    <td><?php echo htmlspecialchars($employee['Role_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['location_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <input type="number" 
                                               class="form-control contribution-input" 
                                               data-user-id="<?php echo $employee['User_ID']; ?>"
                                               data-setting-type="sss_contribution"
                                               value="<?php echo number_format($employee['sss_contribution'] ?? 0, 2, '.', ''); ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="9999.99">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control contribution-input" 
                                               data-user-id="<?php echo $employee['User_ID']; ?>"
                                               data-setting-type="philhealth_contribution"
                                               value="<?php echo number_format($employee['philhealth_contribution'] ?? 0, 2, '.', ''); ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="9999.99">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control contribution-input" 
                                               data-user-id="<?php echo $employee['User_ID']; ?>"
                                               data-setting-type="pagibig_contribution"
                                               value="<?php echo number_format($employee['pagibig_contribution'] ?? 0, 2, '.', ''); ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="9999.99">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control contribution-input" 
                                               data-user-id="<?php echo $employee['User_ID']; ?>"
                                               data-setting-type="tax_withholding"
                                               value="<?php echo number_format($employee['tax_withholding'] ?? 0, 2, '.', ''); ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="9999.99">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="<?php echo $accountingProfile; ?>" alt="Profile Picture" class="profile-pic-large mb-3">
                        <h5><?php echo $accountingName; ?></h5>
                        <p class="text-muted">Accounting Staff</p>
                        <a href="update_profile.php" class="btn btn-primary">Update Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="accounting_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">DTR</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <a href="payroll_settings.php" class="mobile-nav-item active">
                <span class="material-icons">settings</span>
                <span class="mobile-nav-text">Settings</span>
            </a>
            <a href="rate_locations.php" class="mobile-nav-item">
                <span class="material-icons">attach_money</span>
                <span class="mobile-nav-text">Rates</span>
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <!-- Main JS -->
    <script src="js/accounting_dashboard.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#settingsTable').DataTable({
                "pageLength": 25,
                "order": [[2, "asc"], [1, "asc"]], // Sort by department, then name
                "columnDefs": [
                    {
                        "targets": [4, 5, 6, 7], // contribution columns
                        "orderable": false,
                        "searchable": false,
                        "width": "120px"
                    }
                ]
            });

            // Handle contribution input changes
            $('.contribution-input').on('change blur', function() {
                const input = $(this);
                const userId = input.data('user-id');
                const settingType = input.data('setting-type');
                const value = parseFloat(input.val()) || 0;
                const year = <?php echo $year; ?>;
                const month = <?php echo $month; ?>;

                // Show loading state
                input.addClass('saving');
                input.prop('disabled', true);

                // Save the setting
                $.ajax({
                    url: 'payroll_settings.php',
                    method: 'POST',
                    data: {
                        action: 'save_setting',
                        user_id: userId,
                        year: year,
                        month: month,
                        setting_type: settingType,
                        value: value
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            input.removeClass('saving').addClass('saved');
                            setTimeout(() => input.removeClass('saved'), 2000);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to save setting'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Network error occurred while saving'
                        });
                    },
                    complete: function() {
                        input.prop('disabled', false);
                    }
                });
            });

            // Date and time update
            function updateDateTime() {
                const now = new Date();
                const dateOptions = { 
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
                
                document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
                document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);
        });
    </script>

    <style>
        .contribution-input {
            width: 100px;
            font-size: 13px;
            text-align: right;
        }

        .contribution-input.saving {
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }

        .contribution-input.saved {
            background-color: #d1edff;
            border-color: #74b9ff;
        }

        .profile-pic-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        .alert-info {
            border-left: 4px solid #17a2b8;
        }
    </style>
</body>
</html>
