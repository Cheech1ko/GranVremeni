<?php
session_start();

if (empty($_SESSION['auth'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$uploadDir = __DIR__ . '/../img/products-img/materials/';
$uploadUrl = '../img/products-img/materials/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Upload error']);
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid file type']);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'File too large']);
        exit;
    }
    
    $name = 'material_' . uniqid() . '.' . $ext;
    $dest = $uploadDir . $name;
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'path' => $uploadUrl . $name]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Move failed']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No file']);
}
?>