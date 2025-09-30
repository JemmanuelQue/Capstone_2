<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);
require_once '../db_connection.php';
require '../vendor/autoload.php'; // For dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// Get filters from request (same as logs.php)
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$exportType = isset($_GET['export']) ? $_GET['export'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($activityType)) {
    $conditions[] = "al.Activity_Type = ?";
    $params[] = $activityType;
}

if (!empty($dateFrom)) {
    $formattedDateFrom = date('Y-m-d', strtotime($dateFrom));
    $conditions[] = "DATE(al.Timestamp) >= ?";
    $params[] = $formattedDateFrom;
}

if (!empty($dateTo)) {
    $formattedDateTo = date('Y-m-d', strtotime($dateTo));
    $conditions[] = "DATE(al.Timestamp) <= ?";
    $params[] = $formattedDateTo;
}

if (!empty($searchTerm)) {
    $conditions[] = "(al.Activity_Details LIKE ? OR u.First_Name LIKE ? OR u.Last_Name LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Fetch all logs
$query = "
    SELECT al.*, u.First_Name, u.Last_Name, u.Role_ID, r.Role_Name 
    FROM activity_logs al
    LEFT JOIN users u ON al.User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    $whereClause
    ORDER BY al.Timestamp DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($exportType === 'excel') {
    // Create CSV file (Excel compatible)
    $filename = 'activity_logs_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, ['Date & Time', 'User', 'Role', 'Activity Type', 'Details']);
    
    // Add data
    foreach ($logs as $log) {
        $userName = (isset($log['First_Name']) && isset($log['Last_Name'])) 
            ? $log['First_Name'] . ' ' . $log['Last_Name'] 
            : 'System';
        $roleName = $log['Role_Name'] ?? 'Unknown';
        
        fputcsv($output, [
            date('M d, Y g:i A', strtotime($log['Timestamp'])),
            $userName,
            $roleName,
            $log['Activity_Type'],
            $log['Activity_Details']
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($exportType === 'pdf') {
    // Create PDF file using dompdf
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Create HTML content
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; color: #dc3545; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
            .filters { margin-bottom: 20px; font-size: 12px; }
            .filters h4 { color: #333; font-size: 14px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; font-size: 10px; }
            th { background-color: #dc3545; color: white; padding: 8px; text-align: center; border: 1px solid #ccc; }
            td { padding: 6px; border: 1px solid #ccc; text-align: left; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .activity-badge { padding: 2px 6px; border-radius: 3px; color: white; font-size: 9px; }
            .bg-danger { background-color: #dc3545; }
            .bg-warning { background-color: #ffc107; color: black; }
            .bg-success { background-color: #28a745; }
            .bg-info { background-color: #17a2b8; }
            .bg-primary { background-color: #007bff; }
        </style>
    </head>
    <body>
        <div class="header">Activity Logs Report</div>';
    
    // Add filters info if any
    if (!empty($activityType) || !empty($dateFrom) || !empty($dateTo) || !empty($searchTerm)) {
        $html .= '<div class="filters"><h4>Applied Filters:</h4>';
        
        if (!empty($activityType)) {
            $html .= '<p>• Activity Type: ' . htmlspecialchars($activityType) . '</p>';
        }
        if (!empty($dateFrom)) {
            $html .= '<p>• From Date: ' . date('F j, Y', strtotime($dateFrom)) . '</p>';
        }
        if (!empty($dateTo)) {
            $html .= '<p>• To Date: ' . date('F j, Y', strtotime($dateTo)) . '</p>';
        }
        if (!empty($searchTerm)) {
            $html .= '<p>• Search Term: ' . htmlspecialchars($searchTerm) . '</p>';
        }
        $html .= '</div>';
    }
    
    // Table
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Activity Type</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($logs as $log) {
        $userName = (isset($log['First_Name']) && isset($log['Last_Name'])) 
            ? $log['First_Name'] . ' ' . $log['Last_Name'] 
            : 'System';
        $roleName = $log['Role_Name'] ?? 'Unknown';
        
        // Determine badge class for activity type
        $activityType = $log['Activity_Type'];
        $badgeClass = 'bg-primary';
        
        if (strpos($activityType, 'Delete') !== false) {
            $badgeClass = 'bg-danger';
        } elseif (strpos($activityType, 'Archive') !== false) {
            $badgeClass = 'bg-warning';
        } elseif (strpos($activityType, 'Recovery') !== false || strpos($activityType, 'Recover') !== false) {
            $badgeClass = 'bg-success';
        } elseif (strpos($activityType, 'Update') !== false || strpos($activityType, 'Edit') !== false) {
            $badgeClass = 'bg-info';
        } elseif (strpos($activityType, 'Create') !== false || strpos($activityType, 'Add') !== false) {
            $badgeClass = 'bg-success';
        }
        
        $html .= '<tr>
            <td>' . date('M d, Y g:i A', strtotime($log['Timestamp'])) . '</td>
            <td>' . htmlspecialchars($userName) . '</td>
            <td>' . htmlspecialchars($roleName) . '</td>
            <td><span class="activity-badge ' . $badgeClass . '">' . htmlspecialchars($activityType) . '</span></td>
            <td>' . htmlspecialchars($log['Activity_Details']) . '</td>
        </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </body>
    </html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Output PDF
    $filename = 'activity_logs_' . date('Y-m-d_H-i-s') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

// If no valid export type, redirect back
header('Location: logs.php');
exit;
?>