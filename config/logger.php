<?php
// config/logger.php - система логирования

class Logger {
    private static $logDir;
    
    /**
     * Инициализация системы логирования
     */
    public static function init($logDir = null) {
        self::$logDir = $logDir ?? __DIR__ . '/../logs';
        
        // Создаем директорию для логов, если её нет
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
    
    /**
     * Записать лог в файл
     * 
     * @param string $type Тип лога (auth, server, payment, error, admin)
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     */
    public static function log($type, $message, $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        $username = $_SESSION['username'] ?? '';
        
        // Формируем контекстную строку
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Формируем строку лога
        $logLine = "[$timestamp] [$type] User: $userId" . ($username ? " ($username)" : "") . " | IP: $ip | Message: $message$contextStr\n";
        
        // Определяем файл лога на основе типа
        $logFile = self::$logDir . '/' . $type . '.log';
        
        // Записываем в файл
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Также записываем в общий лог
        file_put_contents(self::$logDir . '/system.log', $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Логирование аутентификации
     */
    public static function auth($message, $context = []) {
        self::log('auth', $message, $context);
    }
    
    /**
     * Логирование действий с серверами
     */
    public static function server($message, $context = []) {
        self::log('server', $message, $context);
    }
    
    /**
     * Логирование платежей
     */
    public static function payment($message, $context = []) {
        self::log('payment', $message, $context);
    }
    
    /**
     * Логирование ошибок
     */
    public static function error($message, $context = []) {
        self::log('error', $message, $context);
    }
    
    /**
     * Логирование действий администратора
     */
    public static function admin($message, $context = []) {
        self::log('admin', $message, $context);
    }
    
    /**
     * Логирование VIP операций
     */
    public static function vip($message, $context = []) {
        self::log('vip', $message, $context);
    }
    
    /**
     * Логирование проектов
     */
    public static function project($message, $context = []) {
        self::log('project', $message, $context);
    }
    
    /**
     * Ротация логов (очистка старых записей)
     * 
     * @param int $daysToKeep Сколько дней хранить логи
     */
    public static function rotate($daysToKeep = 30) {
        self::init();
        
        $files = glob(self::$logDir . '/*.log');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                self::log('system', "Rotated log file: $file");
            }
        }
    }
}

