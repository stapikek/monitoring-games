<?php
// steam_register.php - завершение регистрации через Steam

session_start();

// Проверяем, что есть Steam ID в сессии
if (!isset($_SESSION['steam_register_id'])) {
    header("Location: /login.php");
    exit;
}

$steamId = $_SESSION['steam_register_id'];
$errors = [];

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/auth.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Валидация
    if (empty($username)) {
        $errors[] = "Имя пользователя обязательно";
    } elseif (strlen($username) < 3) {
        $errors[] = "Имя пользователя должно содержать минимум 3 символа";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Имя пользователя может содержать только буквы, цифры и символ подчеркивания";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный email";
    }
    
    // Проверка уникальности username
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Пользователь с таким именем уже существует";
        }
    }
    
    // Проверка уникальности email
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Пользователь с таким email уже существует";
        }
    }
    
    // Проверка, не используется ли уже этот Steam ID
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE steam_id = :steam_id LIMIT 1");
        $stmt->bindParam(":steam_id", $steamId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Пользователь уже существует - логиним
            $user = $stmt->fetch();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            unset($_SESSION['steam_register_id']);
            header("Location: /?steam_login=1");
            exit;
        }
    }
    
    // Регистрируем пользователя
    if (empty($errors)) {
        if ($auth->registerSteamUser($steamId, $username, $email)) {
            // Получаем ID нового пользователя
            $stmt = $db->prepare("SELECT id, username FROM users WHERE steam_id = :steam_id LIMIT 1");
            $stmt->bindParam(":steam_id", $steamId);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user) {
                // Автоматически логиним
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                unset($_SESSION['steam_register_id']);
                header("Location: /?steam_register=1");
                exit;
            } else {
                $errors[] = "Ошибка при завершении регистрации";
            }
        } else {
            $errors[] = "Ошибка регистрации. Попробуйте еще раз.";
        }
    }
}

// SEO настройки
$page_title = 'Завершение регистрации через Steam - CS2 Мониторинг';
$page_description = 'Завершите регистрацию через Steam, указав ваш email и имя пользователя.';
$page_keywords = 'CS2, регистрация, Steam';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/steam_register.php';

// Подключаем CSS для форм авторизации
$additional_css = ['/assets/css/auth.css'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <h2>Завершение регистрации через Steam</h2>
    <p class="form-description">
        Для завершения регистрации укажите ваш email и имя пользователя.
    </p>
    
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
            <input type="text" id="username" name="username" required 
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   minlength="3" pattern="[a-zA-Z0-9_]+"
                   title="Только буквы, цифры и символ подчеркивания, минимум 3 символа">
            <small>Минимум 3 символа. Только буквы, цифры и символ подчеркивания.</small>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            <small>На этот email будут приходить важные уведомления.</small>
        </div>
        
        <button type="submit" class="btn btn-primary">Завершить регистрацию</button>
    </form>
    
    <div style="margin-top: 1.5rem; text-align: center;">
        <a href="/login.php" style="color: var(--text-secondary);">Отмена</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
