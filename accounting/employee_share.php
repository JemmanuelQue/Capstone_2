<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/session_check.php';

// Require Accounting role (4)
if (!validateSession($conn, 4)) {
    exit();
}

// Load profile data for header safely
$profileData = [
    'First_Name' => $_SESSION['first_name'] ?? ($_SESSION['username'] ?? 'Accounting'),
    'Last_Name' => $_SESSION['last_name'] ?? '',
    'Profile_Pic' => '../images/default_profile.png'
];
try {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!empty($row['First_Name'])) { $profileData['First_Name'] = $row['First_Name']; }
            if (!empty($row['Last_Name'])) { $profileData['Last_Name'] = $row['Last_Name']; }
            if (!empty($row['Profile_Pic']) && file_exists($row['Profile_Pic'])) {
                $profileData['Profile_Pic'] = $row['Profile_Pic'];
            }
        }
    }
} catch (Exception $e) {
    // Ignore profile load errors and keep defaults
}

// Utilities
function sanitizeCurrency($value) {
    $v = preg_replace('/[^0-9.\-]/', '', (string)$value);
    return is_numeric($v) ? round((float)$v, 2) : 0.00;
}

function fetchSSSRows(PDO $conn, $effectiveDate = null, $search = null) {
    $sql = "SELECT * FROM sss_contribution_table WHERE 1=1";
    $params = [];
    if (!empty($effectiveDate)) {
        $sql .= " AND effective_date = ?";
        $params[] = $effectiveDate;
    }
    if (!empty($search)) {
        $sql .= " AND (msc_total LIKE ? OR range_min LIKE ? OR range_max LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY effective_date DESC, range_min ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadDistinctEffectiveDates(PDO $conn) {
    $stmt = $conn->query("SELECT DISTINCT effective_date FROM sss_contribution_table ORDER BY effective_date DESC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// CRUD Handlers
$action = $_POST['action'] ?? null;
$message = null;
$error = null;

try {
    if ($action === 'create' || $action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $range_min = sanitizeCurrency($_POST['range_min'] ?? 0);
        $range_max = sanitizeCurrency($_POST['range_max'] ?? 0);
        // monthly_salary_credit removed in Option B schema; rely on msc_* fields
        $msc_reg = sanitizeCurrency($_POST['msc_regular_ss'] ?? null);
        $msc_ec = sanitizeCurrency($_POST['msc_ec'] ?? null);
        $msc_mpf = sanitizeCurrency($_POST['msc_mpf'] ?? null);
        $msc_total = sanitizeCurrency($_POST['msc_total'] ?? null);
        $reg_er = sanitizeCurrency($_POST['employer_regular_ss'] ?? 0);
        $reg_ee = sanitizeCurrency($_POST['employee_regular_ss'] ?? 0);
        $mpf_er = sanitizeCurrency($_POST['employer_mpf'] ?? 0);
        $mpf_ee = sanitizeCurrency($_POST['employee_mpf'] ?? 0);
        $ec = sanitizeCurrency($_POST['employer_ec'] ?? 0);
        $total_er = sanitizeCurrency($_POST['employer_total'] ?? 0);
        $total_ee = sanitizeCurrency($_POST['employee_total'] ?? 0);
        $total = sanitizeCurrency($_POST['total_contribution'] ?? 0);
        $effective_date = $_POST['effective_date'] ?? date('Y-m-d');

        if ($range_min > $range_max) {
            throw new Exception('Range min must be less than or equal to Range max.');
        }
        if (empty($effective_date)) {
            throw new Exception('Effective date is required.');
        }

        // Auto-derive MSC total if parts provided and total not specified
        if (($msc_total === 0.0 || $msc_total === null) && ($msc_reg !== null || $msc_ec !== null || $msc_mpf !== null)) {
            $msc_total = round(($msc_reg ?? 0) + ($msc_ec ?? 0) + ($msc_mpf ?? 0), 2);
        }

        if ($action === 'create') {
            $sql = "INSERT INTO sss_contribution_table 
                (range_min, range_max, msc_regular_ss, msc_ec, msc_mpf, msc_total, employer_regular_ss, employee_regular_ss, employer_mpf, employee_mpf, employer_ec, employer_total, employee_total, total_contribution, effective_date)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$range_min, $range_max, $msc_reg, $msc_ec, $msc_mpf, $msc_total, $reg_er, $reg_ee, $mpf_er, $mpf_ee, $ec, $total_er, $total_ee, $total, $effective_date]);
            $message = 'SSS bracket row created.';
        } else {
            $sql = "UPDATE sss_contribution_table SET 
                range_min=?, range_max=?, msc_regular_ss=?, msc_ec=?, msc_mpf=?, msc_total=?, employer_regular_ss=?, employee_regular_ss=?, employer_mpf=?, employee_mpf=?, employer_ec=?, employer_total=?, employee_total=?, total_contribution=?, effective_date=?
                WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$range_min, $range_max, $msc_reg, $msc_ec, $msc_mpf, $msc_total, $reg_er, $reg_ee, $mpf_er, $mpf_ee, $ec, $total_er, $total_ee, $total, $effective_date, $id]);
            $message = 'SSS bracket row updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM sss_contribution_table WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'SSS bracket row deleted.';
    }
} catch (Exception $ex) {
    $error = $ex->getMessage();
}

// Import result messages via query params
if (!$error && isset($_GET['import'])) {
    if ($_GET['import'] === 'success') {
        $inserted = (int)($_GET['inserted'] ?? 0);
        $updated = (int)($_GET['updated'] ?? 0);
        $message = "Imported successfully: $inserted inserted, $updated updated.";
    } elseif ($_GET['import'] === 'error') {
        $error = $_GET['reason'] ? htmlspecialchars($_GET['reason']) : 'Import failed.';
    }
}

// Filters
$filterEffective = $_GET['effective_date'] ?? '';
$search = $_GET['search'] ?? '';
$rows = fetchSSSRows($conn, $filterEffective, $search);
$effectiveDates = loadDistinctEffectiveDates($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SSS Employer/Employee Share - Green Meadows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/accounting_dashboard.css">
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name"><div>SECURITY AGENCY</div></div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a href="accounting_dashboard.php" class="nav-link" title="Dashboard"><span class="material-icons">dashboard</span><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="daily_time_record.php" class="nav-link" title="Daily Time Record"><span class="material-icons">schedule</span><span>Daily Time Record</span></a></li>
            <li class="nav-item"><a href="tardiness.php" class="nav-link" title="Tardiness Report"><span class="material-icons">timer</span><span>Tardiness Report</span></a></li>
            <li class="nav-item"><a href="payroll.php" class="nav-link" title="Payroll"><span class="material-icons">payments</span><span>Payroll</span></a></li>
            <li class="nav-item"><a href="payroll_register.php" class="nav-link" title="Payroll"><span class="material-icons">receipt_long</span><span>Payroll Register</span></a></li>
            <li class="nav-item"><a href="rate_locations.php" class="nav-link" title="Rate per Locations"><span class="material-icons">attach_money</span><span>Rate per Locations</span></a></li>
            <li class="nav-item"><a href="calendar.php" class="nav-link" title="Calendar"><span class="material-icons">date_range</span><span>Calendar</span></a></li>
            <li class="nav-item"><a href="masterlist.php" class="nav-link" title="Masterlist"><span class="material-icons">assignment</span><span>Masterlist</span></a></li>
            <li class="nav-item"><a href="archives.php" class="nav-link" title="Archives"><span class="material-icons">archive</span><span>Archives</span></a></li>
            <li class="nav-item"><a href="logs.php" class="nav-link" title="Logs"><span class="material-icons">receipt_long</span><span>Logs</span></a></li>
            <li class="nav-item"><a href="employee_share.php" class="nav-link active" title="Employee Share"><span class="material-icons">diversity_3</span><span>Employer Contributions</span></a></li>
            <li class="nav-item mt-5"><a href="../logout.php" class="nav-link" title="Logout"><span class="material-icons">logout</span><span>Logout</span></a></li>
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
                <span><?php echo htmlspecialchars(($profileData['First_Name'] ?? 'Accounting') . ' ' . ($profileData['Last_Name'] ?? '')); ?></span>
                <a href="profile.php"><img src="<?php echo htmlspecialchars($profileData['Profile_Pic'] ?? '../images/default_profile.png'); ?>" alt="User Profile"></a>
            </div>
        </div>

        <div class="container-fluid mt-4">
            <h2 class="mb-4"><center>SSS Contribution Brackets (Editable)</center></h2>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="dashboard-card bg-white">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>Manage SSS Contribution Table</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <span class="material-icons-outlined" style="vertical-align: middle;">file_upload</span> Import Excel
                        </button>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
                            <span class="material-icons-outlined" style="vertical-align: middle;">add</span> Add Row
                        </button>
                    </div>
                </div>
                <hr class="divider bg-primary">
                <form class="row g-3 p-3" method="get">
                    <div class="col-md-3">
                        <label class="form-label">Effective Date</label>
                        <select name="effective_date" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($effectiveDates as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($filterEffective === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search MSC or range" />
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary me-2" type="submit">Apply</button>
                        <a class="btn btn-outline-secondary" href="employee_share.php">Reset</a>
                    </div>
                </form>

                <style>
                    /* Header band colors similar to the schedule image */
                    thead .band-title { background-color: #1f4e92; color: #fff; }
                    thead .band-subtitle { background-color: #2385c7; color: #fff; }
                    thead th { border-right: 1px solid #cfd8dc !important; }
                    tbody td { border-right: 1px solid #e0e0e0 !important; }
                </style>
                <div class="table-responsive p-3">
                    <table class="table table-striped table-hover align-middle table-bordered">
                        <thead>
                            <tr>
                                <th rowspan="3" class="text-center align-middle band-title">Range of Compensation<br/><small>(Range Min / Range Max)</small></th>
                                <th colspan="3" class="text-center band-title">Monthly Salary Credit</th>
                                <th colspan="4" class="text-center band-title">Amount of Contributions — Employer</th>
                                <th colspan="3" class="text-center band-title">Amount of Contributions — Employee</th>
                                <th rowspan="3" class="text-center align-middle band-title">TOTAL</th>
                                <th rowspan="3" class="text-center align-middle band-title">Effective Date</th>
                                <th rowspan="3" class="text-center align-middle band-title">Actions</th>
                            </tr>
                            <tr>
                                <th class="text-center band-subtitle">
                                    <div>Regular SS</div>
                                    <div style="border-top: 1px solid #cfd8dc; margin-top:4px; padding-top:4px;">EC</div>
                                </th>
                                <th class="text-center band-subtitle">MPF</th>
                                <th class="text-center band-subtitle">TOTAL</th>
                                <th class="text-center band-subtitle">Regular SS</th>
                                <th class="text-center band-subtitle">MPF</th>
                                <th class="text-center band-subtitle">EC</th>
                                <th class="text-center band-subtitle">TOTAL</th>
                                <th class="text-center band-subtitle">Regular SS</th>
                                <th class="text-center band-subtitle">MPF</th>
                                <th class="text-center band-subtitle">TOTAL</th>
                            </tr>
                            <!-- Removed extra guide row to eliminate blank white space -->
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="13" class="text-center text-muted">No rows found. Add a new bracket.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo number_format($r['range_min'], 2); ?> – <?php echo number_format($r['range_max'], 2); ?></td>
                                        <!-- Monthly Salary Credit breakdown with stacked Regular SS / EC -->
                                        <td class="text-center">
                                            <div><?php echo isset($r['msc_regular_ss']) ? number_format($r['msc_regular_ss'], 2) : '—'; ?></div>
                                            <div style="border-top:1px solid #e0e0e0; margin-top:4px; padding-top:4px;">
                                                <?php
                                                    if (isset($r['msc_ec'])) {
                                                        echo ((float)$r['msc_ec'] > 0) ? number_format($r['msc_ec'], 2) : '';
                                                    } else {
                                                        echo '—';
                                                    }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo isset($r['msc_mpf']) ? number_format($r['msc_mpf'], 2) : '—'; ?></td>
                                        <td class="text-center"><?php 
                                            $mscTotal = $r['msc_total'] ?? null; 
                                            echo ($mscTotal !== null && $mscTotal !== '') ? number_format($mscTotal, 2) : '—';
                                        ?></td>
                                        <!-- Employer columns -->
                                        <td class="text-center"><?php echo number_format($r['employer_regular_ss'], 2); ?></td>
                                        <td class="text-center"><?php echo number_format($r['employer_mpf'], 2); ?></td>
                                        <td class="text-center"><?php echo number_format($r['employer_ec'], 2); ?></td>
                                        <td class="text-center"><?php echo number_format($r['employer_total'], 2); ?></td>
                                        <!-- Employee columns -->
                                        <td class="text-center"><?php echo number_format($r['employee_regular_ss'], 2); ?></td>
                                        <td class="text-center"><?php echo number_format($r['employee_mpf'], 2); ?></td>
                                        <td class="text-center"><?php echo number_format($r['employee_total'], 2); ?></td>
                                        <!-- Overall total -->
                                        <td class="text-center"><?php echo number_format($r['total_contribution'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($r['effective_date']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                data-id="<?php echo (int)$r['id']; ?>"
                                                data-range_min="<?php echo htmlspecialchars($r['range_min']); ?>"
                                                data-range_max="<?php echo htmlspecialchars($r['range_max']); ?>"
                                                data-msc="<?php echo htmlspecialchars($r['msc_total'] ?? ''); ?>"
                                                data-msc_reg="<?php echo htmlspecialchars($r['msc_regular_ss'] ?? ''); ?>"
                                                data-msc_ec="<?php echo htmlspecialchars($r['msc_ec'] ?? ''); ?>"
                                                data-msc_mpf="<?php echo htmlspecialchars($r['msc_mpf'] ?? ''); ?>"
                                                data-msc_total="<?php echo htmlspecialchars($r['msc_total'] ?? ''); ?>"
                                                data-reg_er="<?php echo htmlspecialchars($r['employer_regular_ss']); ?>"
                                                data-mpf_er="<?php echo htmlspecialchars($r['employer_mpf']); ?>"
                                                data-ec="<?php echo htmlspecialchars($r['employer_ec']); ?>"
                                                data-total_er="<?php echo htmlspecialchars($r['employer_total']); ?>"
                                                data-reg_ee="<?php echo htmlspecialchars($r['employee_regular_ss']); ?>"
                                                data-mpf_ee="<?php echo htmlspecialchars($r['employee_mpf']); ?>"
                                                data-total_ee="<?php echo htmlspecialchars($r['employee_total']); ?>"
                                                data-total="<?php echo htmlspecialchars($r['total_contribution']); ?>"
                                                data-effective_date="<?php echo htmlspecialchars($r['effective_date']); ?>">
                                                Edit
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this row?');">
                                                <input type="hidden" name="action" value="delete" />
                                                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>" />
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create Modal -->
            <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add SSS Bracket Row</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="create" />
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Range Min</label>
                                        <input type="number" step="0.01" name="range_min" class="form-control" required />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Range Max</label>
                                        <input type="number" step="0.01" name="range_max" class="form-control" required />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">MSC (TOTAL)</label>
                                        <input type="number" step="0.01" name="monthly_salary_credit" class="form-control" />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">MSC Regular SS</label>
                                        <input type="number" step="0.01" name="msc_regular_ss" class="form-control" />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">MSC EC</label>
                                        <input type="number" step="0.01" name="msc_ec" class="form-control" />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">MSC MPF</label>
                                        <input type="number" step="0.01" name="msc_mpf" class="form-control" />
                                    </div>

                                    <div class="col-md-3"><label class="form-label">Regular SS ER</label><input type="number" step="0.01" name="regular_ss_employer" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Regular SS EE</label><input type="number" step="0.01" name="regular_ss_employee" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">MPF ER</label><input type="number" step="0.01" name="mpf_employer" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">MPF EE</label><input type="number" step="0.01" name="mpf_employee" class="form-control" required /></div>

                                    <div class="col-md-3"><label class="form-label">EC Contribution</label><input type="number" step="0.01" name="ec_contribution" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Total ER</label><input type="number" step="0.01" name="total_employer" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Total EE</label><input type="number" step="0.01" name="total_employee" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Total</label><input type="number" step="0.01" name="total_contribution" class="form-control" required /></div>

                                    <div class="col-md-4"><label class="form-label">Effective Date</label><input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required /></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Import Modal -->
            <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Bulk Import SSS Contribution Table</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                Download the Excel template, fill rows, then upload. Headers must not be changed.
                            </div>
                            <div class="mb-3">
                                <a href="download_sss_template.php" class="btn btn-outline-primary btn-sm">
                                    <span class="material-icons-outlined" style="vertical-align: middle;">download</span>
                                    Download Excel Template (.xlsx)
                                </a>
                            </div>
                            <form id="importForm" action="import_sss_contributions.php" method="post" enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="sss_file" class="form-label">Upload Filled Excel File (.xlsx)</label>
                                        <input type="file" class="form-control" id="sss_file" name="sss_file" accept=".xlsx" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="effective_date_override" class="form-label">Effective Date (fallback)</label>
                                        <input type="date" class="form-control" id="effective_date_override" name="effective_date_override">
                                    </div>
                                    <div class="col-md-12 form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="update_existing" name="update_existing" checked>
                                        <label class="form-check-label" for="update_existing">Update rows if the same Range and Effective Date already exist</label>
                                    </div>
                                </div>
                            </form>
                            <hr>
                            <div>
                                <strong>Template columns (in order):</strong>
                                <div class="small text-muted mt-1">
                                    range_min, range_max, msc_regular_ss, msc_ec, msc_mpf, msc_total, employer_regular_ss, employee_regular_ss, employer_mpf, employee_mpf, employer_ec, employer_total, employee_total, total_contribution, effective_date
                                </div>
                                <div class="mt-2">
                                    <em>Sample row:</em>
                                    <code>0, 5249.99, 5000, 0, 0, 5000, 500, 250, 0, 0, 10, 510, 250, 760, 2025-01-01</code>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" form="importForm" class="btn btn-success">
                                <span class="material-icons-outlined" style="vertical-align: middle;">upload</span>
                                Import
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit SSS Bracket Row</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="update" />
                            <input type="hidden" name="id" id="edit_id" />
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Range Min</label><input type="number" step="0.01" name="range_min" id="edit_range_min" class="form-control" required /></div>
                                    <div class="col-md-4"><label class="form-label">Range Max</label><input type="number" step="0.01" name="range_max" id="edit_range_max" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">MSC (TOTAL)</label><input type="number" step="0.01" name="monthly_salary_credit" id="edit_msc" class="form-control" /></div>
                                    <div class="col-md-3"><label class="form-label">MSC Regular SS</label><input type="number" step="0.01" name="msc_regular_ss" id="edit_msc_reg" class="form-control" /></div>
                                    <div class="col-md-3"><label class="form-label">MSC EC</label><input type="number" step="0.01" name="msc_ec" id="edit_msc_ec" class="form-control" /></div>
                                    <div class="col-md-3"><label class="form-label">MSC MPF</label><input type="number" step="0.01" name="msc_mpf" id="edit_msc_mpf" class="form-control" /></div>

                                    <div class="col-md-3"><label class="form-label">Regular SS ER</label><input type="number" step="0.01" name="regular_ss_employer" id="edit_reg_er" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Regular SS EE</label><input type="number" step="0.01" name="regular_ss_employee" id="edit_reg_ee" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">MPF ER</label><input type="number" step="0.01" name="mpf_employer" id="edit_mpf_er" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">MPF EE</label><input type="number" step="0.01" name="mpf_employee" id="edit_mpf_ee" class="form-control" required /></div>

                                    <div class="col-md-3"><label class="form-label">EC Contribution</label><input type="number" step="0.01" name="ec_contribution" id="edit_ec" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Total ER</label><input type="number" step="0.01" name="total_employer" id="edit_total_er" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Total EE</label><input type="number" step="0.01" name="total_employee" id="edit_total_ee" class="form-control" required /></div>
                                    <div class="col-md-3"><label class="form-label">Total</label><input type="number" step="0.01" name="total_contribution" id="edit_total" class="form-control" required /></div>

                                    <div class="col-md-4"><label class="form-label">Effective Date</label><input type="date" name="effective_date" id="edit_effective_date" class="form-control" required /></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Update</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
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
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
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
            <a href="employee_share.php" class="mobile-nav-item active">
                <span class="material-icons">diversity_3</span>
                <span class="mobile-nav-text">Employee Share</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Fill edit modal with row data
    var editModal = document.getElementById('editModal');
    editModal && editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_range_min').value = button.getAttribute('data-range_min');
        document.getElementById('edit_range_max').value = button.getAttribute('data-range_max');
        document.getElementById('edit_msc').value = button.getAttribute('data-msc');
        var mscReg = document.getElementById('edit_msc_reg');
        var mscEc = document.getElementById('edit_msc_ec');
        var mscMpf = document.getElementById('edit_msc_mpf');
        if (mscReg) mscReg.value = button.getAttribute('data-msc_reg') || '';
        if (mscEc) mscEc.value = button.getAttribute('data-msc_ec') || '';
        if (mscMpf) mscMpf.value = button.getAttribute('data-msc_mpf') || '';
        document.getElementById('edit_reg_er').value = button.getAttribute('data-reg_er');
        document.getElementById('edit_reg_ee').value = button.getAttribute('data-reg_ee');
        document.getElementById('edit_mpf_er').value = button.getAttribute('data-mpf_er');
        document.getElementById('edit_mpf_ee').value = button.getAttribute('data-mpf_ee');
        document.getElementById('edit_ec').value = button.getAttribute('data-ec');
        document.getElementById('edit_total_er').value = button.getAttribute('data-total_er');
        document.getElementById('edit_total_ee').value = button.getAttribute('data-total_ee');
        document.getElementById('edit_total').value = button.getAttribute('data-total');
        document.getElementById('edit_effective_date').value = button.getAttribute('data-effective_date');
    });

    // Date/time
    function updateDateTime() {
        const now = new Date();
        const dateEl = document.getElementById('current-date');
        const timeEl = document.getElementById('current-time');
        if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    </script>
</body>
</html>
