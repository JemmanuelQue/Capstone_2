<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 5)) { exit; }

// Check if guard_faces table exists and if user already has a registered face
$hasRegisteredFace = false;
try {
    // Check if guard_faces table exists
    $tableCheckStmt = $conn->query("SHOW TABLES LIKE 'guard_faces'");
    if ($tableCheckStmt->rowCount() > 0) {
        // Table exists, check if user has registered face
        $checkFaceStmt = $conn->prepare("SELECT COUNT(*) FROM guard_faces WHERE guard_id = ?");
        $checkFaceStmt->execute([$_SESSION['user_id']]);
        $hasRegisteredFace = $checkFaceStmt->fetchColumn() > 0;
    } else {
        // Table doesn't exist, so no face is registered yet
        $hasRegisteredFace = false;
    }
} catch (PDOException $e) {
    // Table doesn't exist or query failed, assume no face is registered yet
    $hasRegisteredFace = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register Face - Guard Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #256845;
            color: white;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        h2 {
            margin-bottom: 20px;
        }
        #face-container {
            position: relative;
            width: 70%;
            max-width: 600px;
            margin: 0 auto;
        }
        video {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
        }
        canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .btn-container {
            margin-top: 15px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
            background-color: #28a745;
        }
        button:hover { background-color: #218838; }
        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            display: none;
        }
        .success { 
            background-color: #28a745;
            display: block;
        }
        .error { 
            background-color: #dc3545;
            display: block;
        }
        #loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            color: white;
        }
        .instructions {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: left;
        }
        .instructions ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@1.7.4/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body>
    <div id="loading">
        <div>
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Loading face recognition models...</p>
        </div>
    </div>

    <div class="container">
        <?php if ($hasRegisteredFace): ?>
            <!-- Already Registered Message -->
            <div style="max-width: 600px; margin: 0 auto;">
                <i class="fas fa-check-circle fa-5x" style="color: #28a745; margin-bottom: 20px;"></i>
                <h2 style="color: #28a745;">Face Profile Successfully Registered! ✨</h2>
                <div style="background-color: rgba(255, 255, 255, 0.1); padding: 30px; border-radius: 15px; margin: 20px 0;">
                    <p style="font-size: 18px; line-height: 1.6; margin-bottom: 15px;">
                        Your biometric profile is already securely registered in our system. 
                        If you think this is a mistake, please contact HR or the Super Admin.
                        You're all set to use our advanced face recognition technology! 🎉
                    </p>
                    <div style="background-color: rgba(40, 167, 69, 0.2); padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0; font-size: 14px; opacity: 0.8;">
                            <i class="fas fa-info-circle"></i> 
                            Redirecting you to the dashboard in <span id="countdown">5</span> seconds...
                        </p>
                    </div>
                    <div style="margin-top: 30px;">
                        <a href="guards_dashboard.php" style="display: inline-block; background-color: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 0 10px; transition: 0.3s;">
                            <i class="fas fa-home"></i> Go to Dashboard Now
                        </a>
                        <a href="mailto:hr@greenmeadows.com" style="display: inline-block; background-color: #17a2b8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 0 10px; transition: 0.3s;">
                            <i class="fas fa-envelope"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Face Registration Form -->
            <h2>Register Your Face</h2>
            
            <!-- Warning Message -->
            <div style="background-color: rgba(255, 193, 7, 0.2); border: 2px solid #ffc107; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: left;">
                <h3 style="color: #ffc107; margin: 0 0 10px 0;">
                    <i class="fas fa-exclamation-triangle"></i> Important Notice
                </h3>
                <p style="margin: 0; font-size: 16px; line-height: 1.5;">
                    <strong>You can only register your face ONE TIME.</strong> Once your face profile is captured and saved, 
                    it cannot be changed or updated without contacting HR or the Admin. Please ensure you follow 
                    all instructions carefully before capturing your face.
                </p>
            </div>
            
            <div class="instructions">
                <h3>Instructions:</h3>
                <ul>
                    <li>Ensure you are in a well-lit area</li>
                    <li>Position your face in the center of the frame</li>
                    <li>Keep your face straight and neutral</li>
                    <li>Remove any face coverings or glasses</li>
                    <li>Stay still when capturing</li>
                </ul>
            </div>
            <div id="error-container"></div>
            <div id="face-container">
                <video id="video" autoplay playsinline></video>
                <canvas id="overlay"></canvas>
            </div>
            <div id="status" class="status"></div>
            <div class="btn-container">
                <button onclick="captureFace()" disabled><i class="fas fa-camera"></i> Capture Face</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let isModelLoaded = false;
        let recognizedFace = false;
        let faceDescriptor = null;
        const button = document.querySelector('button');
        const MODEL_URL = './models';
        let lastDetectionState = null;

        function showError(message) {
            const errorContainer = document.getElementById('error-container');
            errorContainer.innerHTML = `<div class="error-message">${message}</div>`;
        }

        function showStatus(message, isSuccess) {
            const status = document.getElementById('status');
            status.textContent = message;
            status.className = 'status ' + (isSuccess ? 'success' : 'error');
            status.style.display = 'block';
        }

        async function loadModels() {
            const loading = document.getElementById('loading');
            loading.style.display = 'flex';
            
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                
                isModelLoaded = true;
                loading.style.display = 'none';
                await startCamera();
            } catch (error) {
                console.error('Error loading models:', error);
                showError('Error loading face recognition models. Please refresh the page.');
                loading.style.display = 'none';
            }
        }

        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                const video = document.getElementById("video");
                video.srcObject = stream;

                video.addEventListener('play', () => {
                    const canvas = document.getElementById('overlay');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    
                    setInterval(async () => {
                        if (!isModelLoaded) return;
                        
                        try {
                            const detections = await faceapi.detectAllFaces(video, 
                                new faceapi.TinyFaceDetectorOptions())
                                .withFaceLandmarks()
                                .withFaceDescriptors();

                            const context = canvas.getContext('2d');
                            context.clearRect(0, 0, canvas.width, canvas.height);

                            let currentState;
                            if (detections.length === 1) {
                                currentState = 'single';
                                recognizedFace = true;
                                faceDescriptor = detections[0].descriptor;
                                
                                if (lastDetectionState !== currentState) {
                                    showStatus('Face detected! Click capture when ready.', true);
                                    button.disabled = false;
                                }

                                const dims = faceapi.matchDimensions(canvas, video, true);
                                const resizedDetections = faceapi.resizeResults(detections, dims);
                                faceapi.draw.drawDetections(canvas, resizedDetections);
                                faceapi.draw.drawFaceLandmarks(canvas, resizedDetections);
                            } else {
                                currentState = detections.length > 1 ? 'multiple' : 'none';
                                recognizedFace = false;
                                faceDescriptor = null;
                                button.disabled = true;
                                
                                if (lastDetectionState !== currentState) {
                                    showStatus(detections.length > 1 ? 
                                        'Multiple faces detected. Please ensure only your face is visible.' : 
                                        'No face detected. Please position yourself properly.', false);
                                }
                            }
                            
                            lastDetectionState = currentState;
                        } catch (error) {
                            console.error('Face detection error:', error);
                        }
                    }, 100);
                });
            } catch (error) {
                showStatus("Error accessing camera: " + error.message, false);
            }
        }

        async function captureFace() {
            if (!recognizedFace || !faceDescriptor) {
                showStatus("Please ensure your face is properly detected.", false);
                return;
            }

            try {
                // Disable the button while processing
                const captureButton = document.querySelector('button');
                captureButton.disabled = true;
                
                // Show loading status
                showStatus("Saving face profile...", true);

                const video = document.getElementById('video');
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                const imageData = canvas.toDataURL('image/jpeg');

                const response = await fetch('save_face_profile.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        faceDescriptor: Array.from(faceDescriptor),
                        profileImage: imageData
                    })
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }

                const result = await response.json();
                
                if (result.status === 'error') {
                    throw new Error(result.message);
                }

                showStatus(result.message, true);

                if (result.status === 'success') {
                    setTimeout(() => {
                        window.location.href = 'guards_dashboard.php';
                    }, 2000);
                }
            } catch (error) {
                console.error('Error saving face profile:', error);
                showStatus("Error saving face profile: " + error.message, false);
            } finally {
                // Re-enable the button
                const captureButton = document.querySelector('button');
                captureButton.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($hasRegisteredFace): ?>
                // Auto-redirect countdown for registered users
                let countdown = 5;
                const countdownElement = document.getElementById('countdown');
                
                const timer = setInterval(function() {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        window.location.href = 'guards_dashboard.php';
                    }
                }, 1000);
            <?php else: ?>
                loadModels();
            <?php endif; ?>
        });
    </script>
</body>
</html> 