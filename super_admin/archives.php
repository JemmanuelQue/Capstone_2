<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);
require_once '../db_connection.php';

// Get current super admin user's name (Role_ID = 1)
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 1 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? ($superadminData['First_Name'] . ' ' . $superadminData['Last_Name']) : 'Super Admin';

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

// Role mapping: include multiple roles (adjust labels as needed)
// Example roles: 1=Super Admin,2=Admin?,3=HR,5=Guard (adjust per actual schema)
$roleMap = [
    1 => 'Super Admins',
    2 => 'Admins',
    3 => 'HR Employees',
    4 => 'Accounting',
    5 => 'Guards'
];

// Helper to build query string preserving existing params
function build_qs($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) { $params[$k] = $v; }
    return '?' . http_build_query($params);
}

// Fetch archived users for selected roles with pagination per section
$archivedUsersByRole = [];
$archiverNames = [];
$archiverIds = [];
$rolePagination = [];
foreach ($roleMap as $roleId => $roleName) {
    $uSize = isset($_GET['usz_' . $roleId]) ? max(5, min(100, (int)$_GET['usz_' . $roleId])) : 10;
    $uPage = isset($_GET['upage_' . $roleId]) ? max(1, (int)$_GET['upage_' . $roleId]) : 1;
    $uOffset = ($uPage - 1) * $uSize;

    // Count
    $cnt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Role_ID = ? AND archived_at IS NOT NULL");
    $cnt->execute([$roleId]);
    $total = (int)$cnt->fetchColumn();

    // Page data
    $stmt = $conn->prepare("SELECT * FROM users WHERE Role_ID = :role AND archived_at IS NOT NULL ORDER BY Last_Name LIMIT :lim OFFSET :off");
    $stmt->bindValue(':role', $roleId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $uSize, PDO::PARAM_INT);
    $stmt->bindValue(':off', $uOffset, PDO::PARAM_INT);
    $stmt->execute();
    $archivedUsersByRole[$roleId] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($archivedUsersByRole[$roleId] as $user) {
        if (!empty($user['archived_by'])) {
            $archiverIds[(int)$user['archived_by']] = true;
        }
    }

    $rolePagination[$roleId] = [
        'size' => $uSize,
        'page' => $uPage,
        'total' => $total,
        'pages' => max(1, (int)ceil($total / $uSize)),
    ];
}
// Fetch archiver names used on currently visible pages
if (!empty($archiverIds)) {
    $ids = implode(',', array_map('intval', array_keys($archiverIds)));
    $archiverStmt = $conn->query("SELECT u.User_ID, u.First_Name, u.Last_Name, r.Role_Name FROM users u LEFT JOIN roles r ON u.Role_ID = r.Role_ID WHERE u.User_ID IN ($ids)");
    foreach ($archiverStmt->fetchAll(PDO::FETCH_ASSOC) as $archiver) {
        $archiverNames[$archiver['User_ID']] = $archiver['First_Name'] . ' ' . $archiver['Last_Name'] . ' (' . $archiver['Role_Name'] . ')';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Users - Green Meadows Security Agency</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/archives.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
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
                <a href="superadmin_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
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
                <a href="users_list.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
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
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Archives">
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
                    <span><?php echo $superadminName; ?></span>
                    <img src="<?php echo $superadminProfile; ?>" alt="User Profile">
                </a>
        </div>
        
        <div class="content-container">
            <div class="container-fluid px-4">
                <div class="archived-header">Archived Guards</div>
                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email...">
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
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Accordion for archived guards -->
                <div class="accordion" id="archivedUsersAccordion">
                    <?php foreach ($roleMap as $roleId => $roleName): ?>
                    <div class="accordion-item" id="role-<?php echo $roleId; ?>" data-role="<?php echo $roleId; ?>">
                        <h2 class="accordion-header" id="heading<?php echo $roleId; ?>">
                            <button class="accordion-button<?php echo $roleId !== 1 ? ' collapsed' : ''; ?>" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#collapse<?php echo $roleId; ?>" 
                                    aria-expanded="<?php echo $roleId === 1 ? 'true' : 'false'; ?>" 
                                    aria-controls="collapse<?php echo $roleId; ?>">
                                <?php echo $roleName; ?> Archived Users
                            </button>
                        </h2>
                        <div id="collapse<?php echo $roleId; ?>" class="accordion-collapse collapse<?php echo $roleId === 1 ? ' show' : ''; ?>" aria-labelledby="heading<?php echo $roleId; ?>">
                            <div class="accordion-body p-0">
                                <div class="d-flex justify-content-between align-items-center px-3 pt-3">
                                    <div class="input-group" style="max-width: 280px;">
                                        <label class="input-group-text" for="usz_<?php echo $roleId; ?>">Per page</label>
                                        <select class="form-select" id="usz_<?php echo $roleId; ?>" onchange="changeUserPageSize(<?php echo $roleId; ?>, this.value)">
                                            <?php foreach ([10,25,50,100] as $opt): ?>
                                                <option value="<?php echo $opt; ?>" <?php echo $rolePagination[$roleId]['size']==$opt?'selected':''; ?>><?php echo $opt; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary">Total: <?php echo $rolePagination[$roleId]['total']; ?></span>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Full Name</th>
                                                <th>Email</th>
                                                <th>Phone Number</th>
                                                <th>Status</th>
                                                <th>Archived At</th>
                                                <th>Archived By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $users = $archivedUsersByRole[$roleId];
                                            // Replace the basic "No archived users found" message with a styled version
                                            if (count($users) === 0) {
                                                echo '<tr class="php-no-results-msg"><td colspan="8" class="text-center">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="material-icons align-middle me-2">search_off</i> No archived users found for this role
                                                    </div>
                                                </td></tr>';
                                            } else {
                                                foreach ($users as $user) {
                                                    $profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) 
                                                        ? $user['Profile_Pic'] 
                                                        : '../images/default_profile.png';
                                                    $statusClass = ($user['status'] === 'Active') ? 'bg-success' : 'bg-secondary';
                                                    $archivedBy = isset($archiverNames[$user['archived_by']]) ? $archiverNames[$user['archived_by']] : 'Unknown';
                                                    echo '<tr class="user-row" data-role="'.$user['Role_ID'].'">';
                                                    echo '<td><img src="'.$profilePic.'" class="rounded-circle" width="40" height="40"></td>';
                                                    echo '<td>'.$user['First_Name'].' '.$user['Last_Name'].'</td>';
                                                    echo '<td>'.$user['Email'].'</td>';
                                                    echo '<td>'.$user['phone_number'].'</td>';
                                                    echo '<td><span class="badge '.$statusClass.'">'.$user['status'].'</span></td>';
                                                    echo '<td>'.date('Y-m-d H:i', strtotime($user['archived_at'])).'</td>';
                                                    echo '<td>'.$archivedBy.'</td>';
                                                    echo '<td><div class="btn-group">';
                                                    echo '<button class="btn btn-sm btn-success recover-user-btn" data-user-id="'.$user['User_ID'].'"><i class="material-icons">restore</i></button>';
                                                    echo '<button class="btn btn-sm btn-danger delete-user-btn" data-user-id="'.$user['User_ID'].'"><i class="material-icons">delete</i></button>';
                                                    echo '</div></td>';
                                                    echo '</tr>';
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-3 pb-3">
                                    <?php $rp = $rolePagination[$roleId]; $pages = $rp['pages']; $cp = $rp['page']; ?>
                                    <nav aria-label="Archived users pagination role <?php echo $roleId; ?>">
                                        <ul class="pagination justify-content-end mb-0">
                                            <li class="page-item <?php echo $cp<=1?'disabled':''; ?>">
                                                <a class="page-link" href="<?php echo build_qs(['upage_'.$roleId => max(1,$cp-1)]) . '#role-' . $roleId; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($p=1; $p<= $pages; $p++): ?>
                                                <li class="page-item <?php echo $p==$cp?'active':''; ?>">
                                                    <a class="page-link" href="<?php echo build_qs(['upage_'.$roleId => $p]) . '#role-' . $roleId; ?>"><?php echo $p; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $cp>=$pages?'disabled':''; ?>">
                                                <a class="page-link" href="<?php echo build_qs(['upage_'.$roleId => min($pages,$cp+1)]) . '#role-' . $roleId; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Archived Attendance Records -->
                <div id="att-archives" class="archived-header mt-5">Archived Attendance</div>
                <?php
                // Pagination setup
                $page = isset($_GET['apage']) ? max(1, intval($_GET['apage'])) : 1;
                $pageSize = isset($_GET['asz']) ? max(5, min(100, intval($_GET['asz']))) : 10;

                // Filters (unified search for name or employee ID)
                $aQ = isset($_GET['a_q']) ? trim($_GET['a_q']) : '';
                $aLoc = isset($_GET['a_loc']) ? trim($_GET['a_loc']) : '';
                $aFrom = isset($_GET['a_from']) ? trim($_GET['a_from']) : '';
                $aTo = isset($_GET['a_to']) ? trim($_GET['a_to']) : '';

                // Validate location filter against known locations to avoid stale values
                try {
                    $validLocs = $conn->query("SELECT DISTINCT location_name FROM guard_locations WHERE location_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
                    if ($aLoc !== '' && !in_array($aLoc, $validLocs, true)) {
                        $aLoc = '';
                    }
                } catch (Exception $e) { /* ignore */ }

                // Parse dd/mm/yyyy to Y-m-d if needed
                $fromSql = '';
                $toSql = '';
                if ($aFrom !== '') {
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $aFrom)) {
                        $dt = DateTime::createFromFormat('d/m/Y', $aFrom);
                        if ($dt) $fromSql = $dt->format('Y-m-d');
                    } else {
                        $ts = strtotime($aFrom); if ($ts) $fromSql = date('Y-m-d', $ts);
                    }
                }
                if ($aTo !== '') {
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $aTo)) {
                        $dt = DateTime::createFromFormat('d/m/Y', $aTo);
                        if ($dt) $toSql = $dt->format('Y-m-d');
                    } else {
                        $ts = strtotime($aTo); if ($ts) $toSql = date('Y-m-d', $ts);
                    }
                }

                $where = [];
                $binds = [];
                if ($aQ !== '') {
                    $where[] = "(a.first_name LIKE :q OR a.last_name LIKE :q OR CONCAT_WS(' ', a.first_name, a.last_name) LIKE :q OR u.employee_id LIKE :q)";
                    $binds[':q'] = "%$aQ%";
                }
                if ($aLoc !== '') {
                    $where[] = "gl.location_name = :loc";
                    $binds[':loc'] = $aLoc;
                }
                if ($fromSql !== '' && $toSql !== '') {
                    $where[] = "DATE(a.time_in) BETWEEN :df AND :dt";
                    $binds[':df'] = $fromSql;
                    $binds[':dt'] = $toSql;
                } elseif ($fromSql !== '') {
                    $where[] = "DATE(a.time_in) >= :df";
                    $binds[':df'] = $fromSql;
                } elseif ($toSql !== '') {
                    $where[] = "DATE(a.time_in) <= :dt";
                    $binds[':dt'] = $toSql;
                }

                $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

                // Total count with filters
                $countSql = "SELECT COUNT(*) FROM archive_dtr_data a 
                              LEFT JOIN users u ON u.User_ID = a.User_ID
                              LEFT JOIN guard_locations gl ON gl.user_id = a.User_ID AND gl.is_primary = 1" . $whereSql;
                $countStmt = $conn->prepare($countSql);
                foreach ($binds as $k=>$v) { $countStmt->bindValue($k, $v); }
                $countStmt->execute();
                $totalArchived = (int)$countStmt->fetchColumn();

                // Compute total pages and clamp current page; then compute offset
                $totalPages = max(1, (int)ceil($totalArchived / $pageSize));
                if ($page > $totalPages) { $page = $totalPages; }
                $offset = ($page - 1) * $pageSize;

                // Fetch page with filters using (possibly) adjusted offset
                $listSql = "SELECT a.ID, a.User_ID, a.first_name, a.last_name, u.employee_id, a.time_in, a.time_out, COALESCE(gl.location_name, '—') AS location_name
                            FROM archive_dtr_data a
                            LEFT JOIN users u ON u.User_ID = a.User_ID
                            LEFT JOIN guard_locations gl ON gl.user_id = a.User_ID AND gl.is_primary = 1" .
                            $whereSql . " ORDER BY a.time_in DESC LIMIT :lim OFFSET :off";
                $attStmt = $conn->prepare($listSql);
                foreach ($binds as $k=>$v) { $attStmt->bindValue($k, $v); }
                $attStmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
                $attStmt->bindValue(':off', $offset, PDO::PARAM_INT);
                $attStmt->execute();
                $archivedAttendance = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <?php
                        // Locations for filter dropdown
                        $locs = $conn->query("SELECT DISTINCT location_name FROM guard_locations WHERE location_name <> '' ORDER BY location_name")->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <form id="attFilterForm" method="GET" class="row g-2 align-items-end mb-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" id="attSearch" name="a_q" class="form-control" value="<?php echo htmlspecialchars($aQ ?? ''); ?>" placeholder="search name or employee id">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Location</label>
                                <select name="a_loc" class="form-select">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locs as $ln): ?>
                                        <option value="<?php echo htmlspecialchars($ln); ?>" <?php echo $aLoc===$ln?'selected':''; ?>><?php echo htmlspecialchars($ln); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Date Range</label>
                                <input type="text" id="dateRange" class="form-control" placeholder="Select date range" readonly>
                                <input type="hidden" name="a_from" value="<?php echo htmlspecialchars($aFrom); ?>">
                                <input type="hidden" name="a_to" value="<?php echo htmlspecialchars($aTo); ?>">
                            </div>
                            <div class="col-6 col-md-2 d-flex">
                                <button type="submit" class="btn btn-outline-success w-100 me-2"><i class="material-icons align-middle">search</i></button>
                                <a href="archives.php" class="btn btn-outline-secondary w-100"><i class="material-icons align-middle">restart_alt</i></a>
                            </div>
                        </form>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="input-group" style="max-width: 280px;">
                                <label class="input-group-text" for="asz">Per page</label>
                                <select class="form-select" id="asz" onchange="changeAttPageSize(this.value)">
                                    <?php foreach ([10,25,50,100] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $pageSize==$opt?'selected':''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <span class="badge bg-secondary">Total: <?php echo $totalArchived; ?></span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Guard Name</th>
                                        <th>Employee ID</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($archivedAttendance)): ?>
                                        <tr><td colspan="7" class="text-center">
                                            <div class="alert alert-info mb-0">
                                                <i class="material-icons align-middle me-2">search_off</i> No archived attendance found
                                            </div>
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($archivedAttendance as $a):
                                            $inDate = $a['time_in'] ? date('M j, Y', strtotime($a['time_in'])) : '';
                                            $inTime = $a['time_in'] ? date('h:i A', strtotime($a['time_in'])) : '';
                                            $outTime = $a['time_out'] ? date('h:i A', strtotime($a['time_out'])) : 'Not logged out';
                                            $outDate = $a['time_out'] ? date('M j, Y', strtotime($a['time_out'])) : '';
                                            $dateCell = $outDate && $outDate !== $inDate ? ($inDate.' to '.$outDate) : $inDate;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['employee_id'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($a['location_name']); ?></td>
                                                <td><?php echo $dateCell; ?></td>
                                                <td><?php echo $inTime; ?></td>
                                                <td><?php echo $outTime; ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-success restore-attendance" data-id="<?php echo $a['ID']; ?>" title="Restore">
                                                            <i class="material-icons">restore</i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-attendance" data-id="<?php echo $a['ID']; ?>" title="Delete permanently">
                                                            <i class="material-icons">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Archived Attendance pagination">
                            <ul class="pagination justify-content-end mb-0">
                                <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
                                    <a class="page-link" href="<?php echo build_qs(['apage'=>max(1,$page-1)]) . '#att-archives'; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php for ($p=1; $p<= $totalPages; $p++): ?>
                                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="<?php echo build_qs(['apage'=>$p]) . '#att-archives'; ?>"><?php echo $p; ?></a></li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page>=$totalPages?'disabled':''; ?>">
                                    <a class="page-link" href="<?php echo build_qs(['apage'=>min($totalPages,$page+1)]) . '#att-archives'; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize date range picker for Archived Attendance filter
        const rangeInput = document.getElementById('dateRange');
        const fromHidden = document.querySelector('input[name="a_from"]');
        const toHidden = document.querySelector('input[name="a_to"]');
        if (rangeInput && typeof $(rangeInput).daterangepicker === 'function') {
            // Parse existing dd/mm/yyyy values
            let start = fromHidden && fromHidden.value ? moment(fromHidden.value, 'DD/MM/YYYY') : null;
            let end = toHidden && toHidden.value ? moment(toHidden.value, 'DD/MM/YYYY') : null;

            $(rangeInput).daterangepicker({
                autoUpdateInput: false,
                locale: { format: 'DD/MM/YYYY', cancelLabel: 'Clear' },
                startDate: start || moment().startOf('month'),
                endDate: end || moment(),
                opens: 'left'
            });

            // If values present, reflect in visible input
            if (start && end) {
                rangeInput.value = start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY');
            }

            $(rangeInput).on('apply.daterangepicker', function(ev, picker) {
                const s = picker.startDate.format('DD/MM/YYYY');
                const e = picker.endDate.format('DD/MM/YYYY');
                rangeInput.value = s + ' - ' + e;
                if (fromHidden) fromHidden.value = s;
                if (toHidden) toHidden.value = e;
            });
            $(rangeInput).on('cancel.daterangepicker', function() {
                rangeInput.value = '';
                if (fromHidden) fromHidden.value = '';
                if (toHidden) toHidden.value = '';
            });
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const roleFilter = document.getElementById('roleFilter');

        function filterUsers() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedRole = roleFilter.value;
            const accordionItems = document.querySelectorAll('.accordion-item');
            let totalVisibleRows = 0;
            let accordionsWithResults = [];
            
            // Remove any previous "no results" message
            const oldNoResults = document.querySelector('.no-results-message');
            if (oldNoResults) oldNoResults.remove();
            
            // Clear all section-specific no-results messages
            document.querySelectorAll('.section-no-results').forEach(el => el.remove());
            
            // Process each accordion section
            accordionItems.forEach(item => {
                const userRows = item.querySelectorAll('.user-row');
                const phpNoResultsMsg = item.querySelector('.php-no-results-msg');
                let visibleRows = 0;
                
                // If we're filtering, hide the PHP "no results" message
                if (searchTerm !== '' || selectedRole !== '') {
                    if (phpNoResultsMsg) phpNoResultsMsg.style.display = 'none';
                } else {
                    // If not filtering, show the PHP message if it exists
                    if (phpNoResultsMsg) phpNoResultsMsg.style.display = '';
                }
                
                // Process rows in this section
                userRows.forEach(row => {
                    const fullName = row.cells[1].textContent.toLowerCase();
                    const email = row.cells[2].textContent.toLowerCase();
                    const phoneNumber = row.cells[3].textContent.toLowerCase();
                    
                    const matchesSearch = fullName.includes(searchTerm) || 
                                        email.includes(searchTerm) || 
                                        phoneNumber.includes(searchTerm);
                    const matchesRole = selectedRole === '' || row.getAttribute('data-role') === selectedRole;
                    
                    const shouldShow = matchesSearch && matchesRole;
                    row.style.display = shouldShow ? '' : 'none';
                    
                    if (shouldShow) {
                        visibleRows++;
                        totalVisibleRows++;
                    }
                });
                
                // Store accordion sections for later
                const itemRoleId = item.getAttribute('data-role');
                const collapseElement = item.querySelector('.accordion-collapse');
                
                // Show all sections when no role is selected
                if (selectedRole === '') {
                    item.style.display = '';
                    
                    // Auto-expand all sections to show content or "no results" messages
                    collapseElement.classList.add('show');
                    const button = document.querySelector(`[data-bs-target="#${collapseElement.id}"]`);
                    if (button) button.classList.remove('collapsed');
                    
                    // Show section-specific "no results" message if needed
                    const existingMsg = item.querySelector('.section-no-results');
                    
                    if (visibleRows === 0 && userRows.length > 0) {
                        if (!existingMsg) {
                            const tbody = item.querySelector('tbody');
                            const tr = document.createElement('tr');
                            tr.className = 'section-no-results';
                            tr.innerHTML = `<td colspan="8" class="text-center">
                                <div class="alert alert-info mb-0">
                                    <i class="material-icons align-middle me-2">search_off</i> No users matching your search criteria in this section
                                </div>
                            </td>`;
                            tbody.appendChild(tr);
                        }
                    } else if (existingMsg) {
                        existingMsg.remove();
                    }
                } else {
                    // When role filter is applied, only show matching sections
                    if (itemRoleId === selectedRole) {
                        item.style.display = '';
                        
                        // Auto-expand the matching section
                        collapseElement.classList.add('show');
                        const button = document.querySelector(`[data-bs-target="#${collapseElement.id}"]`);
                        if (button) button.classList.remove('collapsed');
                        
                        // Add "no results" message if needed for this specific role section
                        if (visibleRows === 0) {
                            const existingMsg = item.querySelector('.section-no-results');
                            if (!existingMsg) {
                                const tbody = item.querySelector('tbody');
                                const tr = document.createElement('tr');
                                tr.className = 'section-no-results';
                                tr.innerHTML = `<td colspan="8" class="text-center">
                                    <div class="alert alert-info mb-0">
                                        <i class="material-icons align-middle me-2">search_off</i> No archived users found for this role matching your search
                                    </div>
                                </td>`;
                                tbody.appendChild(tr);
                            }
                        }
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
            
            // Global "no results" message if no matches found at all
            if (totalVisibleRows === 0) {
                // Remove this SweetAlert call since we already show messages in the tables
                // Swal.fire({
                //     icon: 'info',
                //     title: 'No Results Found',
                //     text: 'No archived users match your search criteria. Try adjusting your search or filters.',
                //     confirmButtonColor: '#28a745'
                // });
            }
        }

        // Add event listeners for search
        searchInput.addEventListener('input', filterUsers);
        searchButton.addEventListener('click', filterUsers);
        roleFilter.addEventListener('change', filterUsers);

        // Recover and Delete actions
        document.querySelectorAll('.recover-user-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                Swal.fire({
                    title: 'Recover Archived User?',
                    text: 'This will restore the user to the active users list.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Recover',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'archives_management.php';
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'userId';
                        input.value = userId;
                        form.appendChild(input);
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'recover';
                        form.appendChild(actionInput);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    document.querySelectorAll('.delete-user-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                Swal.fire({
                    title: 'Delete Archived User?',
                    text: 'This will permanently delete the user. This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Ask for confirmation again due to the severity of this action
                        Swal.fire({
                            title: 'Are you absolutely sure?',
                            html: 'This will <b>permanently delete</b> the user and all associated data.<br>This action <b>cannot be reversed</b>.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Yes, Delete',
                            cancelButtonText: 'No, Keep'
                        }).then((finalResult) => {
                            if (finalResult.isConfirmed) {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = 'archives_management.php';
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'userId';
                                input.value = userId;
                                form.appendChild(input);
                                const actionInput = document.createElement('input');
                                actionInput.type = 'hidden';
                                actionInput.name = 'action';
                                actionInput.value = 'delete';
                                form.appendChild(actionInput);
                                document.body.appendChild(form);
                                form.submit();
                            }
                        });
                    }
                });
            });
        });

        // Helpers for per-role pagination
        window.changeUserPageSize = function(roleId, size){
            const params = new URLSearchParams(window.location.search);
            params.set('usz_'+roleId, size);
            params.set('upage_'+roleId, '1');
            window.location.href = '?' + params.toString() + '#role-' + roleId;
        }

        // Restore attendance
        document.querySelectorAll('.restore-attendance').forEach(function(btn){
            btn.addEventListener('click', function(){
                const id = this.getAttribute('data-id');
                Swal.fire({
                    title: 'Restore Archived Attendance?',
                    text: 'This will move the record back to active attendance.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Restore',
                    cancelButtonText: 'Cancel'
                }).then((r)=>{
                    if(r.isConfirmed){
                        const f = document.createElement('form');
                        f.method = 'POST'; f.action = 'archives_management.php';
                        const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='restore_attendance'; f.appendChild(a);
                        const i = document.createElement('input'); i.type='hidden'; i.name='attendanceId'; i.value=id; f.appendChild(i);
                        document.body.appendChild(f); f.submit();
                    }
                });
            });
        });

        // Delete attendance permanently
        document.querySelectorAll('.delete-attendance').forEach(function(btn){
            btn.addEventListener('click', function(){
                const id = this.getAttribute('data-id');
                Swal.fire({
                    title: 'Delete Archived Attendance?',
                    html: 'This will <b>permanently delete</b> the archived attendance record. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel'
                }).then((r)=>{
                    if(r.isConfirmed){
                        const f = document.createElement('form');
                        f.method = 'POST'; f.action = 'archives_management.php';
                        const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete_attendance'; f.appendChild(a);
                        const i = document.createElement('input'); i.type='hidden'; i.name='attendanceId'; i.value=id; f.appendChild(i);
                        document.body.appendChild(f); f.submit();
                    }
                });
            });
        });
    });
    </script>

    <script>
    // Ensure attendance per-page and filter submissions jump to the attendance section
    function changeAttPageSize(v){
        const params = new URLSearchParams(window.location.search);
        params.set('asz', v);
        params.set('apage', '1');
        window.location.href = '?' + params.toString() + '#att-archives';
    }

    // Smooth scroll after reload when hash exists and attach handlers
    document.addEventListener('DOMContentLoaded', function(){
        if (window.location.hash) {
            const el = document.querySelector(window.location.hash);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        // Realtime filter rows on the current page as the user types in unified search
        const attSearch = document.getElementById('attSearch');
        if (attSearch) {
            attSearch.addEventListener('input', function(){
                const q = this.value.toLowerCase();
                const rows = document.querySelectorAll('#att-archives ~ .card table tbody tr');
                let any = false;
                rows.forEach(tr => {
                    // Skip the empty-state row
                    if (tr.querySelector('.alert')) return;
                    const name = (tr.cells[0]?.textContent || '').toLowerCase();
                    const empid = (tr.cells[1]?.textContent || '').toLowerCase();
                    const match = q === '' || name.includes(q) || empid.includes(q);
                    tr.style.display = match ? '' : 'none';
                    if (match) any = true;
                });
            });
        }

        // Attach submit handler to attendance filter form to add hash
        const attForm = document.getElementById('attFilterForm');
        if (attForm) {
            attForm.addEventListener('submit', function(e){
                // Let the browser build the querystring, but force anchor
                e.preventDefault();
                const params = new URLSearchParams(new FormData(attForm));
                window.location.href = '?' + params.toString() + '#att-archives';
            });
        }
    });
    </script>

    

    <!-- SWAL Alerts for User Recovery and Deletion -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
     
    <script src="js/superadmin_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="superadmin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
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
            <a href="users_list.php" class="mobile-nav-item">
                <span class="material-icons">people</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="archives.php" class="mobile-nav-item active">
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