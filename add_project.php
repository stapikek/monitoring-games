<?php
// add_project.php - добавление проекта

// SEO настройки
$page_title = 'Создать проект - CS2 Мониторинг';
$page_description = 'Создайте свой проект в CS2 мониторинге. Объедините несколько серверов в один проект и повысьте узнаваемость.';
$page_keywords = 'CS2, создать проект, добавить проект';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/add_project.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/add_project.css'];

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $auth->getCurrentUser();
$errors = [];
$success = false;

// Получаем серверы пользователя
$stmt = $db->prepare("
    SELECT s.id, s.name, s.ip, s.port, s.rating
    FROM servers s
    WHERE s.user_id = :user_id AND s.status = 'active'
    ORDER BY s.name
");
$stmt->bindParam(':user_id', $user['id']);
$stmt->execute();
$userServers = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $discord = trim($_POST['discord'] ?? '');
    $vk = trim($_POST['vk'] ?? '');
    $selectedServers = $_POST['servers'] ?? [];
    
    // Валидация
    if (empty($name)) {
        $errors[] = 'Название проекта обязательно';
    }
    
    if (empty($description) || mb_strlen($description) < 50) {
        $errors[] = 'Описание должно содержать минимум 50 символов';
    }
    
    if (count($selectedServers) < 2) {
        $errors[] = 'Необходимо выбрать минимум 2 сервера';
    }
    
    // Проверка баланса
    if ($user['balance'] < 100) {
        $errors[] = 'Недостаточно средств. Необходимо 100 рублей';
    }
    
    // Загрузка логотипа
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $fileType = $_FILES['logo']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $fileName = 'project_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadDir = __DIR__ . '/uploads/projects/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fileName)) {
                $logoPath = '/uploads/projects/' . $fileName;
            }
        } else {
            $errors[] = 'Неверный формат файла. Разрешены: JPG, PNG, WEBP';
        }
    } else {
        $errors[] = 'Логотип проекта обязателен';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Списываем средства
            $stmt = $db->prepare("UPDATE users SET balance = balance - 100 WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->execute();
            
            // Создаем проект
            $stmt = $db->prepare("
                INSERT INTO projects (user_id, name, description, logo, website, discord, vk, status)
                VALUES (:user_id, :name, :description, :logo, :website, :discord, :vk, 'active')
            ");
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':logo', $logoPath);
            $stmt->bindParam(':website', $website);
            $stmt->bindParam(':discord', $discord);
            $stmt->bindParam(':vk', $vk);
            $stmt->execute();
            
            $projectId = $db->lastInsertId();
            
            // Добавляем серверы
            $stmt = $db->prepare("INSERT INTO project_servers (project_id, server_id) VALUES (:project_id, :server_id)");
            foreach ($selectedServers as $serverId) {
                $stmt->bindParam(':project_id', $projectId);
                $stmt->bindParam(':server_id', $serverId);
                $stmt->execute();
            }
            
            $db->commit();
            
            require_once __DIR__ . '/config/logger.php';
            Logger::project("Project created", ['project_id' => $projectId, 'name' => $name, 'user_id' => $user['id'], 'servers_count' => count($selectedServers)]);
            
            $success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            require_once __DIR__ . '/config/logger.php';
            Logger::error("Error creating project", ['user_id' => $user['id'], 'error' => $e->getMessage()]);
            $errors[] = 'Ошибка при создании проекта: ' . $e->getMessage();
        }
    }
}
?>

<div class="project-info-block">
    <h3>Описание услуги</h3>
    <p>Если у вас есть игровой проект, имеющий 2 и более верифицированных сервера - вы можете создать страницу проекта.</p>
    <p><strong>Цена услуги: 100 рублей</strong> (Размещается навсегда)</p>
    <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
        * Все поля обязательны к заполнению (за исключением сайта/discord/vk)<br>
        * Размещение сервера в блоке "Случайный проект" происходит в случайном порядке (среди проектов, достигших общего рейтинга свыше 100000)
    </p>
    <p style="margin-top: 1rem;">
        <strong>Ваш баланс: <?php echo number_format($user['balance'], 2); ?> ₽</strong>
        <?php if ($user['balance'] < 100): ?>
            <a href="/balance.php" class="btn btn-sm btn-primary" style="margin-left: 1rem;">Пополнить</a>
        <?php endif; ?>
    </p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        Проект успешно создан! <a href="/projects.php">Перейти к списку проектов</a>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (count($userServers) < 2): ?>
    <div class="alert alert-warning">
        У вас недостаточно серверов для создания проекта. Необходимо минимум 2 активных сервера.
        <a href="/add_server.php">Добавить сервер</a>
    </div>
<?php else: ?>
    <form method="POST" enctype="multipart/form-data" class="project-form">
        <div class="form-group">
            <label for="name">Название проекта *</label>
            <input type="text" id="name" name="name" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="logo">Логотип проекта * (16:9, 4:3, 16:10)</label>
            <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" required>
            <small>Форматы: JPG, PNG, WEBP. Рекомендуемый размер: 1280x720px</small>
        </div>
        
        <div class="form-group">
            <label for="servers">Выбрать серверы * (минимум 2)</label>
            <div class="servers-list">
                <?php foreach ($userServers as $server): ?>
                    <label class="server-checkbox">
                        <input type="checkbox" name="servers[]" value="<?php echo $server['id']; ?>" 
                               <?php echo in_array($server['id'], $_POST['servers'] ?? []) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($server['name']); ?> (<?php echo htmlspecialchars($server['ip']); ?>:<?php echo $server['port']; ?>)
                        - Рейтинг: <?php echo $server['rating']; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Описание проекта * (минимум 50 символов)</label>
            <textarea id="description" name="description" class="form-control" rows="8" required minlength="50"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            <small>Доступны BB-коды: [b]текст[/b], [i]текст[/i], [u]текст[/u], [url=ссылка]текст[/url]</small>
        </div>
        
        <div class="form-group">
            <label for="website">Веб-сайт</label>
            <input type="url" id="website" name="website" class="form-control" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="discord">Discord Invite Code</label>
            <input type="text" id="discord" name="discord" class="form-control" placeholder="abc123xyz" value="<?php echo htmlspecialchars($_POST['discord'] ?? ''); ?>">
            <small>Только код приглашения, например: abc123xyz</small>
        </div>
        
        <div class="form-group">
            <label for="vk">ВКонтакте</label>
            <input type="url" id="vk" name="vk" class="form-control" placeholder="https://vk.com/yourgroup" value="<?php echo htmlspecialchars($_POST['vk'] ?? ''); ?>">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Создать проект (100 ₽)</button>
            <a href="/projects.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

