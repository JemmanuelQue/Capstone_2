<?php
// Create models directory if it doesn't exist
$modelsDir = __DIR__ . '/models';
if (!file_exists($modelsDir)) {
    mkdir($modelsDir, 0777, true);
}

// Create temp directory for face image processing if it doesn't exist
if (!file_exists('temp')) {
    mkdir('temp', 0777, true);
}

// Clear existing models
foreach (glob($modelsDir . '/*') as $file) {
    unlink($file);
}

// Define model URLs - using a reliable CDN
$modelFiles = [
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/tiny_face_detector_model-weights_manifest.json',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/tiny_face_detector_model-shard1',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_landmark_68_model-weights_manifest.json',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_landmark_68_model-shard1',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_recognition_model-weights_manifest.json',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_recognition_model-shard1',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_recognition_model-shard2',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_expression_model-weights_manifest.json',
    'https://raw.githubusercontent.com/WebDevSimplified/Face-Detection-JavaScript/master/models/face_expression_model-shard1'
];

echo "<h2>Downloading Face Recognition Models</h2>";

foreach ($modelFiles as $url) {
    $fileName = basename($url);
    $destination = $modelsDir . '/' . $fileName;
    
    echo "Downloading {$fileName}... ";
    
    $content = file_get_contents($url);
    if ($content !== false && file_put_contents($destination, $content)) {
        echo "<span style='color: green'>SUCCESS</span><br>";
    } else {
        echo "<span style='color: red'>FAILED</span><br>";
    }
}

echo "<br>Download complete. Please refresh your attendance page.";

// Verify manifest files
echo "<br><h3>Model Verification:</h3>";
$allValid = true;

foreach ($modelFiles as $url) {
    $fileName = basename($url);
    $filePath = $modelsDir . '/' . $fileName;
    if (!file_exists($filePath)) {
        echo "<p class='error'>❌ {$fileName} is missing</p>";
        $allValid = false;
        continue;
    }

    $size = filesize($filePath);
    if ($size < 1000 && !strpos($fileName, 'manifest.json')) {
        echo "<p class='warning'>⚠️ {$fileName} seems too small ({$size} bytes)</p>";
        $allValid = false;
        continue;
    }

    if (strpos($fileName, '.json') !== false) {
        $content = file_get_contents($filePath);
        if (json_decode($content) === null) {
            echo "<p class='error'>❌ {$fileName} is not valid JSON</p>";
            $allValid = false;
        } else {
            echo "<p class='success'>✅ {$fileName} is valid</p>";
        }
    } else {
        echo "<p class='success'>✅ {$fileName} exists and has reasonable size</p>";
    }
}

if ($allValid) {
    echo "<br><p class='success' style='font-weight: bold;'>✅ All models appear to be valid!</p>";
    echo "<p>Please try using the face recognition system now.</p>";
    
    // Update the guards_attendance.php file to use the correct model loading
    $attendanceFile = __DIR__ . '/guards_attendance.php';
    if (file_exists($attendanceFile)) {
        $content = file_get_contents($attendanceFile);
        
        // Update TensorFlow.js and face-api.js versions
        $content = preg_replace(
            '/<script src="https:\/\/cdn\.jsdelivr\.net\/npm\/@tensorflow\/tfjs@.*\/dist\/tf\.min\.js"><\/script>/',
            '<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@2.7.0/dist/tf.min.js"></script>',
            $content
        );
        
        $content = preg_replace(
            '/<script src="https:\/\/cdn\.jsdelivr\.net\/npm\/.*\/face-api.*\.js"><\/script>/',
            '<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>',
            $content
        );
        
        file_put_contents($attendanceFile, $content);
        echo "<p class='success'>✅ Updated JavaScript library references</p>";
    }
} else {
    echo "<br><p class='error' style='font-weight: bold;'>⚠️ Some models are invalid or missing.</p>";
    echo "<p>Please check the errors above and try downloading again.</p>";
}
?> 