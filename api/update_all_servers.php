<?php
// api/update_all_servers.php - Скрипт для периодического обновления информации о серверах

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/server_query.php';

echo "Starting server update...\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $serverQuery = new ServerQuery();
    
    // Получаем все активные серверы
    $stmt = $db->query("SELECT id, ip, port FROM servers WHERE status = 'active'");
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($servers) . " active servers\n";
    
    $updatedCount = 0;
    $failedCount = 0;
    
    foreach ($servers as $server) {
        echo "Updating server " . $server['id'] . " (" . $server['ip'] . ":" . $server['port'] . ")...\n";
        $info = $serverQuery->getServerInfo($server['ip'], $server['port']);
        
        if ($info['success']) {
            // Обновляем информацию о сервере, включая пик игроков
            $currentPlayers = intval($info['players']);
            $maxPlayers = intval($info['max_players']);
            
            // Получаем текущий пик игроков
            $peakStmt = $db->prepare("SELECT peak_players FROM servers WHERE id = :id");
            $peakStmt->bindValue(":id", $server['id'], PDO::PARAM_INT);
            $peakStmt->execute();
            $currentPeak = $peakStmt->fetch(PDO::FETCH_ASSOC);
            $currentPeakValue = intval($currentPeak['peak_players'] ?? 0);
            
            // Если текущее количество игроков больше пика - обновляем пик
            $newPeak = max($currentPeakValue, $currentPlayers);
            
            // Подготавливаем данные для карты
            $currentMap = isset($info['map']) ? $info['map'] : null;
            
            $updateStmt = $db->prepare("
                UPDATE servers 
                SET current_players = :players, 
                    max_players = :max_players,
                    peak_players = :peak_players,
                    current_map = :current_map,
                    last_updated = NOW()
                WHERE id = :id
            ");
            
            $updateStmt->bindValue(":players", $currentPlayers, PDO::PARAM_INT);
            $updateStmt->bindValue(":max_players", $maxPlayers, PDO::PARAM_INT);
            $updateStmt->bindValue(":peak_players", $newPeak, PDO::PARAM_INT);
            $updateStmt->bindValue(":current_map", $currentMap);
            $updateStmt->bindValue(":id", $server['id'], PDO::PARAM_INT);
            
            if ($updateStmt->execute()) {
                $peakInfo = ($newPeak > $currentPeakValue) ? " [NEW PEAK: $newPeak!]" : "";
                echo "  ✓ Updated: Players " . $info['players'] . "/" . $info['max_players'] . ", Peak: $newPeak, Map: " . ($info['map'] ?? 'N/A') . $peakInfo . "\n";
                $updatedCount++;
            } else {
                echo "  ✗ Error updating DB for server " . $server['id'] . "\n";
                $failedCount++;
            }
        } else {
            echo "  ✗ Server unavailable: " . ($info['error'] ?? 'Unknown error') . "\n";
            $failedCount++;
        }
    }
    
    echo "Update completed. Updated: $updatedCount, Failed: $failedCount\n";
    
} catch (Exception $e) {
    error_log("Error in update_all_servers.php: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}


