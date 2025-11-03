<?php
// api/purchase_rating.php - покупка рейтинга для сервера

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('error' => 'Необходимо войти в систему'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$serverId = isset($_POST['server_id']) ? intval($_POST['server_id']) : 0;
$ratingAmount = isset($_POST['rating_amount']) ? intval($_POST['rating_amount']) : 0;

if ($serverId <= 0) {
    echo json_encode(array('error' => 'Неверный ID сервера'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($ratingAmount < 1 || $ratingAmount > 100000) {
    echo json_encode(array('error' => 'Количество рейтинга должно быть от 1 до 100000'), JSON_UNESCAPED_UNICODE);
    exit;
}

$totalCost = $ratingAmount; // 1 рейтинг = 1 рубль

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $userId = $_SESSION['user_id'];
    
    // Проверяем баланс пользователя
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user || $user['balance'] < $totalCost) {
        echo json_encode(array('error' => 'Недостаточно средств на балансе'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем, принадлежит ли сервер пользователю
    $stmt = $db->prepare("SELECT id FROM servers WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $serverId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(array('error' => 'Сервер не найден или не принадлежит вам'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $db->beginTransaction();
    
    // Списываем средства
    $stmt = $db->prepare("UPDATE users SET balance = balance - :cost WHERE id = :id");
    $stmt->bindParam(':cost', $totalCost);
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    
    // Увеличиваем рейтинг сервера
    $stmt = $db->prepare("UPDATE servers SET rating = COALESCE(rating, 0) + :amount WHERE id = :id");
    $stmt->bindParam(':amount', $ratingAmount);
    $stmt->bindParam(':id', $serverId);
    $stmt->execute();
    
    // Записываем покупку (если таблица существует)
    try {
        $stmt = $db->prepare("INSERT INTO purchases (user_id, type, amount, cost, server_id, created_at) VALUES (:user_id, 'rating', :amount, :cost, :server_id, NOW())");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':amount', $ratingAmount);
        $stmt->bindParam(':cost', $totalCost);
        $stmt->bindParam(':server_id', $serverId);
        $stmt->execute();
    } catch (Exception $e) {
        // Игнорируем ошибку, если таблицы нет
        error_log("Purchases table not found: " . $e->getMessage());
    }
    
    // Получаем новый баланс
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $newBalance = $stmt->fetch()['balance'];
    
    // Получаем новый рейтинг
    $stmt = $db->prepare("SELECT rating FROM servers WHERE id = :id");
    $stmt->bindParam(':id', $serverId);
    $stmt->execute();
    $newRating = $stmt->fetch()['rating'];
    
    $db->commit();
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::server("Rating purchased", ['user_id' => $userId, 'server_id' => $serverId, 'rating_amount' => $ratingAmount, 'cost' => $totalCost]);
    
    echo json_encode(array(
        'success' => true,
        'message' => "Успешно куплено $ratingAmount рейтинга",
        'new_balance' => $newBalance,
        'new_rating' => $newRating
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::error("Rating purchase error", ['user_id' => $_SESSION['user_id'] ?? null, 'server_id' => $serverId, 'error' => $e->getMessage()]);
    
    echo json_encode(array('error' => 'Ошибка: ' . $e->getMessage()), JSON_UNESCAPED_UNICODE);
    error_log("Purchase rating error: " . $e->getMessage());
}

