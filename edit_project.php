<?php
// edit_project.php - редактирование проекта

// SEO настройки
$page_title = 'Редактировать проект - CS2 Мониторинг';
$page_description = 'Измените информацию о своем проекте в CS2 мониторинге. Обновите описание, серверы и другие параметры.';
$page_keywords = 'CS2, редактировать проект, изменить проект';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/edit_project.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/edit_project.css'];

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $auth->getCurrentUser();
$projectId = intval($_GET['id'] ?? 0);
$errors = [];
$success = false;

if ($projectId == 0) {
    header('Location: /profile.php');
    exit;
}

// Получаем проект
$stmt = $db->prepare("SELECT * FROM projects WHERE id = :id");
$stmt->bindParam(':id', $projectId);
$stmt->execute();
$project = $stmt->fetch();

if (!$project) {
    header('Location: /profile.php');
    exit;
}

// Проверяем, что пользователь - владелец проекта
if ($project['user_id'] != $user['id']) {
    header('Location: /profile.php');
    exit;
}

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

// Получаем текущие серверы проекта
$stmt = $db->prepare("SELECT server_id FROM project_servers WHERE project_id = :project_id");
$stmt->bindParam(':project_id', $projectId);
$stmt->execute();
$currentServers = array_column($stmt->fetchAll(), 'server_id');

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
    
    // Загрузка логотипа (если есть)
    $logoPath = $project['logo']; // Сохраняем текущий логотип
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
                // Удаляем старый логотип
                if ($logoPath && file_exists(__DIR__ . $logoPath)) {
                    @unlink(__DIR__ . $logoPath);
                }
                $logoPath = '/uploads/projects/' . $fileName;
            }
        } else {
            $errors[] = 'Неверный формат файла. Разрешены: JPG, PNG, WEBP';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Обновляем проект
            $stmt = $db->prepare("
                UPDATE projects 
                SET name = :name, description = :description, logo = :logo, website = :website, discord = :discord, vk = :vk
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':logo', $logoPath);
            $stmt->bindParam(':website', $website);
            $stmt->bindParam(':discord', $discord);
            $stmt->bindParam(':vk', $vk);
            $stmt->bindParam(':id', $projectId);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->execute();
            
            // Удаляем старые связи с серверами
            $stmt = $db->prepare("DELETE FROM project_servers WHERE project_id = :project_id");
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            // Добавляем новые серверы
            $stmt = $db->prepare("INSERT INTO project_servers (project_id, server_id) VALUES (:project_id, :server_id)");
            foreach ($selectedServers as $serverId) {
                $stmt->bindParam(':project_id', $projectId);
                $stmt->bindParam(':server_id', $serverId);
                $stmt->execute();
            }
            
            $db->commit();
            
            require_once __DIR__ . '/config/logger.php';
            Logger::project("Project updated", ['project_id' => $projectId, 'name' => $name, 'user_id' => $user['id'], 'servers_count' => count($selectedServers)]);
            
            $success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            require_once __DIR__ . '/config/logger.php';
            Logger::error("Error updating project", ['project_id' => $projectId, 'user_id' => $user['id'], 'error' => $e->getMessage()]);
            $errors[] = 'Ошибка при обновлении проекта: ' . $e->getMessage();
        }
    }
    
    // Перезагружаем данные проекта после обновления
    if ($success) {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->bindParam(':id', $projectId);
        $stmt->execute();
        $project = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT server_id FROM project_servers WHERE project_id = :project_id");
        $stmt->bindParam(':project_id', $projectId);
        $stmt->execute();
        $currentServers = array_column($stmt->fetchAll(), 'server_id');
    }
}
?>

<div class="project-info-block" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
    <h3>Редактирование проекта</h3>
    <p>Вы можете изменить информацию о вашем проекте.</p>
    <p><strong>Текущий статус: <?php echo $project['status'] === 'active' ? 'Активен' : ($project['status'] === 'pending' ? 'На модерации' : 'Отклонен'); ?></strong></p>
    <p style="margin-top: 1rem; font-size: 0.9rem; color: rgba(255,255,255,0.85);">
        * После сохранения изменений проект может быть отправлен на повторную модерацию
    </p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        Проект успешно обновлен! <a href="/project.php?id=<?php echo $projectId; ?>">Посмотреть проект</a>
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
        У вас недостаточно серверов для редактирования проекта. Необходимо минимум 2 активных сервера.
        <a href="/add_server.php">Добавить сервер</a>
    </div>
<?php else: ?>
    <form method="POST" enctype="multipart/form-data" class="project-form">
        <div class="form-group">
            <label for="name">Название проекта *</label>
            <input type="text" id="name" name="name" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($_POST['name'] ?? $project['name']); ?>">
        </div>
        
        <div class="form-group">
            <label for="logo">Логотип проекта * (16:9, 4:3, 16:10)</label>
            <?php if ($project['logo']): ?>
                <div style="margin-bottom: 1rem;">
                    <img src="<?php echo htmlspecialchars($project['logo']); ?>" alt="Текущий логотип" style="max-width: 200px; border-radius: 8px; border: 2px solid #e5e7eb;">
                </div>
                <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">Оставьте пустым, чтобы сохранить текущий логотип</p>
            <?php endif; ?>
            <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp">
            <small>Форматы: JPG, PNG, WEBP. Рекомендуемый размер: 1280x720px</small>
        </div>
        
        <div class="form-group">
            <label for="servers">Выбрать серверы * (минимум 2)</label>
            <div class="servers-list">
                <?php foreach ($userServers as $server): ?>
                    <label class="server-checkbox">
                        <input type="checkbox" name="servers[]" value="<?php echo $server['id']; ?>" 
                               <?php echo in_array($server['id'], $currentServers) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($server['name']); ?> (<?php echo htmlspecialchars($server['ip']); ?>:<?php echo $server['port']; ?>)
                        - Рейтинг: <?php echo $server['rating']; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Описание проекта * (минимум 50 символов)</label>
            <textarea id="description" name="description" class="form-control" rows="8" required minlength="50"><?php echo htmlspecialchars($_POST['description'] ?? $project['description']); ?></textarea>
            <small>Доступны BB-коды: [b]текст[/b], [i]текст[/i], [u]текст[/u], [url=ссылка]текст[/url]</small>
        </div>
        
        <div class="form-group">
            <label for="website">Веб-сайт</label>
            <input type="url" id="website" name="website" class="form-control" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['website'] ?? $project['website']); ?>">
        </div>
        
        <div class="form-group">
            <label for="discord">Discord Invite Code</label>
            <input type="text" id="discord" name="discord" class="form-control" placeholder="abc123xyz" value="<?php echo htmlspecialchars($_POST['discord'] ?? $project['discord']); ?>">
            <small>Только код приглашения, например: abc123xyz</small>
        </div>
        
        <div class="form-group">
            <label for="vk">ВКонтакте</label>
            <input type="url" id="vk" name="vk" class="form-control" placeholder="https://vk.com/yourgroup" value="<?php echo htmlspecialchars($_POST['vk'] ?? $project['vk']); ?>">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-warning">Сохранить изменения</button>
            <a href="/project.php?id=<?php echo $projectId; ?>" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

