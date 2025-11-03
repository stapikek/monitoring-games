<?php
// register.php

// Обработка POST запросов ДО вывода header
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_start();
    
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/auth.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Имя пользователя обязательно";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный email";
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают";
    }
    
    if (empty($errors)) {
        if ($auth->register($username, $email, $password)) {
            header("Location: /login.php?success=1");
            exit;
        } else {
            $errors[] = "Ошибка регистрации. Возможно, пользователь с таким именем или email уже существует.";
        }
    }
}

// SEO настройки
$page_title = 'Регистрация - CS2 Мониторинг';
$page_description = 'Зарегистрируйтесь в CS2 мониторинге и начните добавлять свои серверы, создавать проекты и развивать свое сообщество.';
$page_keywords = 'CS2, регистрация, создать аккаунт';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/register.php';

// Подключаем CSS для форм авторизации
$additional_css = ['/assets/css/auth.css'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Имя пользователя:</label>
            <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Пароль (мин. 6 символов):</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Подтвердите пароль:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
    </form>
    
    <p class="auth-link">
        Уже есть аккаунт? <a href="/login.php">Войти</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
