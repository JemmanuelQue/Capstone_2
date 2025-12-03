<?php
// Start session before any output and enforce access like other accounting pages
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../includes/session_check.php';
if (!validateSession($conn, 4)) { exit(); }
require_once __DIR__ . '/payroll_calculation/unified_employer_calculator.php';

// Filters: default to current month
$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$endDate   = isset($_GET['end'])   ? $_GET['end']   : date('Y-m-t');
$locationId = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : null;

// Fetch active locations for filter dropdown (copying layout behavior from other accounting pages)
$locations = [];
try {
    $sql = "SELECT l.location_id, l.location_name
            FROM locations l
            WHERE l.is_active = 1
            ORDER BY l.location_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $locations = [];
}

$calc = new EmployerContributionsCalculator($conn);
$data = $calc->compute($startDate, $endDate, ['location_id'=>$locationId]);
$employees = $data['employees'];
$totals = $data['totals'];

function fmt($n) { return number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employer Contributions</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <!-- Accounting shared CSS -->
    <link rel="stylesheet" href="css/accounting_dashboard.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: Arial, sans-serif; }
        .page-wrapper { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 16px 24px; }
        .container { max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th:nth-child(1), td:nth-child(1) { text-align: left; }
        thead { background: #f7f7f7; }
        .filters { display: flex; gap: 12px; align-items: center; margin: 16px 0; }
        .filters .actions { margin-left: auto; }
        .summary { font-weight: bold; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include_once __DIR__ . '/../includes/accounting_sidebar.php'; ?>
    <div class="content" id="main-content">
        <?php include_once __DIR__ . '/../includes/accounting_header.php'; ?>
<div class="container">
    <h2>Employer Contributions</h2>

    <form class="filters" method="get">
        <div>
            <label>Start:</label>
            <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div>
            <label>End:</label>
            <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div>
            <label>Location:</label>
            <select name="location_id">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['location_id'] ?>" <?= ($locationId === (int)$loc['location_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['location_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button type="submit">Apply Filters</button>
        </div>
    </form>

    <h3>SSS Contributions</h3>
    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>SSS EE</th>
                <th>SSS ER</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['name']) ?></td>
                <td><?= fmt($e['sss_ee']) ?></td>
                <td><?= fmt($e['sss_er']) ?></td>
                <td><?= fmt($e['sss_total']) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="summary">
                <td>TOTAL</td>
                <td><?= fmt($totals['sss_ee']) ?></td>
                <td><?= fmt($totals['sss_er']) ?></td>
                <td><?= fmt($totals['sss_total']) ?></td>
            </tr>
        </tbody>
    </table>

    <h3>PhilHealth Contributions</h3>
    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>PH EE</th>
                <th>PH ER</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['name']) ?></td>
                <td><?= fmt($e['ph_ee']) ?></td>
                <td><?= fmt($e['ph_er']) ?></td>
                <td><?= fmt($e['ph_total']) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="summary">
                <td>TOTAL</td>
                <td><?= fmt($totals['ph_ee']) ?></td>
                <td><?= fmt($totals['ph_er']) ?></td>
                <td><?= fmt($totals['ph_total']) ?></td>
            </tr>
        </tbody>
    </table>

    <h3>Pag-IBIG Contributions</h3>
    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>HDMF EE</th>
                <th>HDMF ER</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['name']) ?></td>
                <td><?= fmt($e['hdmf_ee']) ?></td>
                <td><?= fmt($e['hdmf_er']) ?></td>
                <td><?= fmt($e['hdmf_total']) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="summary">
                <td>TOTAL</td>
                <td><?= fmt($totals['hdmf_ee']) ?></td>
                <td><?= fmt($totals['hdmf_er']) ?></td>
                <td><?= fmt($totals['hdmf_total']) ?></td>
            </tr>
        </tbody>
    </table>

    <h3>Full Monthly Remittance Summary</h3>
    <table>
        <thead>
            <tr>
                <th>Contribution Type</th>
                <th>EE Total</th>
                <th>ER Total</th>
                <th>Grand Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>SSS</td>
                <td><?= fmt($totals['sss_ee']) ?></td>
                <td><?= fmt($totals['sss_er']) ?></td>
                <td><?= fmt($totals['sss_total']) ?></td>
            </tr>
            <tr>
                <td>PhilHealth</td>
                <td><?= fmt($totals['ph_ee']) ?></td>
                <td><?= fmt($totals['ph_er']) ?></td>
                <td><?= fmt($totals['ph_total']) ?></td>
            </tr>
            <tr>
                <td>Pag-IBIG</td>
                <td><?= fmt($totals['hdmf_ee']) ?></td>
                <td><?= fmt($totals['hdmf_er']) ?></td>
                <td><?= fmt($totals['hdmf_total']) ?></td>
            </tr>
            <tr class="summary">
                <td>TOTAL REMITTANCE</td>
                <td><?= fmt($totals['sss_ee'] + $totals['ph_ee'] + $totals['hdmf_ee']) ?></td>
                <td><?= fmt($totals['sss_er'] + $totals['ph_er'] + $totals['hdmf_er']) ?></td>
                <td><?= fmt($totals['sss_total'] + $totals['ph_total'] + $totals['hdmf_total']) ?></td>
            </tr>
        </tbody>
    </table>
</div>
        <?php include_once __DIR__ . '/../includes/accounting_mobile_nav.php'; ?>
    </div>
</div>
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/accounting_common.js"></script>
</body>
</html>
