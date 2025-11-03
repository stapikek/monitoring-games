// assets/js/server.js - функции для страницы сервера

// Обновление информации о сервере
function updateServerPageInfo(serverId) {
    console.log('Обновление информации о сервере:', serverId);
    
    var mapDisplay = document.getElementById('server-map-display');
    var mapDetail = document.getElementById('server-map');
    var playersDisplay = document.getElementById('server-players-display');
    var playersOnline = document.getElementById('players-online');
    
    if (mapDisplay) {
        mapDisplay.innerHTML = '<span style="color: #999;">Загрузка...</span>';
    }
    
    fetch('/api/server_info.php?id=' + serverId)
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            console.log('Ответ API:', data);
            
            if (data.success) {
                // Обновляем отображение карты
                if (data.map) {
                    var mapName = data.map.toUpperCase();
                    
                    if (mapDisplay) {
                        mapDisplay.textContent = mapName;
                    }
                    
                    if (mapDetail) {
                        mapDetail.textContent = data.map;
                    }
                    
                    // Обновляем миниатюру карты
                    var mapThumbnail = document.getElementById('map-thumbnail');
                    if (mapThumbnail) {
                        // Обновляем только текст, если нет изображения (fallback)
                        var textNode = mapThumbnail.childNodes[0];
                        if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                            textNode.textContent = mapName;
                        }
                    }
                } else {
                    // Если карта не получена
                    if (mapDisplay) {
                        mapDisplay.innerHTML = '<span style="color: #dc3545;">Недоступно</span>';
                    }
                    if (mapDetail) {
                        mapDetail.textContent = 'Недоступно';
                    }
                }
                
                // Обновляем количество игроков
                if (data.players !== undefined && data.max_players !== undefined) {
                    if (playersDisplay) {
                        playersDisplay.innerHTML = data.players + ' <span style="color: #999;">/ ' + data.max_players + '</span>';
                    }
                    
                    if (playersOnline) {
                        playersOnline.textContent = data.players + ' / ' + data.max_players;
                    }
                    
                    // Обновляем прогресс-бар
                    var progressBar = document.querySelector('.progress-fill');
                    if (progressBar && data.max_players > 0) {
                        var percentage = (data.players / data.max_players) * 100;
                        progressBar.style.width = percentage + '%';
                    }
                }
                
                // Обновляем пинг
                if (data.ping !== undefined && data.ping !== null) {
                    var pingDisplay = document.getElementById('server-ping');
                    if (pingDisplay) {
                        pingDisplay.textContent = data.ping + ' ms';
                    }
                }
                
                // Обновляем пик игроков
                if (data.peak_players !== undefined && data.peak_players !== null) {
                    var peakDisplay = document.getElementById('peak-players-detail');
                    if (peakDisplay) {
                        peakDisplay.textContent = data.peak_players;
                    }
                }
                
            } else {
                console.warn('API вернул success: false', data.error);
                
                // Показываем, что сервер недоступен
                if (mapDisplay) {
                    mapDisplay.innerHTML = '<span style="color: #dc3545;">Недоступно</span>';
                }
            }
        })
        .catch(function(error) {
            console.error('Ошибка обновления информации о сервере:', error);
            
            if (mapDisplay) {
                mapDisplay.innerHTML = '<span style="color: #dc3545;">Ошибка загрузки</span>';
            }
        });
}

// Функция голосования за сервер
function voteForServer(serverId) {
    console.log('Голосование за сервер ID:', serverId);
    
    var btnText = document.getElementById('vote-btn-text');
    var btnSpinner = document.getElementById('vote-btn-spinner');
    
    if (btnText) btnText.style.display = 'none';
    if (btnSpinner) btnSpinner.style.display = 'inline';
    
    fetch('/api/vote_server.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'server_id=' + serverId
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            // Обновляем рейтинг на странице
            var ratingDisplay = document.getElementById('server-rating');
            if (ratingDisplay && data.new_rating) {
                ratingDisplay.textContent = data.new_rating;
            }
            
            // Показываем сообщение об успехе
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage(data.message || 'Спасибо за ваш голос!', 'success');
            } else {
                alert(data.message || 'Спасибо за ваш голос!');
            }
            
            // Перезагружаем страницу для обновления кулдауна
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage('Ошибка: ' + (data.error || 'Не удалось проголосовать'), 'error');
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось проголосовать'));
            }
            if (btnText) btnText.style.display = 'inline';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }
    })
    .catch(function(error) {
        console.error('Ошибка голосования:', error);
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Ошибка при обработке запроса. Проверьте подключение к интернету.', 'error');
        } else {
            alert('Ошибка при обработке запроса. Проверьте подключение к интернету.');
        }
        if (btnText) btnText.style.display = 'inline';
        if (btnSpinner) btnSpinner.style.display = 'none';
    });
}

// Запускаем обновление при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Получаем ID сервера из data-атрибута
    var serverContainer = document.querySelector('[data-server-id]');
    if (serverContainer) {
        var serverId = serverContainer.getAttribute('data-server-id');
        if (serverId) {
            console.log('Загрузка данных для сервера ID:', serverId);
            updateServerPageInfo(serverId);
            
            // Обновляем каждые 5 минут
            setInterval(function() {
                updateServerPageInfo(serverId);
            }, 300000); // 5 минут = 300000 миллисекунд
        }
    }
});
