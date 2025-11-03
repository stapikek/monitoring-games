<?php
// add_server.php

// SEO настройки
$page_title = 'Добавить сервер - CS2 Мониторинг';
$page_description = 'Добавьте свой CS2 сервер в мониторинг. Привлекайте новых игроков, получайте голоса и развивайте свой сервер.';
$page_keywords = 'CS2, добавить сервер, разместить сервер';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/add_server.php';

// Подключаем дополнительные CSS и JS
$additional_js = ['/assets/js/add_server.js'];

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $auth->getUserId();

// Получаем списки для форм
$games_stmt = $db->query("SELECT * FROM games ORDER BY name");
$games = $games_stmt->fetchAll();

$modes_stmt = $db->query("SELECT * FROM game_modes ORDER BY name");
$modes = $modes_stmt->fetchAll();

$maps_stmt = $db->query("SELECT * FROM maps ORDER BY name");
$maps = $maps_stmt->fetchAll();

$errors = [];
$success = false;
$existing_server = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $port = intval($_POST['port'] ?? 0);
    $game_id = intval($_POST['game_id'] ?? 0);
    $game_mode_id = !empty($_POST['game_mode_id']) ? intval($_POST['game_mode_id']) : null;
    $map_id = !empty($_POST['map_id']) ? intval($_POST['map_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $discord_url = trim($_POST['discord_url'] ?? '');
    $vk_url = trim($_POST['vk_url'] ?? '');
    $site_url = trim($_POST['site_url'] ?? '');
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    // Валидация
    if (empty($name)) {
        $errors[] = "Название сервера обязательно";
    }
    
    if (empty($ip)) {
        $errors[] = "IP адрес обязателен";
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $errors[] = "Некорректный IP адрес";
    }
    
    if ($port < 1 || $port > 65535) {
        $errors[] = "Некорректный порт (1-65535)";
    }
    
    if (empty($game_id)) {
        $errors[] = "Выберите игру";
    }
    
    if (empty($errors)) {
        // Проверяем, не существует ли уже сервер с таким IP и портом
        $check_stmt = $db->prepare("SELECT id, name FROM servers WHERE ip = :ip AND port = :port LIMIT 1");
        $check_stmt->bindParam(":ip", $ip);
        $check_stmt->bindParam(":port", $port);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $existing_server = $check_stmt->fetch();
        } else {
            $insert_stmt = $db->prepare("INSERT INTO servers (name, ip, port, game_id, game_mode_id, map_id, 
                                        description, discord_url, vk_url, site_url, user_id, status)
                                        VALUES (:name, :ip, :port, :game_id, :game_mode_id, :map_id, 
                                        :description, :discord_url, :vk_url, :site_url, :user_id, 'pending')");
            
            $insert_stmt->bindParam(":name", $name);
            $insert_stmt->bindParam(":ip", $ip);
            $insert_stmt->bindParam(":port", $port);
            $insert_stmt->bindParam(":game_id", $game_id);
            $insert_stmt->bindParam(":game_mode_id", $game_mode_id);
            $insert_stmt->bindParam(":map_id", $map_id);
            $insert_stmt->bindParam(":description", $description);
            $discord_url_empty = empty($discord_url) ? null : $discord_url;
            $vk_url_empty = empty($vk_url) ? null : $vk_url;
            $site_url_empty = empty($site_url) ? null : $site_url;
            $insert_stmt->bindParam(":discord_url", $discord_url_empty);
            $insert_stmt->bindParam(":vk_url", $vk_url_empty);
            $insert_stmt->bindParam(":site_url", $site_url_empty);
            $insert_stmt->bindParam(":user_id", $user_id);
            
            if ($insert_stmt->execute()) {
                $server_id = $db->lastInsertId();
                
                // Сохраняем теги
                if (!empty($tags)) {
                    try {
                        $tag_stmt = $db->prepare("INSERT INTO server_tags (server_id, tag_id) VALUES (:server_id, :tag_id)");
                        foreach ($tags as $tag_id) {
                            $tag_id = intval($tag_id);
                            if ($tag_id > 0) {
                                $tag_stmt->bindParam(":server_id", $server_id);
                                $tag_stmt->bindParam(":tag_id", $tag_id);
                                $tag_stmt->execute();
                            }
                        }
                    } catch (PDOException $e) {
                        // Таблицы тегов еще нет - игнорируем ошибку
                    }
                }
                
                require_once __DIR__ . '/config/logger.php';
                Logger::server("Server added", ['server_id' => $server_id, 'name' => $name, 'ip' => "$ip:$port"]);
                
                $success = true;
            } else {
                $errors[] = "Ошибка при добавлении сервера";
            }
        }
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success">
        Сервер успешно добавлен и отправлен на модерацию!
    </div>
    <p><a href="/profile.php">Вернуться в профиль</a> | <a href="/add_server.php">Добавить еще один сервер</a></p>
<?php elseif ($existing_server): ?>
    <div class="alert alert-warning">
        <strong>Сервер уже добавлен!</strong><br>
        Сервер с IP <strong><?php echo htmlspecialchars($ip . ':' . $port); ?></strong> уже существует в системе.<br>
        Название: <strong><?php echo htmlspecialchars($existing_server['name']); ?></strong>
    </div>
    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="/server.php?id=<?php echo $existing_server['id']; ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">Перейти на сервер</a>
        <a href="/profile.php" class="btn" style="margin-left: 10px;">Вернуться в профиль</a>
    </div>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST">
            <div class="form-group">
                <label for="name">Название сервера *:</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="ip">IP адрес *:</label>
                <input type="text" id="ip" name="ip" required placeholder="95.213.255.137" value="<?php echo htmlspecialchars($_POST['ip'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="port">Порт *:</label>
                <input type="number" id="port" name="port" required min="1" max="65535" value="<?php echo htmlspecialchars($_POST['port'] ?? '27015'); ?>">
            </div>
            
            <div class="form-group">
                <label for="game_id">Игра *:</label>
                <select id="game_id" name="game_id" required>
                    <option value="">Выберите игру</option>
                    <?php foreach ($games as $game): ?>
                        <option value="<?php echo $game['id']; ?>" <?php echo (isset($_POST['game_id']) && $_POST['game_id'] == $game['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($game['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="game_mode_id">Режим игры:</label>
                <select id="game_mode_id" name="game_mode_id">
                    <option value="">Не указан</option>
                    <?php foreach ($modes as $mode): ?>
                        <option value="<?php echo $mode['id']; ?>" <?php echo (isset($_POST['game_mode_id']) && $_POST['game_mode_id'] == $mode['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mode['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Описание:</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="discord_url">Discord (ссылка):</label>
                <input type="url" id="discord_url" name="discord_url" placeholder="https://discord.gg/..." value="<?php echo htmlspecialchars($_POST['discord_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="vk_url">VK (ссылка):</label>
                <input type="url" id="vk_url" name="vk_url" placeholder="https://vk.com/..." value="<?php echo htmlspecialchars($_POST['vk_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="site_url">Сайт (ссылка):</label>
                <input type="url" id="site_url" name="site_url" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['site_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="tags">Теги (выберите несколько, зажав Ctrl/Cmd):</label>
                <select id="tags" name="tags[]" multiple size="5" style="height: 120px;">
                    <?php
                    try {
                        $tags_stmt = $db->query("SELECT * FROM tags ORDER BY name");
                        $tags = $tags_stmt->fetchAll();
                    } catch (PDOException $e) {
                        $tags = [];
                    }
                    foreach ($tags as $tag):
                        $selected = isset($_POST['tags']) && in_array($tag['id'], $_POST['tags']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $tag['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($tag['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 0.875rem;">Для выбора нескольких тегов используйте Ctrl (Windows) или Cmd (Mac)</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Добавить сервер</button>
            <a href="/profile.php" class="btn" style="margin-left: 10px;">Отмена</a>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

