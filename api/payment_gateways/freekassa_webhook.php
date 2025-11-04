<?php
// api/payment_gateways/freekassa_webhook.php

require_once __DIR__ . '/../../config/database.php';

// Получаем данные от FreeKassa
$merchant_id = $_POST['MERCHANT_ID'] ?? '';
$amount = floatval($_POST['AMOUNT'] ?? 0);
$payment_id = $_POST['MERCHANT_ORDER_ID'] ?? '';
$sign = $_POST['SIGN'] ?? '';
$us_payment_id = intval($_POST['us_payment_id'] ?? 0);

if (empty($merchant_id) || empty($payment_id) || empty($sign) || $us_payment_id <= 0) {
    http_response_code(400);
    die('Invalid parameters');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Получаем информацию о платеже
    $stmt = $db->prepare("SELECT p.*, ps.settings as payment_system_settings FROM payments p
                          LEFT JOIN payment_systems ps ON p.payment_system_id = ps.id
                          WHERE p.id = :id");
    $stmt->bindParam(':id', $us_payment_id);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        http_response_code(404);
        die('Payment not found');
    }
    
    if ($payment['status'] == 'completed') {
        // Платеж уже обработан
        echo 'OK';
        exit;
    }
    
    // Парсим настройки
    $settings = json_decode($payment['payment_system_settings'] ?? '{}', true);
    $secret_key2 = $settings['secret_key2'] ?? '';
    
    // Проверяем подпись
    $expected_sign = md5($merchant_id . ':' . $amount . ':' . $secret_key2 . ':' . $payment_id);
    
    if ($sign !== $expected_sign) {
        error_log("FreeKassa webhook: Invalid signature");
        http_response_code(403);
        die('Invalid signature');
    }
    
    // Проверяем сумму
    if (abs($amount - floatval($payment['final_amount'])) > 0.01) {
        error_log("FreeKassa webhook: Amount mismatch");
        http_response_code(400);
        die('Amount mismatch');
    }
    
    // Зачисляем средства на баланс
    $db->beginTransaction();
    
    $stmt = $db->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = :id");
    $stmt->bindParam(':id', $us_payment_id);
    $stmt->execute();
    
    $stmt = $db->prepare("UPDATE users SET balance = balance + :amount WHERE id = :user_id");
    $stmt->bindParam(':amount', $payment['amount']);
    $stmt->bindParam(':user_id', $payment['user_id']);
    $stmt->execute();
    
    $db->commit();
    
    // Логируем успешный платеж
    require_once __DIR__ . '/../../config/logger.php';
    if (class_exists('Logger')) {
        Logger::payment("Payment completed via FreeKassa", [
            'payment_id' => $us_payment_id,
            'user_id' => $payment['user_id'],
            'amount' => $payment['amount']
        ]);
    }
    
    echo 'OK';
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("FreeKassa webhook error: " . $e->getMessage());
    http_response_code(500);
    die('Internal error');
}
