<?php
// api/create_payment.php - создание платежа

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Необходимо войти в систему'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_system_id = isset($_POST['payment_system_id']) ? intval($_POST['payment_system_id']) : 0;

if ($amount < 1 || $amount > 100000) {
    echo json_encode(['error' => 'Неверная сумма платежа. Минимум: 1 ₽, максимум: 100,000 ₽'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($payment_system_id <= 0) {
    echo json_encode(['error' => 'Платежная система не выбрана'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $userId = $_SESSION['user_id'];
    
    // Получаем информацию о платежной системе
    $stmt = $db->prepare("SELECT * FROM payment_systems WHERE id = :id AND enabled = 1");
    $stmt->bindParam(':id', $payment_system_id);
    $stmt->execute();
    $paymentSystem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paymentSystem) {
        echo json_encode(['error' => 'Платежная система не найдена или отключена'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Парсим настройки
    $settings = json_decode($paymentSystem['settings'] ?? '{}', true);
    
    // Проверяем минимальную и максимальную сумму
    if ($amount < floatval($paymentSystem['min_amount'])) {
        echo json_encode(['error' => 'Сумма меньше минимальной: ' . $paymentSystem['min_amount'] . ' ₽'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($amount > floatval($paymentSystem['max_amount'])) {
        echo json_encode(['error' => 'Сумма больше максимальной: ' . $paymentSystem['max_amount'] . ' ₽'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Вычисляем комиссию
    $fee_percent = floatval($paymentSystem['fee_percent'] ?? 0);
    $fee_fixed = floatval($paymentSystem['fee_fixed'] ?? 0);
    $fee = ($amount * $fee_percent / 100) + $fee_fixed;
    $final_amount = $amount + $fee;
    
    // Генерируем уникальный ID платежа
    $payment_id = 'PAY_' . time() . '_' . $userId . '_' . mt_rand(1000, 9999);
    
    // Создаем запись о платеже
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        INSERT INTO payments (user_id, payment_system_id, amount, fee, final_amount, status, payment_id, created_at)
        VALUES (:user_id, :payment_system_id, :amount, :fee, :final_amount, 'pending', :payment_id, NOW())
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':payment_system_id', $payment_system_id);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':fee', $fee);
    $stmt->bindParam(':final_amount', $final_amount);
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->execute();
    
    $paymentDbId = $db->lastInsertId();
    
    // Получаем URL для оплаты в зависимости от типа платежной системы
    $payment_url = null;
    
    switch ($paymentSystem['type']) {
        case 'freekassa':
            require_once __DIR__ . '/payment_gateways/freekassa.php';
            $payment_url = FreeKassaGateway::createPayment($db, $paymentDbId, $payment_id, $final_amount, $settings);
            break;
            
        case 'yookassa':
            require_once __DIR__ . '/payment_gateways/yookassa.php';
            $payment_url = YooKassaGateway::createPayment($db, $paymentDbId, $payment_id, $final_amount, $settings);
            break;
            
        case 'stripe':
            require_once __DIR__ . '/payment_gateways/stripe.php';
            $payment_url = StripeGateway::createPayment($db, $paymentDbId, $payment_id, $final_amount, $settings);
            break;
            
        case 'paypal':
            require_once __DIR__ . '/payment_gateways/paypal.php';
            $payment_url = PayPalGateway::createPayment($db, $paymentDbId, $payment_id, $final_amount, $settings);
            break;
            
        case 'crypto':
            // Для криптовалют создаем платеж с адресом кошелька
            require_once __DIR__ . '/payment_gateways/crypto.php';
            $payment_url = CryptoGateway::createPayment($db, $paymentDbId, $payment_id, $final_amount, $settings);
            break;
            
        case 'bank_transfer':
            // Для банковского перевода показываем реквизиты
            $payment_url = '/payment.php?id=' . $paymentDbId;
            break;
            
        default:
            // Для неизвестных типов просто перенаправляем на страницу оплаты
            $payment_url = '/payment.php?id=' . $paymentDbId;
    }
    
    // Сохраняем URL платежа
    if ($payment_url) {
        $stmt = $db->prepare("UPDATE payments SET payment_url = :payment_url WHERE id = :id");
        $stmt->bindParam(':payment_url', $payment_url);
        $stmt->bindParam(':id', $paymentDbId);
        $stmt->execute();
    }
    
    $db->commit();
    
    // Логируем создание платежа
    require_once __DIR__ . '/../config/logger.php';
    if (class_exists('Logger')) {
        Logger::payment("Payment created", [
            'payment_id' => $paymentDbId,
            'user_id' => $userId,
            'amount' => $amount,
            'payment_system' => $paymentSystem['type']
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'payment_id' => $paymentDbId,
        'payment_url' => $payment_url,
        'message' => 'Платеж успешно создан'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Payment creation error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Ошибка при создании платежа. Попробуйте позже.'
    ], JSON_UNESCAPED_UNICODE);
}
