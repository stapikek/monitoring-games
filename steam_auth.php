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
                    // Пользователь не существует - регистрируем
                    // Получаем имя пользователя из Steam API (если нужно, можно добавить)
                    $username = "SteamUser_" . substr($steamId, -6);
                    
                    // Проверяем уникальность имени
                    $check_stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
                    $check_stmt->bindParam(":username", $username);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $counter = 1;
                        $original_username = $username;
                        do {
                            $username = $original_username . "_" . $counter;
                            $check_stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
                            $check_stmt->bindParam(":username", $username);
                            $check_stmt->execute();
                            $counter++;
                        } while ($check_stmt->rowCount() > 0 && $counter < 1000);
                    }
                    
                    // Регистрируем нового пользователя
                    $new_user_id = $auth->registerSteamUser($steamId, $username);
                    
                    if ($new_user_id) {
                        // Автоматически логиним
                        $_SESSION['user_id'] = $new_user_id;
                        $_SESSION['username'] = $username;
                        error_log("Steam Auth: Successfully registered and logged in Steam ID " . $steamId);
                        header("Location: /?steam_login=1");
                        exit;
                    } else {
                        error_log("Steam Auth: Failed to register Steam ID " . $steamId);
                        header("Location: /login.php?steam_error=1");
                        exit;
                    }
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

