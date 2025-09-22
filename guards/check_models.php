<?php
header('Content-Type: application/json');

$modelPath = __DIR__ . '/../models/';
$required_files = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_expression_model-weights_manifest.json',
    'face_expression_model-shard1'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($modelPath . $file)) {
        $missing_files[] = $file;
    }
}

echo json_encode([
    'status' => empty($missing_files) ? 'ok' : 'missing',
    'missing_files' => $missing_files,
    'model_path' => $modelPath
]); 