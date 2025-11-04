<?php
// steam_auth.php - обработка Steam аутентификации
error_reporting(E_ALL);
ini_set('display_errors', 0); // Не показываем ошибки пользователю
ini_set('log_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/steam_auth.php';
require_once __DIR__ . '/config/auth.php';

session_start();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        header("Location: /login.php?steam_error=1");
        exit;
    }
    
    $auth = new Auth($db);
    $steamAuth = new SteamAuth();

    // Если это перенаправление от Steam
    if (isset($_GET['openid_mode']) || isset($_GET['openid.mode'])) {
        // Логируем для отладки
        error_log("Steam Auth: Received callback with mode=" . ($_GET['openid_mode'] ?? $_GET['openid.mode'] ?? 'unknown'));
        error_log("Steam Auth: GET params: " . print_r($_GET, true));
        
        $steamId = $steamAuth->validate();
        error_log("Steam Auth: Validated Steam ID = " . ($steamId ?: 'NULL'));
        
        if ($steamId) {
            // Проверяем, есть ли в сессии user_id (значит это привязка Steam)
            if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
                // Привязываем Steam к существующему аккаунту
                if ($auth->linkSteamAccount($_SESSION['user_id'], $steamId)) {
                    header("Location: /profile.php?steam_linked=1");
                    exit;
                } else {
                    error_log("Steam Auth: Failed to link Steam ID " . $steamId . " to user " . $_SESSION['user_id']);
                    header("Location: /profile.php?steam_error=1");
                    exit;
                }
            } else {
                // Обычный вход/регистрация через Steam
                // Проверяем, есть ли пользователь с таким Steam ID
                $stmt = $db->prepare("SELECT id, username FROM users WHERE steam_id = :steam_id LIMIT 1");
                $stmt->bindParam(":steam_id", $steamId);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Пользователь существует - логиним
                    $user = $stmt->fetch();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    error_log("Steam Auth: Successfully logged in Steam ID " . $steamId);
                    header("Location: /?steam_login=1");
                    exit;
                } else {
                    // Пользователь не существует - сохраняем Steam ID в сессии для ввода email
                    $_SESSION['steam_register_id'] = $steamId;
                    error_log("Steam Auth: New user, redirecting to email form. Steam ID: " . $steamId);
                    header("Location: /steam_register.php");
                    exit;
                }
            }
        } else {
            error_log("Steam Auth: Validation failed - no Steam ID extracted");
            if (isset($_SESSION['user_id'])) {
                header("Location: /profile.php?steam_error=1");
            } else {
                header("Location: /login.php?steam_error=1");
            }
            exit;
        }
    } else {
        // Инициируем вход через Steam
        $steamAuth = new SteamAuth();
        $loginUrl = $steamAuth->getLoginUrl();
        header("Location: " . $loginUrl);
        exit;
    }
} catch (Exception $e) {
    error_log("Steam Auth Error: " . $e->getMessage());
    header("Location: /login.php?steam_error=1");
    exit;
}
?>

