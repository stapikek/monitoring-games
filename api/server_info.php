<?php
// api/server_info.php - API для получения информации о сервере напрямую через Steam Query
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/server_query.php';

$serverId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($serverId <= 0) {
    echo json_encode(array(
        'success' => false,
        'error' => 'Неверный ID сервера'
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Получаем IP и порт сервера из БД
    $stmt = $db->prepare("
        SELECT id, ip, port
        FROM servers 
        WHERE id = :id AND status = 'active'
        LIMIT 1
    ");
    $stmt->bindParam(":id", $serverId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(array(
            'success' => false,
            'error' => 'Сервер не найден'
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получаем актуальную информацию напрямую с сервера через Steam Query
    $serverQuery = new ServerQuery();
    $info = $serverQuery->getServerInfo($server['ip'], $server['port']);
    
    if ($info['success']) {
        $currentPlayers = intval($info['players']);
        $maxPlayers = intval($info['max_players']);
        
        // Получаем текущий пик игроков из БД
        $peakStmt = $db->prepare("SELECT peak_players FROM servers WHERE id = :id");
        $peakStmt->bindParam(":id", $serverId, PDO::PARAM_INT);
        $peakStmt->execute();
        $peakData = $peakStmt->fetch(PDO::FETCH_ASSOC);
        $currentPeak = intval($peakData['peak_players'] ?? 0);
        
        // Если текущее количество игроков больше пика - обновляем пик
        if ($currentPlayers > $currentPeak) {
            $updatePeakStmt = $db->prepare("
                UPDATE servers 
                SET peak_players = :peak_players
                WHERE id = :id
            ");
            $updatePeakStmt->bindValue(":peak_players", $currentPlayers, PDO::PARAM_INT);
            $updatePeakStmt->bindValue(":id", $serverId, PDO::PARAM_INT);
            $updatePeakStmt->execute();
            
            $currentPeak = $currentPlayers;
        }
        
        // Возвращаем данные напрямую с сервера + пик игроков
        echo json_encode(array(
            'success' => true,
            'players' => $currentPlayers,
            'max_players' => $maxPlayers,
            'peak_players' => $currentPeak,
            'map' => $info['map'],
            'ping' => $info['ping']
        ), JSON_UNESCAPED_UNICODE);
    } else {
        // Сервер недоступен
        echo json_encode(array(
            'success' => false,
            'error' => $info['error'] ?? 'Сервер недоступен'
        ), JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => 'Внутренняя ошибка: ' . $e->getMessage()
    ), JSON_UNESCAPED_UNICODE);
    error_log("API server_info.php error: " . $e->getMessage());
}


