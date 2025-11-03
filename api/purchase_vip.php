<?php
// api/purchase_vip.php - покупка VIP статуса для сервера

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('error' => 'Необходимо войти в систему'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$serverId = isset($_POST['server_id']) ? intval($_POST['server_id']) : 0;
$weeks = isset($_POST['weeks']) ? intval($_POST['weeks']) : 0;
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
$nameColor = isset($_POST['name_color']) ? trim($_POST['name_color']) : '';

// Логируем получение цвета
error_log("VIP Purchase - Received name_color: " . ($nameColor ? $nameColor : 'empty'));

if ($serverId <= 0) {
    echo json_encode(array('error' => 'Неверный ID сервера'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($weeks <= 0 || $price <= 0) {
    echo json_encode(array('error' => 'Неверные параметры'), JSON_UNESCAPED_UNICODE);
    exit;
}

// Валидация цвета (если указан)
if (!empty($nameColor) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $nameColor)) {
    echo json_encode(array('error' => 'Неверный формат цвета. Используйте формат #RRGGBB'), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $userId = $_SESSION['user_id'];
    
    // Проверяем баланс пользователя
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['balance'] < $price) {
        echo json_encode(array('error' => 'Недостаточно средств на балансе'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем, принадлежит ли сервер пользователю
    $stmt = $db->prepare("SELECT id, name FROM servers WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $serverId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(array('error' => 'Сервер не найден или не принадлежит вам'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $db->beginTransaction();
    
    // Списываем средства
    $stmt = $db->prepare("UPDATE users SET balance = balance - :price WHERE id = :id");
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    
    // Вычисляем дату окончания VIP
    $vipUntil = date('Y-m-d H:i:s', strtotime("+$weeks weeks"));
    
    // Проверяем, есть ли уже VIP для этого сервера
    $stmt = $db->prepare("SELECT id, vip_until FROM server_vip WHERE server_id = :server_id ORDER BY id DESC LIMIT 1");
    $stmt->bindParam(':server_id', $serverId);
    $stmt->execute();
    $existingVip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nameColorValue = !empty($nameColor) ? $nameColor : null;
    
    // Логируем цвет который будет сохранен
    error_log("VIP Purchase - Color to save: " . ($nameColorValue ? $nameColorValue : 'NULL'));
    
    if ($existingVip && strtotime($existingVip['vip_until']) > time()) {
        // Продлеваем существующий VIP
        $currentUntil = $existingVip['vip_until'];
        $newUntil = date('Y-m-d H:i:s', strtotime($currentUntil . " +$weeks weeks"));
        
        // Проверяем, есть ли поле name_color в таблице
        $hasNameColor = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM server_vip LIKE 'name_color'");
            $hasNameColor = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            // Игнорируем ошибку
        }
        
        if ($hasNameColor) {
            // Обновляем с цветом
            $stmt = $db->prepare("UPDATE server_vip SET vip_until = :vip_until, name_color = :name_color WHERE id = :id");
            $stmt->bindParam(':vip_until', $newUntil);
            $stmt->bindParam(':name_color', $nameColorValue);
            $stmt->bindParam(':id', $existingVip['id']);
            $stmt->execute();
        } else {
            // Обновляем без цвета (старая версия БД)
            $stmt = $db->prepare("UPDATE server_vip SET vip_until = :vip_until WHERE id = :id");
            $stmt->bindParam(':vip_until', $newUntil);
            $stmt->bindParam(':id', $existingVip['id']);
            $stmt->execute();
        }
        
        $finalUntil = $newUntil;
    } else {
        // Удаляем старую запись, если есть
        $stmt = $db->prepare("DELETE FROM server_vip WHERE server_id = :server_id");
        $stmt->bindParam(':server_id', $serverId);
        $stmt->execute();
        
        // Проверяем, есть ли поле name_color в таблице
        $hasNameColor = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM server_vip LIKE 'name_color'");
            $hasNameColor = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            // Игнорируем ошибку
        }
        
        if ($hasNameColor) {
            // Создаем новый VIP с цветом
            $stmt = $db->prepare("INSERT INTO server_vip (server_id, vip_until, name_color, created_at) VALUES (:server_id, :vip_until, :name_color, NOW())");
            $stmt->bindParam(':server_id', $serverId);
            $stmt->bindParam(':vip_until', $vipUntil);
            $stmt->bindParam(':name_color', $nameColorValue);
            $stmt->execute();
        } else {
            // Создаем новый VIP без цвета (старая версия БД)
            $stmt = $db->prepare("INSERT INTO server_vip (server_id, vip_until, created_at) VALUES (:server_id, :vip_until, NOW())");
            $stmt->bindParam(':server_id', $serverId);
            $stmt->bindParam(':vip_until', $vipUntil);
            $stmt->execute();
        }
        
        $finalUntil = $vipUntil;
    }
    
    // Записываем покупку (если таблица существует)
    try {
        $stmt = $db->prepare("INSERT INTO purchases (user_id, type, amount, cost, server_id, weeks, created_at) VALUES (:user_id, 'vip', :weeks, :cost, :server_id, :weeks, NOW())");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':weeks', $weeks);
        $stmt->bindParam(':cost', $price);
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
    $newBalance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];
    
    $db->commit();
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::vip("VIP purchased", ['user_id' => $userId, 'server_id' => $serverId, 'server_name' => $server['name'], 'weeks' => $weeks, 'price' => $price, 'vip_until' => $finalUntil]);
    
    echo json_encode(array(
        'success' => true,
        'message' => "VIP статус успешно приобретен на $weeks недель",
        'server_name' => $server['name'],
        'vip_until' => $finalUntil,
        'new_balance' => $newBalance,
        'name_color' => $nameColorValue,
        'note' => $nameColorValue ? 'Цвет названия установлен' : ''
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::error("VIP purchase error", ['user_id' => $_SESSION['user_id'] ?? null, 'server_id' => $serverId, 'error' => $e->getMessage()]);
    
    echo json_encode(array('error' => 'Ошибка: ' . $e->getMessage()), JSON_UNESCAPED_UNICODE);
    error_log("Purchase VIP error: " . $e->getMessage());
}
