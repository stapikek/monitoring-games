# Конфигурация путей в проекте

## Система путей

Все пути в проекте теперь **динамические** и не привязаны к конкретному расположению на сервере.

---

## Правильные подходы к путям:

### 1. **В PHP файлах**

Используйте `__DIR__` для получения текущей директории:

```php
// ПРАВИЛЬНО - динамический путь
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$uploadDir = __DIR__ . '/uploads/projects/';
```

```php
// НЕПРАВИЛЬНО - жесткий абсолютный путь
require_once '/var/www/domen_pw_usr/data/www/domen.pw/config/database.php';
```

### 2. **В Bash скриптах**

Используйте динамическое определение пути:

```bash
# ПРАВИЛЬНО - динамическое определение
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SITE_ROOT="$SCRIPT_DIR"

SCRIPT_PATH="$SITE_ROOT/api/update_all_servers.php"
LOG_PATH="$SITE_ROOT/logs/server_update.log"
```

```bash
# НЕПРАВИЛЬНО - жесткий абсолютный путь
SCRIPT_PATH="/var/www/domen_pw_usr/data/www/domen.pw/api/update_all_servers.php"
```

### 3. **В HTML/CSS**

Используйте пути относительно корня сайта:

```html
<!-- ПРАВИЛЬНО - путь от корня сайта -->
<link rel="stylesheet" href="/assets/css/style.css">
<script src="/assets/js/common.js"></script>
<img src="/uploads/projects/logo.jpg">
```

```html
<!-- НЕПРАВИЛЬНО - абсолютный путь файловой системы -->
<img src="/var/www/domen_pw_usr/data/www/domen.pw/uploads/projects/logo.jpg">
```

### 4. **В документации**

Используйте placeholder `/path/to/site`:

```bash
# ПРАВИЛЬНО - универсальный пример
*/5 * * * * /usr/bin/php /path/to/site/api/update_all_servers.php
```

```bash
# НЕПРАВИЛЬНО - конкретный путь
*/5 * * * * /usr/bin/php /var/www/domen_pw_usr/data/www/domen.pw/api/update_all_servers.php
```

---

## Конфигурация Cron

### Автоматическая настройка (рекомендуется):

Используйте скрипт `setup_cron.php`, который автоматически определяет пути:

```bash
cd /path/to/site
php setup_cron.php --install
```

Скрипт:
- Автоматически определяет путь к сайту
- Находит PHP интерпретатор
- Создает правильную запись в crontab
- Проверяет наличие необходимых файлов
- Работает как чистый PHP скрипт (без .sh файлов)

### Ручная настройка:

Если нужно настроить вручную, замените `/path/to/site` на реальный путь:

```bash
# Пример для ISPmanager / cPanel
*/5 * * * * /usr/bin/php /home/username/public_html/api/update_all_servers.php

# Пример для VDS/VPS
*/5 * * * * /usr/bin/php /var/www/mydomain/api/update_all_servers.php
```

---

## Структура путей в проекте

```
/                           <- Корень сайта (DocumentRoot)
├── api/                    <- API endpoints
│   ├── server_info.php
│   └── update_all_servers.php
├── assets/                 <- Статические ресурсы
│   ├── css/
│   ├── js/
│   └── images/
├── config/                 <- Конфигурационные файлы
│   ├── database.php
│   ├── auth.php
│   └── cache.php
├── includes/               <- Общие включаемые файлы
│   ├── header.php
│   └── footer.php
├── uploads/                <- Загруженные пользователями файлы
│   └── projects/
├── logs/                   <- Логи системы
│   └── server_update.log
├── docs/                   <- Документация
└── sql/                    <- SQL скрипты
```

---

## Пути в разных окружениях

### Локальная разработка:
```
/home/developer/projects/cs2-monitoring/
```

### Shared Hosting (ISPmanager, cPanel):
```
/home/username/public_html/
/home/username/domains/mydomain.com/public_html/
```

### VDS/VPS:
```
/var/www/mydomain.com/
/usr/share/nginx/html/
```

### Docker:
```
/var/www/html/
/app/
```

---

## Преимущества динамических путей:

1. **Портативность** - проект работает на любом сервере без изменений
2. **Безопасность** - не раскрывает структуру файловой системы
3. **Универсальность** - один код для всех окружений
4. **Простота развертывания** - просто скопируйте файлы
5. **Легкость миграции** - перенос на другой сервер без правок

---

## Чек-лист для разработчиков:

При создании нового функционала проверьте:

- [ ] Используете ли вы `__DIR__` в PHP файлах?
- [ ] Используете ли вы `/` для путей в HTML/CSS/JS?
- [ ] Нет ли абсолютных путей типа `/var/www/...`?
- [ ] Корректно ли работают пути при загрузке файлов?
- [ ] Проверены ли пути в разных окружениях?

---

## Отладка проблем с путями

### Проблема: "File not found"

```php
// Добавьте отладочный вывод
echo "Current directory: " . __DIR__ . "\n";
echo "Target file: " . __DIR__ . '/config/database.php' . "\n";
echo "File exists: " . (file_exists(__DIR__ . '/config/database.php') ? 'Yes' : 'No') . "\n";
```

### Проблема: "Permission denied"

```bash
# Проверьте права на файлы
ls -la /path/to/site/

# Установите правильные права
chmod 755 /path/to/site/api/
chmod 644 /path/to/site/api/*.php
chmod 777 /path/to/site/uploads/projects/
```

### Проблема: Cron не работает

```bash
# Проверьте логи cron
grep CRON /var/log/syslog

# Проверьте права на скрипт
ls -la /path/to/site/api/update_all_servers.php

# Проверьте путь к PHP
which php
```

---

## Дополнительная информация

- См. также: `docs/SERVER_UPDATE_CONFIG.md` для настройки обновлений
- См. также: `setup_cron.php` для автоматической настройки cron
- См. также: `docs/SQL_OPTIMIZATION.md` для оптимизации БД

---

