<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$to = 'asvezhov_gv.rf@xn--80aebkas0afexd6l.xn--p1ai';


$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $name = $_POST['name'] ?? 'Не указано';
    $phone = $_POST['phone'] ?? 'Не указано';
    $interest = $_POST['interest'] ?? 'Не указано';
    $comment = $_POST['comment'] ?? '';
    $source = $_POST['source'] ?? 'Сайт';
    $cemetery = $_POST['cemetery'] ?? 'Не указано';
    $time = $_POST['time'] ?? 'Не указано';
    $sku = $_POST['sku'] ?? '';
} else {
    $name = $data['name'] ?? 'Не указано';
    $phone = $data['phone'] ?? 'Не указано';
    $interest = $data['interest'] ?? 'Не указано';
    $comment = $data['comment'] ?? '';
    $source = $data['source'] ?? 'Сайт';
    $cemetery = $data['cemetery'] ?? 'Не указано';
    $time = $data['time'] ?? 'Не указано';
    $sku = $data['sku'] ?? '';
}

$subject = 'Новая заявка с сайта';
$message = "----------------------------------------\n";
$message .= "НОВАЯ ЗАЯВКА\n";
$message .= "----------------------------------------\n\n";
$message .= "Имя:         $name\n";
$message .= "Телефон:     $phone\n";
$message .= "Интерес:     $interest\n";
if ($sku) {
    $message .= "Артикул:     $sku\n";
}
$message .= "Кладбище:    $cemetery\n";
if ($time && $time !== 'Не указано') {
    $message .= "Время выезда: $time\n";
}
$message .= "\nКомментарий:\n$comment\n\n";
$message .= "----------------------------------------\n";
$message .= "Источник:    $source\n";
$message .= "Дата:        " . date('d.m.Y H:i:s') . "\n";
$message .= "----------------------------------------\n";

$from = 'asvezhov_gv.rf@xn--80aebkas0afexd6l.xn--p1ai';

$headers = "From: $from\r\n";
$headers .= "Reply-To: $from\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

$result = mail($to, $subject, $message, $headers);

header('Content-Type: application/json');
if ($result) {
    echo json_encode(['ok' => true, 'message' => 'Письмо отправлено через mail()']);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ошибка отправки через mail()']);
}