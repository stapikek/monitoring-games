<?php
// api/add_hosting_review.php - Добавление отзыва о хостинге

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

$hosting_id = intval($_POST['hosting_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

// Валидация
if ($hosting_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID хостинга']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Рейтинг должен быть от 1 до 5']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'error' => 'Комментарий не может быть пустым']);
    exit;
}

// Проверяем, существует ли хостинг
$stmt = $db->prepare("SELECT id FROM hostings WHERE id = :id AND status = 'active'");
$stmt->bindParam(':id', $hosting_id);
$stmt->execute();

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Хостинг не найден']);
    exit;
}

// Проверяем, не оставлял ли пользователь уже отзыв
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT id FROM hosting_reviews WHERE hosting_id = :hosting_id AND user_id = :user_id");
$stmt->bindParam(':hosting_id', $hosting_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Вы уже оставляли отзыв на этот хостинг']);
    exit;
}

// Добавляем отзыв
try {
    $stmt = $db->prepare("INSERT INTO hosting_reviews (hosting_id, user_id, rating, comment) VALUES (:hosting_id, :user_id, :rating, :comment)");
    $stmt->bindParam(':hosting_id', $hosting_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':rating', $rating);
    $stmt->bindParam(':comment', $comment);
    $stmt->execute();
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::server("Hosting review added", ['hosting_id' => $hosting_id, 'user_id' => $user_id, 'rating' => $rating]);
    
    echo json_encode(['success' => true, 'message' => 'Отзыв успешно добавлен']);
} catch (PDOException $e) {
    require_once __DIR__ . '/../config/logger.php';
    Logger::error("Error adding hosting review", ['hosting_id' => $hosting_id, 'user_id' => $user_id, 'error' => $e->getMessage()]);
    error_log("Error adding hosting review: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка при добавлении отзыва']);
}

