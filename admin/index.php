<?php
function loadAdminPassword() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'ADMIN_PASS=') === 0) {
                $pass = trim(substr($line, strlen('ADMIN_PASS=')));
                if (!empty($pass)) {
                    return $pass;
                }
            }
        }
    }
    
    $envPass = getenv('ADMIN_PASS');
    if (!empty($envPass)) {
        return $envPass;
    }
    
    die('Ошибка: пароль не найден. Создайте файл .env в корне сайта с ADMIN_PASS=ваш_пароль');
}

define('ADMIN_PASSWORD', loadAdminPassword());
define('SESSION_LIFETIME', 28800);
define('PRODUCTS_FILE',  __DIR__ . '/../data/products.json');
define('UPLOAD_DIR',     __DIR__ . '/../img/products-img/');
define('MATERIALS_UPLOAD_DIR', __DIR__ . '/../img/products-img/materials/');
define('UPLOAD_URL',     '../img/products-img/');
define('MATERIALS_UPLOAD_URL', '../img/products-img/materials/');
define('SESSION_NAME',   'mp_admin');

session_name(SESSION_NAME);
session_start();

if (!empty($_SESSION['auth'])) {
    $elapsed = time() - ($_SESSION['last_activity'] ?? 0);
    if ($elapsed > SESSION_LIFETIME) {
        session_destroy();
        session_start();
        $_SESSION = [];
    } else {
        $_SESSION['last_activity'] = time();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if ($_SESSION['login_attempts'] >= 5) {
    sleep(3);
}

$error = '';
$authed = !empty($_SESSION['auth']);

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['auth'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_attempts'] = 0;
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $error = 'Неверный пароль';
    }
}

function loadProducts(): array {
    if (!file_exists(PRODUCTS_FILE)) return [];
    $json = file_get_contents(PRODUCTS_FILE);
    $data = json_decode($json, true);
    $products = is_array($data) ? $data : [];

    $changed = false;
    foreach ($products as &$p) {
        if (empty($p['sku'])) {
            $p['sku'] = generateSku($products);
            $changed = true;
        }
    }
    unset($p);
    if ($changed) {
        saveProducts($products);
    }

    return $products;
}

function generateSku(array $products): string {
    $existing = [];
    foreach ($products as $p) {
        if (!empty($p['sku'])) {
            $existing[(string)$p['sku']] = true;
        }
    }
    do {
        $sku = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
    } while (isset($existing[$sku]));
    return $sku;
}

function saveProducts(array $products): bool {
    if (file_exists(PRODUCTS_FILE)) {
        $backupName = PRODUCTS_FILE . '.bak.' . date('Y-m-d_H-i-s');
        copy(PRODUCTS_FILE, $backupName);
        $backups = glob(PRODUCTS_FILE . '.bak.*');
        if (count($backups) > 10) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $toDelete = array_slice($backups, 0, count($backups) - 10);
            foreach ($toDelete as $f) {
                @unlink($f);
            }
        }
    }
    
    $dir = dirname(PRODUCTS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $fp = fopen(PRODUCTS_FILE, 'r+');
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    
    ftruncate($fp, 0);
    fwrite($fp, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    
    return true;
}

function generateId(): string {
    return 'p' . time() . rand(100, 999);
}

function parseOptionRows(string $prefix, array $post): array {
    $result = [];
    $labels = $post[$prefix . '_label'] ?? [];
    $prices = $post[$prefix . '_price'] ?? [];
    foreach ($labels as $i => $label) {
        $label = trim($label);
        if ($label === '') continue;
        $result[] = ['label' => $label, 'price' => (int)($prices[$i] ?? 0)];
    }
    return $result;
}

function parseMaterialsWithPhotos(array $post): array {
    $result = [];
    $labels = $post['materials_label'] ?? [];
    $photos = $post['materials_photo'] ?? [];
    
    foreach ($labels as $i => $label) {
        $label = trim($label);
        if ($label === '') continue;
        $photo = trim($photos[$i] ?? '');
        $result[] = ['label' => $label, 'photo' => $photo];
    }
    return $result;
}

function uploadPhoto(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } else {
            $mime = 'application/octet-stream';
        }
    }
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) return false;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;
    
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = uniqid('photo_') . '.' . $ext;
    $dest = UPLOAD_DIR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return UPLOAD_URL . $name;
}

function uploadMaterialPhoto(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } else {
            $mime = 'application/octet-stream';
        }
    }
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) return false;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;
    
    if (!is_dir(MATERIALS_UPLOAD_DIR)) mkdir(MATERIALS_UPLOAD_DIR, 0755, true);
    $name = uniqid('material_') . '.' . $ext;
    $dest = MATERIALS_UPLOAD_DIR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return MATERIALS_UPLOAD_URL . $name;
}

function getDefaultOptions(string $category): array {
    $options = [
        'engravings' => [
            ['label' => 'Без гравировки', 'price' => 0],
            ['label' => 'Портрет', 'price' => 3500],
            ['label' => 'Текст + эпитафия', 'price' => 1500],
            ['label' => 'Орнамент', 'price' => 2500],
            ['label' => 'Художественная резьба', 'price' => 6000]
        ],
        'finishes' => [
            ['label' => 'Полировка', 'price' => 0],
            ['label' => 'Шлифовка', 'price' => 1500],
            ['label' => 'Матовая', 'price' => 1000],
            ['label' => 'Золочение', 'price' => 5000]
        ],
        'services' => [
            ['label' => 'Установка на кладбище', 'price' => 5000],
            ['label' => 'Доставка по Москве', 'price' => 2000],
            ['label' => 'Демонтаж старого памятника', 'price' => 3000]
        ]
    ];
    
    if (in_array($category, ['fence', 'socle', 'vases'])) {
        $options['engravings'] = [['label' => 'Без гравировки', 'price' => 0]];
        $options['finishes'] = [['label' => 'Стандарт', 'price' => 0]];
        $options['services'] = [
            ['label' => 'Установка', 'price' => 5000],
            ['label' => 'Доставка по Москве', 'price' => 2000]
        ];
    }
    
    return $options;
}

if ($authed) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            die('CSRF атака');
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        $products = loadProducts();
        foreach ($products as $p) {
            if ($p['id'] === $_POST['id'] && !empty($p['photoURL'])) {
                $oldPhoto = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($p['photoURL'], './');
                if (file_exists($oldPhoto)) @unlink($oldPhoto);
                break;
            }
        }
        $products = array_values(array_filter($products, fn($p) => $p['id'] !== $_POST['id']));
        saveProducts($products);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    
    if ($action === 'toggle_hidden' && isset($_POST['id'])) {
        $products = loadProducts();
        foreach ($products as &$p) {
            if ($p['id'] === $_POST['id']) {
                $p['hidden'] = empty($p['hidden']);
                break;
            }
        }
        saveProducts($products);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    
    if ($action === 'clone' && isset($_POST['id'])) {
        $products = loadProducts();
        $clone = null;
        foreach ($products as $p) {
            if ($p['id'] === $_POST['id']) {
                $clone = $p;
                break;
            }
        }
        if ($clone) {
            $clone['id'] = generateId();
            $clone['sku'] = generateSku($products);
            $clone['name'] = $clone['name'] . ' (копия)';
            $clone['photoURL'] = '';
            $clone['hidden'] = true;
            $clone['sortOrder'] = count($products) + 1;
            $clone['categories'] = $clone['categories'] ?? [$clone['category'] ?? 'vertical'];
            unset($clone['category']);
            $products[] = $clone;
            saveProducts($products);
        }
        header('Location: index.php?cloned=1');
        exit;
    }
    
    if ($action === 'quick_add' && isset($_POST['category']) && isset($_POST['name'])) {
        $products = loadProducts();
        $options = getDefaultOptions($_POST['category']);
        
        $product = [
            'id' => generateId(),
            'sku' => generateSku($products),
            'name' => trim($_POST['name']),
            'categories' => [$_POST['category']],
            'price' => (int)($_POST['price'] ?? 0),
            'basePrice' => (int)($_POST['price'] ?? 0),
            'photoURL' => '',
            'badge' => trim($_POST['badge'] ?? '') ?: null,
            'popular' => isset($_POST['popular']),
            'hidden' => true,
            'sortOrder' => count($products) + 1,
            'description' => trim($_POST['description'] ?? ''),
            'components' => [],
            'sizes' => [],
            'materials' => [],
            'engravings' => $options['engravings'],
            'finishes' => $options['finishes'],
            'services' => $options['services']
        ];
        
        $products[] = $product;
        saveProducts($products);
        header('Location: index.php?quick_added=1');
        exit;
    }
    
    if ($action === 'reorder' && isset($_POST['ids'])) {
        $ids = json_decode($_POST['ids'], true);
        $products = loadProducts();
        $map = [];
        foreach ($products as $p) $map[$p['id']] = $p;
        $sorted = [];
        foreach ($ids as $i => $id) {
            if (isset($map[$id])) {
                $map[$id]['sortOrder'] = $i + 1;
                $sorted[] = $map[$id];
                unset($map[$id]);
            }
        }
        foreach ($map as $p) $sorted[] = $p;
        saveProducts($sorted);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $products = loadProducts();
        $id = trim($_POST['id'] ?? '');
        $isNew = ($id === '');
        if ($isNew) $id = generateId();
        
        $photoURL = $_POST['photoURL'] ?? '';
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = uploadPhoto($_FILES['photo']);
            if ($uploaded) {
                if (!$isNew) {
                    foreach ($products as $oldP) {
                        if ($oldP['id'] === $id && !empty($oldP['photoURL'])) {
                            $oldPhoto = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($oldP['photoURL'], './');
                            if (file_exists($oldPhoto)) @unlink($oldPhoto);
                            break;
                        }
                    }
                }
                $photoURL = $uploaded;
            }
        }
        
        $sizesRaw = $_POST['sizes'] ?? '';
        $sizes = array_values(array_filter(array_map('trim', explode("\n", $sizesRaw))));
        
        $materials = parseMaterialsWithPhotos($_POST);
        if (empty($materials)) {
            $materialsRaw = $_POST['materials_raw'] ?? '';
            $materialsList = array_values(array_filter(array_map('trim', explode("\n", $materialsRaw))));
            $materials = array_map(function($label) {
                return ['label' => $label, 'photo' => ''];
            }, $materialsList);
        }
        
        $categories = $_POST['categories'] ?? [];
        if (empty($categories)) {
            $categories = ['vertical'];
        }
        
        $engravings = parseOptionRows('engravings', $_POST);
        $finishes = parseOptionRows('finishes', $_POST);
        $services = parseOptionRows('services', $_POST);

        if ($isNew) {
            $sku = generateSku($products);
        } else {
            $sku = '';
            foreach ($products as $oldP) {
                if ($oldP['id'] === $id) {
                    $sku = $oldP['sku'] ?? '';
                    break;
                }
            }
            if ($sku === '') $sku = generateSku($products);
        }
        
        $product = [
            'id'          => $id,
            'sku'         => $sku,
            'name'        => trim($_POST['name'] ?? ''),
            'categories'  => $categories,
            'price'       => (int)($_POST['price'] ?? 0),
            'basePrice'   => (int)($_POST['price'] ?? 0),
            'photoURL'    => $photoURL,
            'badge'       => trim($_POST['badge'] ?? '') ?: null,
            'popular'     => isset($_POST['popular']),
            'hidden'      => isset($_POST['hidden']),
            'sortOrder'   => (int)($_POST['sortOrder'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'components'  => [],
            'sizes'       => $sizes,
            'materials'   => $materials,
            'engravings'  => $engravings,
            'finishes'    => $finishes,
            'services'    => $services,
        ];
        
        if ($isNew) {
            $products[] = $product;
        } else {
            $found = false;
            foreach ($products as &$p) {
                if ($p['id'] === $id) { $p = $product; $found = true; break; }
            }
            if (!$found) $products[] = $product;
        }
        
        usort($products, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
        saveProducts($products);
        
        header('Location: index.php?saved=1');
        exit;
    }
    
    if ($action === 'get' && isset($_GET['id'])) {
        $products = loadProducts();
        foreach ($products as $p) {
            if ($p['id'] === $_GET['id']) {
                if (!isset($p['categories']) && isset($p['category'])) {
                    $p['categories'] = [$p['category']];
                }
                if (empty($p['categories'])) {
                    $p['categories'] = ['vertical'];
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($p, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
        exit;
    }
}

$products = $authed ? loadProducts() : [];
$visibleProducts = array_filter($products, fn($p) => ($p['categories'] ?? [$p['category'] ?? ''])[0] !== '_component');
$visibleProducts = array_values($visibleProducts);

// ===== ВСЕ КАТЕГОРИИ (синхронизированы с каталогом) =====
$categories = [
    // ===== ОСНОВНЫЕ =====
    'vertical'   => 'Вертикальные',
    'memorial-milling' => 'Фрезерные',
    'horizontal' => 'Горизонтальные',
    'double'     => 'Двойные',
    'memorial-arches' => 'Арки и Колонны',
    'memorial-elite' => 'Элитные',
    'cross'      => 'С крестом',
    'memorial-exclusive' => 'Эксклюзивные',
    'memorial-chapels' => 'Часовни и Голгофы',
    'memorial-granite-crosses' => 'Кресты из гранита',
    'memorial-european' => 'Европейские',
    'complex'    => 'Комплексы',
    'fence'      => 'Ограды',
    'socle'      => 'Цоколь',
    'vases'      => 'Вазы',

    // ===== ОФОРМЛЕНИЕ — ФОТО (3) =====
    'design-photo' => 'Фотокерамика',
    'design-glass' => 'Фото на стекле',
    'design-metal-photo' => 'Фото на металле',

    // ===== ОФОРМЛЕНИЕ — ОСТАЛЬНЫЕ (18) =====
    'design-angels' => 'Ангелы',
    'design-crosses' => 'Кресты',
    'design-candles' => 'Свечи',
    'design-vertical' => 'На вертикальные памятники',
    'design-horizontal' => 'На горизонтальные памятники',
    'design-branches' => 'Ветви, деревья',
    'design-military' => 'Военным',
    'design-animals' => 'Животные, птицы',
    'design-moon' => 'Луна (мусульманская)',
    'design-music' => 'Музыкальная тематика',
    'design-frames' => 'Рамки',
    'design-heraldry' => 'Символика, геральдика',
    'design-flowers' => 'Цветы',
    'design-churches' => 'Церкви, храмы',
    'design-mosques' => 'Мечети',
    'design-icons' => 'Иконы',
    'design-landscapes' => 'Пейзажи',
    'design-nature' => 'Природа',
];

?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Админ — ГраньВремени.рф</title>
<link rel="icon" type="image/x-icon" href="/logoico.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --dark: #1a1a1a; --gold: #b8944a; --gold-l: #d4ae5e;
    --red: #d32f2f; --green: #2e7d32; --gray: #f5f5f5;
    --border: #ddd; --text: #333; --text-light: #666;
    --radius: 8px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f0f0; color: var(--text); min-height: 100vh; }

.login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.login-box {
    background: #fff; border-radius: 16px; padding: 40px;
    width: 100%; max-width: 380px; box-shadow: 0 4px 32px rgba(0,0,0,.12);
    text-align: center;
}
.login-box h1 { font-size: 22px; margin-bottom: 6px; color: var(--dark); }
.login-box p { font-size: 13px; color: var(--text-light); margin-bottom: 28px; }
.login-box input {
    width: 100%; padding: 12px 16px; border: 1.5px solid var(--border);
    border-radius: var(--radius); font-size: 15px; margin-bottom: 14px; outline: none;
}
.login-box input:focus { border-color: var(--gold); }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px;
    border: none; border-radius: var(--radius); cursor: pointer; font-size: 13px;
    font-weight: 600; transition: .2s; text-decoration: none; }
.btn-gold  { background: var(--gold); color: #fff; }
.btn-gold:hover  { background: var(--gold-l); }
.btn-dark  { background: var(--dark); color: #fff; }
.btn-dark:hover  { background: #333; }
.btn-red   { background: #fff; color: var(--red); border: 1.5px solid var(--red); }
.btn-red:hover   { background: var(--red); color: #fff; }
.btn-green { background: #fff; color: var(--green); border: 1.5px solid #4caf50; }
.btn-green:hover { background: var(--green); color: #fff; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-full { width: 100%; justify-content: center; }
.error-msg { background: #fff3f3; border: 1px solid #f5c6c6; border-radius: 6px;
    color: var(--red); padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }

.topbar {
    background: var(--dark); color: #fff; padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    height: 56px; position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.topbar-logo { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.topbar-logo span { color: var(--gold); }
.topbar-actions { display: flex; align-items: center; gap: 12px; }

.main { max-width: 1300px; margin: 0 auto; padding: 24px 16px; }
.page-title { font-size: 22px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.notice { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px;
    color: var(--green); padding: 12px 18px; font-size: 13px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px; }

.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 12px; padding: 18px 20px;
    box-shadow: 0 1px 8px rgba(0,0,0,.06); border-left: 4px solid var(--gold); }
.stat-card .num { font-size: 28px; font-weight: 800; color: var(--dark); }
.stat-card .lbl { font-size: 12px; color: var(--text-light); margin-top: 2px; }

.quick-tools {
    display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;
    background: #fff; border-radius: 12px; padding: 16px 20px;
    box-shadow: 0 1px 8px rgba(0,0,0,.06);
    align-items: center;
}
.quick-tools .label { font-size: 13px; font-weight: 600; color: var(--text-light); }
.quick-tools select, .quick-tools input { padding: 6px 12px; border: 1.5px solid var(--border); border-radius: var(--radius); font-size: 13px; }
.quick-tools input[type="text"] { min-width: 150px; }
.quick-tools input[type="number"] { width: 80px; }

.toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.toolbar input[type=text] {
    padding: 8px 14px; border: 1.5px solid var(--border); border-radius: var(--radius);
    font-size: 13px; width: 240px; outline: none;
}
.toolbar input:focus { border-color: var(--gold); }
.toolbar select { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: var(--radius); font-size: 13px; outline: none; }

.products-table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 8px rgba(0,0,0,.06); overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead th { background: var(--gray); padding: 11px 14px; text-align: left; font-size: 12px;
    font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
tbody tr { border-bottom: 1px solid #f0f0f0; transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #fafafa; }
tbody tr.hidden-row { opacity: .5; }
td { padding: 12px 14px; font-size: 13px; vertical-align: middle; }
.td-photo { width: 60px; }
.td-photo img { width: 52px; height: 52px; object-fit: cover; border-radius: 8px;
    border: 1px solid var(--border); }
.td-photo .no-img { width: 52px; height: 52px; background: var(--gray); border-radius: 8px;
    display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 20px; }
.td-name { font-weight: 600; max-width: 240px; }
.td-name .cat-tag { display: inline-block; background: var(--gray); border-radius: 4px;
    padding: 1px 7px; font-size: 11px; color: var(--text-light); margin-top: 3px; font-weight: 400; }
.td-price { font-weight: 700; white-space: nowrap; }
.td-actions { white-space: nowrap; }
.td-actions .btn { margin-right: 4px; }
.badge-tag { display: inline-block; background: var(--gold); color: #fff; font-size: 10px;
    font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-left: 6px; }
.badge-hidden { background: #999; }
.drag-handle { cursor: grab; color: #ccc; font-size: 16px; padding-right: 8px; }
.drag-handle:hover { color: #999; }
tr.drag-over { border-top: 2px solid var(--gold); }

.modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6);
    z-index: 500; align-items: flex-start; justify-content: center;
    padding: 20px; overflow-y: auto;
}
.modal-overlay.open { display: flex; }
.modal {
    background: #fff; border-radius: 16px; width: 100%; max-width: 860px;
    margin: auto; position: relative; animation: slideUp .25s ease;
}
@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: none; opacity: 1; } }
.modal-head {
    padding: 20px 24px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; background: #fff; z-index: 2; border-radius: 16px 16px 0 0;
}
.modal-head h2 { font-size: 18px; }
.modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #999; line-height: 1; }
.modal-close:hover { color: var(--dark); }
.modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
.modal-foot {
    padding: 16px 24px; border-top: 1px solid var(--border);
    display: flex; gap: 10px; justify-content: flex-end;
    position: sticky; bottom: 0; background: #fff; border-radius: 0 0 16px 16px;
}

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid .full { grid-column: 1 / -1; }
.fgroup { display: flex; flex-direction: column; gap: 5px; }
.fgroup label { font-size: 12px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: .4px; }
.fgroup input[type=text],
.fgroup input[type=number],
.fgroup select,
.fgroup textarea {
    border: 1.5px solid var(--border); border-radius: var(--radius);
    padding: 9px 12px; font-size: 14px; font-family: inherit; outline: none;
    transition: border-color .2s; width: 100%;
}
.fgroup input:focus, .fgroup select:focus, .fgroup textarea:focus { border-color: var(--gold); }
.fgroup textarea { resize: vertical; min-height: 72px; }
.check-row { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
.check-row input { width: 16px; height: 16px; accent-color: var(--gold); cursor: pointer; }

.categories-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px 12px;
    padding: 10px 12px;
    background: var(--gray-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    max-height: 400px;
    overflow-y: auto;
}
.category-check {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 13px;
    padding: 4px 6px;
    border-radius: 4px;
    transition: background .2s;
}
.category-check:hover {
    background: #fff;
}
.category-check input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--gold);
    cursor: pointer;
    flex-shrink: 0;
}
.category-check input[type="checkbox"]:checked + span {
    font-weight: 700;
    color: var(--gold-dark);
}

.categories-grid .group-header {
    grid-column: 1 / -1;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 0 2px;
    border-bottom: 1px solid var(--border);
    margin-top: 6px;
}
.categories-grid .group-header:first-of-type {
    margin-top: 0;
    padding-top: 0;
}

@media (max-width: 700px) {
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 480px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
}

.photo-preview { width: 100%; max-width: 200px; height: 140px; background: var(--gray);
    border-radius: 10px; object-fit: cover; border: 1.5px solid var(--border); display: none; }
.photo-preview.show { display: block; }
.photo-placeholder { width: 100%; max-width: 200px; height: 140px; background: var(--gray);
    border-radius: 10px; border: 2px dashed var(--border);
    display: flex; align-items: center; justify-content: center; color: #bbb;
    font-size: 36px; flex-direction: column; gap: 6px; cursor: pointer; }
.photo-placeholder span { font-size: 12px; }

.materials-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 8px;
}
.material-row {
    display: flex;
    gap: 8px;
    align-items: center;
    background: var(--gray-bg);
    border-radius: var(--radius);
    padding: 8px 10px;
    border: 1px solid var(--border);
}
.material-row input[type="text"] {
    flex: 1;
    min-width: 80px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 6px 10px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: border-color .2s;
}
.material-row input[type="text"]:focus {
    border-color: var(--gold);
}
.material-row .material-photo-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}
.material-row .material-photo-wrap .material-preview {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.material-row .material-photo-wrap .material-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.material-row .material-photo-wrap .material-preview i {
    font-size: 16px;
    color: var(--border);
}
.material-row .btn-remove-material {
    background: none;
    border: none;
    color: #ccc;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
}
.material-row .btn-remove-material:hover {
    color: var(--red);
}
.btn-add-material {
    background: none;
    border: 1.5px dashed var(--border);
    color: var(--text-light);
    border-radius: var(--radius);
    padding: 7px 14px;
    font-size: 12px;
    cursor: pointer;
    text-align: center;
    transition: .2s;
    width: 100%;
}
.btn-add-material:hover {
    border-color: var(--gold);
    color: var(--gold);
}
.material-upload-btn {
    background: var(--dark);
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 4px 10px;
    font-size: 11px;
    cursor: pointer;
    transition: background .2s;
    white-space: nowrap;
}
.material-upload-btn:hover {
    background: #333;
}

.section-title { font-size: 13px; font-weight: 700; color: var(--text-light);
    text-transform: uppercase; letter-spacing: .5px; margin: 20px 0 10px; padding-bottom: 6px;
    border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.options-list { display: flex; flex-direction: column; gap: 6px; }
.option-row { display: flex; gap: 8px; align-items: center; }
.option-row input[type=text] { flex: 1; }
.option-row input[type=number] { width: 100px; }
.option-row .btn-remove { background: none; border: none; color: #ccc; cursor: pointer; font-size: 16px; padding: 4px; }
.option-row .btn-remove:hover { color: var(--red); }
.btn-add-option { background: none; border: 1.5px dashed var(--border); color: var(--text-light);
    border-radius: var(--radius); padding: 7px 14px; font-size: 12px; cursor: pointer;
    width: 100%; text-align: center; transition: .2s; }
.btn-add-option:hover { border-color: var(--gold); color: var(--gold); }

.confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 600; align-items: center; justify-content: center; padding: 20px; }
.confirm-overlay.open { display: flex; }
.confirm-box { background: #fff; border-radius: 14px; padding: 28px; max-width: 360px;
    width: 100%; text-align: center; }
.confirm-box h3 { font-size: 18px; margin-bottom: 10px; }
.confirm-box p { font-size: 14px; color: var(--text-light); margin-bottom: 22px; }
.confirm-box .actions { display: flex; gap: 10px; justify-content: center; }

@media (max-width: 700px) {
    .form-grid { grid-template-columns: 1fr; }
    .categories-grid { grid-template-columns: repeat(2, 1fr); }
    .materials-grid { grid-template-columns: 1fr; }
    .material-row { flex-wrap: wrap; }
    table thead { display: none; }
    tbody tr { display: flex; flex-wrap: wrap; padding: 12px; gap: 8px; border-bottom: 1px solid var(--border); }
    td { padding: 0; border: none; }
    .td-photo { order: 0; }
    .td-name { order: 1; flex: 1; }
    .td-price { order: 2; width: 100%; }
    .td-actions { order: 3; width: 100%; }
    .td-cat, .td-sort { display: none; }
    .drag-handle { display: none; }
    .quick-tools { flex-direction: column; align-items: stretch; }
    .quick-tools input[type="text"] { min-width: auto; }
}
</style>
</head>
<body>

<?php if (!$authed): ?>
<div class="login-wrap">
  <div class="login-box">
    <h1>🔐 ГраньВремени.рф</h1>
    <p>Панель управления каталогом</p>
    <?php if ($error): ?>
      <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="Пароль" autofocus autocomplete="current-password">
      <button type="submit" class="btn btn-gold btn-full"><i class="fas fa-sign-in-alt"></i> Войти</button>
    </form>
  </div>
</div>

<?php else: ?>

<div class="topbar">
  <div class="topbar-logo">
    <i class="fas fa-monument" style="color:var(--gold)"></i>
    ГраньВремени.рф <span>Админ</span>
  </div>
  <div class="topbar-actions">
    <span style="font-size:12px; color:rgba(255,255,255,0.4);">
      <i class="far fa-clock"></i> Сессия: <?= round((SESSION_LIFETIME - (time() - ($_SESSION['last_activity'] ?? 0))) / 3600, 1) ?> ч
    </span>
    <a href="../catalog/" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;">
      <i class="fas fa-external-link-alt"></i> Сайт
    </a>
    <form method="POST" style="margin:0">
      <button name="logout" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;">
        <i class="fas fa-sign-out-alt"></i> Выйти
      </button>
    </form>
  </div>
</div>

<div class="main">
  <div class="page-title">
    <i class="fas fa-boxes" style="color:var(--gold)"></i> Каталог товаров
    <button class="btn btn-gold" onclick="openEditor()">
      <i class="fas fa-plus"></i> Добавить товар
    </button>
    <button class="btn btn-dark" onclick="openQuickAdd()">
      <i class="fas fa-bolt"></i> Быстрое добавление
    </button>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="notice"><i class="fas fa-check-circle"></i> Товар сохранён!</div>
  <?php endif; ?>
  <?php if (isset($_GET['cloned'])): ?>
    <div class="notice"><i class="fas fa-copy"></i> Товар скопирован!</div>
  <?php endif; ?>
  <?php if (isset($_GET['quick_added'])): ?>
    <div class="notice"><i class="fas fa-bolt"></i> Товар создан!</div>
  <?php endif; ?>

  <div class="quick-tools" style="display:none;">
    <span class="label"><i class="fas fa-magic"></i> Шаблоны:</span>
    <select id="templateCategory">
      <option value="">Выберите категорию...</option>
    </select>
    <button class="btn btn-sm btn-dark" onclick="applyTemplateToCurrent()">
      <i class="fas fa-copy"></i> Применить к текущему
    </button>
    <span style="font-size:12px;color:var(--text-light);margin-left:4px;">
      (заполнит размеры и материалы)
    </span>
  </div>

  <?php
    $total   = count($visibleProducts);
    $visible = count(array_filter($visibleProducts, fn($p) => empty($p['hidden'])));
  ?>
  <div class="stats">
    <div class="stat-card"><div class="num"><?= $total ?></div><div class="lbl">Всего товаров</div></div>
    <div class="stat-card"><div class="num"><?= $visible ?></div><div class="lbl">Видимых</div></div>
    <div class="stat-card"><div class="num"><?= $total - $visible ?></div><div class="lbl">Скрытых</div></div>
    <div class="stat-card"><div class="num"><?= count($categories) ?></div><div class="lbl">Категорий</div></div>
  </div>

  <div class="toolbar">
    <input type="text" id="searchInput" placeholder="🔍 Поиск по названию..." oninput="filterTable()">
    <select id="catFilter" onchange="filterTable()">
      <option value="">Все категории</option>
      <?php foreach ($categories as $val => $lbl): ?>
        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;">
      <input type="checkbox" id="showHidden" onchange="filterTable()" style="accent-color:var(--gold);">
      Показать скрытые
    </label>
  </div>

  <div class="products-table-wrap">
    <table id="productsTable">
      <thead>
        <tr>
          <th style="width:32px"></th>
          <th style="width:70px">Фото</th>
          <th style="width:80px">Артикул</th>
          <th>Название</th>
          <th class="td-cat">Категории</th>
          <th>Цена</th>
          <th style="width:60px" class="td-sort">№</th>
          <th style="width:200px">Действия</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php foreach ($visibleProducts as $p):
          $hidden  = !empty($p['hidden']);
          $catNames = [];
          $cats = $p['categories'] ?? [$p['category'] ?? 'vertical'];
          foreach ($cats as $cat) {
              if (isset($categories[$cat])) {
                  $catNames[] = $categories[$cat];
              }
          }
          $catDisplay = implode(', ', $catNames);
          $photo   = $p['photoURL'] ?? '';
        ?>
        <tr data-id="<?= htmlspecialchars($p['id']) ?>"
            data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
            data-cat="<?= htmlspecialchars(implode(',', $cats)) ?>"
            class="<?= $hidden ? 'hidden-row' : '' ?>"
            draggable="true">
          <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
          <td class="td-photo">
            <?php if ($photo): ?>
              <img src="<?= htmlspecialchars('../' . ltrim($photo, './')) ?>" alt="" onerror="this.style.display='none'">
            <?php else: ?>
              <div class="no-img"><i class="fas fa-image"></i></div>
            <?php endif; ?>
          </td>
          <td class="td-sku" style="font-family:monospace; color:var(--text-light);"><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
          <td class="td-name">
            <?= htmlspecialchars($p['name']) ?>
            <?php if (!empty($p['badge'])): ?>
              <span class="badge-tag"><?= htmlspecialchars($p['badge']) ?></span>
            <?php endif; ?>
            <?php if ($hidden): ?>
              <span class="badge-tag badge-hidden">скрыт</span>
            <?php endif; ?>
            <div class="cat-tag"><?= htmlspecialchars($catDisplay) ?></div>
          </td>
          <td class="td-cat"><?= htmlspecialchars($catDisplay) ?></td>
          <td class="td-price"><?= number_format($p['price'] ?? 0, 0, '', ' ') ?> ₽</td>
          <td class="td-sort"><?= (int)($p['sortOrder'] ?? 0) ?></td>
          <td class="td-actions">
            <button class="btn btn-dark btn-sm" onclick="editProduct('<?= htmlspecialchars($p['id']) ?>')" title="Редактировать">
              <i class="fas fa-pencil-alt"></i>
            </button>
            <button class="btn btn-sm" style="background:#6c5ce7;color:#fff;" onclick="cloneProduct('<?= htmlspecialchars($p['id']) ?>')" title="Клонировать">
              <i class="fas fa-copy"></i>
            </button>
            <button class="btn btn-sm <?= $hidden ? 'btn-green' : 'btn-red' ?>"
                    onclick="toggleHidden('<?= htmlspecialchars($p['id']) ?>', this)" title="<?= $hidden ? 'Показать' : 'Скрыть' ?>">
              <i class="fas fa-<?= $hidden ? 'eye' : 'eye-slash' ?>"></i>
            </button>
            <button class="btn btn-red btn-sm" onclick="confirmDelete('<?= htmlspecialchars($p['id']) ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>')" title="Удалить">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="editorModal">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">Добавить товар</h2>
      <button class="modal-close" onclick="closeEditor()">×</button>
    </div>
    <div class="modal-body">
      <form id="productForm" method="POST" enctype="multipart/form-data" action="index.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="f_id">
        <input type="hidden" name="photoURL" id="f_photoURL">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-grid">
          <div class="fgroup full" id="skuDisplayWrap" style="display:none;">
            <label>Артикул</label>
            <input type="text" id="f_sku_display" readonly disabled style="width:140px; font-family:monospace; background:var(--gray-bg); color:var(--text-light);">
          </div>
          <div class="fgroup full">
            <label>Название товара *</label>
            <input type="text" name="name" id="f_name" required placeholder="Вертикальный «Классик»">
          </div>

          <div class="fgroup">
            <label>Категории *</label>
            <div class="categories-grid" id="categoriesGrid">
              <!-- ===== ОСНОВНЫЕ ===== -->
              <div class="group-header"> Основные категории</div>
              <?php 
              $mainCategories = [
                  'vertical', 'memorial-milling', 'horizontal', 'double',
                  'memorial-arches', 'memorial-elite', 'cross', 'memorial-exclusive',
                  'memorial-chapels', 'memorial-granite-crosses', 'memorial-european',
                  'complex', 'fence', 'socle', 'vases'
              ];
              foreach ($mainCategories as $val): 
                  if (!isset($categories[$val])) continue;
              ?>
                <label class="category-check">
                  <input type="checkbox" name="categories[]" value="<?= $val ?>">
                  <span><?= htmlspecialchars($categories[$val]) ?></span>
                </label>
              <?php endforeach; ?>

              <!-- ===== ОФОРМЛЕНИЕ — ФОТО ===== -->
              <div class="group-header"> Оформление — Фото</div>
              <?php 
              $designPhoto = ['design-photo', 'design-glass', 'design-metal-photo'];
              foreach ($designPhoto as $val): 
                  if (!isset($categories[$val])) continue;
              ?>
                <label class="category-check">
                  <input type="checkbox" name="categories[]" value="<?= $val ?>">
                  <span><?= htmlspecialchars($categories[$val]) ?></span>
                </label>
              <?php endforeach; ?>

              <!-- ===== ОФОРМЛЕНИЕ — ОСТАЛЬНЫЕ ===== -->
              <div class="group-header"> Оформление — Остальные</div>
              <?php 
              $designOther = [
                  'design-angels', 'design-crosses', 'design-candles',
                  'design-vertical', 'design-horizontal', 'design-branches',
                  'design-military', 'design-animals', 'design-moon',
                  'design-music', 'design-frames', 'design-heraldry',
                  'design-flowers', 'design-churches', 'design-mosques',
                  'design-icons', 'design-landscapes', 'design-nature'
              ];
              foreach ($designOther as $val): 
                  if (!isset($categories[$val])) continue;
              ?>
                <label class="category-check">
                  <input type="checkbox" name="categories[]" value="<?= $val ?>">
                  <span><?= htmlspecialchars($categories[$val]) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="fgroup">
            <label>Цена (₽) *</label>
            <input type="number" name="price" id="f_price" required min="0" placeholder="18000">
          </div>

          <div class="fgroup">
            <label>Порядок (меньше = выше)</label>
            <input type="number" name="sortOrder" id="f_sortOrder" min="0" placeholder="1">
          </div>

          <div class="fgroup">
            <label>Бейдж</label>
            <input type="text" name="badge" id="f_badge" placeholder="Хит / Премиум / Новинка">
          </div>

          <div class="fgroup full" style="flex-direction:row; gap:24px; align-items:center;">
            <label class="check-row">
              <input type="checkbox" name="popular" id="f_popular"> Популярный
            </label>
            <label class="check-row">
              <input type="checkbox" name="hidden" id="f_hidden"> Скрыть из каталога
            </label>
          </div>

          <div class="fgroup full">
            <label>Описание</label>
            <textarea name="description" id="f_description" placeholder="Краткое описание товара..."></textarea>
          </div>

          <div class="fgroup full">
            <label>Фото товара</label>
            <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
              <div>
                <img id="photoPreview" class="photo-preview" src="" alt="Превью">
                <div id="photoPlaceholder" class="photo-placeholder" onclick="document.getElementById('f_photo').click()">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <span>Нажмите для загрузки</span>
                </div>
              </div>
              <div style="flex:1; min-width:200px;">
                <input type="file" name="photo" id="f_photo" accept="image/*" style="display:none" onchange="previewPhoto(this)">
                <button type="button" class="btn btn-dark btn-sm" onclick="document.getElementById('f_photo').click()" style="margin-bottom:8px;">
                  <i class="fas fa-upload"></i> Загрузить фото
                </button>
                <p style="font-size:12px; color:#999; margin-top:6px;">JPG, PNG, WEBP до 5 МБ</p>
                <div style="margin-top:10px;">
                  <label style="font-size:12px; color:var(--text-light); font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Или укажите путь</label>
                  <input type="text" id="f_photoURLInput" placeholder="img/products-img/photo.jpg" style="width:100%; padding:8px 10px; border:1.5px solid var(--border); border-radius:var(--radius); font-size:13px; outline:none;"
                    oninput="document.getElementById('f_photoURL').value=this.value; updatePhotoPreview(this.value)">
                </div>
              </div>
            </div>
          </div>

          <div class="fgroup full">
            <label>Размеры (каждый с новой строки)</label>
            <textarea name="sizes" id="f_sizes" style="min-height:100px; font-family:monospace; font-size:13px;"></textarea>
          </div>
        </div>

        <div class="section-title">
          <span><i class="fas fa-cube"></i> Материалы</span>
          <button type="button" class="btn btn-sm btn-dark" onclick="addMaterialRow()">
            <i class="fas fa-plus"></i> Добавить материал
          </button>
        </div>
        <div class="materials-grid" id="materialsGrid">
        </div>

        <div class="section-title">
          Гравировки (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('engravings')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_engravings"></div>

        <div class="section-title">
          Обработка поверхности (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('finishes')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_finishes"></div>

        <div class="section-title">
          Услуги (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('services')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_services"></div>

        <input type="file" id="materialPhotoInput" accept="image/*" style="display:none" onchange="uploadMaterialPhoto(this)">

      </form>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" onclick="closeEditor()">Отмена</button>
      <button type="button" class="btn btn-gold" onclick="document.getElementById('productForm').submit()">
        <i class="fas fa-save"></i> Сохранить товар
      </button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="quickAddModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-head">
      <h2><i class="fas fa-bolt" style="color:var(--gold)"></i> Быстрое добавление</h2>
      <button class="modal-close" onclick="closeQuickAdd()">×</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="quick_add">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="fgroup" style="margin-bottom:12px;">
          <label>Категория *</label>
          <select name="category" id="qa_category" required>
            <?php foreach ($categories as $val => $lbl): ?>
              <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fgroup" style="margin-bottom:12px;">
          <label>Название товара *</label>
          <input type="text" name="name" id="qa_name" required placeholder="Вертикальный «Классик»">
        </div>

        <div class="fgroup" style="margin-bottom:12px;">
          <label>Цена (₽) *</label>
          <input type="number" name="price" id="qa_price" required min="0" placeholder="18000">
        </div>

        <div class="fgroup" style="margin-bottom:12px;">
          <label>Бейдж (необязательно)</label>
          <input type="text" name="badge" id="qa_badge" placeholder="Хит / Новинка / Премиум">
        </div>

        <div class="fgroup" style="margin-bottom:12px;">
          <label>Описание (необязательно)</label>
          <textarea name="description" id="qa_description" rows="2" placeholder="Краткое описание..."></textarea>
        </div>

        <div class="fgroup" style="margin-bottom:12px;">
          <label class="check-row">
            <input type="checkbox" name="popular" id="qa_popular"> Популярный
          </label>
        </div>

        <div style="background:#f9f9f9; border-radius:8px; padding:12px; margin-bottom:12px; font-size:13px; color:var(--text-light);">
          <i class="fas fa-info-circle" style="color:var(--gold);"></i>
          Размеры и материалы нужно будет заполнить вручную после создания.
          Товар будет создан скрытым — вы сможете отредактировать его позже.
        </div>

        <div style="display:flex; gap:10px;">
          <button type="button" class="btn" onclick="closeQuickAdd()">Отмена</button>
          <button type="submit" class="btn btn-gold" style="flex:1;">
            <i class="fas fa-bolt"></i> Создать товар
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <h3>Удалить товар?</h3>
    <p id="confirmText">Это действие нельзя отменить.</p>
    <div class="actions">
      <button class="btn" onclick="closeConfirm()">Отмена</button>
      <button class="btn btn-red" id="confirmBtn">Удалить</button>
    </div>
  </div>
</div>

<script>
function addMaterialRow(label, photo) {
    label = label || '';
    photo = photo || '';
    const grid = document.getElementById('materialsGrid');
    const row = document.createElement('div');
    row.className = 'material-row';
    const idx = materialPhotoIndex++;

    const safeLabel = label
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/,/g, '&#44;')
        .replace(/\./g, '&#46;')
        .replace(/\+/g, '&#43;')
        .replace(/\//g, '&#47;')
        .replace(/\\/g, '&#92;');

    const safePhoto = photo
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/,/g, '&#44;')
        .replace(/\./g, '&#46;')
        .replace(/\+/g, '&#43;');

    let previewHtml = '';
    if (photo) {
        previewHtml = '<img src="../' + safePhoto + '" alt="Фото материала">';
    } else {
        previewHtml = '<i class="fas fa-image"></i>';
    }

    row.innerHTML = `
        <input type="text" name="materials_label[]" placeholder="Название материала" value="${safeLabel}" style="flex:1;">
        <div class="material-photo-wrap">
            <div class="material-preview" id="materialPreview_${idx}">
                ${previewHtml}
            </div>
            <button type="button" class="material-upload-btn" onclick="document.getElementById('materialPhotoInput').click(); window._currentMaterialIdx = ${idx};">
                <i class="fas fa-upload"></i>
            </button>
            <input type="hidden" name="materials_photo[]" id="materialPhoto_${idx}" value="${safePhoto}">
        </div>
        <button type="button" class="btn-remove-material" onclick="this.closest('.material-row').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    grid.appendChild(row);
}

function uploadMaterialPhoto(input) {
    if (!input.files || !input.files[0]) {
        alert('Выберите файл');
        return;
    }
    
    const idx = window._currentMaterialIdx;
    if (idx === undefined) {
        alert('Ошибка: не найден индекс материала');
        return;
    }

    const formData = new FormData();
    formData.append('photo', input.files[0]);

    const preview = document.getElementById('materialPreview_' + idx);
    if (preview) {
        preview.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:24px;color:#b8944a;"></i>';
    }

    fetch('upload_material_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Upload response:', data);
        
        if (data.ok && data.path) {
            const preview = document.getElementById('materialPreview_' + idx);
            const hidden = document.getElementById('materialPhoto_' + idx);
            
            if (preview) {
                preview.innerHTML = '<img src="../' + data.path + '" alt="Фото материала" style="width:100%;height:100%;object-fit:cover;">';
            }
            if (hidden) {
                hidden.value = data.path;
            }
            alert('✅ Фото загружено!');
        } else {
            alert('❌ Ошибка: ' + (data.error || 'неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('❌ Ошибка подключения к серверу');
    });

    input.value = '';
}

function applyTemplateToCurrent() {
    alert('Шаблоны временно отключены. Заполните размеры и материалы вручную.');
}

function cloneProduct(id) {
    if (!confirm('Создать копию товара?')) return;

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clone&id=' + encodeURIComponent(id) + '&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
    })
    .then(() => location.reload());
}

function openQuickAdd() {
    document.getElementById('quickAddModal').classList.add('open');
}
function closeQuickAdd() {
    document.getElementById('quickAddModal').classList.remove('open');
}

function filterTable() {
    var q = document.getElementById('searchInput').value.toLowerCase();
    var cat = document.getElementById('catFilter').value;
    var showH = document.getElementById('showHidden').checked;
    document.querySelectorAll('#tableBody tr').forEach(function(tr) {
        var name = tr.dataset.name || '';
        var trCat = tr.dataset.cat || '';
        var hidden = tr.classList.contains('hidden-row');
        var show = (!q || name.includes(q)) &&
                   (!cat || trCat.includes(cat)) &&
                   (!hidden || showH);
        tr.style.display = show ? '' : 'none';
    });
}

var dragSrc = null;
document.querySelectorAll('#tableBody tr').forEach(addDragEvents);
function addDragEvents(tr) {
    tr.addEventListener('dragstart', function() { dragSrc = this; this.style.opacity = '.4'; });
    tr.addEventListener('dragend',   function() { this.style.opacity = ''; dragSrc = null; cleanDragOver(); });
    tr.addEventListener('dragover',  function(e) { e.preventDefault(); cleanDragOver(); this.classList.add('drag-over'); });
    tr.addEventListener('drop',      function(e) {
        e.preventDefault();
        if (dragSrc && dragSrc !== this) {
            var tbody = this.parentNode;
            var rows = Array.from(tbody.rows);
            var si = rows.indexOf(dragSrc);
            var ti = rows.indexOf(this);
            if (si < ti) tbody.insertBefore(dragSrc, this.nextSibling);
            else tbody.insertBefore(dragSrc, this);
            saveOrder();
        }
        cleanDragOver();
    });
}
function cleanDragOver() {
    document.querySelectorAll('#tableBody tr').forEach(function(r) { r.classList.remove('drag-over'); });
}
function saveOrder() {
    var ids = Array.from(document.querySelectorAll('#tableBody tr')).map(function(r) { return r.dataset.id; });
    fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=reorder&ids=' + encodeURIComponent(JSON.stringify(ids)) + '&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>' });
}

function toggleHidden(id, btn) {
    fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=toggle_hidden&id=' + encodeURIComponent(id) + '&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>' })
    .then(function() {
        var tr = btn.closest('tr');
        var isHidden = tr.classList.toggle('hidden-row');
        btn.className = 'btn btn-sm ' + (isHidden ? 'btn-green' : 'btn-red');
        btn.innerHTML = '<i class="fas fa-' + (isHidden ? 'eye' : 'eye-slash') + '"></i>';
        btn.title = isHidden ? 'Показать' : 'Скрыть';
        var badges = tr.querySelectorAll('.badge-hidden');
        badges.forEach(function(b){ b.remove(); });
        if (isHidden) {
            var span = document.createElement('span');
            span.className = 'badge-tag badge-hidden'; span.textContent = 'скрыт';
            tr.querySelector('.td-name').appendChild(span);
        }
    });
}

function confirmDelete(id, name) {
    document.getElementById('confirmText').textContent = 'Удалить «' + name + '»? Это нельзя отменить.';
    document.getElementById('confirmBtn').onclick = function() {
        fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + encodeURIComponent(id) + '&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>' })
        .then(function() {
            var tr = document.querySelector('#tableBody tr[data-id="' + id + '"]');
            if (tr) { tr.style.transition = 'opacity .3s'; tr.style.opacity = '0'; setTimeout(function(){ tr.remove(); }, 300); }
            closeConfirm();
        });
    };
    document.getElementById('confirmOverlay').classList.add('open');
}
function closeConfirm() { document.getElementById('confirmOverlay').classList.remove('open'); }

function openEditor() {
    resetForm();
    document.getElementById('modalTitle').textContent = 'Добавить товар';
    document.getElementById('editorModal').classList.add('open');
}
function closeEditor() {
    document.getElementById('editorModal').classList.remove('open');
}
function resetForm() {
    var form = document.getElementById('productForm');
    form.reset();
    document.getElementById('f_id').value = '';
    document.getElementById('skuDisplayWrap').style.display = 'none';
    document.getElementById('f_photoURL').value = '';
    document.getElementById('f_photoURLInput').value = '';
    document.getElementById('photoPreview').classList.remove('show');
    document.getElementById('photoPlaceholder').style.display = '';
    
    document.querySelectorAll('#categoriesGrid input[type="checkbox"]').forEach(function(cb) {
        cb.checked = false;
    });
    
    document.getElementById('materialsGrid').innerHTML = '';
    materialPhotoIndex = 0;
    
    ['engravings','finishes','services'].forEach(function(k) {
        document.getElementById('opt_' + k).innerHTML = '';
    });
}

function editProduct(id) {
    fetch('index.php?action=get&id=' + encodeURIComponent(id))
    .then(function(r){ return r.json(); })
    .then(function(p) {
        resetForm();
        document.getElementById('modalTitle').textContent = 'Редактировать: ' + p.name;
        document.getElementById('f_id').value = p.id || '';
        if (p.sku) {
            document.getElementById('f_sku_display').value = p.sku;
            document.getElementById('skuDisplayWrap').style.display = '';
        }
        document.getElementById('f_name').value = p.name || '';
        
        const categories = p.categories || ['vertical'];
        document.querySelectorAll('#categoriesGrid input[type="checkbox"]').forEach(function(cb) {
            cb.checked = categories.includes(cb.value);
        });
        
        document.getElementById('f_price').value = p.price || '';
        document.getElementById('f_sortOrder').value = p.sortOrder || '';
        document.getElementById('f_badge').value = p.badge || '';
        document.getElementById('f_description').value = p.description || '';
        document.getElementById('f_popular').checked = !!p.popular;
        document.getElementById('f_hidden').checked = !!p.hidden;
        
        var photoURL = p.photoURL || '';
        document.getElementById('f_photoURL').value = photoURL;
        document.getElementById('f_photoURLInput').value = photoURL;
        updatePhotoPreview(photoURL);
        
        var sizes = (p.sizes || []).join('\n');
        document.getElementById('f_sizes').value = sizes;
        
        var materialsGrid = document.getElementById('materialsGrid');
        materialsGrid.innerHTML = '';
        if (p.materials && p.materials.length) {
            p.materials.forEach(function(m) {
                var label = typeof m === 'string' ? m : (m.label || '');
                var photo = typeof m === 'string' ? '' : (m.photo || '');
                addMaterialRow(label, photo);
            });
        }
        
        ['engravings','finishes','services'].forEach(function(key) {
            var items = p[key] || [];
            items.forEach(function(item) {
                addOption(key, item.label, item.price);
            });
        });
        
        document.getElementById('editorModal').classList.add('open');
    });
}

function addOption(key, label, price) {
    label = label || '';
    price = price !== undefined ? price : 0;
    var container = document.getElementById('opt_' + key);
    var row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML =
        '<input type="text" name="' + key + '_label[]" placeholder="Название" value="' + escHtml(label) + '">' +
        '<input type="number" name="' + key + '_price[]" placeholder="Доплата ₽" value="' + (+price) + '" min="0">' +
        '<button type="button" class="btn-remove" onclick="this.parentNode.remove()"><i class="fas fa-times"></i></button>';
    container.appendChild(row);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('f_photoURL').value = '';
        document.getElementById('f_photoURLInput').value = '';
        showPreview(e.target.result);
    };
    reader.readAsDataURL(input.files[0]);
}

function updatePhotoPreview(url) {
    if (!url) { hidePreview(); return; }
    var src = url.startsWith('http') || url.startsWith('/') || url.startsWith('data:') ? url : '../' + url.replace(/^\.\.\//, '');
    showPreview(src);
}

function showPreview(src) {
    var img = document.getElementById('photoPreview');
    var ph = document.getElementById('photoPlaceholder');
    img.src = src;
    img.classList.add('show');
    ph.style.display = 'none';
}

function hidePreview() {
    document.getElementById('photoPreview').classList.remove('show');
    document.getElementById('photoPlaceholder').style.display = '';
}

document.getElementById('editorModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditor();
});
document.getElementById('quickAddModal').addEventListener('click', function(e) {
    if (e.target === this) closeQuickAdd();
});
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

let materialPhotoIndex = 0;

window.addMaterialRow = addMaterialRow;
</script>

<?php endif; ?>
</body>
</html>