<?php
// api/vote_server.php - API для голосования за сервер

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('error' => 'Необходимо войти в систему'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$serverId = isset($_POST['server_id']) ? intval($_POST['server_id']) : 0;

if ($serverId <= 0) {
    echo json_encode(array('error' => 'Неверный ID сервера'), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $userId = $_SESSION['user_id'];
    
    // Проверяем, голосовал ли пользователь за этот сервер за последние 24 часа
    $stmt = $db->prepare("
        SELECT voted_at FROM server_votes 
        WHERE user_id = :user_id AND server_id = :server_id 
        AND voted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':server_id', $serverId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(array('error' => 'Вы уже голосовали за этот сервер. Повторное голосование доступно через 24 часа.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем существование сервера
    $stmt = $db->prepare("SELECT id FROM servers WHERE id = :id AND status = 'active'");
    $stmt->bindParam(':id', $serverId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(array('error' => 'Сервер не найден'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Начинаем транзакцию
    $db->beginTransaction();
    
    // Добавляем голос (для конкретного сервера)
    // Каждый голос - отдельная запись для отслеживания истории
    $stmt = $db->prepare("
        INSERT INTO server_votes (user_id, server_id, voted_at) 
        VALUES (:user_id, :server_id, NOW())
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':server_id', $serverId);
    
    if (!$stmt->execute()) {
        throw new Exception("Ошибка при добавлении голоса");
    }
    
    // Увеличиваем рейтинг сервера на 1
    $stmt = $db->prepare("UPDATE servers SET rating = COALESCE(rating, 0) + 1 WHERE id = :id");
    $stmt->bindParam(':id', $serverId);
    
    if (!$stmt->execute()) {
        throw new Exception("Ошибка при обновлении рейтинга");
    }
    
    // Получаем новый рейтинг
    $stmt = $db->prepare("SELECT rating FROM servers WHERE id = :id");
    $stmt->bindParam(':id', $serverId);
    $stmt->execute();
    $newRating = $stmt->fetch()['rating'];
    
    $db->commit();
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::server("Vote cast", ['user_id' => $userId, 'server_id' => $serverId]);
    
    echo json_encode(array(
        'success' => true,
        'message' => 'Спасибо за ваш голос!',
        'new_rating' => $newRating
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::error("Vote error", ['user_id' => $_SESSION['user_id'] ?? null, 'server_id' => $serverId, 'error' => $e->getMessage()]);
    
    echo json_encode(array('error' => 'Ошибка: ' . $e->getMessage()), JSON_UNESCAPED_UNICODE);
    error_log("Vote error: " . $e->getMessage());
}


