<?php
/**
 * setup_cron.php - Автоматическая настройка cron задач
 * Этот PHP скрипт заменяет .sh файлы и настраивает cron напрямую
 */

// Безопасность: только CLI режим
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    die("This script can only be run from CLI\n");
}

// Проверяем аргументы
$autoInstall = in_array('--install', $argv ?? []);

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║     Автоматическая настройка Cron задач для CS2 Мониторинга       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

// Определяем пути
$siteRoot = __DIR__;
$updateScript = "$siteRoot/api/update_all_servers.php";
$cacheScript = "$siteRoot/cleanup_cache.php";
$logDir = "$siteRoot/logs";

// Определяем PHP путь
$phpPath = trim(shell_exec('which php') ?: '/usr/bin/php');

// Проверка файлов
if (!file_exists($updateScript)) {
    die("❌ Файл $updateScript не найден!\n");
}

if (!file_exists($cacheScript)) {
    die("❌ Файл $cacheScript не найден!\n");
}

// Создаем директорию для логов
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

echo "✓ PHP: $phpPath\n";
echo "✓ Корень сайта: $siteRoot\n";
echo "✓ Скрипт обновления: $updateScript\n";
echo "✓ Скрипт очистки кеша: $cacheScript\n";
echo "✓ Директория логов: $logDir\n\n";

// Формируем cron задачи
$cronJobs = [
    "*/5 * * * * $phpPath $updateScript >> $logDir/server_update.log 2>&1",
    "0 * * * * $phpPath $cacheScript >> $logDir/cache_cleanup.log 2>&1"
];

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Cron задачи, которые будут добавлены:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($cronJobs as $i => $job) {
    echo ($i + 1) . ". $job\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Проверяем текущий crontab
$currentCrontab = [];
exec('crontab -l 2>/dev/null', $currentCrontab);
$currentCrontabStr = implode("\n", $currentCrontab);

// Проверяем, есть ли уже наши задачи
$needsAddUpdate = !strpos($currentCrontabStr, 'update_all_servers.php');
$needsAddCache = !strpos($currentCrontabStr, 'cleanup_cache.php');

if (!$needsAddUpdate && !$needsAddCache) {
    echo "✅ Все cron задачи уже настроены!\n\n";
    echo "Текущий crontab:\n";
    echo $currentCrontabStr . "\n";
    exit(0);
}

// В интерактивном режиме спрашиваем подтверждение
if (!$autoInstall) {
    echo "\nДобавить cron задачи? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) !== 'y') {
        echo "❌ Установка отменена\n";
        exit(0);
    }
}

// Добавляем задачи
$newCrontab = $currentCrontabStr;

if ($needsAddUpdate) {
    echo "➕ Добавляю задачу обновления серверов...\n";
    $newCrontab .= "\n" . $cronJobs[0];
}

if ($needsAddCache) {
    echo "➕ Добавляю задачу очистки кеша...\n";
    $newCrontab .= "\n" . $cronJobs[1];
}

// Пытаемся установить новый crontab
$tempFile = tempnam(sys_get_temp_dir(), 'cron_');
file_put_contents($tempFile, $newCrontab . "\n");

$result = exec("crontab $tempFile 2>&1", $output, $returnCode);
unlink($tempFile);

if ($returnCode === 0) {
    echo "\n✅ Cron задачи успешно установлены!\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Текущий crontab:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $newCrontabLines = [];
    exec('crontab -l 2>/dev/null', $newCrontabLines);
    foreach ($newCrontabLines as $line) {
        echo "$line\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Информация:\n\n";
    echo "Обновление серверов: каждые 5 минут\n";
    echo "  Логи: $logDir/server_update.log\n\n";
    echo "Очистка кеша: каждый час\n";
    echo "  Логи: $logDir/cache_cleanup.log\n\n";
    
    echo "🔧 Полезные команды:\n";
    echo "  Просмотр логов обновления: tail -f $logDir/server_update.log\n";
    echo "  Просмотр логов кеша: tail -f $logDir/cache_cleanup.log\n";
    echo "  Редактирование cron: crontab -e\n";
    echo "  Просмотр текущих задач: crontab -l\n";
    
} else {
    echo "❌ Ошибка при установке cron задач\n";
    echo "Вывод: " . implode("\n", $output) . "\n";
    exit(1);
}

echo "\n✅ Настройка завершена!\n\n";

