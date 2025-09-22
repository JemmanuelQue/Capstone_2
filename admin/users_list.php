<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);
require_once '../db_connection.php';
require '../vendor/autoload.php'; // Make sure you have PHPMailer installed via composer

// Get super admin user's name
$adminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 2 AND status = 'Active' AND User_ID = ?");
$adminStmt->execute([$_SESSION['user_id']]);
$adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $adminData ? $adminData['First_Name'] . ' ' . $adminData['Last_Name'] : "Admin";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $adminProfile = $profileData['Profile_Pic'];
} else {
    $adminProfile = '../images/default_profile.png';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users List - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/users_list.css">

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
                <a href="admin_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Request">
                    <span class="material-icons">event_note</span>
                    <span>Leave Request</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="recruitment.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Recruitment">
                    <span class="material-icons">person_search</span>
                    <span>Recruitment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">assessment</span>
                    <span>Performance Evaluation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">people</span>
                    <span>Masterlist</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="daily_time_record.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
                    <span class="material-icons">schedule</span>
                    <span>Daily Time Record</span>
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
                    <span><?php echo $adminName; ?></span>
                    <img src="<?php echo $adminProfile; ?>" alt="User Profile">
                </a>
        </div>
        
        <!-- Content -->
        <div class="content-container">
            <div class="container-fluid px-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h2 class="mt-3" style="margin-bottom: 25px;color: #2a7d4f;font-weight: 600;font-size: 28px;padding-bottom: 10px;">Masterlist</h2>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="material-icons align-middle">add</i> Add New User
                        </button>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-2 g-md-3 align-items-stretch">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search name or employee ID...">
                                    <button class="btn btn-outline-success" type="button" id="searchButton">
                                        <i class="material-icons align-middle">search</i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="roleFilter">
                                    <option value="">All Roles</option>
                                    <option value="1">Super Admin</option>
                                    <option value="2">Admin</option>
                                    <option value="3">Human Resource</option>
                                    <option value="4">Accounting</option>
                                    <option value="5">Guard</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users by Role -->
                <div class="accordion" id="usersAccordion">
                    <!-- Super Admin Section -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="superAdminHeading">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#superAdminCollapse" aria-expanded="true" aria-controls="superAdminCollapse">
                                Super Admin Users
                            </button>
                        </h2>
                        <div id="superAdminCollapse" class="accordion-collapse collapse show" aria-labelledby="superAdminHeading">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover users-table">
                                        <colgroup class="users-colgroup">
                                            <col style="width:12%">
                                            <col style="width:34%">
                                            <col style="width:24%">
                                            <col style="width:10%">
                                            <col style="width:20%">
                                        </colgroup>
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Full Name</th>
                                                <th>Employee ID</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="superAdminTable">
                                            <?php
                                            // Get admin users
                                            $adminStmt = $conn->prepare("SELECT * FROM users WHERE Role_ID = 1 AND archived_at IS NULL ORDER BY Last_Name");
                                            $adminStmt->execute();
                                            $adminUsers = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($adminUsers as $user) {
                                                $profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) 
                                                    ? $user['Profile_Pic'] 
                                                    : '../images/default_profile.png';
                                                
                                                $statusClass = ($user['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                                                echo '<tr class="user-row" data-role="'.$user['Role_ID'].'" data-user-id="'.$user['User_ID'].'" data-emp-id="'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'" data-status="'.htmlspecialchars(strtolower($user['status'] ?? ''), ENT_QUOTES).'">';
                                                echo '<td><img src="'.$profilePic.'" class="rounded-circle" width="40" height="40"></td>';
                                                echo '<td>'.$user['First_Name'].' '.$user['Last_Name'].'</td>';
                                                echo '<td>'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'</td>';
                                                // removed Email and Phone Number columns
                                                echo '<td><span class="badge '.$statusClass.'">'.$user['status'].'</span></td>';
                                                echo '<td>';
                                                echo '<button class="btn btn-sm btn-primary view-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">visibility</i></button> ';
                                                echo '<button class="btn btn-sm btn-info edit-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">edit</i></button> ';
                                                echo '<button class="btn btn-sm btn-warning archive-user-btn" data-user-id="'.$user['User_ID'].'" data-name="'.htmlspecialchars($user['First_Name'].' '.$user['Last_Name'], ENT_QUOTES).'"><i class="material-icons">archive</i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                            
                                            if (count($adminUsers) === 0) {
                                                echo '<tr><td colspan="5" class="text-center">No super admin users found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Section -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="adminHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminCollapse" aria-expanded="false" aria-controls="adminCollapse">
                                Admin Users
                            </button>
                        </h2>
                        <div id="adminCollapse" class="accordion-collapse collapse" aria-labelledby="adminHeading">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover users-table">
                                        <colgroup class="users-colgroup">
                                            <col style="width:12%">
                                            <col style="width:34%">
                                            <col style="width:24%">
                                            <col style="width:10%">
                                            <col style="width:20%">
                                        </colgroup>
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Full Name</th>
                                                <th>Employee ID</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="adminTable">
                                            <?php
                                            // Get admin users
                                            $adminStmt = $conn->prepare("SELECT * FROM users WHERE Role_ID = 2 AND archived_at IS NULL ORDER BY Last_Name");
                                            $adminStmt->execute();
                                            $adminUsers = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($adminUsers as $user) {
                                                $profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) 
                                                    ? $user['Profile_Pic'] 
                                                    : '../images/default_profile.png';
                                                
                                                $statusClass = ($user['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                                                echo '<tr class="user-row" data-role="'.$user['Role_ID'].'" data-user-id="'.$user['User_ID'].'" data-emp-id="'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'" data-status="'.htmlspecialchars(strtolower($user['status'] ?? ''), ENT_QUOTES).'">';
                                                echo '<td><img src="'.$profilePic.'" class="rounded-circle" width="40" height="40"></td>';
                                                echo '<td>'.$user['First_Name'].' '.$user['Last_Name'].'</td>';
                                                echo '<td>'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'</td>';
                                                // removed Email and Phone Number columns
                                                echo '<td><span class="badge '.$statusClass.'">'.$user['status'].'</span></td>';
                                                echo '<td>';
                                                echo '<button class="btn btn-sm btn-primary view-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">visibility</i></button> ';
                                                echo '<button class="btn btn-sm btn-info edit-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">edit</i></button> ';
                                                echo '<button class="btn btn-sm btn-warning archive-user-btn" data-user-id="'.$user['User_ID'].'" data-name="'.htmlspecialchars($user['First_Name'].' '.$user['Last_Name'], ENT_QUOTES).'"><i class="material-icons">archive</i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                            
                                            if (count($adminUsers) === 0) {
                                                echo '<tr><td colspan="5" class="text-center">No admin users found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Human Resource Section -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="hrHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hrCollapse" aria-expanded="false" aria-controls="hrCollapse">
                                Human Resource Users
                            </button>
                        </h2>
                        <div id="hrCollapse" class="accordion-collapse collapse" aria-labelledby="hrHeading">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover users-table">
                                        <colgroup class="users-colgroup">
                                            <col style="width:12%">
                                            <col style="width:34%">
                                            <col style="width:24%">
                                            <col style="width:10%">
                                            <col style="width:20%">
                                        </colgroup>
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Full Name</th>
                                                <th>Employee ID</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="hrTable">
                                            <?php
                                            // Get HR users
                                            $hrStmt = $conn->prepare("SELECT * FROM users WHERE Role_ID = 3 AND archived_at IS NULL ORDER BY Last_Name");
                                            $hrStmt->execute();
                                            $hrUsers = $hrStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($hrUsers as $user) {
                                                $profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) 
                                                    ? $user['Profile_Pic'] 
                                                    : '../images/default_profile.png';
                                                
                                                $statusClass = ($user['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                                                echo '<tr class="user-row" data-role="'.$user['Role_ID'].'" data-user-id="'.$user['User_ID'].'" data-emp-id="'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'" data-status="'.htmlspecialchars(strtolower($user['status'] ?? ''), ENT_QUOTES).'">';
                                                echo '<td><img src="'.$profilePic.'" class="rounded-circle" width="40" height="40"></td>';
                                                echo '<td>'.$user['First_Name'].' '.$user['Last_Name'].'</td>';
                                                echo '<td>'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'</td>';
                                                // removed Email and Phone Number columns
                                                echo '<td><span class="badge '.$statusClass.'">'.$user['status'].'</span></td>';
                                                echo '<td>';
                                                echo '<button class="btn btn-sm btn-primary view-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">visibility</i></button> ';
                                                echo '<button class="btn btn-sm btn-info edit-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">edit</i></button> ';
                                                echo '<button class="btn btn-sm btn-warning archive-user-btn" data-user-id="'.$user['User_ID'].'" data-name="'.htmlspecialchars($user['First_Name'].' '.$user['Last_Name'], ENT_QUOTES).'"><i class="material-icons">archive</i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                            
                                            if (count($hrUsers) === 0) {
                                                echo '<tr><td colspan="5" class="text-center">No HR users found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Accounting Section -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="accountingHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accountingCollapse" aria-expanded="false" aria-controls="accountingCollapse">
                                Accounting Users
                            </button>
                        </h2>
                        <div id="accountingCollapse" class="accordion-collapse collapse" aria-labelledby="accountingHeading">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover users-table">
                                        <colgroup class="users-colgroup">
                                            <col style="width:12%">
                                            <col style="width:34%">
                                            <col style="width:24%">
                                            <col style="width:10%">
                                            <col style="width:20%">
                                        </colgroup>
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Full Name</th>
                                                <th>Employee ID</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="accountingTable">
                                            <?php
                                            // Get accounting users
                                            $acctStmt = $conn->prepare("SELECT * FROM users WHERE Role_ID = 4 AND archived_at IS NULL ORDER BY Last_Name");
                                            $acctStmt->execute();
                                            $acctUsers = $acctStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($acctUsers as $user) {
                                                $profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) 
                                                    ? $user['Profile_Pic'] 
                                                    : '../images/default_profile.png';
                                                
                                                $statusClass = ($user['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                                                echo '<tr class="user-row" data-role="'.$user['Role_ID'].'" data-user-id="'.$user['User_ID'].'" data-emp-id="'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'" data-status="'.htmlspecialchars(strtolower($user['status'] ?? ''), ENT_QUOTES).'">';
                                                echo '<td><img src="'.$profilePic.'" class="rounded-circle" width="40" height="40"></td>';
                                                echo '<td>'.$user['First_Name'].' '.$user['Last_Name'].'</td>';
                                                echo '<td>'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'</td>';
                                                // removed Email and Phone Number columns
                                                echo '<td><span class="badge '.$statusClass.'">'.$user['status'].'</span></td>';
                                                echo '<td>';
                                                echo '<button class="btn btn-sm btn-primary view-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">visibility</i></button> ';
                                                echo '<button class="btn btn-sm btn-info edit-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">edit</i></button> ';
                                                echo '<button class="btn btn-sm btn-warning archive-user-btn" data-user-id="'.$user['User_ID'].'" data-name="'.htmlspecialchars($user['First_Name'].' '.$user['Last_Name'], ENT_QUOTES).'"><i class="material-icons">archive</i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                            
                                            if (count($acctUsers) === 0) {
                                                echo '<tr><td colspan="5" class="text-center">No accounting users found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Guards Section -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="guardsHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guardsCollapse" aria-expanded="false" aria-controls="guardsCollapse">
                                Guard Users
                            </button>
                        </h2>
                        <div id="guardsCollapse" class="accordion-collapse collapse" aria-labelledby="guardsHeading">
                            <div class="accordion-body p-0">
                                <div class="alert alert-info m-3">
                                    <i class="material-icons align-middle">info</i> Guard accounts can only be created by HR department.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover users-table">
                                        <colgroup class="users-colgroup">
                                            <col style="width:12%">
                                            <col style="width:34%">
                                            <col style="width:24%">
                                            <col style="width:10%">
                                            <col style="width:20%">
                                        </colgroup>
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Full Name</th>
                                                <th>Employee ID</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="guardsTable">
                                            <?php
                                            // Get guards
                                            $guardsStmt = $conn->prepare("SELECT * FROM users WHERE Role_ID = 5 AND archived_at IS NULL ORDER BY Last_Name");
                                            $guardsStmt->execute();
                                            $guardsUsers = $guardsStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($guardsUsers as $user) {
                                                $profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) 
                                                    ? $user['Profile_Pic'] 
                                                    : '../images/default_profile.png';
                                                
                                                $statusClass = ($user['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                                                echo '<tr class="user-row" data-role="'.$user['Role_ID'].'" data-user-id="'.$user['User_ID'].'" data-emp-id="'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'" data-status="'.htmlspecialchars(strtolower($user['status'] ?? ''), ENT_QUOTES).'">';
                                                echo '<td><img src="'.$profilePic.'" class="rounded-circle" width="40" height="40"></td>';
                                                echo '<td>'.$user['First_Name'].' '.$user['Last_Name'].'</td>';
                                                echo '<td>'.htmlspecialchars($user['employee_id'] ?? '', ENT_QUOTES).'</td>';
                                                echo '<td><span class="badge '.$statusClass.'">'.$user['status'].'</span></td>';
                                                echo '<td>';
                                                echo '<button class="btn btn-sm btn-primary view-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">visibility</i></button> ';
                                                echo '<button class="btn btn-sm btn-info edit-user-btn me-1" data-user-id="'.$user['User_ID'].'"><i class="material-icons">edit</i></button> ';
                                                echo '<button class="btn btn-sm btn-warning archive-user-btn" data-user-id="'.$user['User_ID'].'" data-name="'.htmlspecialchars($user['First_Name'].' '.$user['Last_Name'], ENT_QUOTES).'"><i class="material-icons">archive</i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                            
                                            if (count($guardsUsers) === 0) {
                                                echo '<tr><td colspan="5" class="text-center">No guard users found</td></tr>';
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
        </div>

        <!-- Add User Modal -->
        <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Progress bar -->
                        <div class="progress mb-4" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 20%; transition: width 0.3s ease;"></div>
                        </div>

                        <form id="addUserForm" method="POST" action="users_list_management.php">
                            <input type="hidden" name="action" value="add_user">
                            
                            <!-- Step 1: Personal Information -->
                            <div class="form-step">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-success me-2">1</span>
                                    <h6 class="mb-0">Personal Information</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="middle_name" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="name_extension" class="form-label">Name Extension</label>
                                        <select class="form-select" id="name_extension" name="name_extension">
                                            <option value="">Select Extension</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="II">II</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                            <option value="V">V</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label for="birth_date" class="form-label">Birth Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sex" class="form-label">Sex <span class="text-danger">*</span></label>
                                        <select class="form-select" id="sex" name="sex" required>
                                            <option value="">Select Sex</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="civil_status" class="form-label">Civil Status</label>
                                        <select class="form-select" id="civil_status" name="civil_status">
                                            <option value="">Select Status</option>
                                            <option value="Single">Single</option>
                                            <option value="Married">Married</option>
                                            <option value="Widowed">Widowed</option>
                                            <option value="Divorced">Divorced</option>
                                            <option value="Separated">Separated</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-success next-step">
                                        Next <i class="material-icons-outlined ms-1" style="font-size: 18px;">arrow_forward</i>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Contact Information -->
                            <div class="form-step" style="display: none;">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-success me-2">2</span>
                                    <h6 class="mb-0">Contact Information</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <small class="text-muted">Only trusted domains allowed (gmail.com, yahoo.com, etc.)</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                               placeholder="09XXXXXXXXX" pattern="^09[0-9]{9}$" required>
                                        <small class="text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-outline-secondary prev-step me-2">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px;">arrow_back</i> Previous
                                    </button>
                                    <button type="button" class="btn btn-success next-step">
                                        Next <i class="material-icons-outlined ms-1" style="font-size: 18px;">arrow_forward</i>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 3: Employment Information -->
                            <div class="form-step" style="display: none;">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-success me-2">3</span>
                                    <h6 class="mb-0">Employment Information</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                                        <select class="form-select" id="role_id" name="role_id" required>
                                            <option value="">Select Role</option>
                                            <option value="1">Super Admin</option>
                                            <option value="2">Admin</option>
                                            <option value="3">Human Resource</option>
                                            <option value="4">Accounting</option>
                                            <option value="5">Security Guard</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="employee_id" name="employee_id" required placeholder="Enter employee ID">
                                        <small class="text-muted">Enter unique employee ID</small>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2" id="guardLocationRow" style="display: none;">
                                    <div class="col-md-12">
                                        <label for="guard_location" class="form-label">Guard Location <span class="text-danger">*</span></label>
                                        <select class="form-select" id="guard_location" name="guard_location">
                                            <option value="">Select Location</option>
                                            <option value="Batangas">Batangas</option>
                                            <option value="Biñan">Biñan</option>
                                            <option value="Bulacan">Bulacan</option>
                                            <option value="Cavite">Cavite</option>
                                            <option value="Laguna">Laguna</option>
                                            <option value="Manila">Manila</option>
                                            <option value="Naga">Naga</option>
                                            <option value="NCR">NCR</option>
                                            <option value="Pampanga">Pampanga</option>
                                            <option value="Pangasinan">Pangasinan</option>
                                            <option value="San Pedro Laguna">San Pedro Laguna</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-outline-secondary prev-step me-2">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px;">arrow_back</i> Previous
                                    </button>
                                    <button type="button" class="btn btn-success next-step">
                                        Next <i class="material-icons-outlined ms-1" style="font-size: 18px;">arrow_forward</i>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 4: Government IDs -->
                            <div class="form-step" style="display: none;">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-success me-2">4</span>
                                    <h6 class="mb-0">Government IDs</h6>
                                </div>
                                <div class="alert alert-info">
                                    <i class="material-icons-outlined me-2">info</i>
                                    All government IDs are required for payroll and benefits processing.
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="sss_number" class="form-label">SSS Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="sss_number" name="sss_number" 
                                               placeholder="10-1234567-8" maxlength="12" required>
                                        <small class="text-muted">Format: XX-XXXXXXX-X (10 digits)</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tin_number" class="form-label">TIN Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="tin_number" name="tin_number" 
                                               placeholder="123-456-789" maxlength="15" required>
                                        <small class="text-muted">Format: XXX-XXX-XXX (9 or 12 digits)</small>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-6">
                                        <label for="philhealth_number" class="form-label">PhilHealth Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="philhealth_number" name="philhealth_number" 
                                               placeholder="12-345678901-2" maxlength="14" required>
                                        <small class="text-muted">Format: XX-XXXXXXXXX-X (12 digits)</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pagibig_number" class="form-label">Pag-IBIG Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="pagibig_number" name="pagibig_number" 
                                               placeholder="1234-5678-9012" maxlength="14" required>
                                        <small class="text-muted">Format: XXXX-XXXX-XXXX (12 digits)</small>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-outline-secondary prev-step me-2">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px;">arrow_back</i> Previous
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px;">person_add</i>
                                        Create User
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #2a7d4f; color: #fff;">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm" method="POST" action="process_edit_user.php">
                            <input type="hidden" id="editUserId" name="userId">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="editFirstName" name="firstName" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="editLastName" name="lastName" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editMiddleName" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="editMiddleName" name="middleName">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="editEmail" name="email" required>
                                    </div>
                                </div>
                            </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editStatus" name="status" required>
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archive Employee Modal (HR-style with reason) -->
        <div class="modal fade" id="archiveEmployeeModal" tabindex="-1" aria-labelledby="archiveEmployeeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="archiveEmployeeModalLabel">Archive User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="archiveEmployeeForm" method="POST" action="users_list_management.php">
                            <input type="hidden" name="action" value="archive_employee">
                            <input type="hidden" id="archiveEmployeeId" name="employee_id">
                            <p>Are you sure you want to archive <strong id="archiveEmployeeName"></strong>?</p>
                            <div class="mb-3">
                                <label for="archiveReason" class="form-label">Reason for archiving (Optional):</label>
                                <textarea class="form-control" id="archiveReason" name="reason" rows="3" placeholder="Enter reason for archiving this user..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">Archive User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #2a7d4f; color: #fff;">
                    <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="viewUserContent">
                        <div class="text-center text-muted">Loading...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
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
                                <img id="currentProfileImage" src="<?php echo !empty($adminProfile) ? $adminProfile : '../images/default_profile.png'; ?>" 
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
                                value="<?php echo isset($superadminData['First_Name']) ? $superadminData['First_Name'] : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" 
                                value="<?php echo isset($superadminData['Last_Name']) ? $superadminData['Last_Name'] : ''; ?>">
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

    <!-- Add JavaScript for the page -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Multi-step form handling
        const form = document.getElementById('addUserForm');
        const steps = form.querySelectorAll('.form-step');
        const nextButtons = form.querySelectorAll('.next-step');
        const prevButtons = form.querySelectorAll('.prev-step');
        const progressBar = document.querySelector('.progress-bar');
        let currentStep = 0;

        // Initialize form
        function showStep(stepIndex) {
            steps.forEach((step, index) => {
                step.style.display = index === stepIndex ? 'block' : 'none';
            });
            
            // Update progress bar
            const progress = ((stepIndex + 1) / steps.length) * 100;
            progressBar.style.width = progress + '%';
        }

        // Show first step initially
        showStep(0);

        // Role change handler - show/hide guard location
        const roleSelect = document.getElementById('role_id');
        const guardLocationRow = document.getElementById('guardLocationRow');
        const guardLocationSelect = document.getElementById('guard_location');

        roleSelect.addEventListener('change', function() {
            if (this.value === '5') { // Security Guard
                guardLocationRow.style.display = 'block';
                guardLocationSelect.required = true;
            } else {
                guardLocationRow.style.display = 'none';
                guardLocationSelect.required = false;
                guardLocationSelect.value = '';
            }
        });

        // Employee ID validation
        const employeeIdInput = document.getElementById('employee_id');
        let employeeIdTimeout;
        
        employeeIdInput.addEventListener('input', function() {
            clearTimeout(employeeIdTimeout);
            const value = this.value.trim();
            
            if (value.length >= 3) {
                employeeIdTimeout = setTimeout(() => {
                    checkEmployeeIdDuplicate(value);
                }, 500);
            }
        });
        
        function checkEmployeeIdDuplicate(employeeId) {
            const formData = new FormData();
            formData.append('action', 'check_employee_id_duplicate');
            formData.append('employee_id', employeeId);
            
            fetch('users_list_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.duplicate) {
                    employeeIdInput.classList.add('is-invalid');
                    // Show or update validation message
                    let feedback = employeeIdInput.parentNode.querySelector('.invalid-feedback');
                    if (!feedback) {
                        feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        employeeIdInput.parentNode.appendChild(feedback);
                    }
                    feedback.textContent = 'This Employee ID is already taken';
                } else {
                    employeeIdInput.classList.remove('is-invalid');
                    const feedback = employeeIdInput.parentNode.querySelector('.invalid-feedback');
                    if (feedback) feedback.remove();
                }
            })
            .catch(error => {
                console.error('Error checking employee ID:', error);
            });
        }

        // Add event listener for edit employee ID validation
        document.addEventListener('input', function(e) {
            if (e.target && e.target.id === 'edit_employee_id') {
                clearTimeout(employeeIdTimeout);
                const value = e.target.value.trim();
                
                if (value.length >= 3) {
                    employeeIdTimeout = setTimeout(() => {
                        checkEditEmployeeIdDuplicate(value, e.target);
                    }, 500);
                } else {
                    e.target.classList.remove('is-invalid', 'is-valid');
                    const feedback = e.target.parentNode.querySelector('#edit_employee_id_feedback');
                    if (feedback) feedback.textContent = '';
                }
            }
        });
        
        function checkEditEmployeeIdDuplicate(employeeId, inputElement) {
            const formData = new FormData();
            formData.append('action', 'check_employee_id_duplicate');
            formData.append('employee_id', employeeId);
            
            // Get current user ID from the form to exclude from duplicate check
            const userIdInput = document.querySelector('input[name="user_id"]');
            if (userIdInput) {
                formData.append('exclude_user_id', userIdInput.value);
            }
            
            fetch('users_list_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const feedback = inputElement.parentNode.querySelector('#edit_employee_id_feedback');
                if (data.duplicate) {
                    inputElement.classList.add('is-invalid');
                    inputElement.classList.remove('is-valid');
                    if (feedback) feedback.textContent = 'This Employee ID is already taken';
                } else {
                    inputElement.classList.remove('is-invalid');
                    inputElement.classList.add('is-valid');
                    if (feedback) feedback.textContent = 'Employee ID is available';
                }
            })
            .catch(error => {
                console.error('Error checking employee ID:', error);
                if (feedback) feedback.textContent = 'Error checking Employee ID';
            });
        }

        // Government ID formatting
        function formatGovtId(input, format) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            
            switch(format) {
                case 'sss':
                    if (value.length > 2) value = value.substring(0,2) + '-' + value.substring(2);
                    if (value.length > 10) value = value.substring(0,10) + '-' + value.substring(10);
                    if (value.length > 12) value = value.substring(0,12);
                    break;
                case 'tin':
                    if (value.length > 3) value = value.substring(0,3) + '-' + value.substring(3);
                    if (value.length > 7) value = value.substring(0,7) + '-' + value.substring(7);
                    if (value.length > 11) value = value.substring(0,11) + '-' + value.substring(11);
                    if (value.length > 15) value = value.substring(0,15);
                    break;
                case 'philhealth':
                    if (value.length > 2) value = value.substring(0,2) + '-' + value.substring(2);
                    if (value.length > 12) value = value.substring(0,12) + '-' + value.substring(12);
                    if (value.length > 14) value = value.substring(0,14);
                    break;
                case 'pagibig':
                    if (value.length > 4) value = value.substring(0,4) + '-' + value.substring(4);
                    if (value.length > 9) value = value.substring(0,9) + '-' + value.substring(9);
                    if (value.length > 14) value = value.substring(0,14);
                    break;
            }
            
            input.value = value;
        }

        // Add formatting to government ID inputs
        document.getElementById('sss_number').addEventListener('input', function() {
            formatGovtId(this, 'sss');
        });
        document.getElementById('tin_number').addEventListener('input', function() {
            formatGovtId(this, 'tin');
        });
        document.getElementById('philhealth_number').addEventListener('input', function() {
            formatGovtId(this, 'philhealth');
        });
        document.getElementById('pagibig_number').addEventListener('input', function() {
            formatGovtId(this, 'pagibig');
        });

        // Next button click handler
        nextButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                if (validateStep(currentStep)) {
                    currentStep++;
                    showStep(currentStep);
                }
            });
        });

        // Previous button click handler
        prevButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                currentStep--;
                showStep(currentStep);
            });
        });

        // Form validation
        function validateStep(stepIndex) {
            const currentStepElement = steps[stepIndex];
            const requiredFields = currentStepElement.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add error feedback if not exists
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'This field is required';
                        field.parentNode.appendChild(feedback);
                    }
                } else {
                    field.classList.remove('is-invalid');
                    
                    // Remove error feedback
                    const feedback = field.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.remove();
                    }
                }
            });

            // Additional validation for specific steps
            if (stepIndex === 0) { // Personal Information
                const birthDate = document.getElementById('birth_date').value;
                if (birthDate) {
                    const age = calculateAge(new Date(birthDate));
                    if (age < 18) {
                        isValid = false;
                        const birthInput = document.getElementById('birth_date');
                        birthInput.classList.add('is-invalid');
                        
                        // Add age error feedback
                        let feedback = birthInput.parentNode.querySelector('.invalid-feedback');
                        if (!feedback) {
                            feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            birthInput.parentNode.appendChild(feedback);
                        }
                        feedback.textContent = 'Employee must be at least 18 years old';
                    }
                }
            } else if (stepIndex === 1) { // Contact Information
                const phoneNumber = document.getElementById('phone_number').value;
                const phonePattern = /^09\d{9}$/;
                if (phoneNumber && !phonePattern.test(phoneNumber.replace(/\D/g, ''))) {
                    isValid = false;
                    const phoneInput = document.getElementById('phone_number');
                    phoneInput.classList.add('is-invalid');
                    
                    let feedback = phoneInput.parentNode.querySelector('.invalid-feedback');
                    if (!feedback) {
                        feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        phoneInput.parentNode.appendChild(feedback);
                    }
                    feedback.textContent = 'Invalid phone number format';
                }
            }

            return isValid;
        }

        function calculateAge(birthDate) {
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        }

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateStep(currentStep)) {
                // Show loading
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
                submitBtn.disabled = true;

                const formData = new FormData(this);
                
                fetch('users_list_management.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            confirmButtonColor: '#2a7d4f'
                        }).then(() => {
                            location.reload(); // Reload to show new user
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while creating the user',
                        confirmButtonColor: '#dc3545'
                    });
                })
                .finally(() => {
                    // Reset submit button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            }
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');

        function filterUsers() {
            const searchTerm = searchInput.value.trim().toLowerCase();
            const selectedRole = roleFilter.value;
            const selectedStatus = (statusFilter && statusFilter.value) ? statusFilter.value.toLowerCase() : '';

            // If no filters are active, reset view and exit early
            if (searchTerm === '' && selectedRole === '' && selectedStatus === '') {
                // Show all accordion items and rows
                document.querySelectorAll('.accordion-item').forEach(item => (item.style.display = ''));
                document.querySelectorAll('.user-row').forEach(row => (row.style.display = ''));
                // Remove any previous placeholders
                const oldNoResults = document.querySelector('.no-results-message');
                if (oldNoResults) oldNoResults.remove();
                const oldNoResultsTable = document.querySelector('.no-results-table-container');
                if (oldNoResultsTable) oldNoResultsTable.remove();
                // Reset accordion to initial state (only first open)
                const accordionSections = document.querySelectorAll('.accordion-collapse');
                const accordionButtons = document.querySelectorAll('.accordion-button');
                accordionSections.forEach((section, index) => {
                    if (index === 0) {
                        section.classList.add('show');
                        accordionButtons[index].classList.remove('collapsed');
                        accordionButtons[index].setAttribute('aria-expanded', 'true');
                    } else {
                        section.classList.remove('show');
                        accordionButtons[index].classList.add('collapsed');
                        accordionButtons[index].setAttribute('aria-expanded', 'false');
                    }
                });
                return;
            }

            // Get all accordion items and their tables
            const accordionItems = document.querySelectorAll('.accordion-item');
            let hasAnyVisibleRows = false;
            let totalVisibleRows = 0;

            // Hide all accordion items by default
            accordionItems.forEach(item => {
                item.style.display = 'none';
                // Collapse all sections
                const collapseSection = item.querySelector('.accordion-collapse');
                const collapseButton = item.querySelector('.accordion-button');
                if (collapseSection && collapseButton) {
                    collapseSection.classList.remove('show');
                    collapseButton.classList.add('collapsed');
                    collapseButton.setAttribute('aria-expanded', 'false');
                }
            });

            // Remove any previous "no results" placeholders
            const oldNoResults = document.querySelector('.no-results-message');
            if (oldNoResults) oldNoResults.remove();
            const oldNoResultsTable = document.querySelector('.no-results-table-container');
            if (oldNoResultsTable) oldNoResultsTable.remove();

            // For each accordion item, check if it has visible rows
            accordionItems.forEach(item => {
                const userRows = item.querySelectorAll('.user-row');
                let hasVisibleRow = false;

                userRows.forEach(row => {
                    const fullName = (row.cells[1].textContent || '').toLowerCase();
                    const empId = (row.getAttribute('data-emp-id') || '').toLowerCase();
                    const userRole = row.getAttribute('data-role');
                    const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();

                    const matchesSearch = searchTerm === '' ||
                        fullName.includes(searchTerm) ||
                        empId.includes(searchTerm);

                    const matchesRole = selectedRole === '' || userRole === selectedRole;
                    const matchesStatus = selectedStatus === '' || rowStatus === selectedStatus;
                    const shouldShow = matchesSearch && matchesRole && matchesStatus;
                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) {
                        hasVisibleRow = true;
                        totalVisibleRows++;
                    }
                });

                // If this table has at least one visible row, show and expand it
                if (hasVisibleRow) {
                    item.style.display = '';
                    const collapseSection = item.querySelector('.accordion-collapse');
                    const collapseButton = item.querySelector('.accordion-button');
                    if (collapseSection && collapseButton) {
                        collapseSection.classList.add('show');
                        collapseButton.classList.remove('collapsed');
                        collapseButton.setAttribute('aria-expanded', 'true');
                    }
                    hasAnyVisibleRows = true;
                } else {
                    item.style.display = 'none';
                }
            });

            // If no rows are visible in any table, show a standardized no-results table
            if (totalVisibleRows === 0) {
                const container = document.createElement('div');
                container.className = 'no-results-table-container m-3';
                container.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-hover users-table">
                            <colgroup class="users-colgroup">
                                <col style="width:12%">
                                <col style="width:34%">
                                <col style="width:24%">
                                <col style="width:10%">
                                <col style="width:20%">
                            </colgroup>
                            <thead class="table-dark">
                                <tr>
                                    <th>Profile</th>
                                    <th>Full Name</th>
                                    <th>Employee ID</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No results found for the selected filters.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `;
                const content = document.querySelector('.content-container');
                const accordion = document.querySelector('.accordion');
                if (accordion && accordion.parentNode) {
                    accordion.parentNode.insertBefore(container, accordion);
                } else if (content) {
                    // Fallback: append at the end of content container
                    content.appendChild(container);
                }
            }
        }

        // Update the clear search function
        function clearSearch() {
            searchInput.value = '';
            roleFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            
            // Show all accordion items
            const accordionItems = document.querySelectorAll('.accordion-item');
            accordionItems.forEach(item => {
                item.style.display = '';
            });
            
            // Show all rows
            const userRows = document.querySelectorAll('.user-row');
            userRows.forEach(row => {
                row.style.display = '';
            });
            
            // Remove no results message if it exists
            const noResultsMessage = document.querySelector('.no-results-message');
            if (noResultsMessage) noResultsMessage.remove();
            const noResultsTable = document.querySelector('.no-results-table-container');
            if (noResultsTable) noResultsTable.remove();
            
            // Reset accordion to initial state (only first section expanded)
            const accordionSections = document.querySelectorAll('.accordion-collapse');
            const accordionButtons = document.querySelectorAll('.accordion-button');
            
            accordionSections.forEach((section, index) => {
                if (index === 0) {
                    section.classList.add('show');
                    accordionButtons[index].classList.remove('collapsed');
                    accordionButtons[index].setAttribute('aria-expanded', 'true');
                } else {
                    section.classList.remove('show');
                    accordionButtons[index].classList.add('collapsed');
                    accordionButtons[index].setAttribute('aria-expanded', 'false');
                }
            });
        }

    // Add event listeners for search
        searchInput.addEventListener('input', filterUsers);
        searchButton.addEventListener('click', filterUsers);
    roleFilter.addEventListener('change', filterUsers);
    if (statusFilter) statusFilter.addEventListener('change', filterUsers);

    // Run once on page load to honor any pre-selected filters
    filterUsers();

        // View user functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-user-btn')) {
                const btn = e.target.closest('.view-user-btn');
                const userId = btn.getAttribute('data-user-id');
                const modalEl = document.getElementById('viewUserModal');
                const contentEl = document.getElementById('viewUserContent');
                contentEl.innerHTML = '<div class="text-center text-muted">Loading...</div>';
                // Reuse existing management endpoint for detail HTML if available
                fetch('users_list_management.php?action=get_details&user_id=' + encodeURIComponent(userId))
                    .then(r => r.text())
                    .then(html => {
                        contentEl.innerHTML = html;
                    })
                    .catch(() => {
                        contentEl.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>';
                    });
                new bootstrap.Modal(modalEl).show();
            }
        });

        // Edit user functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-user-btn')) {
                const btn = e.target.closest('.edit-user-btn');
                const userId = btn.getAttribute('data-user-id');
                const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                const content = document.querySelector('#editUserModal .modal-body');
                content.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                fetch('users_list_management.php?action=get_edit_form&user_id=' + encodeURIComponent(userId))
                    .then(r => r.text())
                    .then(html => { content.innerHTML = html; })
                    .catch(() => { content.innerHTML = '<div class="alert alert-danger">Failed to load edit form.</div>'; });
                modal.show();
            }
        });

        // Delete user functionality
        document.addEventListener('click', function(e) {
                        if (e.target.closest('.archive-user-btn')) {
                                const btn = e.target.closest('.archive-user-btn');
                                const userId = btn.getAttribute('data-user-id');
                                const name = btn.getAttribute('data-name') || 'this user';
                                document.getElementById('archiveEmployeeId').value = userId;
                                document.getElementById('archiveEmployeeName').textContent = name;
                                new bootstrap.Modal(document.getElementById('archiveEmployeeModal')).show();
                        }
        });
                // Handle archive form submit
                document.addEventListener('submit', function(e){
                        if(e.target && e.target.id === 'archiveEmployeeForm'){
                                e.preventDefault();
                                const form = e.target;
                                const data = new FormData(form);
                                fetch('users_list_management.php', { method: 'POST', body: data })
                                    .then(r=>r.json())
                                    .then(resp=>{
                                        if(resp.success){
                                                bootstrap.Modal.getInstance(document.getElementById('archiveEmployeeModal')).hide();
                                                Swal.fire({icon:'success',title:'Success!',text:resp.message,confirmButtonColor:'#28a745'})
                                                .then(()=> location.reload());
                                        } else {
                                                Swal.fire({icon:'error',title:'Error',text:resp.message,confirmButtonColor:'#dc3545'});
                                        }
                                    })
                                    .catch(()=>{
                                        Swal.fire({icon:'error',title:'Error',text:'An error occurred while archiving the user.',confirmButtonColor:'#dc3545'});
                                    });
                        }
                });
    });

        // Intercept edit modal inner form submit (from management endpoint)
        document.addEventListener('submit', function(e){
                if(e.target && e.target.id === 'editEmployeeFormInner'){
                        e.preventDefault();
                        const form = e.target;
                        
                        // Validate required fields including employee ID
                        const employeeIdInput = form.querySelector('input[name="employee_id"]');
                        const requiredFields = form.querySelectorAll('input[required], select[required]');
                        let isValid = true;
                        
                        // Check all required fields
                        requiredFields.forEach(field => {
                            if (!field.value.trim()) {
                                field.classList.add('is-invalid');
                                isValid = false;
                            } else {
                                field.classList.remove('is-invalid');
                            }
                        });
                        
                        // Check if employee ID has validation errors
                        if (employeeIdInput && employeeIdInput.classList.contains('is-invalid')) {
                            isValid = false;
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Please fix the Employee ID error before submitting.',
                                confirmButtonColor: '#dc3545'
                            });
                            return;
                        }
                        
                        if (!isValid) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Please fill in all required fields.',
                                confirmButtonColor: '#dc3545'
                            });
                            return;
                        }
                        
                        const data = new FormData(form);
                        fetch('users_list_management.php', { method: 'POST', body: data })
                            .then(r=>r.json())
                            .then(resp=>{
                                if(resp.success){
                                        bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                                        Swal.fire({icon:'success',title:'Success!',text:resp.message,confirmButtonColor:'#198754'})
                                        .then(()=> location.reload());
                                } else {
                                        Swal.fire({icon:'error',title:'Error',text:resp.message,confirmButtonColor:'#dc3545'});
                                }
                            })
                            .catch(()=>{
                                Swal.fire({icon:'error',title:'Error',text:'An error occurred while updating the user.',confirmButtonColor:'#dc3545'});
                            });
                }
        });

    // Show success/error messages
    <?php if(isset($_SESSION['success_message'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
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
    </script>

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/superadmin_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="admin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>

            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="recruitment.php" class="mobile-nav-item">
                <span class="material-icons">person_search</span>
                <span class="mobile-nav-text">Recruitment</span>
            </a>
            <a href="performance_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">assessment</span>
                <span class="mobile-nav-text">Performance Evaluation</span>
            </a>
            <a href="users_list.php" class="mobile-nav-item active">
                <span class="material-icons">people</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
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