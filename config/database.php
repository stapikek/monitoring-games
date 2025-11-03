<?php
// config/database.php - подключение к базе данных

class Database {
    private $host = "localhost";
    private $db_name = "monitoring";
    private $username = "your_username";
    private $password = "your_password";
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            $this->showDatabaseError($exception->getMessage());
        }
        
        return $this->conn;
    }
    
    private function showDatabaseError($errorMessage) {
        // Не выводим ошибку, если уже начат вывод
        if (headers_sent()) {
            return;
        }
        
        // Очищаем буфер, если что-то было выведено
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        http_response_code(500);
        ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка подключения к базе данных</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-icon svg {
            width: 50px;
            height: 50px;
            fill: white;
        }
        
        h1 {
            color: #1f2937;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        p {
            color: #6b7280;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .error-details {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .error-details strong {
            color: #991b1b;
            font-size: 0.875rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .error-details code {
            color: #7f1d1d;
            font-size: 0.875rem;
            word-break: break-all;
        }
        
        .actions {
            margin-top: 2rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 0 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
        </div>
        
        <h1>Ошибка подключения к базе данных</h1>
        <p>Не удалось установить соединение с базой данных.</p>
        <p>Пожалуйста, проверьте настройки подключения.</p>
        
        <div class="error-details">
            <strong>Детали ошибки:</strong>
            <code><?php echo htmlspecialchars($errorMessage); ?></code>
        </div>
        
        <div class="actions">
            <button class="btn" onclick="window.location.reload()">Обновить страницу</button>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}


