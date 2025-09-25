<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);

require_once '../db_connection.php'; // $conn = PDO

$currentDate = date('Y-m-d');

// Filters
if (isset($_GET['filter_submit'])) {
    $employmentStatus = $_GET['employment_status'] ?? 'all';
    $evaluationStatus = $_GET['evaluation_status'] ?? 'all';
    $searchTerm = isset($_GET['guardSearch']) ? trim($_GET['guardSearch']) : '';
    $locationFilter = isset($_GET['location']) ? trim($_GET['location']) : '';
} else {
    $employmentStatus = 'all';
    $evaluationStatus = 'all';
    $searchTerm = '';
    $locationFilter = '';
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Admin info
$adminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 2 AND status = 'Active' AND User_ID = ?");
$adminStmt->execute([$_SESSION['user_id']]);
$adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $adminData ? $adminData['First_Name'] . ' ' . $adminData['Last_Name'] : 'Admin';

$profileStmt = $conn->prepare("SELECT Profile_Pic FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
$adminProfile = ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) ? $profileData['Profile_Pic'] : '../images/default_profile.png';

// Locations for filter
$locationsStmt = $conn->prepare("SELECT DISTINCT location_name FROM guard_locations WHERE location_name != '' ORDER BY location_name");
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

// Helpers
function getEmploymentStatus($hiredDate) {
    if (!$hiredDate) return 'Unknown';
    $h = new DateTime($hiredDate);
    $now = new DateTime();
    $i = $h->diff($now);
    $months = $i->y * 12 + $i->m;
    return $months >= 6 ? 'Regular' : 'Probationary';
}

function getEvaluationStatus($hiredDate, $lastEvaluationDate, $employmentStatus) {
    if (!$hiredDate) return ['status' => 'Not Yet Started', 'next_due' => null];
    $now = new DateTime();
    $h = new DateTime($hiredDate);
    if (!$lastEvaluationDate) {
        $m = $h->diff($now); $monthsSinceHired = $m->y * 12 + $m->m;
        if ($employmentStatus === 'Probationary') {
            if ($monthsSinceHired >= 6) { $d = (clone $h)->add(new DateInterval('P6M')); return ['status' => 'Overdue', 'next_due' => $d]; }
            if ($monthsSinceHired >= 3) { $d = (clone $h)->add(new DateInterval('P3M')); return ['status' => 'Due', 'next_due' => $d]; }
            $d = (clone $h)->add(new DateInterval('P3M')); return ['status' => 'Not Yet Started', 'next_due' => $d];
        } else {
            if ($monthsSinceHired >= 12) { $d = (clone $h)->add(new DateInterval('P12M')); return ['status' => 'Due', 'next_due' => $d]; }
            $d = (clone $h)->add(new DateInterval('P12M')); return ['status' => 'Not Yet Started', 'next_due' => $d];
        }
    } else {
        $last = new DateTime($lastEvaluationDate);
        $m = $last->diff($now); $monthsSince = $m->y * 12 + $m->m;
        if ($employmentStatus === 'Probationary') {
            $d = (clone $last)->add(new DateInterval('P3M'));
            if ($monthsSince >= 3) return ['status' => ($now > $d ? 'Overdue' : 'Due'), 'next_due' => $d];
            return ['status' => 'Completed', 'next_due' => $d];
        } else {
            $d = (clone $last)->add(new DateInterval('P12M'));
            if ($monthsSince >= 12) return ['status' => ($now > $d ? 'Overdue' : 'Due'), 'next_due' => $d];
            return ['status' => 'Completed', 'next_due' => $d];
        }
    }
}

// Query base data
$params = [];
$searchCondition = '';
$locationCondition = '';
if ($searchTerm !== '') {
    $searchCondition = " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?) ";
    $like = "%$searchTerm%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($locationFilter !== '') {
    $locationCondition = " AND gl.location_name = ? ";
    $params[] = $locationFilter;
}

$guardsQuery = "
    SELECT 
        u.User_ID,
        u.employee_id,
        u.First_Name,
        u.Last_Name,
        u.middle_name,
        u.hired_date,
        u.Created_At,
        gl.location_name,
        pe.evaluation_date as last_evaluation_date,
        pe.overall_rating,
        pe.status as eval_status,
        COUNT(pe2.evaluation_id) as total_evaluations
    FROM users u
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    LEFT JOIN (
        SELECT pe1.user_id, pe1.evaluation_date, pe1.overall_rating, pe1.status
        FROM performance_evaluations pe1
        INNER JOIN (
            SELECT user_id, MAX(evaluation_date) AS max_date
            FROM performance_evaluations
            WHERE status = 'Completed'
            GROUP BY user_id
        ) latest ON latest.user_id = pe1.user_id AND pe1.evaluation_date = latest.max_date
        WHERE pe1.status = 'Completed'
    ) pe ON u.User_ID = pe.user_id
    LEFT JOIN performance_evaluations pe2 ON u.User_ID = pe2.user_id AND pe2.status = 'Completed'
    WHERE u.Role_ID = 5 AND u.status = 'Active'
    $searchCondition
    $locationCondition
    GROUP BY u.User_ID, u.employee_id, u.First_Name, u.Last_Name, u.middle_name, u.hired_date, u.Created_At, gl.location_name, pe.evaluation_date, pe.overall_rating, pe.status
    ORDER BY u.Last_Name, u.First_Name
";

$guardsStmt = $conn->prepare($guardsQuery);
foreach ($params as $i => $p) { $guardsStmt->bindValue($i + 1, $p); }
$guardsStmt->execute();
$guards = $guardsStmt->fetchAll(PDO::FETCH_ASSOC);

$filteredGuards = [];
foreach ($guards as $g) {
    $hiredDate = $g['hired_date'] ?: $g['Created_At'];
    $emp = getEmploymentStatus($hiredDate);
    $evalInfo = getEvaluationStatus($hiredDate, $g['last_evaluation_date'], $emp);

    $g['employment_status'] = $emp;
    $g['evaluation_status'] = $evalInfo['status'];
    $g['next_due_date'] = $evalInfo['next_due'];
    $g['effective_hired_date'] = $hiredDate;

    if ($employmentStatus !== 'all' && $emp !== $employmentStatus) continue;
    if ($evaluationStatus !== 'all' && $evalInfo['status'] !== $evaluationStatus) continue;

    $filteredGuards[] = $g;
}

$totalGuards = count($filteredGuards);
$probationaryCount = count(array_filter($filteredGuards, fn($x) => $x['employment_status'] === 'Probationary'));
$regularCount = count(array_filter($filteredGuards, fn($x) => $x['employment_status'] === 'Regular'));
$dueCount = count(array_filter($filteredGuards, fn($x) => in_array($x['evaluation_status'], ['Due','Overdue'], true)));
$overdueCount = count(array_filter($filteredGuards, fn($x) => $x['evaluation_status'] === 'Overdue'));
$completedCount = count(array_filter($filteredGuards, fn($x) => $x['evaluation_status'] === 'Completed'));
$notStartedCount = count(array_filter($filteredGuards, fn($x) => $x['evaluation_status'] === 'Not Yet Started'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Evaluation - Green Meadows Security Agency</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="css/performance_evaluation.css">
    <style>
        .filter-toolbar{display:flex;flex-wrap:nowrap;gap:1rem;align-items:flex-end;overflow-x:auto;white-space:nowrap;padding-bottom:.25rem}
        .filter-toolbar .form-group{min-width:220px}
        .filter-toolbar .btn-group{flex:0 0 auto}
        .filter-toolbar::-webkit-scrollbar{height:6px}
        .filter-toolbar::-webkit-scrollbar-thumb{background:#ced4da;border-radius:3px}
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name"><div> SECURITY AGENCY</div></div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a href="admin_dashboard.php" class="nav-link"><span class="material-icons">dashboard</span><span>Dashboard</span></a></li>

            <li class="nav-item"><a href="leave_request.php" class="nav-link"><span class="material-icons">event_note</span><span>Leave Request</span></a></li>
            <li class="nav-item"><a href="recruitment.php" class="nav-link"><span class="material-icons">person_search</span><span>Recruitment</span></a></li>
            <li class="nav-item"><a href="performance_evaluation.php" class="nav-link active"><span class="material-icons">assessment</span><span>Performance Evaluation</span></a></li>
            <li class="nav-item"><a href="users_list.php" class="nav-link"><span class="material-icons">people</span><span>Masterlist</span></a></li>
            <li class="nav-item"><a href="daily_time_record.php" class="nav-link"><span class="material-icons">schedule</span><span>Daily Time Record</span></a></li>
            <li class="nav-item"><a href="archives.php" class="nav-link"><span class="material-icons">archive</span><span>Archives</span></a></li>
            <li class="nav-item"><a href="logs.php" class="nav-link"><span class="material-icons">receipt_long</span><span>Logs</span></a></li>
            <li class="nav-item mt-5"><a href="../logout.php" class="nav-link"><span class="material-icons">logout</span><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-content" id="main-content">
        <div class="header">
            <button class="toggle-sidebar" id="toggleSidebar"><span class="material-icons">menu</span></button>
            <div class="current-datetime ms-3 d-none d-md-block"><span id="current-date"></span> | <span id="current-time"></span></div>
            <a href="profile.php" class="user-profile" id="userProfile" style="color:black; text-decoration:none;"><span><?php echo htmlspecialchars($adminName); ?></span><img src="<?php echo $adminProfile; ?>" alt="User Profile"></a>
        </div>

        <div class="container-fluid mt-4">
            <h1 class="page-title">Performance Evaluation System</h1>

            <div class="row mb-4">
                <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body d-flex justify-content-between"><div><h4><?php echo $totalGuards; ?></h4><p class="mb-0">Total Guards</p></div><i class="material-icons" style="font-size:2rem;">people</i></div></div></div>
                <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body d-flex justify-content-between"><div><h4><?php echo $probationaryCount; ?></h4><p class="mb-0">Probationary</p></div><i class="material-icons" style="font-size:2rem;">schedule</i></div></div></div>
                <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body d-flex justify-content-between"><div><h4><?php echo $regularCount; ?></h4><p class="mb-0">Regular</p></div><i class="material-icons" style="font-size:2rem;">verified</i></div></div></div>
                <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body d-flex justify-content-between"><div><h4><?php echo $dueCount; ?></h4><p class="mb-0">Evaluations Due</p></div><i class="material-icons" style="font-size:2rem;">warning</i></div></div></div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="material-icons">filter_list</i> Filters</h5></div>
                <div class="card-body">
                    <form method="GET" class="filter-toolbar">
                        <div class="form-group">
                            <label for="employment_status" class="form-label mb-1">Employment Status</label>
                            <select class="form-select" id="employment_status" name="employment_status">
                                <option value="all" <?php echo $employmentStatus=='all'?'selected':''; ?>>All Status</option>
                                <option value="Probationary" <?php echo $employmentStatus=='Probationary'?'selected':''; ?>>Probationary</option>
                                <option value="Regular" <?php echo $employmentStatus=='Regular'?'selected':''; ?>>Regular</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="evaluation_status" class="form-label mb-1">Evaluation Status</label>
                            <select class="form-select" id="evaluation_status" name="evaluation_status">
                                <option value="all" <?php echo $evaluationStatus=='all'?'selected':''; ?>>All Evaluations</option>
                                <option value="Due" <?php echo $evaluationStatus=='Due'?'selected':''; ?>>Due</option>
                                <option value="Overdue" <?php echo $evaluationStatus=='Overdue'?'selected':''; ?>>Overdue</option>
                                <option value="Completed" <?php echo $evaluationStatus=='Completed'?'selected':''; ?>>Completed</option>
                                <option value="Not Yet Started" <?php echo $evaluationStatus=='Not Yet Started'?'selected':''; ?>>Not Yet Started</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location" class="form-label mb-1">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="" <?php echo $locationFilter===''?'selected':''; ?>>All Locations</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locationFilter===$loc?'selected':''; ?>><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="guardSearch" class="form-label mb-1">Search Guard</label>
                            <input type="text" class="form-control" id="guardSearch" name="guardSearch" placeholder="Enter guard name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="btn-group ms-auto">
                            <button type="submit" class="btn btn-success" name="filter_submit" value="1"><span class="material-icons" style="vertical-align:middle;font-size:18px;">search</span><span class="ms-1">Apply</span></button>
                            <a href="performance_evaluation.php" class="btn btn-secondary ms-2"><span class="material-icons" style="vertical-align:middle;font-size:18px;">clear</span><span class="ms-1">Clear</span></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="material-icons">assessment</i> Guards Performance Evaluation</h5>
                    <div><button class="btn btn-success btn-sm" onclick="exportToExcel()"><i class="material-icons">download</i> Export Excel</button></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="performanceTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Hired Date</th>
                                    <th>Employment Status</th>
                                    <th>Total Evaluations</th>
                                    <th>Last Evaluation</th>
                                    <th>Evaluation Status</th>
                                    <th>Next Due</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredGuards as $guard): ?>
                                    <?php 
                                        $rowClass = in_array($guard['evaluation_status'], ['Due','Overdue'], true) ? 'table-danger' : '';
                                        $statusBadgeClass = $guard['employment_status'] === 'Probationary' ? 'bg-warning' : 'bg-info';
                                        $evalBadgeClass = match($guard['evaluation_status']) {
                                            'Due' => 'bg-warning',
                                            'Overdue' => 'bg-danger',
                                            'Completed' => 'bg-success',
                                            'Not Yet Started' => 'bg-secondary',
                                            default => 'bg-secondary'
                                        };
                                        $fullName = trim(($guard['First_Name'] ?? '').' '.($guard['middle_name'] ?? '').' '.($guard['Last_Name'] ?? ''));
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo htmlspecialchars($guard['employee_id'] ?: sprintf('EMP%04d', $guard['User_ID'])); ?></td>
                                        <td><?php echo htmlspecialchars($fullName); ?></td>
                                        <td><?php echo htmlspecialchars($guard['location_name'] ?: 'Not Assigned'); ?></td>
                                        <td><?php echo $guard['effective_hired_date'] ? date('M d, Y', strtotime($guard['effective_hired_date'])) : 'Not Set'; ?></td>
                                        <td><span class="badge <?php echo $statusBadgeClass; ?>"><?php echo $guard['employment_status']; ?></span></td>
                                        <td class="text-center"><?php echo (int)$guard['total_evaluations']; ?></td>
                                        <td><?php echo $guard['last_evaluation_date'] ? date('M d, Y', strtotime($guard['last_evaluation_date'])) : 'Never'; ?></td>
                                        <td><span class="badge <?php echo $evalBadgeClass; ?>"><?php echo $guard['evaluation_status']; ?></span></td>
                                        <td><?php echo $guard['next_due_date'] ? $guard['next_due_date']->format('M d, Y') : 'N/A'; ?></td>
                                        <td>
                                            <?php if (!empty($guard['overall_rating'])): ?>
                                                <span class="badge bg-secondary"><?php echo number_format((float)$guard['overall_rating'], 1); ?>%</span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($guard['last_evaluation_date'])): ?>
                                                <a class="btn btn-sm btn-info" href="view_evaluation.php?user_id=<?php echo (int)$guard['User_ID']; ?>">
                                                    <i class="material-icons">visibility</i> View
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="material-icons">visibility_off</i> No Result
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

    <script>
        $(function(){
            $('#performanceTable').DataTable({
                responsive:true,
                order:[[7,'desc'],[4,'asc']],
                pageLength:25,
                dom:'frtip',
                buttons:[{extend:'excel',text:'<i class="material-icons">download</i> Export Excel',className:'btn btn-success btn-sm',exportOptions:{columns:[0,1,2,3,4,5,6,7,8,9]}}],
                columnDefs:[{targets:[10],orderable:false},{targets:[4,7],className:'text-center'}]
            });
        });
        function exportToExcel(){ const t=$('#performanceTable').DataTable(); t.button('.buttons-excel').trigger(); }
        // Optional toast for due/overdue
        (function(){
            const due = <?php echo (int)$dueCount; ?>, over = <?php echo (int)$overdueCount; ?>;
            if(due>0){
                const Toast = Swal.mixin({
                    toast:true,
                    position:'top-end',
                    showConfirmButton:false,
                    showCloseButton:true,
                    timer:5000,
                    timerProgressBar:true
                });
                let msg = `You have ${due} performance evaluation${due>1?'s':''} pending.`;
                if(over>0) msg += ` ${over} of them ${over>1?'are':'is'} overdue!`;
                Toast.fire({icon: over>0 ? 'error':'warning', title:'Pending Evaluations', text: msg});
            }
        })();
    </script>

    <script src="js/superadmin_dashboard.js"></script>

    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="admin_dashboard.php" class="mobile-nav-item"><span class="material-icons">dashboard</span><span class="mobile-nav-text">Dashboard</span></a>

            <a href="leave_request.php" class="mobile-nav-item"><span class="material-icons">event_note</span><span class="mobile-nav-text">Leave Request</span></a>
            <a href="recruitment.php" class="mobile-nav-item"><span class="material-icons">person_search</span><span class="mobile-nav-text">Recruitment</span></a>
            <a href="performance_evaluation.php" class="mobile-nav-item active"><span class="material-icons">assessment</span><span class="mobile-nav-text">Performance Evaluation</span></a>
            <a href="users_list.php" class="mobile-nav-item"><span class="material-icons">people</span><span class="mobile-nav-text">Masterlist</span></a>
            <a href="daily_time_record.php" class="mobile-nav-item"><span class="material-icons">schedule</span><span class="mobile-nav-text">Daily Time Record</span></a>
            <a href="archives.php" class="mobile-nav-item"><span class="material-icons">archive</span><span class="mobile-nav-text">Archives</span></a>
            <a href="logs.php" class="mobile-nav-item"><span class="material-icons">receipt_long</span><span class="mobile-nav-text">Logs</span></a>
            <a href="../logout.php" class="mobile-nav-item"><span class="material-icons">logout</span><span class="mobile-nav-text">Logout</span></a>
        </div>
    </div>
</body>
</html>