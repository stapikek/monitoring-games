<?php
// config/auth.php - класс для работы с авторизацией

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
            $stmt->bindParam(":id", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch();
            return ($user && $user['is_admin'] == 1);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function login($username, $password) {
        try {
            require_once __DIR__ . '/logger.php';
            
            $stmt = $this->db->prepare("SELECT id, username, email, password FROM users WHERE username = :username LIMIT 1");
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                Logger::auth("Successful login", ['username' => $username]);
                return true;
            }
            
            Logger::auth("Failed login attempt", ['username' => $username]);
            return false;
        } catch (PDOException $e) {
            require_once __DIR__ . '/logger.php';
            Logger::error("Login database error", ['username' => $username, 'error' => $e->getMessage()]);
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function register($username, $email, $password) {
        try {
            require_once __DIR__ . '/logger.php';
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password, created_at) VALUES (:username, :email, :password, NOW())");
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashedPassword);
            
            if ($stmt->execute()) {
                Logger::auth("New user registered", ['username' => $username, 'email' => $email]);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            require_once __DIR__ . '/logger.php';
            Logger::error("Registration error", ['username' => $username, 'error' => $e->getMessage()]);
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        require_once __DIR__ . '/logger.php';
        Logger::auth("User logged out", ['user_id' => $_SESSION['user_id'] ?? null]);
        session_unset();
        session_destroy();
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->bindParam(":id", $_SESSION['user_id']);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function hasSteamLinked() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT steam_id FROM users WHERE id = :id LIMIT 1");
            $stmt->bindParam(":id", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch();
            return ($user && !empty($user['steam_id']));
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function linkSteam($steam_id, $steam_username) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET steam_id = :steam_id, steam_username = :steam_username WHERE id = :id");
            $stmt->bindParam(":steam_id", $steam_id);
            $stmt->bindParam(":steam_username", $steam_username);
            $stmt->bindParam(":id", $_SESSION['user_id']);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Link Steam error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Привязка Steam аккаунта к существующему пользователю
     */
    public function linkSteamAccount($userId, $steamId) {
        try {
            // Проверяем, не привязан ли уже этот Steam ID к другому аккаунту
            $stmt = $this->db->prepare("SELECT id FROM users WHERE steam_id = :steam_id AND id != :user_id LIMIT 1");
            $stmt->bindParam(":steam_id", $steamId);
            $stmt->bindParam(":user_id", $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                error_log("Steam ID {$steamId} already linked to another account");
                return false;
            }
            
            // Привязываем Steam ID
            $stmt = $this->db->prepare("UPDATE users SET steam_id = :steam_id WHERE id = :id");
            $stmt->bindParam(":steam_id", $steamId);
            $stmt->bindParam(":id", $userId);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Link Steam Account error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Регистрация нового пользователя через Steam
     */
    public function registerSteamUser($steamId, $username, $email) {
        try {
            // Проверяем, не существует ли уже пользователь с таким Steam ID
            $stmt = $this->db->prepare("SELECT id FROM users WHERE steam_id = :steam_id LIMIT 1");
            $stmt->bindParam(":steam_id", $steamId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                error_log("Steam user already exists: {$steamId}");
                return false;
            }
            
            // Регистрируем пользователя с указанным email
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, steam_id, created_at) 
                VALUES (:username, :email, :steam_id, NOW())
            ");
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":steam_id", $steamId);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Register Steam User error: " . $e->getMessage());
            return false;
        }
    }
}

