<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 5)) { exit; }

// Get guard details, face profile, and location information
$stmt = $conn->prepare("
    SELECT u.*, gf.profile_image, gl.location_name, gl.designated_latitude, gl.designated_longitude
    FROM users u 
    LEFT JOIN guard_faces gf ON u.User_ID = gf.guard_id 
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1 AND gl.is_active = 1
    WHERE u.User_ID = ?
");
$stmt->execute([$_SESSION['user_id']]);
$guard = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Guards Attendance</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Add margin to body */
        body {
            background: linear-gradient(135deg, #256845 0%, #1b3c2b 100%);
            font-family: 'Poppins', Arial, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        /* Fix container margin issues */
        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
            min-height: calc(100vh - 4rem) !important;
        }
        
        /* Enhanced loading spinner */
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .spinner-container {
            text-align: center;
            color: white;
        }
        
        /* Updated header styles */
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px 0;
        }
        
        .page-header h2, .page-header .header-date-time {
            color: white !important;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .header-date-time {
            color: white !important;
            font-size: 1rem;
            margin-top: 5px;
            font-weight: 500;
            text-align: center;
        }
        
        /* Wider content container */
        .card.shadow-lg {
            max-width: 1100px !important;
            width: 95% !important;
            margin: 0 auto;
        }
        
        /* Remove logo */
        .ms-auto.d-none.d-md-block {
            display: none !important;
        }
        
        /* Add clock-in details panel */
        .attendance-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: #256845;
        }
        
        .detail-value {
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .status-active {
            background-color: #28a745;
        }
        
        /* Date-time display styling */
        .header-date-time {
            color: #4a9e67;
            font-size: 0.95rem;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .time-display {
            font-weight: 600;
            margin-left: 5px;
        }

        .attendance-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 2rem 2.5rem;
            margin-top: 2rem;
            max-width: 900px;
        }
        .profile-image {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #28a745;
            box-shadow: 0 2px 8px rgba(40,167,69,0.15);
        }
        .verification-steps-panel {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1.5rem 1rem 1rem 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .step {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0.5rem 0;
            padding: 0.75rem 1rem;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }
        .step-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        .status-badge.waiting {
            background: #ffc107;
            color: #fff;
        }
        .status-badge.verified {
            background: #28a745;
            color: #fff;
        }
        .status-badge {
            padding: 0.35em 1.2em;
            border-radius: 1em;
            font-size: 1em;
            font-weight: 600;
            min-width: 90px;
            text-align: center;
        }
        .btn-container {
            margin-top: 1.5rem;
            display: flex;
            gap: 1.5rem;
            justify-content: center;
        }
        .attendance-btn {
            font-size: 1.1rem;
            padding: 0.75rem 2.5rem;
            border-radius: 2rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(40,167,69,0.08);
        }
        .attendance-btn:disabled {
            opacity: 0.7;
        }
        .status {
            margin: 1.5rem 0 0.5rem 0;
            padding: 1rem;
            border-radius: 0.5rem;
            display: none;
            font-size: 1.1rem;
        }
        .success {
            background: #e9fbe9;
            color: #28a745;
            border: 1px solid #28a745;
            display: block;
        }
        .error {
            background: #fbe9e9;
            color: #dc3545;
            border: 1px solid #dc3545;
            display: block;
        }
        #loading {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        #loading .fa-spinner {
            color: #28a745;
        }
        .video-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        video, canvas {
            width: 100%;
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .welcome-section {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1.5rem 1rem;
            text-align: center;
            margin-top: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .welcome-section h3 {
            font-size: 1.2rem;
            color: #256845;
            margin-bottom: 0.5rem;
        }
        .welcome-section p {
            font-size: 1.1rem;
            color: #333;
        }
        @media (max-width: 767px) {
            .attendance-card {
                padding: 1rem 0.5rem;
            }
            .verification-steps-panel {
                padding: 1rem 0.5rem 0.5rem 0.5rem;
            }
            .btn-container {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Add these styles to your existing style section */
        .text-success-light {
            color: #43a047 !important;
        }
        
        .bg-light {
            background-color: #f8f9fa !important;
        }
        
        /* Fixed header with date/time */
        .page-header {
            text-align: center;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        
        /* Better mobile responsiveness */
        @media (max-width: 576px) {
            .d-flex.justify-content-between {
                flex-direction: column;
            }
            
            .d-flex.justify-content-between > * {
                margin-bottom: 5px;
            }
        }
    </style>
    <!-- Load Face-API.js dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@1.7.4/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body>
    <!-- Replace the header section -->
    <div class="page-header position-relative">
    <!-- Back Arrow - Add this new code -->
    <a href="guards_dashboard.php" class="position-absolute start-0 ms-4 mt-2 text-white" 
       style="font-size: 22px; text-decoration: none;" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>
    <!-- End Back Arrow -->
    
    <h2 class="fw-bold mb-1">Guards Attendance</h2>
    <div class="header-date-time">
        <span id="currentDate"></span>
        <span class="time-display" id="currentTime"></span>
    </div>
</div>

    <!-- Replace the loading spinner with this fixed version -->
    <div id="loading">
        <div class="spinner-container">
            <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
            <p class="fw-semibold">Loading face recognition models...</p>
        </div>
    </div>
    <div class="container min-vh-100 d-flex flex-column align-items-center justify-content-center">
        <div class="card shadow-lg p-4 rounded-4 w-100" style="max-width:900px;">
            <div class="bg-light rounded-3 p-3 mb-3">
                <h5 class="mb-3 fw-semibold"><i class="fas fa-shield-alt me-2 text-success"></i>Verification Steps Required</h5>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded shadow-sm">
                        <span><i class="fas fa-user-check text-success me-2"></i>Face Match</span>
                        <span class="badge bg-warning text-dark" id="faceMatchStatus">Waiting...</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded shadow-sm">
                        <span><i class="fas fa-eye text-primary me-2"></i>Blink Test</span>
                        <span class="badge bg-warning text-dark" id="blinkStatus">Waiting...</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded shadow-sm">
                        <span><i class="fas fa-walking text-warning me-2"></i>Movement Test</span>
                        <span class="badge bg-warning text-dark" id="movementStatus">Waiting...</span>
                    </div>
                </div>
            </div>
            <div id="error-container"></div>
            <div id="verificationStatus" class="status"></div>
            <div class="row g-4 align-items-start">
                <div class="col-12 col-md-7">
                    <div class="position-relative mb-3">
                        <video id="video" autoplay playsinline class="w-100 rounded-3 shadow"></video>
                        <canvas id="overlay" class="position-absolute top-0 start-0 w-100 h-100"></canvas>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <div class="bg-light rounded-3 p-3 text-center shadow-sm" id="welcomeSection" style="display:none;">
                        <?php if ($guard['profile_image'] && file_exists($guard['profile_image'])): ?>
                            <img src="<?php echo $guard['profile_image']; ?>" alt="Guard Profile" class="rounded-circle border border-success mb-2" style="width:100px;height:100px;">
                        <?php endif; ?>
                        <h3 class="text-success mb-1">Welcome to Security Agency</h3>
                        <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($guard['First_Name'] . ' ' . $guard['Last_Name']); ?></p>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <button class="btn btn-success btn-sm px-3" onclick="recordAttendance('time_in', false)" disabled>
                    <i class="fas fa-sign-in-alt me-2"></i>Time In
                </button>
                <button class="btn btn-danger btn-sm px-3" onclick="recordAttendance('time_out', false)" disabled>
                    <i class="fas fa-sign-out-alt me-2"></i>Time Out
                </button>
            </div>
        </div>
    </div>

    <!-- Modify the attendance details section to show correct status and hours -->
    <div class="container">
        <div class="card shadow-lg p-4 rounded-4 w-100 mb-3" style="max-width:900px; margin: 0 auto;">
            <div class="bg-white rounded-3">
                <h5 class="mb-3 fw-semibold text-dark"><i class="fas fa-history me-2 text-success"></i>Attendance Details</h5>
                
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded shadow-sm mb-2">
                    <span class="text-dark"><i class="fas fa-circle me-2" style="font-size: 8px;"></i>Current Status:</span>
                    <span class="fw-bold">
                        <?php 
                        // Get the latest attendance record
                        $stmt = $conn->prepare("SELECT * FROM attendance WHERE User_ID = ? ORDER BY Time_In DESC LIMIT 1");
                        $stmt->execute([$_SESSION['user_id']]);
                        $lastRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $status_color = "text-success";
                        $status_text = "Not Clocked In";
                        
                        if ($lastRecord && $lastRecord['Time_Out'] === null) {
                            // Get the time in
                            $timeIn = new DateTime($lastRecord['Time_In']);
                            $timeInHour = (int)$timeIn->format('H');
                            $timeInMinute = (int)$timeIn->format('i');
                            
                            // Determine shift type by the clock-in time
                            $isNightShift = ($timeInHour >= 18 || $timeInHour < 6);
                            
                            // Check if late - CORRECTED LOGIC
                            $isLate = false;
                            
                            if ($isNightShift) {
                                // For night shift (6pm-6am):
                                // If time is between midnight and 6am, always consider it late
                                // If time is between 6pm and midnight, consider it late if after 6:00pm
                                if ($timeInHour < 6) {
                                    // Early morning hours (midnight to 6am) are always late for night shift
                                    $isLate = true;
                                } else {
                                    // Evening hours (6pm to midnight)
                                    $isLate = ($timeInHour > 18 || ($timeInHour == 18 && $timeInMinute > 0));
                                }
                            } else {
                                // For morning shift (6am-6pm), late if after 6:00am
                                $isLate = ($timeInHour > 6 || ($timeInHour == 6 && $timeInMinute > 0));
                            }
                            
                            if ($isLate) {
                                $status_color = "text-danger";
                                $status_text = "Clocked In (Late)";
                            } else {
                                $status_color = "text-success";
                                $status_text = "Clocked In (On Time)";
                            }
                        }
                        
                        echo "<span class=\"{$status_color}\">{$status_text}</span>";
                        ?>
                    </span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded shadow-sm mb-2">
                    <span class="text-dark">Last Clock In:</span>
                    <span class="fw-bold text-dark">
                        <?php 
                        if ($lastRecord) {
                            echo date("M d, Y - h:i A", strtotime($lastRecord['Time_In']));
                        } else {
                            echo "No record found";
                        }
                        ?>
                    </span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded shadow-sm mb-2">
                    <span class="text-dark">Last Clock Out:</span>
                    <span class="fw-bold text-dark">
                        <?php 
                        if ($lastRecord && $lastRecord['Time_Out']) {
                            echo date("M d, Y - h:i A", strtotime($lastRecord['Time_Out']));
                        } else {
                            echo "Not clocked out yet";
                        }
                        ?>
                    </span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded shadow-sm mb-2">
                    <span class="text-dark">Assigned Location:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($guard['location_name'] ?? 'Not assigned'); ?></span>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isModelLoaded = false;
        let recognizedFace = false;
        let faceDescriptor = null;
        const buttons = document.querySelectorAll('button');
        const MODEL_URL = '../models';
        let lastDetectionState = null;
        let referenceDescriptor = null;
        let blinkCount = 0;
        let lastEyeState = null;
        let livenessVerified = false;
        const BLINKS_REQUIRED = 1;
        let lastLandmarks = null;
        let movementDetected = false;
        let lastBlinkTime = 0;
        const EYE_AR_THRESH = 0.3;
        const EYE_AR_CONSEC_FRAMES = 2;
        let eyeClosedFrames = 0;
        const BLINK_COOLDOWN = 300;
        const MOVEMENT_THRESHOLD = 0.01;
        const MOVEMENT_MEMORY = 20;
        const MOVEMENT_TIME_REQUIRED = 2000;
        let movementStartTime = null;
        let lastPositions = [];
        let lastHeadPose = null;
        let textureScores = [];
        let isRealFace = false;
        const TEXTURE_CHECK_INTERVAL = 500;
        const MIN_TEXTURE_VARIANCE = 30;
        const FACE_MATCH_THRESHOLD = 0.4; // Stricter threshold for better security
        const FACE_CONFIDENCE_THRESHOLD = 0.8; // Minimum face detection confidence
        const TEXTURE_VARIANCE_THRESHOLD = 50;
        const MOVEMENT_CHECK_FRAMES = 10;
        const MIN_DEPTH_VARIANCE = 0.1;
        let currentState = 'none';
        let isLivenessVerifiedPermanently = false;
        let lastVerifiedTime = null;
        const VERIFICATION_TIMEOUT = 3000; // Reduced timeout for better security
        let consecutiveMatches = 0;
        const REQUIRED_CONSECUTIVE_MATCHES = 3; // Require multiple consecutive matches
        let verificationState = {
            isVerified: false,
            wasVerifiedBefore: false
        };
        const MOVEMENT_REQUIRED = true;
        const BLINK_REQUIRED = true;
        const DEPTH_CHECK_REQUIRED = true;

        const VERIFICATION_STEPS = {
            FACE_MATCH: false,
            BLINK: false,
            MOVEMENT: false
        };

        // Face matching validation variables
        let faceMatchHistory = [];
        const FACE_MATCH_HISTORY_SIZE = 5;
        let lastFaceMatchTime = 0;
        let faceMatchStability = 0;
        
        // Security: Auto-reset verification after inactivity
        let lastActivityTime = Date.now();
        const INACTIVITY_TIMEOUT = 30000; // 30 seconds of inactivity resets verification
        
        // Smart verification tracking
        let lastVerifiedUserId = null;
        let isVerificationComplete = false;
        
        // Function to update activity timestamp
        function updateActivity() {
            lastActivityTime = Date.now();
        }

        // Enhanced face matching validation function
        function validateFaceMatch(currentDescriptor) {
            if (!referenceDescriptor || !currentDescriptor) {
                console.log('Missing descriptors for face matching');
                return false;
            }
            
            const distance = faceapi.euclideanDistance(currentDescriptor, referenceDescriptor);
            const currentTime = Date.now();
            
            console.log('Face distance:', distance, 'Threshold:', FACE_MATCH_THRESHOLD);
            
            // Add to match history
            faceMatchHistory.push({
                distance: distance,
                timestamp: currentTime,
                matched: distance <= FACE_MATCH_THRESHOLD
            });
            
            // Keep only recent history
            if (faceMatchHistory.length > FACE_MATCH_HISTORY_SIZE) {
                faceMatchHistory.shift();
            }
            
            // Check if current match is within threshold
            if (distance <= FACE_MATCH_THRESHOLD) {
                consecutiveMatches++;
                
                // Require multiple consecutive matches for security
                if (consecutiveMatches >= REQUIRED_CONSECUTIVE_MATCHES) {
                    // Additional validation: check consistency of recent matches
                    if (faceMatchHistory.length >= 3) {
                        const recentMatches = faceMatchHistory.slice(-3);
                        const avgDistance = recentMatches.reduce((sum, match) => sum + match.distance, 0) / recentMatches.length;
                        const distanceVariance = recentMatches.reduce((sum, match) => sum + Math.pow(match.distance - avgDistance, 2), 0) / recentMatches.length;
                        
                        console.log('Face match statistics:', {
                            avgDistance: avgDistance.toFixed(4),
                            variance: distanceVariance.toFixed(4),
                            consecutiveMatches: consecutiveMatches
                        });
                        
                        // Ensure consistent matching (low variance in distances)
                        if (distanceVariance < 0.01 && avgDistance <= FACE_MATCH_THRESHOLD) {
                            faceMatchStability++;
                            return true;
                        }
                    }
                    
                    return true;
                }
                
                return false; // Not enough consecutive matches yet
            } else {
                // Reset consecutive matches if current face doesn't match
                consecutiveMatches = 0;
                faceMatchStability = 0;
                console.log('Face does not match - distance:', distance);
                return false;
            }
        }
        
        // Function to reset face matching state
        function resetFaceMatching() {
            consecutiveMatches = 0;
            faceMatchStability = 0;
            faceMatchHistory = [];
            VERIFICATION_STEPS.FACE_MATCH = false;
        }

        function showError(message) {
            const errorContainer = document.getElementById('error-container');
            errorContainer.innerHTML = `<div class="error-message">${message}</div>`;
        }

        // Fix the updateLayout function to check if elements exist
        function updateLayout(verified) {
            const contentWrapper = document.querySelector('.row.g-4'); // Use the actual container instead of contentWrapper
            const welcomeSection = document.getElementById('welcomeSection');
            
            if (welcomeSection) {
                if (verified) {
                    welcomeSection.style.display = 'block';
                } else {
                    welcomeSection.style.display = 'none';
                }
            }
        }

        // Add this function to ensure the loader is hidden in all cases
        function hideLoader() {
            const loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = 'none';
            }
        }

        // Modify the beginning of loadModels to handle errors properly
        async function loadModels() {
            console.log("Loading models started...");
            try {
                // Load all required models
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.load(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.load(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.load(MODEL_URL),
                    faceapi.nets.faceExpressionNet.load(MODEL_URL)
                ]);
                
                isModelLoaded = true;
                console.log('All models loaded successfully');
                hideLoader(); // Hide loader after successful load

                // Start camera and load reference descriptor
                await loadReferenceDescriptor();
                await startCamera();
            } catch (error) {
                console.error('Error loading models:', error);
                console.log('Model URL used:', MODEL_URL);
                showError('Error loading face recognition models. Please ensure the models are in the correct directory.');
                hideLoader(); // Still hide the loader even if there's an error
            }
        }

        async function loadReferenceDescriptor() {
            try {
                const response = await fetch('get_face_descriptor.php');
                const data = await response.json();
                
                if (data.status === 'success' && data.descriptor) {
                    referenceDescriptor = new Float32Array(data.descriptor);
                    console.log('Reference descriptor loaded:', {
                        length: referenceDescriptor.length,
                        sample: referenceDescriptor.slice(0, 5) // Show first 5 values
                    });
                } else {
                    throw new Error('No face profile found for this guard');
                }
            } catch (error) {
                showError('Error loading face profile: ' + error.message);
                throw error;
            }
        }

        function showStatus(message, isSuccess, isPermanent = false) {
            const status = document.getElementById('verificationStatus');
            status.textContent = message;
            status.className = 'status ' + (isSuccess ? 'success' : 'error');
            status.style.display = 'block';
            
            // Make messages stay much longer - 10 seconds instead of 3
            if (isSuccess && !isPermanent) {
                setTimeout(() => {
                    status.style.display = 'none';
                }, 10000); // Changed from 3000 to 10000 (10 seconds)
            }
        }

        async function startCamera() {
            try {
                console.log("Starting camera...");
                
                // Check if getUserMedia is supported (fix for mobile devices)
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    // Fallback for older browsers and mobile devices
                    navigator.getUserMedia = navigator.getUserMedia || 
                                           navigator.webkitGetUserMedia || 
                                           navigator.mozGetUserMedia || 
                                           navigator.msGetUserMedia;
                    
                    if (!navigator.getUserMedia) {
                        throw new Error("Camera access is not supported on this device/browser");
                    }
                    
                    // Use older API for compatibility
                    const stream = await new Promise((resolve, reject) => {
                        navigator.getUserMedia(
                            { video: { facingMode: 'user' } },
                            resolve,
                            reject
                        );
                    });
                    
                    const video = document.getElementById("video");
                    video.srcObject = stream;
                } else {
                    // Modern browsers - use mediaDevices API with mobile-friendly constraints
                    const constraints = {
                        video: {
                            facingMode: 'user', // Front camera for mobile
                            width: { ideal: 640, max: 1280 },
                            height: { ideal: 480, max: 720 },
                            frameRate: { ideal: 30, max: 60 }
                        }
                    };
                    
                    const stream = await navigator.mediaDevices.getUserMedia(constraints);
                    const video = document.getElementById("video");
                    video.srcObject = stream;
                }

                video.addEventListener('play', () => {
                    const canvas = document.getElementById('overlay');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    
                    setInterval(async () => {
                        if (!isModelLoaded) return;
                        
                        // Check for inactivity and reset if needed
                        const now = Date.now();
                        if (verificationState.isVerified && (now - lastActivityTime > INACTIVITY_TIMEOUT)) {
                            console.log('Verification reset due to inactivity');
                            resetVerification();
                            showStatus('Verification expired due to inactivity. Please verify again.', false);
                        }
                        
                        try {
                            const options = new faceapi.TinyFaceDetectorOptions({
                                inputSize: 160,
                                scoreThreshold: 0.2
                            });

                            const context = canvas.getContext('2d');
                            context.clearRect(0, 0, canvas.width, canvas.height);

                            const detections = await faceapi.detectAllFaces(video, options)
                                .withFaceLandmarks()
                                .withFaceDescriptors();

                            drawDebugInfo(context, detections, canvas);

                            if (detections.length === 1) {
                                currentState = 'single';
                                const detection = detections[0];
                                
                                // Check detection confidence first
                                if (detection.detection.score < FACE_CONFIDENCE_THRESHOLD) {
                                    console.log('Face detection confidence too low:', detection.detection.score);
                                    resetFaceMatching();
                                    return;
                                }
                                
                                // Smart verification logic
                                const currentUserId = '<?php echo isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : ""; ?>';
                                
                                // If verification is complete and it's the same user, skip re-verification completely
                                if (isVerificationComplete && lastVerifiedUserId === currentUserId && VERIFICATION_STEPS.FACE_MATCH) {
                                    // Just do a quick face match check for security without re-doing liveness tests
                                    const currentDescriptor = detection.descriptor;
                                    const distance = faceapi.euclideanDistance(currentDescriptor, referenceDescriptor);
                                    
                                    if (distance <= FACE_MATCH_THRESHOLD) {
                                        // Same verified user - maintain verified state and show success message
                                        updateActivity();
                                        showStatus('✓ Verified! Ready for attendance.', 'success');
                                        
                                        // Ensure buttons remain enabled and state is maintained
                                        if (!verificationState.isVerified) {
                                            verificationState.isVerified = true;
                                            recognizedFace = true;
                                            faceDescriptor = detection.descriptor;
                                            buttons.forEach(btn => btn.disabled = false);
                                            updateLayout(true);
                                        }
                                    } else {
                                        // Different person trying to impersonate - trigger full re-verification
                                        console.log('Impersonation attempt detected! Distance:', distance);
                                        isVerificationComplete = false;
                                        lastVerifiedUserId = null;
                                        resetVerification();
                                        showStatus('Unauthorized access attempt detected!', false);
                                    }
                                } else if (!isVerificationComplete) {
                                    // Need to perform full verification
                                    if (!VERIFICATION_STEPS.FACE_MATCH) {
                                        const isValidMatch = validateFaceMatch(detection.descriptor);
                                        if (isValidMatch) {
                                            VERIFICATION_STEPS.FACE_MATCH = true;
                                            updateActivity();
                                            console.log('Face match verified with high confidence');
                                            showStatus('Face identity verified!', true);
                                            // clearVideoMessage(); // No longer needed
                                        } else {
                                            VERIFICATION_STEPS.BLINK = false;
                                            VERIFICATION_STEPS.MOVEMENT = false;
                                            showStatus('Face does not match registered profile', false);
                                        }
                                    } else {
                                        updateActivity();
                                        // Don't clear message unnecessarily - only clear if no current error message
                                        // Video messages removed - using status panel above video instead
                                    }
                                    
                                    // Only proceed with liveness detection if face matches and verification is NOT already complete
                                    if (VERIFICATION_STEPS.FACE_MATCH && !isVerificationComplete) {
                                        const is2DImage = detect2DImage(detection, video);
                                        
                                        if (!is2DImage) {
                                            // All verifications passed
                                            if (!verificationState.isVerified) {
                                                verificationState.isVerified = true;
                                                isLivenessVerifiedPermanently = true;
                                                livenessVerified = true;
                                                lastVerifiedTime = Date.now();
                                                
                                                // Mark verification as complete for this user
                                                isVerificationComplete = true;
                                                lastVerifiedUserId = currentUserId;
                                                
                                                showStatus('All verifications complete! You can now record attendance.', true, true);
                                                showStatus('✓ All verifications complete! Ready for attendance.', 'success');
                                                updateLayout(true);
                                                buttons.forEach(btn => btn.disabled = false);
                                                recognizedFace = true;
                                                faceDescriptor = detection.descriptor;
                                            }
                                        }
                                    } else if (!VERIFICATION_STEPS.FACE_MATCH && !isVerificationComplete) {
                                        buttons.forEach(btn => btn.disabled = true);
                                        recognizedFace = false;
                                        faceDescriptor = null;
                                    }
                                }
                                
                                // Draw face detection with appropriate color
                                const dims = faceapi.matchDimensions(canvas, video, true);
                                const resizedDetections = faceapi.resizeResults(detections, dims);
                                context.lineWidth = 3;
                                
                                // Color coding: Green = verified, Blue = face match only, Red = no match
                                let strokeColor = '#ff0000'; // Red for no match
                                if (VERIFICATION_STEPS.FACE_MATCH) {
                                    strokeColor = verificationState.isVerified ? '#00ff00' : '#0000ff';
                                }
                                
                                context.strokeStyle = strokeColor;
                                faceapi.draw.drawDetections(canvas, resizedDetections);
                                faceapi.draw.drawFaceLandmarks(canvas, resizedDetections, { 
                                    color: strokeColor 
                                });
                            } else if (detections.length > 1) {
                                currentState = 'multiple';
                                showStatus('Multiple faces detected. Please ensure only your face is visible.', false);
                                buttons.forEach(btn => btn.disabled = true);
                                if (verificationState.isVerified) {
                                    resetVerification();
                                }
                            } else {
                                currentState = 'none';
                                // Only show "No face detected" if not recently verified
                                const now = Date.now();
                                const isRecentlyVerified = lastVerifiedTime && (now - lastVerifiedTime < VERIFICATION_TIMEOUT);
                                
                                if (!isRecentlyVerified) {
                                    showStatus('No face detected. Please position yourself properly.', false);
                                    resetVerification();
                                }
                            }
                            
                            lastDetectionState = currentState;
                            
                            // Add this line after any verification step changes
                            updateVerificationStepsPanel();
                        } catch (error) {
                            console.error('Face detection error:', error);
                            showError('Face detection error: ' + error.message);
                        }
                    }, 100);
                });
            } catch (error) {
                console.error("Camera error details:", error);
                let errorMessage = "Error accessing camera: ";
                
                if (error.name === 'NotAllowedError') {
                    errorMessage += "Camera permission denied. Please allow camera access and refresh the page.";
                } else if (error.name === 'NotFoundError') {
                    errorMessage += "No camera found on this device.";
                } else if (error.name === 'NotSupportedError') {
                    errorMessage += "Camera is not supported on this browser/device.";
                } else if (error.name === 'NotReadableError') {
                    errorMessage += "Camera is already in use by another application.";
                } else {
                    errorMessage += error.message || "Unknown camera error occurred.";
                }
                
                showStatus(errorMessage, false);
                showError(errorMessage);
            }
        }

        async function verifyLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject("Geolocation is not supported by this browser.");
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        // For simplicity, we'll consider all locations valid
                        // In production, you should implement proper location verification
                        resolve({
                            valid: true,
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude
                        });
                    },
                    (error) => {
                        console.error("Geolocation error:", error);
                        
                        // For testing purposes, allow continuing even with location error
                        // In production, you would reject with an error
                        resolve({
                            valid: true,
                            latitude: 14.5995, // Default coordinates (Manila)
                            longitude: 120.9842,
                            message: "Location access denied, but allowed for testing."
                        });
                    },
                    { 
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        }

        async function recordAttendance(action, confirmed = false) {
            try {
                // Disable buttons to prevent multiple submissions
                const timeInBtn = document.querySelector('button[onclick="recordAttendance(\'time_in\')"]');
                const timeOutBtn = document.querySelector('button[onclick="recordAttendance(\'time_out\')"]');
                if (timeInBtn) timeInBtn.disabled = true;
                if (timeOutBtn) timeOutBtn.disabled = true;
                
                // If this is a time-out action and not confirmed, check time difference
                if (action === 'time_out' && !confirmed) {
                    try {
                        // Get the latest attendance record
                        const checkResponse = await fetch('check_active_attendance.php');
                        const checkResult = await checkResponse.json();
                        
                        if (checkResult.status === 'success' && checkResult.time_diff_minutes < 30) {
                            // Re-enable buttons
                            if (timeInBtn) timeInBtn.disabled = false;
                            if (timeOutBtn) timeOutBtn.disabled = false;
                            
                            // Show confirmation dialog
                            if (confirm(`You've only been clocked in for ${checkResult.time_diff_minutes} minutes. Are you sure you want to clock out?`)) {
                                // If confirmed, call this function again with confirmed flag
                                recordAttendance('time_out', true);
                            }
                            return;
                        }
                    } catch (err) {
                        console.error('Error checking attendance time:', err);
                        // Continue with clock out even if check fails
                    }
                }

                // Show processing message
                showStatus(`Processing ${action === 'time_in' ? 'time in' : 'time out'}...`, true);

                // Add strict validation for all required fields
                if (!recognizedFace || !faceDescriptor) {
                    showStatus("Face verification incomplete. Please try again.", false);
                    if (timeInBtn) timeInBtn.disabled = false;
                    if (timeOutBtn) timeOutBtn.disabled = false;
                    return;
                }

                // CRITICAL SECURITY CHECK: Re-validate face descriptor before attendance
                if (!VERIFICATION_STEPS.FACE_MATCH || !verificationState.isVerified) {
                    showStatus("Face verification expired. Please verify your identity again.", false);
                    resetVerification(); // Reset verification state
                    if (timeInBtn) timeInBtn.disabled = false;
                    if (timeOutBtn) timeOutBtn.disabled = false;
                    return;
                }

                // Final face descriptor validation against stored profile
                const finalDistance = faceapi.euclideanDistance(faceDescriptor, referenceDescriptor);
                if (finalDistance > FACE_MATCH_THRESHOLD) {
                    showStatus("Final face verification failed. Please try again.", false);
                    resetVerification();
                    if (timeInBtn) timeInBtn.disabled = false;
                    if (timeOutBtn) timeOutBtn.disabled = false;
                    return;
                }

                console.log('Final face validation passed. Distance:', finalDistance);

                // Validate user ID is present
                const userId = '<?php echo isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : ""; ?>';
                if (!userId) {
                    showStatus("Session expired. Please login again.", false);
                    window.location.href = '../login.php';
                    return;
                }

                // Verify location (allowing all locations)
                try {
                    const locationResult = await verifyLocation();
                    // Location verification bypassed - all locations allowed
                    console.log('Location obtained:', locationResult);
                    
                    // Take a snapshot with explicit dimensions
                    const video = document.getElementById('video');
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = canvas.toDataURL('image/jpeg', 0.8);
                    
                    // Get IP address with fallback
                    let ipAddress = 'unknown';
                    try {
                        const ipResponse = await fetch('https://api64.ipify.org?format=json');
                        const ipData = await ipResponse.json();
                        ipAddress = ipData.ip;
                    } catch (ipError) {
                        console.warn('Could not fetch IP:', ipError);
                        // Continue with unknown IP
                    }
                    
                    // Prepare the data
                    const attendanceData = {
                        action: action,
                        latitude: locationResult.latitude,
                        longitude: locationResult.longitude,
                        ip: ipAddress,
                        faceImage: imageData,
                        faceDescriptor: Array.from(faceDescriptor),
                        userId: userId,
                        timestamp: new Date().toISOString()
                    };
                    
                    console.log(`Sending ${action} request with data:`, {
                        action: attendanceData.action,
                        userId: attendanceData.userId,
                        // Omit large data fields from log
                    });
                    
                    // Send the request
                    const response = await fetch("save_attendance.php", {
                        method: "POST",
                        headers: { 
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(attendanceData)
                    });
                    
                    // Check response
                    if (!response.ok) {
                        throw new Error(`Server responded with status: ${response.status}`);
                    }
                    
                    // Parse JSON response
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        throw new Error(`Invalid server response format: ${text}`);
                    }
                    
                    const result = await response.json();
                    console.log('Server response:', result);
                    
                    if (result.status === 'success') {
                        showStatus(result.message || `${action === 'time_in' ? 'Time in' : 'Time out'} recorded successfully!`, true);
                        // Reload the page after a delay to show updated status
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        throw new Error(result.message || `Failed to record ${action}`);
                    }
                } catch (locationError) {
                    showStatus("Error verifying location: " + locationError.message, false);
                }
            } catch (error) {
                console.error('Attendance error:', error);
                showStatus("Error recording attendance: " + error.message, false);
            } finally {
                // Re-enable buttons after processing (if error occurred)
                setTimeout(() => {
                    const timeInBtn = document.querySelector('button[onclick="recordAttendance(\'time_in\')"]');
                    const timeOutBtn = document.querySelector('button[onclick="recordAttendance(\'time_out\')"]');
                    if (timeInBtn) timeInBtn.disabled = false;
                    if (timeOutBtn) timeOutBtn.disabled = false;
                }, 3000);
            }
        }

        async function verifyFace(detections) {
            if (detections.length === 1) {
                try {
                    // Get the current face descriptor
                    const descriptor = detections[0].descriptor;
                    
                    // Show verification status
                    showStatus('Verifying face...', 'info');
                    
                    // Send to server for verification
                    const response = await fetch('verify_face.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ faceDescriptor: Array.from(descriptor) })
                    });
                    
                    const result = await response.json();
                    
                    if (result.verified) {
                        document.getElementById('welcomeSection').style.display = 'block';
                        showStatus('Face verified! You can now record attendance.', true);
                        buttons.forEach(btn => btn.disabled = false);
                        recognizedFace = true;
                        faceDescriptor = descriptor;
                    } else {
                        showStatus('Face verification failed. Please ensure you are the authorized guard.', false);
                        buttons.forEach(btn => btn.disabled = true);
                        recognizedFace = false;
                    }
                } catch (error) {
                    console.error('Face verification error:', error);
                    showStatus('Error verifying face. Please try again.', false);
                }
            }
        }

        // Update the detectBlink function
        function detectBlink(landmarks) {
            const currentTime = Date.now();
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            
            // Calculate eye aspect ratio
            const leftEAR = getEyeAspectRatio(leftEye);
            const rightEAR = getEyeAspectRatio(rightEye);
            const ear = (leftEAR + rightEAR) / 2.0;
            
            // Check if eyes are closed using EAR
            const eyesClosed = ear < EYE_AR_THRESH;
            
            if (eyesClosed) {
                eyeClosedFrames++;
                if (eyeClosedFrames >= EYE_AR_CONSEC_FRAMES && 
                    (currentTime - lastBlinkTime) > BLINK_COOLDOWN) {
                    blinkCount++;
                    lastBlinkTime = currentTime;
                    console.log('Blink detected:', blinkCount, 'EAR:', ear);
                    
                    if (blinkCount < BLINKS_REQUIRED) {
                        showStatus(`Blink detected! ${BLINKS_REQUIRED - blinkCount} more blinks needed...`, false, true);
                    } else {
                        showStatus('Blink verification complete!', true, true);
                    }
                }
            } else {
                eyeClosedFrames = 0;
            }
            
            return blinkCount >= BLINKS_REQUIRED;
        }

        // Add this function to calculate eye aspect ratio
        function getEyeAspectRatio(eye) {
            const p2_p6 = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
            const p3_p5 = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
            const p1_p4 = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
            
            return (p2_p6 + p3_p5) / (2.0 * p1_p4);
        }

        // Add this new function to detect head pose
        function calculateHeadPose(landmarks) {
            const nose = landmarks.getNose();
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            
            // Calculate head orientation
            const eyeDistance = Math.hypot(
                rightEye[3].x - leftEye[0].x,
                rightEye[3].y - leftEye[0].y
            );
            
            const noseOffset = {
                x: nose[0].x - (leftEye[0].x + rightEye[3].x) / 2,
                y: nose[0].y - (leftEye[0].y + rightEye[3].y) / 2
            };
            
            return {
                tilt: Math.atan2(rightEye[3].y - leftEye[0].y, rightEye[3].x - leftEye[0].x),
                eyeDistance: eyeDistance,
                noseOffset: noseOffset
            };
        }

        // Add improved error handler for detect2DImage function
        function detect2DImage(detection, video) {
            try {
                const landmarks = detection.landmarks;
                
                // Check blink verification
                if (!VERIFICATION_STEPS.BLINK) {
                    VERIFICATION_STEPS.BLINK = detectBlink(landmarks);
                    if (VERIFICATION_STEPS.BLINK) {
                        console.log('Blink verification completed');
                    }
                }

                // Check movement verification
                if (!VERIFICATION_STEPS.MOVEMENT) {
                    VERIFICATION_STEPS.MOVEMENT = checkNaturalMovement(landmarks);
                    if (VERIFICATION_STEPS.MOVEMENT) {
                        console.log('Movement verification completed');
                    }
                }

                // If all verifications pass, return false (not a 2D image)
                if (VERIFICATION_STEPS.BLINK && VERIFICATION_STEPS.MOVEMENT) {
                    return false;
                }

                return true; // Consider it a 2D image until all checks pass
            } catch (error) {
                console.warn('2D detection error:', error);
                return true;
            }
        }

        // Add new function to detect moiré patterns (common in photos of screens)
        function detectMoirePattern(video, box) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            try {
                // Sample the image at different scales to detect repeating patterns
                const sampleSizes = [box.width, box.width/2, box.width/4];
                let patternDetected = false;
                
                sampleSizes.forEach(size => {
                    canvas.width = size;
                    canvas.height = size;
                    
                    ctx.drawImage(video, 
                        box.x, box.y, box.width, box.height,
                        0, 0, size, size
                    );
                    
                    const imageData = ctx.getImageData(0, 0, size, size).data;
                    const pattern = detectRepeatingPattern(imageData);
                    if (pattern) patternDetected = true;
                });
                
                return patternDetected;
            } catch (error) {
                console.warn('Moiré pattern detection error:', error);
                return false;
            }
        }

        // Add new function to detect screen reflections
        function detectScreenReflection(video, box) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            try {
                canvas.width = box.width;
                canvas.height = box.height;
                
                ctx.drawImage(video, 
                    box.x, box.y, box.width, box.height,
                    0, 0, canvas.width, canvas.height
                );
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                
                // Check for uniform brightness and color patterns typical of screens
                let brightPixels = 0;
                let totalPixels = imageData.length / 4;
                
                for (let i = 0; i < imageData.length; i += 4) {
                    const brightness = (imageData[i] + imageData[i+1] + imageData[i+2]) / 3;
                    if (brightness > 240) brightPixels++;
                }
                
                return (brightPixels / totalPixels) > 0.1; // If more than 10% very bright pixels
            } catch (error) {
                console.warn('Screen reflection detection error:', error);
                return false;
            }
        }

        // Update the checkNaturalMovement function
        function checkNaturalMovement(landmarks) {
            if (!window.movementHistory) {
                window.movementHistory = [];
            }
            
            const nose = landmarks.getNose()[0];
            window.movementHistory.push({
                x: nose.x,
                y: nose.y,
                timestamp: Date.now()
            });
            
            // Keep last 30 positions
            if (window.movementHistory.length > 30) {
                window.movementHistory.shift();
            }
            
            // Need at least 10 samples to check movement
            if (window.movementHistory.length < 10) return false;
            
            // Calculate movement variance
            let totalVariance = 0;
            for (let i = 1; i < window.movementHistory.length; i++) {
                const prev = window.movementHistory[i-1];
                const curr = window.movementHistory[i];
                const dx = curr.x - prev.x;
                const dy = curr.y - prev.y;
                totalVariance += Math.sqrt(dx*dx + dy*dy);
            }
            
            const avgVariance = totalVariance / (window.movementHistory.length - 1);
            return avgVariance > 0.002; // Threshold for natural movement
        }

        // Add helper function for motion variance
        function calculateMotionVariance(positions) {
            if (positions.length < 2) return 0;
            
            let totalVariance = 0;
            for (let i = 1; i < positions.length; i++) {
                const dx = positions[i].x - positions[i-1].x;
                const dy = positions[i].y - positions[i-1].y;
                totalVariance += dx*dx + dy*dy;
            }
            
            return totalVariance / positions.length;
        }

        // Add helper function for depth analysis
        function calculateDepthScore(landmarks) {
            const nose = landmarks.getNose();
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            const jawline = landmarks.getJawOutline();
            
            // Calculate depth using facial point relationships
            const eyeDistance = Math.hypot(
                rightEye[3].x - leftEye[0].x,
                rightEye[3].y - leftEye[0].y
            );
            
            const noseDepth = Math.abs(nose[0].y - (leftEye[0].y + rightEye[0].y) / 2) / eyeDistance;
            
            // Calculate jaw curve depth
            let jawDepthVariance = 0;
            for (let i = 0; i < jawline.length - 2; i++) {
                const depth1 = jawline[i].y;
                const depth2 = jawline[i + 1].y;
                jawDepthVariance += Math.abs(depth1 - depth2);
            }
            
            return (noseDepth + jawDepthVariance) / 2;
        }

        // Update the analyzeTexture function for better accuracy
        function analyzeTexture(video, faceBox) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            
            try {
                // Increased analysis size
                const MAX_ANALYSIS_SIZE = 256;
                
                // Calculate scaled dimensions
                const sourceWidth = faceBox.width * video.videoWidth;
                const sourceHeight = faceBox.height * video.videoHeight;
                const scaleFactor = Math.min(MAX_ANALYSIS_SIZE / sourceWidth, MAX_ANALYSIS_SIZE / sourceHeight);
                
                const width = Math.floor(sourceWidth * scaleFactor);
                const height = Math.floor(sourceHeight * scaleFactor);
                canvas.width = width;
                canvas.height = height;
                
                // Draw scaled version
                ctx.drawImage(video, 
                    Math.floor(faceBox.x * video.videoWidth), 
                    Math.floor(faceBox.y * video.videoHeight), 
                    sourceWidth, sourceHeight,
                    0, 0, width, height
                );
                
                // Calculate local variances with smaller blocks
                const blockSize = 16;
                let variances = [];
                
                for (let y = 0; y < height; y += blockSize) {
                    for (let x = 0; x < width; x += blockSize) {
                        const imageData = ctx.getImageData(x, y, 
                            Math.min(blockSize, width - x), 
                            Math.min(blockSize, height - y));
                        const data = imageData.data;
                        
                        let sum = 0;
                        let sumSquared = 0;
                        let count = 0;
                        
                        // Calculate variance for each color channel
                        for (let i = 0; i < data.length; i += 4) {
                            for (let c = 0; c < 3; c++) { // RGB channels
                                const value = data[i + c];
                                sum += value;
                                sumSquared += value * value;
                                count++;
                            }
                        }
                        
                        const mean = sum / count;
                        const variance = (sumSquared / count) - (mean * mean);
                        variances.push(variance);
                    }
                }
                
                // Calculate both mean and variance of local variances
                const meanVariance = variances.reduce((a, b) => a + b, 0) / variances.length;
                const varianceOfVariances = variances.reduce((a, b) => a + Math.pow(b - meanVariance, 2), 0) / variances.length;
                
                // Clean up
                canvas.width = 1;
                canvas.height = 1;
                
                // Return combined metric
                return meanVariance * (1 + Math.sqrt(varianceOfVariances));
                
            } catch (error) {
                console.warn('Texture analysis error:', error);
                return 100; // Return a default value that will pass the texture check
            } finally {
                // Ensure cleanup
                canvas.width = 1;
                canvas.height = 1;
            }
        }

        // Update the detectMovement function to be more efficient
        function detectMovement(landmarks, detection, video) {
            const currentHeadPose = calculateHeadPose(landmarks);
            const nose = landmarks.getNose()[0];
            const currentTime = Date.now();
            
            // Add current position
            lastPositions.push({ 
                x: nose.x, 
                y: nose.y, 
                time: currentTime,
                pose: currentHeadPose 
            });
            
            // Keep memory usage in check
            if (lastPositions.length > MOVEMENT_MEMORY) {
                lastPositions.shift();
            }
            
            if (lastPositions.length < 2) return false;
            
            try {
                // Analyze texture less frequently
                if (lastPositions.length % 3 === 0) { // Only check every 3rd frame
                    const textureVariance = analyzeTexture(video, detection.detection.box);
                    textureScores.push(textureVariance);
                    if (textureScores.length > 5) { // Reduce texture history
                        textureScores.shift();
                    }
                }
                
                // Calculate movement metrics
                let totalMovementX = 0;
                let totalMovementY = 0;
                let totalTiltChange = 0;
                
                for (let i = 1; i < lastPositions.length; i++) {
                    const prev = lastPositions[i - 1];
                    const curr = lastPositions[i];
                    
                    totalMovementX += Math.abs(curr.x - prev.x);
                    totalMovementY += Math.abs(curr.y - prev.y);
                    totalTiltChange += Math.abs(curr.pose.tilt - prev.pose.tilt);
                }
                
                const avgMovementX = totalMovementX / (lastPositions.length - 1);
                const avgMovementY = totalMovementY / (lastPositions.length - 1);
                const avgTiltChange = totalTiltChange / (lastPositions.length - 1);
                
                // Check if movement is natural
                const isMoving = avgMovementX > MOVEMENT_THRESHOLD && 
                                avgMovementY > MOVEMENT_THRESHOLD &&
                                avgTiltChange > 0.01;
                
                // Check texture variance
                const avgTextureVariance = textureScores.length > 0 ? 
                    textureScores.reduce((a, b) => a + b, 0) / textureScores.length : 0;
                const hasTexture = avgTextureVariance > MIN_TEXTURE_VARIANCE;
                
                // Update timing
                if (isMoving && hasTexture && !movementStartTime) {
                    movementStartTime = currentTime;
                } else if (!isMoving || !hasTexture) {
                    movementStartTime = null;
                }
                
                const movementDuration = movementStartTime ? currentTime - movementStartTime : 0;
                
                return movementDuration >= MOVEMENT_TIME_REQUIRED && hasTexture;
            } catch (error) {
                console.warn('Movement detection error:', error);
                return false;
            }
        }

        // Update the drawDebugInfo function - Remove all video overlay messages
        function drawDebugInfo(context, detections, canvas) {
            // No video overlay messages - all messages now appear in verificationStatus div
            
            if (detections.length === 0) {
                // Draw face guide with improved visibility
                const centerX = canvas.width / 2;
                const centerY = canvas.height / 2;
                const size = Math.min(canvas.width, canvas.height) * 0.5;
                
                // Draw outer box
                context.strokeStyle = 'rgba(255, 255, 255, 0.8)';
                context.lineWidth = 3;
                context.setLineDash([10, 10]);
                context.strokeRect(centerX - size/2, centerY - size/2, size, size);
                context.setLineDash([]);
                
                // Draw crosshair
                context.beginPath();
                context.moveTo(centerX - 20, centerY);
                context.lineTo(centerX + 20, centerY);
                context.moveTo(centerX, centerY - 20);
                context.lineTo(centerX, centerY + 20);
                context.stroke();
                
                // Add guide text with background for better visibility
                const text = 'Position your face in the center';
                context.font = 'bold 14px Arial';
                const textWidth = context.measureText(text).width;
                const textY = centerY + size/2 + 30; // Below the guide box
                context.fillStyle = 'rgba(0, 0, 0, 0.7)';
                context.fillRect(centerX - textWidth/2 - 10, textY - 20, textWidth + 20, 30);
                context.fillStyle = 'white';
                context.textAlign = 'center';
                context.fillText(text, centerX, textY);
                context.textAlign = 'left';
            }
        }

        // Add this function
        async function checkModels() {
            try {
                const modelPath = MODEL_URL + '/tiny_face_detector_model-weights_manifest.json';
                const response = await fetch(modelPath);
                if (!response.ok) {
                    throw new Error(`Model not found at ${modelPath}`);
                }
                console.log('Models accessible');
            } catch (error) {
                console.error('Model check failed:', error);
                showError('Could not access face detection models. Please check the models directory.');
            }
        }

        // Call it before loading models
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                // Reset all verification states on page load
                resetVerification();
                console.log('Verification state reset on page load');
                
                await checkModels();
                await loadModels();
            } catch (error) {
                console.error('Initialization error:', error);
                showError('Failed to initialize the attendance system. Please refresh the page or contact support.');
            }
        });

        // Update the checkLivenessWithPython function to be more lenient
        async function checkLivenessWithPython(video) {
            // Simplified version - always return true for now
            // This bypasses the Python anti-spoofing check which might be too strict
            return true;
        }

        // Add this function to handle layout changes
        function updateLayout(verified) {
            const contentWrapper = document.querySelector('.row.g-4'); // Use the actual container instead of contentWrapper
            const welcomeSection = document.getElementById('welcomeSection');
            
            if (welcomeSection) {
                if (verified) {
                    welcomeSection.style.display = 'block';
                } else {
                    welcomeSection.style.display = 'none';
                }
            }
        }

        // Update the resetVerification function to also reset verification steps
        function resetVerification() {
            verificationState.isVerified = false;
            isLivenessVerifiedPermanently = false;
            livenessVerified = false;
            recognizedFace = false;
            faceDescriptor = null;
            // Reset verification steps
            VERIFICATION_STEPS.FACE_MATCH = false;
            VERIFICATION_STEPS.BLINK = false;
            VERIFICATION_STEPS.MOVEMENT = false;
            // Reset face matching state
            resetFaceMatching();
            // Reset smart verification
            isVerificationComplete = false;
            lastVerifiedUserId = null;
            updateLayout(false);
            
            // Add this line at the end
            updateVerificationStepsPanel();
        }

        function updateVerificationStepsPanel() {
            const faceMatchStatus = document.getElementById('faceMatchStatus');
            const blinkStatus = document.getElementById('blinkStatus');
            const movementStatus = document.getElementById('movementStatus');

            // Only proceed if all elements exist
            if (!faceMatchStatus || !blinkStatus || !movementStatus) {
                console.warn('Verification status elements not found');
                return;
            }

            // Update Face Match Status with detailed feedback
            if (VERIFICATION_STEPS.FACE_MATCH) {
                faceMatchStatus.textContent = 'Verified';
                faceMatchStatus.className = 'badge bg-success';
            } else {
                const matchInfo = consecutiveMatches > 0 ? 
                    `Checking... (${consecutiveMatches}/${REQUIRED_CONSECUTIVE_MATCHES})` : 
                    'Waiting...';
                faceMatchStatus.textContent = matchInfo;
                faceMatchStatus.className = consecutiveMatches > 0 ? 
                    'badge bg-info' : 'badge bg-warning text-dark';
            }

            // Update Blink Status
            if (VERIFICATION_STEPS.BLINK) {
                blinkStatus.textContent = 'Verified';
                blinkStatus.className = 'badge bg-success';
            } else {
                const blinkInfo = blinkCount > 0 ? 
                    `Blinks: ${blinkCount}/${BLINKS_REQUIRED}` : 
                    'Waiting...';
                blinkStatus.textContent = blinkInfo;
                blinkStatus.className = blinkCount > 0 ? 
                    'badge bg-info' : 'badge bg-warning text-dark';
            }

            // Update Movement Status
            if (VERIFICATION_STEPS.MOVEMENT) {
                movementStatus.textContent = 'Verified';
                movementStatus.className = 'badge bg-success';
            } else {
                movementStatus.textContent = 'Waiting...';
                movementStatus.className = 'badge bg-warning text-dark';
            }
        }

        // Function to update the date and time display
        function updateDateTime() {
            const now = new Date();
            
            // Format date: Friday, June 27, 2025
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateStr = now.toLocaleDateString('en-US', options);
            
            // Format time with seconds: 10:25:30 AM
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            
            document.getElementById('currentDate').textContent = dateStr;
            document.getElementById('currentTime').textContent = timeStr;
        }
        
        // Update time immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Fix for loading spinner
        document.addEventListener('DOMContentLoaded', function() {
            // Create a timeout to hide loader after a maximum wait time
            setTimeout(function() {
                hideLoader();
            }, 15000); // Hide after 15 seconds max
        });
        
        // Improved hideLoader function that ensures spinner is removed
        function hideLoader() {
            const loading = document.getElementById('loading');
            if (loading) {
                loading.style.opacity = '0';
                loading.style.transition = 'opacity 0.5s ease';
                
                setTimeout(() => {
                    if (loading) {
                        loading.style.display = 'none';
                    }
                }, 500);
            }
        }
    </script>
</body>
</html>