# CS2 Мониторинг - API Документация

## Обзор

API для получения актуальной информации о CS2 серверах в реальном времени через Steam Server Query Protocol.

---

## Эндпоинты

### 1. Получение информации о сервере

**Endpoint:** `/api/server_info.php`

**Метод:** `GET`

**Параметры:**
- `id` (обязательный) - ID сервера в базе данных

**Пример запроса:**
```
GET /api/server_info.php?id=1
```

**Успешный ответ (200 OK):**
```json
{
    "success": true,
    "players": 12,
    "max_players": 32,
    "map": "de_dust2",
    "ping": 45
}
```

**Ответ при недоступном сервере (200 OK):**
```json
{
    "success": false,
    "error": "Сервер недоступен"
}
```

**Ответ при неверном ID (200 OK):**
```json
{
    "success": false,
    "error": "Неверный ID сервера"
}
```

**Ответ при отсутствии сервера (200 OK):**
```json
{
    "success": false,
    "error": "Сервер не найден"
}
```

---

## Поля ответа

| Поле | Тип | Описание |
|------|-----|----------|
| `success` | boolean | Статус запроса (true/false) |
| `players` | integer | Текущее количество игроков на сервере |
| `max_players` | integer | Максимальное количество игроков |
| `map` | string | Код текущей карты (например: de_dust2, de_mirage) |
| `ping` | integer | Пинг до сервера в миллисекундах |
| `error` | string | Сообщение об ошибке (только при success: false) |

---

## Техническая реализация

### Используемые технологии:
1. **Steam Server Query Protocol (A2S_INFO)**
   - UDP соединение с игровыми серверами
   - Challenge-response механизм для CS2
   - Получение данных напрямую с сервера

2. **PHP ServerQuery класс**
   - Местоположение: `/config/server_query.php`
   - Timeout: 3 секунды
   - Максимум попыток: 5-10 (в зависимости от этапа)

### Алгоритм работы:

```
1. Получить ID сервера из параметра запроса
   ↓
2. Запросить IP:PORT из базы данных
   ↓
3. Открыть UDP соединение с сервером
   ↓
4. Отправить A2S_INFO запрос
   ↓
5. Получить challenge (если требуется)
   ↓
6. Отправить запрос с challenge
   ↓
7. Получить и распарсить ответ
   ↓
8. Измерить ping
   ↓
9. Вернуть JSON ответ
```

---

## Использование на фронтенде

### JavaScript пример:

```javascript
function getServerInfo(serverId) {
    fetch('/api/server_info.php?id=' + serverId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Игроков:', data.players + '/' + data.max_players);
                console.log('Карта:', data.map);
                console.log('Пинг:', data.ping + 'ms');
            } else {
                console.error('Ошибка:', data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка запроса:', error);
        });
}
```

### jQuery пример:

```javascript
$.ajax({
    url: '/api/server_info.php',
    method: 'GET',
    data: { id: serverId },
    dataType: 'json',
    success: function(data) {
        if (data.success) {
            $('#players').text(data.players + '/' + data.max_players);
            $('#map').text(data.map);
            $('#ping').text(data.ping + 'ms');
        } else {
            alert('Ошибка: ' + data.error);
        }
    }
});
```

---

## Автоматическое обновление

### Скрипт для массового обновления:

**Файл:** `/api/update_all_servers.php`

Этот скрипт предназначен для запуска через cron каждые 5 минут:

```bash
*/5 * * * * php /path/to/project/api/update_all_servers.php
```

**Что делает:**
1. Получает все активные серверы из БД
2. Для каждого сервера запрашивает актуальную информацию
3. Обновляет `current_players`, `max_players`, `last_updated` в БД

**Важно:** Карта НЕ сохраняется в БД, она всегда берется из API!

---

## Безопасность

### Защита от SQL инъекций:
- Используются prepared statements для всех запросов к БД
- `intval()` для ID сервера

### Ограничения:
- Timeout: 3 секунды на соединение
- Максимум попыток: 5-10
- Только активные серверы (status = 'active')

### Rate Limiting:
На уровне API не реализован, рекомендуется настроить на уровне веб-сервера (nginx/apache).

---

## Коды ошибок

| Ошибка | Причина | Решение |
|--------|---------|---------|
| "Неверный ID сервера" | ID <= 0 или не передан | Передать корректный ID |
| "Сервер не найден" | Сервер с таким ID не существует или неактивен | Проверить ID в БД |
| "Сервер недоступен" | Сервер не отвечает на UDP запросы | Проверить доступность сервера |
| "Не удалось подключиться к серверу" | Проблемы с сетью | Проверить firewall/сеть |
| "Пустой ответ от сервера" | Сервер не отправил данные | Проверить конфигурацию сервера |
| "Некорректный ответ сервера" | Неверный формат ответа | Возможно, сервер не поддерживает протокол |

---

## Тестирование

### Ручное тестирование:

```bash
# Получить информацию о сервере с ID 1
curl "https://domen.pw/api/server_info.php?id=1"

# С форматированием JSON
curl -s "https://domen.pw/api/server_info.php?id=1" | python -m json.tool
```

### PHP тестирование:

```php
<?php
require_once 'config/database.php';
require_once 'config/server_query.php';

$serverQuery = new ServerQuery();
$info = $serverQuery->getServerInfo('127.0.0.1', 27015);

var_dump($info);
```

---

## Дополнительные ресурсы

### Документация Steam Server Query Protocol:
- [Valve Developer Wiki](https://developer.valvesoftware.com/wiki/Server_queries)
- [A2S_INFO Protocol](https://developer.valvesoftware.com/wiki/Server_Queries#A2S_INFO)

### Связанные файлы:
- `/config/server_query.php` - Класс для запросов к серверам
- `/api/server_info.php` - API endpoint
- `/api/update_all_servers.php` - Массовое обновление
- `/assets/js/common.js` - Frontend обновление

---

## Версионирование

**Текущая версия:** 2.0

### Изменения:
- **v2.0** - Полный переход на Steam Query API, карта не сохраняется в БД
- **v1.5** - Добавлена поддержка challenge-response для CS2
- **v1.0** - Базовая реализация A2S_INFO

---

## FAQ

**Q: Почему карта не сохраняется в БД?**  
A: Карта постоянно меняется на сервере, поэтому актуальнее получать ее напрямую через API.

**Q: Как часто обновляются данные?**  
A: На фронтенде - каждые 5 минут автоматически. Через API - в реальном времени при каждом запросе.

**Q: Что делать, если сервер не отвечает?**  
A: Проверьте доступность сервера, firewall правила, и что порт открыт для UDP соединений.

**Q: Можно ли увеличить timeout?**  
A: Да, измените значение `$timeout` в классе `ServerQuery` (по умолчанию 3 секунды).

**Q: Поддерживаются ли другие игры кроме CS2?**  
A: Да, протокол Steam Query работает для всех Source Engine игр (CSGO, CS 1.6, CSS и др.).

---

## Поддержка

При возникновении проблем проверьте:
1. Логи PHP: `/var/log/apache2/error.log`
2. Логи приложения: `/logs/security.log`
3. Доступность сервера через `telnet IP PORT`
4. Firewall правила для UDP портов

---

