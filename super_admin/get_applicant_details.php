<?php
// filepath: c:\xampp\htdocs\HRIS\hr\get_applicant_details.php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">Applicant ID is required</div>';
    exit();
}

$applicantId = intval($_GET['id']);

try {
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE Applicant_ID = ?");
    $stmt->execute([$applicantId]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$applicant) {
        echo '<div class="alert alert-danger">Applicant not found</div>';
        exit();
    }
    
    // Update the "Reviewed" status if it's not already reviewed
    if (!$applicant['Reviewed']) {
        $updateStmt = $conn->prepare("UPDATE applicants SET Reviewed = 1, Last_Modified = NOW() WHERE Applicant_ID = ?");
        $updateStmt->execute([$applicantId]);
    }
    
    // Determine status badge class with enhanced colors
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
    
    // Format name
    $fullName = htmlspecialchars($applicant['First_Name']) . ' ' . 
                (!empty($applicant['Middle_Name']) ? htmlspecialchars($applicant['Middle_Name'][0]) . '. ' : '') . 
                htmlspecialchars($applicant['Last_Name']) .
                (!empty($applicant['Name_Extension']) ? ' ' . htmlspecialchars($applicant['Name_Extension']) : '');
    
    // Fix resume path
    $resumePath = $applicant['Resume_Path'];
    if (!empty($resumePath) && strpos($resumePath, 'uploads/') === 0) {
        $resumePath = '../' . $resumePath;
    }
    
    ?>
    <style>
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
        
        .detail-row {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #6c757d;
        }
        
        .status-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .resume-download-btn {
            transition: all 0.3s ease;
        }

        .resume-download-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>

    <div class="container-fluid">
        <!-- Status and Application Date -->
        <div class="status-section">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-label">Application Status</div>
                    <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo htmlspecialchars($applicant['Status']); ?></span>
                </div>
                <div class="col-md-6">
                    <div class="detail-label">Application Date</div>
                    <div class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($applicant['Application_Date'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="row">
            <div class="col-md-12">
                <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Personal Information</h6>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value"><?php echo $fullName; ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($applicant['Email']); ?>" class="text-decoration-none">
                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($applicant['Email']); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Phone Number</div>
                    <div class="detail-value">
                        <a href="tel:<?php echo htmlspecialchars($applicant['Phone_Number']); ?>" class="text-decoration-none">
                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($applicant['Phone_Number']); ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-value">
                        <?php 
                        if (!empty($applicant['Date_of_Birth'])) {
                            echo '<i class="fas fa-calendar me-1"></i>' . date('F j, Y', strtotime($applicant['Date_of_Birth']));
                        } else {
                            echo '<span class="text-muted">Not provided</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Details -->
        <div class="row mt-4">
            <div class="col-md-12">
                <h6 class="text-primary mb-3"><i class="fas fa-briefcase me-2"></i>Application Details</h6>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Position Applied</div>
                    <div class="detail-value text-success fw-semibold">
                        <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($applicant['Position']); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Preferred Location</div>
                    <div class="detail-value">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <?php echo !empty($applicant['Preferred_Location']) ? htmlspecialchars($applicant['Preferred_Location']) : 'Any Location'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resume -->
        <div class="row">
            <div class="col-md-12">
                <div class="detail-row">
                    <div class="detail-label">Resume</div>
                    <div class="detail-value">
                        <?php if (!empty($applicant['Resume_Path'])): ?>
                            <a href="<?php echo htmlspecialchars($resumePath); ?>" target="_blank" class="btn btn-outline-success btn-sm resume-download-btn">
                                <i class="fas fa-file-pdf me-2"></i>View Resume
                            </a>
                            <small class="text-muted ms-2">
                                <i class="fas fa-info-circle"></i> Opens in new tab
                            </small>
                        <?php else: ?>
                            <span class="text-muted">
                                <i class="fas fa-exclamation-triangle me-1"></i>No resume uploaded
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <?php if (!empty($applicant['Additional_Info'])): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Additional Information</h6>
                <div class="detail-row">
                    <div class="detail-value bg-light p-3 rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars($applicant['Additional_Info']); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Application Tracking -->
        <div class="row mt-4">
            <div class="col-md-12">
                <h6 class="text-primary mb-3"><i class="fas fa-clock me-2"></i>Application Tracking</h6>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Reviewed Status</div>
                    <div class="detail-value">
                        <?php if ($applicant['Reviewed']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i>Reviewed
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-clock me-1"></i>Pending Review
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-row">
                    <div class="detail-label">Last Modified</div>
                    <div class="detail-value">
                        <?php 
                        if (!empty($applicant['Last_Modified'])) {
                            echo '<i class="fas fa-edit me-1"></i>' . date('F j, Y \a\t g:i A', strtotime($applicant['Last_Modified']));
                        } else {
                            echo '<span class="text-muted">N/A</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="d-flex gap-2 justify-content-center">
                    <a href="mailto:<?php echo htmlspecialchars($applicant['Email']); ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-envelope me-1"></i>Send Email
                    </a>
                    <a href="tel:<?php echo htmlspecialchars($applicant['Phone_Number']); ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-phone me-1"></i>Call
                    </a>
                    <?php if (!empty($applicant['Resume_Path'])): ?>
                    <a href="<?php echo htmlspecialchars($resumePath); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-download me-1"></i>Download Resume
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
    exit();
}
?>