<?php
// login.php

// Обработка POST запросов ДО вывода header
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_start();
    
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/auth.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header("Location: /");
        exit;
    } else {
        $error = "Неверное имя пользователя или пароль";
    }
}

// SEO настройки
$page_title = 'Вход - CS2 Мониторинг';
$page_description = 'Войдите в CS2 мониторинг, чтобы добавить свой сервер, создать проект и получить доступ к VIP функциям.';
$page_keywords = 'CS2, вход, авторизация, войти';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/login.php';

// Подключаем CSS для форм авторизации
$additional_css = ['/assets/css/auth.css'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Регистрация успешна! Теперь вы можете войти.</div>
    <?php endif; ?>
    <?php if (isset($_GET['steam_error'])): ?>
        <div class="alert alert-error">Ошибка при входе через Steam. Попробуйте еще раз.</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Имя пользователя или Email:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Войти</button>
    </form>
    
    <div class="auth-divider">
        <div class="auth-divider-content">
            <hr>
            <span>или</span>
        </div>
    </div>
    
    <div class="auth-text-center">
        <a href="/steam_auth.php" class="btn btn-steam">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
            </svg>
            Войти через Steam
        </a>
    </div>
    
    <p class="auth-link">
        Нет аккаунта? <a href="/register.php">Зарегистрироваться</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

