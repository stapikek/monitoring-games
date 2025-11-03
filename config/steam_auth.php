<?php
// config/steam_auth.php - Steam OpenID авторизация

class SteamAuth {
    private $timeout = 15;
    private $returnUrl;
    private $steamOpenIdUrl = 'https://steamcommunity.com/openid/login';
    
    public function __construct() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $this->returnUrl = $protocol . '://' . $host . '/steam_auth.php';
    }
    
    /**
     * Получить URL для перенаправления на Steam авторизацию
     */
    public function getLoginUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        $params = array(
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $this->returnUrl,
            'openid.realm' => $protocol . '://' . $_SERVER['HTTP_HOST'],
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select'
        );
        
        return $this->steamOpenIdUrl . '?' . http_build_query($params);
    }
    
    /**
     * Валидация ответа от Steam и извлечение Steam ID
     */
    public function validate() {
        try {
            // Проверяем, что это callback от Steam
            $mode = $_GET['openid_mode'] ?? $_GET['openid.mode'] ?? null;
            
            if (!$mode || $mode === 'cancel') {
                error_log("Steam Auth: User cancelled or mode is empty");
                return false;
            }
            
            if ($mode !== 'id_res') {
                error_log("Steam Auth: Invalid mode: " . $mode);
                return false;
            }
            
            // Нормализуем параметры (Steam возвращает с точками)
            $params = array();
            foreach ($_GET as $key => $value) {
                if (strpos($key, 'openid_') === 0) {
                    $params['openid.' . substr($key, 7)] = $value;
                } else if (strpos($key, 'openid.') === 0) {
                    $params[$key] = $value;
                }
            }
            
            // Добавляем параметры для валидации
            $params['openid.mode'] = 'check_authentication';
            
            // Отправляем запрос к Steam для проверки
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => http_build_query($params),
                    'timeout' => $this->timeout
                )
            ));
            
            $response = @file_get_contents($this->steamOpenIdUrl, false, $context);
            
            if ($response === false) {
                error_log("Steam Auth: Failed to validate with Steam (connection error)");
                return false;
            }
            
            // Проверяем ответ
            if (strpos($response, 'is_valid:true') === false) {
                error_log("Steam Auth: Steam validation returned false");
                return false;
            }
            
            // Извлекаем Steam ID из claimed_id
            $claimedId = $_GET['openid_claimed_id'] ?? $_GET['openid.claimed_id'] ?? '';
            
            if (preg_match('/^https?:\/\/steamcommunity\.com\/openid\/id\/(\d+)$/', $claimedId, $matches)) {
                $steamId = $matches[1];
                error_log("Steam Auth: Successfully extracted Steam ID: " . $steamId);
                return $steamId;
            }
            
            error_log("Steam Auth: Failed to extract Steam ID from: " . $claimedId);
            return false;
            
        } catch (Exception $e) {
            error_log("Steam Auth Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получить информацию о пользователе Steam через API
     * Требует Steam API Key
     */
    public function getUserInfo($steamId, $apiKey = null) {
        if (!$apiKey) {
            return null;
        }
        
        try {
            $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$apiKey}&steamids={$steamId}";
            $response = @file_get_contents($url);
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['response']['players'][0])) {
                return $data['response']['players'][0];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Steam API Error: " . $e->getMessage());
            return null;
        }
    }
}
?>
