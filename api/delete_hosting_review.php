<?php
// api/delete_hosting_review.php - Удаление отзыва о хостинге

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

$review_id = intval($_POST['review_id'] ?? 0);

if ($review_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID отзыва']);
    exit;
}

// Проверяем, существует ли отзыв
$stmt = $db->prepare("SELECT user_id FROM hosting_reviews WHERE id = :id");
$stmt->bindParam(':id', $review_id);
$stmt->execute();
$review = $stmt->fetch();

if (!$review) {
    echo json_encode(['success' => false, 'error' => 'Отзыв не найден']);
    exit;
}

// Проверяем права (админ или автор отзыва)
$user_id = $_SESSION['user_id'];
if (!$auth->isAdmin() && $review['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'У вас нет прав для удаления этого отзыва']);
    exit;
}

// Удаляем отзыв
try {
    $stmt = $db->prepare("DELETE FROM hosting_reviews WHERE id = :id");
    $stmt->bindParam(':id', $review_id);
    $stmt->execute();
    
    require_once __DIR__ . '/../config/logger.php';
    Logger::server("Hosting review deleted", ['review_id' => $review_id, 'user_id' => $user_id]);
    
    header('Location: /hosting.php?id=' . intval($_POST['hosting_id'] ?? 0));
    exit;
} catch (PDOException $e) {
    require_once __DIR__ . '/../config/logger.php';
    Logger::error("Error deleting hosting review", ['review_id' => $review_id, 'user_id' => $user_id, 'error' => $e->getMessage()]);
    error_log("Error deleting hosting review: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении отзыва']);
}

