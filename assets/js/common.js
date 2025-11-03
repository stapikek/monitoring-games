// Общие функции, используемые на всех страницах
function copyToClipboard(text, element) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            var originalText = element.textContent;
            element.textContent = 'Скопировано!';
            element.style.color = '#28a745';
            setTimeout(function() {
                element.textContent = originalText;
                element.style.color = '';
            }, 2000);
        }).catch(function(err) {
            console.error('Ошибка копирования:', err);
            fallbackCopy(text, element);
        });
    } else {
        fallbackCopy(text, element);
    }
}

function fallbackCopy(text, element) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            var originalText = element.textContent;
            element.textContent = 'Скопировано!';
            element.style.color = '#28a745';
            setTimeout(function() {
                element.textContent = originalText;
                element.style.color = '';
            }, 2000);
        }
    } catch (err) {
        console.error('Ошибка копирования:', err);
    }
    
    document.body.removeChild(textArea);
}

function updateServerInfo(serverId, silent) {
    silent = silent || false;
    
    var row = document.querySelector('tr[data-server-id="' + serverId + '"]');
    if (!row) {
        if (!silent) {
            console.warn('Строка сервера не найдена для ID: ' + serverId);
        }
        return;
    }
    
    if (!silent) {
        console.log('Обновление сервера ID: ' + serverId);
    }
    
    var playersSpan = row.querySelector('.players-count');
    var maxPlayersSpan = row.querySelector('.max-players');
    var mapCell = row.querySelector('td.server-map');
    var indicator = row.querySelector('.update-indicator');
    
    if (!silent && indicator) {
        indicator.style.display = 'inline';
    }
    
    var url = '/api/server_info.php?id=' + serverId;
    if (!silent) {
        console.log('Запрос к API:', url);
    }
    
    fetch(url)
        .then(function(response) {
            if (!silent) {
                console.log('HTTP response status:', response.status);
            }
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (!silent) {
                console.log('Ответ API для сервера ' + serverId + ':', data);
            }
            
            // Обновляем данные из БД
            if (data.success) {
                if (data.players !== undefined && data.players !== null) {
                    if (playersSpan) {
                        playersSpan.textContent = data.players;
                    }
                }
                
                if (data.max_players !== undefined && data.max_players !== null) {
                    if (maxPlayersSpan) {
                        maxPlayersSpan.textContent = data.max_players;
                    }
                }
                
                if (data.map !== undefined && data.map !== null) {
                    if (mapCell) {
                        mapCell.textContent = data.map || '-';
                    }
                }
            }
        })
        .catch(function(error) {
            console.error('Ошибка обновления информации о сервере ' + serverId + ':', error);
            if (!silent) {
                if (typeof showGlobalMessage === 'function') {
                    showGlobalMessage('Ошибка обновления сервера ' + serverId + ': ' + error.message, 'error');
                } else {
                    alert('Ошибка обновления сервера ' + serverId + ': ' + error.message);
                }
            }
        })
        .finally(function() {
            if (!silent && indicator) {
                indicator.style.display = 'none';
            }
        });
}

function updateAllServers() {
    var rows = document.querySelectorAll('tr[data-server-id]');
    rows.forEach(function(row) {
        var serverId = row.getAttribute('data-server-id');
        if (serverId) {
            updateServerInfo(serverId, true);
        }
    });
}

// Автоматическое обновление информации о серверах каждые 5 минут
document.addEventListener('DOMContentLoaded', function() {
    console.log('Страница загружена, начинаем обновление серверов...');
    var rows = document.querySelectorAll('tr[data-server-id]');
    console.log('Найдено серверов: ' + rows.length);
    
    // Обновляем сразу при загрузке страницы
    updateAllServers();
    
    // Обновляем каждые 5 минут
    setInterval(function() {
        updateAllServers();
    }, 300000); // 5 минут = 300000 миллисекунд
});

// Система переключения темы
(function() {
    const THEME_KEY = 'siteTheme';
    const LIGHT_THEME = 'light';
    const DARK_THEME = 'dark';
    
    // Получить текущую тему из localStorage или определить по умолчанию
    function getTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        if (savedTheme) {
            return savedTheme;
        }
        
        // Проверить системные настройки
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return DARK_THEME;
        }
        
        return LIGHT_THEME;
    }
    
    // Применить тему
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        
        // Обновить иконку
        const moonIcon = document.querySelector('.theme-icon-moon');
        const sunIcon = document.querySelector('.theme-icon-sun');
        if (moonIcon && sunIcon) {
            if (theme === DARK_THEME) {
                moonIcon.style.display = 'none';
                sunIcon.style.display = 'block';
            } else {
                moonIcon.style.display = 'block';
                sunIcon.style.display = 'none';
            }
        }
    }
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = getTheme();
        applyTheme(currentTheme);
        
        // Обработчик клика на кнопку переключения темы
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const currentTheme = getTheme();
                const newTheme = currentTheme === LIGHT_THEME ? DARK_THEME : LIGHT_THEME;
                applyTheme(newTheme);
            });
        }
    });
    
    // Слушать изменения системной темы
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            // Применять только если пользователь не установил свою тему
            if (!localStorage.getItem(THEME_KEY)) {
                applyTheme(e.matches ? DARK_THEME : LIGHT_THEME);
            }
        });
    }
})();

// Мобильное меню
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileToggle && navMenu) {
        mobileToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
        
        // Закрыть меню при клике вне его
        document.addEventListener('click', function(e) {
            if (!mobileToggle.contains(e.target) && !navMenu.contains(e.target)) {
                mobileToggle.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
        
        // Закрыть меню при клике на ссылку
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mobileToggle.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    }
});


