<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 5)) { exit; }

// Add this section to fetch profile data
$userId = $_SESSION['user_id'];
$profileStmt = $conn->prepare("SELECT * FROM users WHERE User_ID = ?");
$profileStmt->execute([$userId]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Set default profile pic if none exists
if (!$profileData || empty($profileData['Profile_Pic']) || !file_exists($profileData['Profile_Pic'])) {
    $profileData['Profile_Pic'] = '../images/default_profile.png';
}

// Fetch performance evaluations for the current user
try {
    $evaluationStmt = $conn->prepare("
        SELECT 
            pe.evaluation_id,
            pe.evaluation_date,
            pe.overall_rating,
            pe.overall_performance,
            pe.recommendation,
            pe.contract_term,
            pe.other_recommendation,
            pe.evaluated_by,
            pe.client_representative,
            pe.gmsai_representative,
            pe.employee_name,
            pe.client_assignment,
            pe.position,
            pe.area_assigned,
            pe.evaluation_period,
            pe.status,
            pe.created_at,
            YEAR(pe.evaluation_date) as eval_year,
            MONTHNAME(pe.evaluation_date) as eval_month
        FROM performance_evaluations pe 
        WHERE pe.user_id = ? AND pe.status = 'Completed'
        ORDER BY pe.evaluation_date DESC
    ");
    $evaluationStmt->execute([$userId]);
    $evaluations = $evaluationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $evaluations = [];
    error_log("Error fetching evaluations: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Evaluation - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/datetime/1.5.1/css/dataTables.dateTime.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/guards_dashboard.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div>SECURITY AGENCY</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a href="guards_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="register_face.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Register Face">
                    <span class="material-icons">face</span>
                    <span>Register Face</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Attendance">
                    <span class="material-icons">schedule</span>
                    <span>Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payslip.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payslip">
                    <span class="material-icons">payments</span>
                    <span>Payslip</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Request Leave">
                    <span class="material-icons">event_note</span>
                    <span>Request Leave</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="view_evaluation.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">fact_check</span>
                    <span>Performance Evaluation</span>
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
                <span><?php echo $profileData['First_Name'] . ' ' . $profileData['Last_Name']; ?></span>
                <img src="<?php echo $profileData['Profile_Pic']; ?>" alt="User Profile">
            </a>
        </div>
        
        <!-- Performance Evaluation Container -->
        <div class="container-fluid mt-4">
            <div class="dashboard-card bg-white p-4 rounded shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">
                        <span class="material-icons me-2" style="vertical-align: middle;">fact_check</span>
                        My Performance Evaluations
                    </h4>
                </div>

                <!-- Date Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <label for="yearFilter" class="form-label">Filter by Year:</label>
                            <select id="yearFilter" class="form-select">
                                <option value="">All Years</option>
                                <?php
                                // Get available years from evaluations
                                $years = array_unique(array_column($evaluations, 'eval_year'));
                                rsort($years); // Sort descending
                                foreach ($years as $year) {
                                    echo "<option value='$year'>$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <label for="dateRange" class="form-label">Date Range:</label>
                            <input type="text" id="dateRange" class="form-control" placeholder="Select date range">
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-4 d-flex align-items-end">
                        <button type="button" id="clearFilters" class="btn btn-outline-secondary d-flex align-items-center">
                            <span class="material-icons me-1">clear</span>Clear All Filters
                        </button>
                    </div>
                </div>

                <!-- Evaluations Table -->
                <div class="table-responsive">
                    <?php if (empty($evaluations)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <span class="material-icons" style="font-size: 4rem; color: #dee2e6;">assignment</span>
                            </div>
                            <h5 class="text-muted">No Performance Evaluations Found</h5>
                            <p class="text-muted">You don't have any completed performance evaluations yet.</p>
                        </div>
                    <?php else: ?>
                        <table id="evaluationsTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Year</th>
                                    <th>Evaluation Date</th>
                                    <th>Overall Rating</th>
                                    <th>Performance</th>
                                    <th>Recommendation</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluations as $evaluation): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $evaluation['eval_year']; ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo date('F j, Y', strtotime($evaluation['evaluation_date'])); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $evaluation['eval_month']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($evaluation['overall_rating']): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-<?php 
                                                        $rating = (float)$evaluation['overall_rating'];
                                                        if ($rating >= 90.00) echo 'success';
                                                        elseif ($rating >= 85.00) echo 'primary';
                                                        elseif ($rating >= 80.00) echo 'info';
                                                        elseif ($rating >= 75.00) echo 'warning';
                                                        else echo 'danger';
                                                    ?> me-2">
                                                        <?php echo number_format($evaluation['overall_rating'], 2); ?>%
                                                    </span>
                                                    <div class="rating-stars">
                                                        <?php
                                                        // Convert percentage to 5-star scale for display
                                                        $starRating = ($rating / 100) * 5;
                                                        $fullStars = floor($starRating);
                                                        $hasHalfStar = ($starRating - $fullStars) >= 0.5;
                                                        
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            if ($i <= $fullStars) {
                                                                echo '<span class="material-icons text-warning" style="font-size: 16px;">star</span>';
                                                            } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                                                                echo '<span class="material-icons text-warning" style="font-size: 16px;">star_half</span>';
                                                            } else {
                                                                echo '<span class="material-icons text-muted" style="font-size: 16px;">star_border</span>';
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($evaluation['overall_performance']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($evaluation['overall_performance']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($evaluation['recommendation']): ?>
                                                <span class="badge bg-<?php 
                                                    echo $evaluation['recommendation'] == 'renewal' ? 'success' : 
                                                        ($evaluation['recommendation'] == 'termination' ? 'danger' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($evaluation['recommendation']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $evaluation['status']; ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary view-evaluation" 
                                                    data-evaluation-id="<?php echo $evaluation['evaluation_id']; ?>"
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <span class="material-icons" style="font-size: 16px;">visibility</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Evaluation Details Modal -->
        <div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="evaluationModalLabel">
                            <span class="material-icons me-2">fact_check</span>Performance Evaluation Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="evaluationContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="guards_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="register_face.php" class="mobile-nav-item">
                <span class="material-icons">face</span>
                <span class="mobile-nav-text">Register Face</span>
            </a>
            <a href="attendance.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Attendance</span>
            </a>
            <a href="payslip.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payslip</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Request Leave</span>
            </a>
            <a href="view_evaluation.php" class="mobile-nav-item active">
                <span class="material-icons">fact_check</span>
                <span class="mobile-nav-text">Performance</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/datetime/1.5.1/js/dataTables.dateTime.min.js"></script>
    
    <!-- Date Range Picker -->
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    
    <script src="js/guards_dashboard.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#evaluationsTable').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[1, 'desc']], // Sort by evaluation date descending
            columnDefs: [
                { orderable: false, targets: -1 } // Disable sorting on Actions column
            ],
            language: {
                emptyTable: "No performance evaluations found",
                zeroRecords: "No matching records found"
            }
        });

        // Initialize Date Range Picker
        $('#dateRange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            }
        });

        $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
            applyFilters();
        });

        $('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            applyFilters();
        });

        // Auto-apply filters when year filter changes
        $('#yearFilter').on('change', function() {
            applyFilters();
        });

        // Function to apply filters
        function applyFilters() {
            var yearFilter = $('#yearFilter').val();
            var dateRange = $('#dateRange').val();
            
            // Clear existing search and custom search functions
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('evaluationsTable') === -1;
            });
            
            table.search('').columns().search('').draw();
            
            // Apply year filter
            if (yearFilter) {
                table.column(0).search(yearFilter, false, false);
            }
            
            // Apply date range filter
            if (dateRange) {
                var dates = dateRange.split(' to ');
                if (dates.length === 2) {
                    var startDate = moment(dates[0]);
                    var endDate = moment(dates[1]);
                    
                    // Custom search function for date range
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        if (settings.nTable.id !== 'evaluationsTable') {
                            return true;
                        }
                        
                        var evalDateText = $(table.row(dataIndex).node()).find('td:eq(1) strong').text();
                        var evalDate = moment(evalDateText, 'MMMM D, YYYY');
                        
                        if (evalDate.isBetween(startDate, endDate, 'day', '[]')) {
                            return true;
                        }
                        return false;
                    });
                }
            }
            
            table.draw();
            
            // Show success toast only if filters are applied
            if (yearFilter || dateRange) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Filters applied successfully!',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            }
        }

        // Clear all filters
        $('#clearFilters').on('click', function() {
            $('#yearFilter').val('');
            $('#dateRange').val('');
            
            // Clear DataTable search and custom search functions
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.toString().indexOf('evaluationsTable') === -1;
            });
            
            table.search('').columns().search('').draw();
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'All filters cleared!',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
        });

        // View evaluation details
        $('.view-evaluation').on('click', function() {
            var evaluationId = $(this).data('evaluation-id');
            
            // Show loading
            $('#evaluationContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading evaluation details...</p>
                </div>
            `);
            
            $('#evaluationModal').modal('show');
            
            // Load evaluation details via AJAX
            $.ajax({
                url: 'get_evaluation_details.php',
                method: 'GET',
                data: { evaluation_id: evaluationId },
                success: function(response) {
                    $('#evaluationContent').html(response);
                },
                error: function() {
                    $('#evaluationContent').html(`
                        <div class="alert alert-danger">
                            <span class="material-icons me-2">error</span>
                            Error loading evaluation details. Please try again.
                        </div>
                    `);
                }
            });
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>

</body>
</html>