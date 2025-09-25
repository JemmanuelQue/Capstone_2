<?php
require_once __DIR__ . '/../includes/session_check.php';
// Require Role_ID = 2 for admin
validateSession($conn, 2);

// Auto-delete applicants older than 1 week (run once per day)
try {
    // Check if cleanup has been run today
    $today = date('Y-m-d');
    $checkCleanup = $conn->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE Activity_Type = 'Auto Cleanup' AND DATE(Timestamp) = ? AND Activity_Details LIKE '%applicant%'");
    $checkCleanup->execute([$today]);
    $cleanupToday = $checkCleanup->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Only run cleanup if it hasn't been run today
    if ($cleanupToday == 0) {
        $deleteOldApplicants = $conn->prepare("DELETE FROM applicants WHERE Application_Date < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
        $deleteOldApplicants->execute();
        $deletedCount = $deleteOldApplicants->rowCount();
        
        // Log the deletion if any records were deleted
        if ($deletedCount > 0) {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, 'Auto Cleanup', ?)");
            $logDetails = "Auto-deleted {$deletedCount} applicant record(s) older than 1 week";
            $logStmt->execute([$_SESSION['user_id'], $logDetails]);
        } else {
            // Log that cleanup was run but no records were deleted
            $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, 'Auto Cleanup', ?)");
            $logDetails = "Applicant cleanup executed - no old records found to delete";
            $logStmt->execute([$_SESSION['user_id'], $logDetails]);
        }
    }
} catch (PDOException $e) {
    // Log error but don't stop the page from loading
    error_log("Error auto-deleting old applicants: " . $e->getMessage());
}

// Get applicants list with filtering
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$position = isset($_GET['position']) ? $_GET['position'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query based on filters
$query = "SELECT * FROM applicants WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $query .= " AND Status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $query .= " AND (First_Name LIKE ? OR Middle_Name LIKE ? OR Last_Name LIKE ? OR Email LIKE ? OR Phone_Number LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($position)) {
    $query .= " AND Position = ?";
    $params[] = $position;
}

if (!empty($location)) {
    $query .= " AND Preferred_Location = ?";
    $params[] = $location;
}

// Add sorting
if ($sort === 'newest') {
    $query .= " ORDER BY Application_Date DESC";
} else if ($sort === 'oldest') {
    $query .= " ORDER BY Application_Date ASC";
} else if ($sort === 'name_asc') {
    $query .= " ORDER BY Last_Name ASC, First_Name ASC";
} else if ($sort === 'name_desc') {
    $query .= " ORDER BY Last_Name DESC, First_Name DESC";
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get positions for filter
    $positionQuery = "SELECT DISTINCT Position FROM applicants ORDER BY Position ASC";
    $positionStmt = $conn->prepare($positionQuery);
    $positionStmt->execute();
    $positions = $positionStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get locations for filter
    $locationQuery = "SELECT DISTINCT Preferred_Location FROM applicants WHERE Preferred_Location IS NOT NULL AND Preferred_Location != '' ORDER BY Preferred_Location ASC";
    $locationStmt = $conn->prepare($locationQuery);
    $locationStmt->execute();
    $locations = $locationStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Handle error
    $error = "Database error: " . $e->getMessage();
}

// Get the current datetime for display
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('l, F j, Y | h:i:s A');
list($currentDate, $currentTime) = explode('|', $currentDateTime);

// Get HR user information
try {
    $hrQuery = $conn->prepare("SELECT First_Name, Last_Name, Profile_Pic FROM users WHERE User_ID = ?");
    $hrQuery->execute([$_SESSION['user_id']]);
    $hrData = $hrQuery->fetch(PDO::FETCH_ASSOC);
    
    // Set Admin name and profile picture
    $adminName = $hrData['First_Name'] . ' ' . $hrData['Last_Name'];
    $adminProfile = !empty($hrData['Profile_Pic']) && file_exists($hrData['Profile_Pic']) ? $hrData['Profile_Pic'] : '../images/default_profile.png';
} catch (PDOException $e) {
    // Set defaults if query fails
    $adminName = "Admin";
    $adminProfile = "../images/default_profile.png";
    $hrData = [
        'First_Name' => '',
        'Last_Name' => ''
    ];
    
    // Log error
    error_log("Error fetching HR data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment - Green Meadows Security Agency</title>
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/recruitment.css">
    
    <style>
        .resume-link {
            color: #28a745;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .resume-link:hover {
            text-decoration: underline;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
            border-radius: 0.25rem;
            font-weight: 600;
        }
        
        /* Enhanced Color-Coded Status Badges */
        .status-new {
            background-color: #3498db;
            color: #ffffff;
            border: 1px solid #2980b9;
        }
        
        .status-contacted {
            background-color: #f39c12;
            color: #ffffff;
            border: 1px solid #e67e22;
        }
        
        .status-interview {
            background-color: #9b59b6;
            color: #ffffff;
            border: 1px solid #8e44ad;
        }
        
        .status-hired {
            background-color: #27ae60;
            color: #ffffff;
            border: 1px solid #229954;
        }
        
        .status-rejected {
            background-color: #e74c3c;
            color: #ffffff;
            border: 1px solid #c0392b;
        }
        
        .application-card {
            transition: transform 0.2s ease;
        }
        
        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
        }

        /* Enhanced header styling for better visual balance */
        .card-header-enhanced {
            padding: 20px;
            background: linear-gradient(135deg, #28a745 0%, #2a7d4f 100%);
        }

        .header-title-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-input-group {
            min-width: 280px;
        }

        .search-input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
        }

        .search-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            height: 32px;
            width: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-left: none;
        }

        .sort-dropdown .btn {
            height: 32px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.875rem;
            min-width: 140px;
        }

        /* Enhanced card container with proper spacing */
        .applicants-container {
            padding: 25px;
        }

        .filters-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .no-applications {
            padding: 60px 20px;
            text-align: center;
        }

        .no-applications .material-icons {
            font-size: 64px;
            color: #6c757d;
            margin-bottom: 20px;
        }

        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .header-controls {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .search-controls {
                flex-direction: column;
                gap: 10px;
            }

            .search-input-group {
                min-width: auto;
                width: 100%;
            }

            .header-title-centered {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }

            .applicants-container {
                padding: 15px;
            }

            .filters-section {
                padding: 15px;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 576px) {
            .search-controls {
                align-items: stretch;
            }

            .sort-dropdown .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div>SECURITY AGENCY</div>
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
                <a href="recruitment.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Recruitment">
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
                <span id="current-date"><?php echo $currentDate; ?></span> | <span id="current-time"><?php echo $currentTime; ?></span>
            </div>
            <a href="profile.php" class="user-profile" id="userProfile" style="color:black; text-decoration:none;">
                <span><?php echo htmlspecialchars($adminName); ?></span>
                <img src="<?php echo $adminProfile; ?>" alt="User Profile">
            </a>
        </div>
        
        <br>
        
        <!-- Enhanced Dashboard Card -->
        <div class="dashboard-card bg-white">
            <div class="card-header card-header-enhanced text-white d-flex align-items-center justify-content-between flex-wrap">
                <!-- Centered Title with Logo Icon -->
                <div class="header-title-centered">
                    <span class="material-icons me-2" style="font-size: 1.5rem;">person_add</span>
                    <span>Job Applicants</span>
                </div>
                
                <!-- Controls Section -->
                <div class="header-controls">
                    <div class="search-controls">
                        <!-- Sort Dropdown -->
                        <div class="dropdown sort-dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <span class="material-icons" style="font-size: 16px;">sort</span>
                                <span><?php echo ($sort === 'newest') ? 'Newest First' : (($sort === 'oldest') ? 'Oldest First' : (($sort === 'name_asc') ? 'Name A-Z' : 'Name Z-A')); ?></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item <?php echo ($sort === 'newest') ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position); ?>&location=<?php echo urlencode($location); ?>&sort=newest">Newest First</a></li>
                                <li><a class="dropdown-item <?php echo ($sort === 'oldest') ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position); ?>&location=<?php echo urlencode($location); ?>&sort=oldest">Oldest First</a></li>
                                <li><a class="dropdown-item <?php echo ($sort === 'name_asc') ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position); ?>&location=<?php echo urlencode($location); ?>&sort=name_asc">Name A-Z</a></li>
                                <li><a class="dropdown-item <?php echo ($sort === 'name_desc') ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position); ?>&location=<?php echo urlencode($location); ?>&sort=name_desc">Name Z-A</a></li>
                            </ul>
                        </div>
                        
                        <!-- Search Input -->
                        <form class="d-flex" action="" method="GET">
                            <div class="input-group search-input-group">
                                <input type="text" class="form-control form-control-sm" name="search" placeholder="Search applicants..." value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                                <input type="hidden" name="position" value="<?php echo htmlspecialchars($position); ?>">
                                <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                                <button class="btn btn-light search-btn" type="submit">
                                    <span class="material-icons" style="font-size: 18px;">search</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Enhanced Card Body with Better Spacing -->
            <div class="applicants-container">
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label small fw-semibold">Application Status</label>
                            <select class="form-select form-select-sm" id="statusFilter">
                                <option value="all" <?php echo ($status == 'all') ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="New" <?php echo ($status == 'New') ? 'selected' : ''; ?>>New</option>
                                <option value="Contacted" <?php echo ($status == 'Contacted') ? 'selected' : ''; ?>>Contacted</option>
                                <option value="Interview Scheduled" <?php echo ($status == 'Interview Scheduled') ? 'selected' : ''; ?>>Interview Scheduled</option>
                                <option value="Hired" <?php echo ($status == 'Hired') ? 'selected' : ''; ?>>Hired</option>
                                <option value="Rejected" <?php echo ($status == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="positionFilter" class="form-label small fw-semibold">Position</label>
                            <select class="form-select form-select-sm" id="positionFilter">
                                <option value="" <?php echo (empty($position)) ? 'selected' : ''; ?>>All Positions</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo htmlspecialchars($pos); ?>" <?php echo ($position == $pos) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pos); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="locationFilter" class="form-label small fw-semibold">Location</label>
                            <select class="form-select form-select-sm" id="locationFilter">
                                <option value="" <?php echo (empty($location)) ? 'selected' : ''; ?>>All Locations</option>
                                <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($location == $loc) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" id="clearFilters" class="btn btn-sm btn-outline-secondary w-100">
                                <span class="material-icons me-1" style="font-size: 16px;">clear</span>
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Applicants Cards with Enhanced Spacing -->
                <div class="row g-4">
                    <?php if (empty($applicants)): ?>
                        <div class="col-12">
                            <div class="no-applications">
                                <span class="material-icons">person_search</span>
                                <h5 class="text-muted mb-3">No applications found</h5>
                                <p class="text-muted">Try adjusting your search or filter criteria</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applicants as $applicant): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 application-card">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <?php 
                                        $statusClass = 'status-new';
                                        switch($applicant['Status']) {
                                            case 'Contacted':
                                                $statusClass = 'status-contacted';
                                                break;
                                            case 'Interview Scheduled':
                                                $statusClass = 'status-interview';
                                                break;
                                            case 'Hired':
                                                $statusClass = 'status-hired';
                                                break;
                                            case 'Rejected':
                                                $statusClass = 'status-rejected';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo htmlspecialchars($applicant['Status']); ?></span>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($applicant['Application_Date'])); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php 
                                            echo htmlspecialchars($applicant['First_Name']) . ' ' . 
                                                 (!empty($applicant['Middle_Name']) ? htmlspecialchars($applicant['Middle_Name'][0]) . '. ' : '') . 
                                                 htmlspecialchars($applicant['Last_Name']) .
                                                 (!empty($applicant['Name_Extension']) ? ' ' . htmlspecialchars($applicant['Name_Extension']) : '');
                                            ?>
                                        </h5>
                                        <p class="card-subtitle mb-2 text-success fw-semibold"><?php echo htmlspecialchars($applicant['Position']); ?></p>
                                        <div class="mb-3">
                                            <div class="mb-1"><i class="fas fa-map-marker-alt text-muted me-2"></i> <?php echo !empty($applicant['Preferred_Location']) ? htmlspecialchars($applicant['Preferred_Location']) : 'Any Location'; ?></div>
                                            <div class="mb-1"><i class="fas fa-envelope text-muted me-2"></i> <?php echo htmlspecialchars($applicant['Email']); ?></div>
                                            <div><i class="fas fa-phone text-muted me-2"></i> <?php echo htmlspecialchars($applicant['Phone_Number']); ?></div>
                                        </div>
                                        <div class="mb-2">
                                            <?php if (!empty($applicant['Resume_Path'])): ?>
                                                <?php 
                                                // Fix the resume path to work from hr directory
                                                $resumePath = $applicant['Resume_Path'];
                                                if (strpos($resumePath, 'uploads/') === 0) {
                                                    $resumePath = '../' . $resumePath;
                                                }
                                                ?>
                                                <a href="<?php echo htmlspecialchars($resumePath); ?>" target="_blank" class="resume-link">
                                                    <span class="material-icons me-1" style="font-size: 18px;">description</span> View Resume
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <span class="material-icons me-1" style="font-size: 18px;">description</span> No resume uploaded
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-white border-top-0">
                                        <button class="btn btn-sm btn-outline-primary view-applicant w-100" data-id="<?php echo $applicant['Applicant_ID']; ?>">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav d-lg-none" id="mobileNav">
        <div class="mobile-nav-container">
            <a href="admin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>

            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="recruitment.php" class="mobile-nav-item active">
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

    
    <!-- Applicant Details Modal -->
    <div class="modal fade" id="applicantModal" tabindex="-1" aria-labelledby="applicantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="applicantModalLabel">Applicant Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="applicantModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
        });

        // Update time every second
        function updateTime() {
            const now = new Date();
            const options = { 
                timeZone: 'Asia/Manila',
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const timeString = now.toLocaleTimeString('en-US', options);
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        setInterval(updateTime, 1000);

        // Filter change handlers
        document.getElementById('statusFilter').addEventListener('change', function() {
            updateFilters();
        });

        document.getElementById('positionFilter').addEventListener('change', function() {
            updateFilters();
        });

        document.getElementById('locationFilter').addEventListener('change', function() {
            updateFilters();
        });

        document.getElementById('clearFilters').addEventListener('click', function() {
            window.location.href = 'recruitment.php';
        });

        function updateFilters() {
            const status = document.getElementById('statusFilter').value;
            const position = document.getElementById('positionFilter').value;
            const location = document.getElementById('locationFilter').value;
            const search = '<?php echo htmlspecialchars($search); ?>';
            const sort = '<?php echo htmlspecialchars($sort); ?>';

            const url = `recruitment.php?status=${encodeURIComponent(status)}&position=${encodeURIComponent(position)}&location=${encodeURIComponent(location)}&search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sort)}`;
            window.location.href = url;
        }

        // View applicant details
        document.querySelectorAll('.view-applicant').forEach(button => {
            button.addEventListener('click', function() {
                const applicantId = this.getAttribute('data-id');
                loadApplicantDetails(applicantId);
            });
        });

        function loadApplicantDetails(applicantId) {
            fetch(`get_applicant_details.php?id=${applicantId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('applicantModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('applicantModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to load applicant details', 'error');
                });
        }

    // Status update controls removed per requirements

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>