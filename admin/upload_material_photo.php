<?php
session_name('mp_admin');
session_start();

$rootDir = __DIR__ . '/../';
$uploadDir = $rootDir . 'img/products-img/materials/';
$uploadUrl = '../img/products-img/materials/';

// Проверка авторизации
if (empty($_SESSION['auth'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Не удалось создать папку для загрузки']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Нет прав на запись в папку']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Метод не POST']);
    exit;
}

if (!isset($_FILES['photo'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Файл не передан']);
    exit;
}

$file = $_FILES['photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Файл превышает максимальный размер',
        UPLOAD_ERR_FORM_SIZE => 'Файл превышает максимальный размер формы',
        UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
        UPLOAD_ERR_NO_FILE => 'Файл не выбран',
        UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки',
        UPLOAD_ERR_CANT_WRITE => 'Ошибка записи на диск',
        UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку',
    ];
    $errorMsg = $errors[$file['error']] ?? 'Неизвестная ошибка';
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $errorMsg]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Разрешены только JPG, PNG, WEBP']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Файл слишком большой (макс. 5 МБ)']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowedMimes)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Неподдерживаемый формат изображения']);
    exit;
}

$name = 'material_' . uniqid() . '.' . $ext;
$dest = $uploadDir . $name;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'path' => $uploadUrl . $name]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл']);
}
?>