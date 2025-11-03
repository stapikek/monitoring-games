<?php
// edit_server.php

// SEO настройки
$page_title = 'Редактировать сервер - CS2 Мониторинг';
$page_description = 'Измените информацию о своем CS2 сервере в мониторинге. Обновите описание, теги и другие параметры.';
$page_keywords = 'CS2, редактировать сервер, изменить сервер';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/edit_server.php';

// Подключаем дополнительные CSS и JS
$additional_js = ['/assets/js/add_server.js'];  // Используем тот же JS что и для add_server

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $auth->getUserId();
$server_id = intval($_GET['id'] ?? 0);

if ($server_id == 0) {
    header("Location: /profile.php");
    exit;
}

// Проверяем принадлежность сервера
$server_stmt = $db->prepare("SELECT * FROM servers WHERE id = :id AND user_id = :user_id");
$server_stmt->bindParam(":id", $server_id);
$server_stmt->bindParam(":user_id", $user_id);
$server_stmt->execute();
$server = $server_stmt->fetch();

if (!$server) {
    header("Location: /profile.php");
    exit;
}

// Получаем списки для форм
$games_stmt = $db->query("SELECT * FROM games ORDER BY name");
$games = $games_stmt->fetchAll();

$modes_stmt = $db->query("SELECT * FROM game_modes ORDER BY name");
$modes = $modes_stmt->fetchAll();

$maps_stmt = $db->query("SELECT * FROM maps ORDER BY name");
$maps = $maps_stmt->fetchAll();

// Получаем текущие теги сервера
try {
    $current_tags_stmt = $db->prepare("SELECT tag_id FROM server_tags WHERE server_id = :id");
    $current_tags_stmt->bindParam(":id", $server_id);
    $current_tags_stmt->execute();
    $current_tags = array_column($current_tags_stmt->fetchAll(), 'tag_id');
} catch (PDOException $e) {
    $current_tags = [];
}

$errors = [];
$success = false;

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
    
    // Валидация (аналогично add_server.php)
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
        // Проверяем, не существует ли уже другой сервер с таким IP и портом
        $check_stmt = $db->prepare("SELECT id FROM servers WHERE ip = :ip AND port = :port AND id != :id");
        $check_stmt->bindParam(":ip", $ip);
        $check_stmt->bindParam(":port", $port);
        $check_stmt->bindParam(":id", $server_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Сервер с таким IP и портом уже существует";
        } else {
            $update_stmt = $db->prepare("UPDATE servers SET name = :name, ip = :ip, port = :port, game_id = :game_id, 
                                        game_mode_id = :game_mode_id, map_id = :map_id, description = :description,
                                        discord_url = :discord_url, vk_url = :vk_url, site_url = :site_url
                                        WHERE id = :id AND user_id = :user_id");
            
            $update_stmt->bindParam(":name", $name);
            $update_stmt->bindParam(":ip", $ip);
            $update_stmt->bindParam(":port", $port);
            $update_stmt->bindParam(":game_id", $game_id);
            $update_stmt->bindParam(":game_mode_id", $game_mode_id);
            $update_stmt->bindParam(":map_id", $map_id);
            $update_stmt->bindParam(":description", $description);
            $discord_url_empty = empty($discord_url) ? null : $discord_url;
            $vk_url_empty = empty($vk_url) ? null : $vk_url;
            $site_url_empty = empty($site_url) ? null : $site_url;
            $update_stmt->bindParam(":discord_url", $discord_url_empty);
            $update_stmt->bindParam(":vk_url", $vk_url_empty);
            $update_stmt->bindParam(":site_url", $site_url_empty);
            $update_stmt->bindParam(":id", $server_id);
            $update_stmt->bindParam(":user_id", $user_id);
            
            if ($update_stmt->execute()) {
                // Обновляем теги
                try {
                    $delete_tags_stmt = $db->prepare("DELETE FROM server_tags WHERE server_id = :server_id");
                    $delete_tags_stmt->bindParam(":server_id", $server_id);
                    $delete_tags_stmt->execute();
                    
                    if (!empty($tags)) {
                        $tag_stmt = $db->prepare("INSERT INTO server_tags (server_id, tag_id) VALUES (:server_id, :tag_id)");
                        foreach ($tags as $tag_id) {
                            $tag_id = intval($tag_id);
                            if ($tag_id > 0) {
                                $tag_stmt->bindParam(":server_id", $server_id);
                                $tag_stmt->bindParam(":tag_id", $tag_id);
                                $tag_stmt->execute();
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // Таблицы тегов еще нет - игнорируем ошибку
                }
                
                require_once __DIR__ . '/config/logger.php';
                Logger::server("Server updated", ['server_id' => $server_id, 'name' => $name, 'ip' => "$ip:$port"]);
                
                $success = true;
                // Обновляем данные сервера для отображения
                $server_stmt->execute();
                $server = $server_stmt->fetch();
                $current_tags = $tags;
            } else {
                $errors[] = "Ошибка при обновлении сервера";
            }
        }
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success">
        Сервер успешно обновлен!
    </div>
    <p><a href="/profile.php">Вернуться в профиль</a></p>
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
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $server['name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="ip">IP адрес *:</label>
                <input type="text" id="ip" name="ip" required value="<?php echo htmlspecialchars($_POST['ip'] ?? $server['ip']); ?>">
            </div>
            
            <div class="form-group">
                <label for="port">Порт *:</label>
                <input type="number" id="port" name="port" required min="1" max="65535" value="<?php echo htmlspecialchars($_POST['port'] ?? $server['port']); ?>">
            </div>
            
            <div class="form-group">
                <label for="game_id">Игра *:</label>
                <select id="game_id" name="game_id" required>
                    <option value="">Выберите игру</option>
                    <?php foreach ($games as $game): ?>
                        <option value="<?php echo $game['id']; ?>" <?php echo ($server['game_id'] == $game['id'] || (isset($_POST['game_id']) && $_POST['game_id'] == $game['id'])) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $mode['id']; ?>" <?php echo ($server['game_mode_id'] == $mode['id'] || (isset($_POST['game_mode_id']) && $_POST['game_mode_id'] == $mode['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mode['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="map_id">Карта:</label>
                <select id="map_id" name="map_id">
                    <option value="">Не указана</option>
                    <?php foreach ($maps as $map): ?>
                        <option value="<?php echo $map['id']; ?>" <?php echo ($server['map_id'] == $map['id'] || (isset($_POST['map_id']) && $_POST['map_id'] == $map['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($map['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Описание:</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($_POST['description'] ?? $server['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="discord_url">Discord (ссылка):</label>
                <input type="url" id="discord_url" name="discord_url" placeholder="https://discord.gg/..." value="<?php echo htmlspecialchars($_POST['discord_url'] ?? $server['discord_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="vk_url">VK (ссылка):</label>
                <input type="url" id="vk_url" name="vk_url" placeholder="https://vk.com/..." value="<?php echo htmlspecialchars($_POST['vk_url'] ?? $server['vk_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="site_url">Сайт (ссылка):</label>
                <input type="url" id="site_url" name="site_url" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['site_url'] ?? $server['site_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="tags">Теги (выберите несколько, зажав Ctrl/Cmd):</label>
                <select id="tags" name="tags[]" multiple size="5" style="height: 120px;">
                    <?php
                    try {
                        $tags_stmt = $db->query("SELECT * FROM tags ORDER BY name");
                        $all_tags = $tags_stmt->fetchAll();
                    } catch (PDOException $e) {
                        $all_tags = [];
                    }
                    $current_tags_array = isset($_POST['tags']) ? $_POST['tags'] : $current_tags;
                    foreach ($all_tags as $tag):
                        $selected = in_array($tag['id'], $current_tags_array) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $tag['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($tag['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 0.875rem;">Для выбора нескольких тегов используйте Ctrl (Windows) или Cmd (Mac)</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            <a href="/profile.php" class="btn" style="margin-left: 10px;">Отмена</a>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

