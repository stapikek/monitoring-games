<?php
// config/server_query.php - класс для запроса информации о сервере через Steam Server Query Protocol

class ServerQuery {
    private $socket;
    private $timeout = 3;
    
    public function __construct() {
    }
    
    private function open($ip, $port) {
        $this->socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            throw new Exception("Не удалось подключиться к серверу: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $this->timeout);
        stream_set_blocking($this->socket, true);
    }
    
    private function close() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
    
    private function sendPacket($data) {
        if (!$this->socket) {
            return false;
        }
        return fwrite($this->socket, $data) !== false;
    }
    
    private function receivePacket($maxLength = 4096) {
        if (!$this->socket) {
            return false;
        }
        return @fread($this->socket, $maxLength);
    }
    
    public function getServerInfo($ip, $port) {
        $result = array(
            'success' => false,
            'map' => null,
            'players' => 0,
            'max_players' => 0,
            'name' => null,
            'error' => null,
            'ping' => null
        );
        
        try {
            $this->open($ip, $port);
            
            $request = "\xFF\xFF\xFF\xFF\x54\x53\x6F\x75\x72\x63\x65\x20\x45\x6E\x67\x69\x6E\x65\x20\x51\x75\x65\x72\x79\x00";
            
            // Начинаем замер пинга перед отправкой запроса
            $pingStart = microtime(true);
            
            if (!$this->sendPacket($request)) {
                $result['error'] = "Не удалось отправить запрос";
                $this->close();
                return $result;
            }
            
            $response = false;
            $attempts = 0;
            $maxAttempts = 5;
            while ($attempts < $maxAttempts && (!$response || strlen($response) < 4)) {
                $response = $this->receivePacket();
                if ($response && strlen($response) >= 4) {
                    break;
                }
                $attempts++;
                if ($attempts < $maxAttempts) {
                    usleep(50000); // 50ms между попытками
                }
            }
            
            // Заканчиваем замер пинга сразу после получения ответа
            $pingEnd = microtime(true);
            $ping = round(($pingEnd - $pingStart) * 1000);
            
            if (!$response || strlen($response) < 4) {
                $result['error'] = "Пустой ответ от сервера";
                $this->close();
                return $result;
            }
            
            if (substr($response, 0, 4) !== "\xFF\xFF\xFF\xFF") {
                $result['error'] = "Некорректный ответ сервера";
                $this->close();
                return $result;
            }
            
            // Проверяем, это challenge ответ ('A' = 0x41)
            if (strlen($response) >= 5 && ord($response[4]) === 0x41) {
                // Это challenge ответ, нужен повторный запрос
                $challenge = '';
                if (strlen($response) >= 9) {
                    // Извлекаем 4 байта challenge после заголовка (0xFFFFFFFF) и типа (0x41)
                    $challenge = substr($response, 5, 4);
                } else {
                    // Неполный challenge, дополняем нулями
                    $remaining = substr($response, 5);
                    $challenge = str_pad($remaining, 4, "\x00", STR_PAD_RIGHT);
                }
                
                $requestWithChallenge = "\xFF\xFF\xFF\xFF\x54\x53\x6F\x75\x72\x63\x65\x20\x45\x6E\x67\x69\x6E\x65\x20\x51\x75\x65\x72\x79\x00" . $challenge;
                
                // Перезамеряем пинг для запроса с challenge
                $pingStart = microtime(true);
                
                if (!$this->sendPacket($requestWithChallenge)) {
                    $result['error'] = "Не удалось отправить запрос с challenge";
                    $this->close();
                    return $result;
                }
                
                $response = false;
                $attempts = 0;
                $maxAttempts = 10;
                while ($attempts < $maxAttempts && (!$response || strlen($response) < 10)) {
                    $response = $this->receivePacket();
                    if ($response && strlen($response) >= 10) {
                        break;
                    }
                    $attempts++;
                    if ($attempts < $maxAttempts) {
                        usleep(50000); // 50ms между попытками
                    }
                }
                
                // Обновляем пинг после получения ответа на challenge
                $pingEnd = microtime(true);
                $ping = round(($pingEnd - $pingStart) * 1000);
                
                if (!$response || strlen($response) < 4) {
                    $result['error'] = "Пустой ответ после challenge";
                    $this->close();
                    return $result;
                }
            }
            
            $data = $this->parseResponse($response);
            
            if ($data) {
                $result['success'] = true;
                $result['map'] = $data['map'] ?? null;
                $result['players'] = intval($data['players'] ?? 0);
                $result['max_players'] = intval($data['max_players'] ?? 0);
                $result['name'] = $data['name'] ?? null;
                $result['ping'] = $ping;
            } else {
                $result['error'] = "Не удалось распарсить ответ сервера";
            }
            
            $this->close();
            
        } catch (Exception $e) {
            $result['error'] = "Ошибка: " . $e->getMessage();
            $this->close();
        }
        
        return $result;
    }
    
    private function parseResponse($response) {
        if (strlen($response) < 10) {
            return false;
        }
        
        $offset = 4;
        
        if (ord($response[$offset]) !== 0x49) {
            return false;
        }
        $offset++;
        
        $offset++;
        
        $name = $this->readString($response, $offset);
        $map = $this->readString($response, $offset);
        $this->readString($response, $offset);
        $this->readString($response, $offset);
        
        if ($offset + 2 > strlen($response)) {
            return false;
        }
        $offset += 2;
        
        if ($offset >= strlen($response)) {
            return false;
        }
        $players = ord($response[$offset]);
        $offset++;
        
        if ($offset >= strlen($response)) {
            return false;
        }
        $max_players = ord($response[$offset]);
        $offset++;
        
        return array(
            'name' => $name,
            'map' => $map,
            'players' => $players,
            'max_players' => $max_players
        );
    }
    
    private function readString($data, &$offset) {
        if ($offset >= strlen($data)) {
            return '';
        }
        
        $end = strpos($data, "\x00", $offset);
        if ($end === false) {
            return '';
        }
        $string = substr($data, $offset, $end - $offset);
        $offset = $end + 1;
        return $string;
    }
}


