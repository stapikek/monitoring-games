// shop.php - специфичный JavaScript для страницы магазина
// userBalance передается через data-атрибут или inline скрипт

function updatePrice() {
    var amount = parseInt(document.getElementById('rating_amount').value) || 0;
    var priceElement = document.getElementById('total-price');
    var balanceCheckElement = document.getElementById('balance-check');
    
    // Получаем баланс из data-атрибута или глобальной переменной
    var userBalance = window.userBalance || parseFloat(document.body.getAttribute('data-balance') || 0);
    
    if (priceElement) {
        priceElement.textContent = amount;
    }
    
    var hasEnough = userBalance >= amount;
    
    if (balanceCheckElement) {
        balanceCheckElement.textContent = hasEnough ? 'Да' : 'Нет';
        balanceCheckElement.style.color = hasEnough ? '#28a745' : '#dc3545';
    }
    
    // Обновляем информацию о рейтинге
    var serverSelect = document.getElementById('server_id');
    var currentRatingElement = document.getElementById('current-rating-display');
    var newRatingElement = document.getElementById('new-rating-display');
    
    if (serverSelect && serverSelect.value) {
        var selectedOption = serverSelect.options[serverSelect.selectedIndex];
        var currentRating = parseInt(selectedOption.getAttribute('data-rating')) || 0;
        
        if (currentRatingElement) {
            currentRatingElement.textContent = currentRating;
        }
        if (newRatingElement) {
            newRatingElement.textContent = currentRating + amount;
        }
    } else {
        if (currentRatingElement) currentRatingElement.textContent = '-';
        if (newRatingElement) newRatingElement.textContent = '-';
    }
}

// Обновляем при выборе сервера
document.addEventListener('DOMContentLoaded', function() {
    var serverSelect = document.getElementById('server_id');
    if (serverSelect) {
        serverSelect.addEventListener('change', updatePrice);
    }
    updatePrice();
});

function purchaseRating(event) {
    event.preventDefault();
    
    var serverId = document.getElementById('server_id').value;
    var ratingAmount = parseInt(document.getElementById('rating_amount').value);
    
    if (!serverId) {
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Выберите сервер', 'warning');
        } else {
            alert('Выберите сервер');
        }
        return;
    }
    
    if (!ratingAmount || ratingAmount < 1) {
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Укажите количество рейтинга (минимум 1)', 'warning');
        } else {
            alert('Укажите количество рейтинга (минимум 1)');
        }
        return;
    }
    
    var userBalance = window.userBalance || parseFloat(document.body.getAttribute('data-balance') || 0);
    if (userBalance < ratingAmount) {
        var need = ratingAmount - userBalance;
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Недостаточно средств на балансе! Не хватает: ' + need.toFixed(2) + ' ₽', 'error');
            setTimeout(() => window.location.href = '/balance.php', 1500);
        } else {
            if (!confirm('Недостаточно средств на балансе!\n\n' +
                         'Требуется: ' + ratingAmount + ' ₽\n' +
                         'На балансе: ' + userBalance.toFixed(2) + ' ₽\n' +
                         'Не хватает: ' + need.toFixed(2) + ' ₽\n\n' +
                         'Перейти на страницу пополнения баланса?')) {
                return;
            }
            window.location.href = '/balance.php';
        }
        return;
    }
    
    if (typeof showGlobalMessage === 'function') {
        // Продолжаем без подтверждения
    } else if (!confirm('Вы уверены, что хотите купить ' + ratingAmount + ' рейтинга за ' + ratingAmount + ' ₽?\n\n' +
                'Средства будут списаны с вашего баланса.')) {
        return;
    }
    
    var btnText = document.getElementById('purchase-btn-text');
    var btnSpinner = document.getElementById('purchase-btn-spinner');
    
    btnText.style.display = 'none';
    btnSpinner.style.display = 'inline';
    
    fetch('/api/purchase_rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'server_id=' + serverId + '&rating_amount=' + ratingAmount
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage('Рейтинг успешно приобретен! Добавлено: ' + ratingAmount + ' рейтинга', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                alert('Рейтинг успешно приобретен!\n\n' + 
                      'Добавлено: ' + ratingAmount + ' рейтинга\n' +
                      'Новый рейтинг: ' + data.new_rating + '\n\n' +
                      (data.note || ''));
                window.location.reload();
            }
        } else {
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage('Ошибка: ' + (data.error || 'Не удалось приобрести рейтинг'), 'error');
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось приобрести рейтинг'));
            }
            btnText.style.display = 'inline';
            btnSpinner.style.display = 'none';
        }
    })
    .catch(function(error) {
        console.error('Ошибка:', error);
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Ошибка при обработке запроса. Проверьте подключение к интернету.', 'error');
        } else {
            alert('Ошибка при обработке запроса. Проверьте подключение к интернету.');
        }
        btnText.style.display = 'inline';
        btnSpinner.style.display = 'none';
    });
}

// VIP функции
function showVipForm(weeks, price) {
    document.getElementById('vip-weeks').value = weeks;
    document.getElementById('vip-price').value = price;
    
    // Преобразуем недели в читаемый период
    var periodText = '';
    if (weeks === 4) periodText = '1 месяц';
    else if (weeks === 12) periodText = '3 месяца';
    else if (weeks === 24) periodText = '6 месяцев';
    else if (weeks === 48) periodText = '1 год';
    else periodText = weeks + ' ' + (weeks === 1 ? 'неделя' : weeks < 5 ? 'недели' : 'недель');
    
    document.getElementById('vip-period-display').textContent = periodText;
    document.getElementById('vip-price-display').textContent = price + ' ₽';
    
    var userBalance = window.userBalance || parseFloat(document.body.getAttribute('data-balance') || 0);
    var hasEnough = userBalance >= price;
    var balanceCheck = document.getElementById('vip-balance-check');
    var balanceCheckContainer = document.getElementById('vip-balance-check-container');
    
    if (balanceCheck) {
        balanceCheck.textContent = hasEnough ? 'Да' : 'Нет';
        balanceCheck.style.color = hasEnough ? '#28a745' : '#dc3545';
    }
    if (balanceCheckContainer) {
        balanceCheckContainer.style.display = 'flex';
    }
    
    document.getElementById('vip-purchase-form').style.display = 'block';
    document.getElementById('vip-purchase-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    updateVipInfo();
}

function hideVipForm() {
    document.getElementById('vip-purchase-form').style.display = 'none';
}

function updateVipInfo() {
    var serverSelect = document.getElementById('vip-server-id');
    var weeks = parseInt(document.getElementById('vip-weeks').value);
    
    if (serverSelect && serverSelect.value) {
        var selectedOption = serverSelect.options[serverSelect.selectedIndex];
        var vipActive = selectedOption.getAttribute('data-vip-active') === '1';
        var vipUntilStr = selectedOption.getAttribute('data-vip-until');
        
        var currentStatusElement = document.getElementById('vip-current-status');
        var vipUntilElement = document.getElementById('vip-until-display');
        
        if (vipActive && vipUntilStr) {
            var vipUntil = new Date(vipUntilStr);
            var newVipUntil = new Date(vipUntil);
            newVipUntil.setDate(newVipUntil.getDate() + (weeks * 7));
            
            if (currentStatusElement) {
                currentStatusElement.textContent = 'Активен до ' + vipUntil.toLocaleDateString('ru-RU');
                currentStatusElement.style.color = '#28a745';
            }
            if (vipUntilElement) {
                vipUntilElement.textContent = newVipUntil.toLocaleDateString('ru-RU');
            }
        } else {
            var now = new Date();
            var newVipUntil = new Date();
            newVipUntil.setDate(newVipUntil.getDate() + (weeks * 7));
            
            if (currentStatusElement) {
                currentStatusElement.textContent = 'Неактивен';
                currentStatusElement.style.color = '#999';
            }
            if (vipUntilElement) {
                vipUntilElement.textContent = newVipUntil.toLocaleDateString('ru-RU');
            }
        }
    } else {
        document.getElementById('vip-current-status').textContent = '-';
        document.getElementById('vip-until-display').textContent = '-';
    }
}

// Синхронизация цветовых полей
function syncColorInputs() {
    var colorPicker = document.getElementById('vip-name-color');
    var colorText = document.getElementById('vip-name-color-text');
    var previewText = document.getElementById('color-preview-text');
    
    if (colorText && colorPicker) {
        var colorValue = colorText.value;
        if (/^#[0-9A-Fa-f]{6}$/.test(colorValue)) {
            colorPicker.value = colorValue;
            if (previewText) {
                previewText.style.color = colorValue;
            }
        } else if (/^#[0-9A-Fa-f]{3}$/.test(colorValue)) {
            var expanded = '#' + colorValue[1] + colorValue[1] + colorValue[2] + colorValue[2] + colorValue[3] + colorValue[3];
            colorPicker.value = expanded;
            colorText.value = expanded;
            if (previewText) {
                previewText.style.color = expanded;
            }
        }
    }
}

// Обработка изменения цвета через color picker
document.addEventListener('DOMContentLoaded', function() {
    var colorPicker = document.getElementById('vip-name-color');
    var colorText = document.getElementById('vip-name-color-text');
    var previewText = document.getElementById('color-preview-text');
    
    if (colorPicker) {
        colorPicker.addEventListener('input', function() {
            if (colorText) {
                colorText.value = this.value;
            }
            if (previewText) {
                previewText.style.color = this.value;
            }
        });
    }
    
    if (colorText) {
        colorText.addEventListener('input', syncColorInputs);
    }
    
    if (colorPicker && previewText) {
        previewText.style.color = colorPicker.value;
    }
    
    var vipServerSelect = document.getElementById('vip-server-id');
    if (vipServerSelect) {
        vipServerSelect.addEventListener('change', updateVipInfo);
    }
});

function resetColor() {
    var colorPicker = document.getElementById('vip-name-color');
    var colorText = document.getElementById('vip-name-color-text');
    var previewText = document.getElementById('color-preview-text');
    
    if (colorText) colorText.value = '';
    if (colorPicker) colorPicker.value = '#000000';
    if (previewText) previewText.style.color = '';
}

function purchaseVip(event) {
    event.preventDefault();
    
    var serverId = document.getElementById('vip-server-id').value;
    var weeks = parseInt(document.getElementById('vip-weeks').value);
    var price = parseInt(document.getElementById('vip-price').value);
    
    if (!serverId) {
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Выберите сервер', 'warning');
        } else {
            alert('Выберите сервер');
        }
        return;
    }
    
    var userBalance = window.userBalance || parseFloat(document.body.getAttribute('data-balance') || 0);
    if (userBalance < price) {
        var need = price - userBalance;
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Недостаточно средств на балансе! Не хватает: ' + need.toFixed(2) + ' ₽', 'error');
            setTimeout(() => window.location.href = '/balance.php', 1500);
        } else {
            if (!confirm('Недостаточно средств на балансе!\n\n' +
                         'Требуется: ' + price + ' ₽\n' +
                         'На балансе: ' + userBalance.toFixed(2) + ' ₽\n' +
                         'Не хватает: ' + need.toFixed(2) + ' ₽\n\n' +
                         'Перейти на страницу пополнения баланса?')) {
                return;
            }
            window.location.href = '/balance.php';
        }
        return;
    }
    
    // Преобразуем недели в читаемый период для подтверждения
    var periodText = '';
    if (weeks === 4) periodText = '1 месяц';
    else if (weeks === 12) periodText = '3 месяца';
    else if (weeks === 24) periodText = '6 месяцев';
    else if (weeks === 48) periodText = '1 год';
    else periodText = weeks + ' ' + (weeks === 1 ? 'неделю' : weeks < 5 ? 'недели' : 'недель');
    
    if (typeof showGlobalMessage === 'function') {
        // Продолжаем без подтверждения
    } else if (!confirm('Вы уверены, что хотите купить VIP статус на ' + periodText + ' за ' + price + ' ₽?\n\n' +
        'Средства будут списаны с вашего баланса.')) {
        return;
    }
    
    var btnText = document.getElementById('vip-purchase-btn-text');
    var btnSpinner = document.getElementById('vip-purchase-btn-spinner');
    
    btnText.style.display = 'none';
    btnSpinner.style.display = 'inline';
    
    var colorTextInput = document.getElementById('vip-name-color-text');
    var colorPickerInput = document.getElementById('vip-name-color');
    
    // Получаем цвет из текстового поля, если заполнено, иначе из color picker
    var nameColor = '';
    if (colorTextInput && colorTextInput.value.trim()) {
        nameColor = colorTextInput.value.trim();
    } else if (colorPickerInput && colorPickerInput.value && colorPickerInput.value !== '#000000') {
        nameColor = colorPickerInput.value;
    }
    
    var bodyParams = 'server_id=' + serverId + '&weeks=' + weeks + '&price=' + price;
    if (nameColor && nameColor !== '#000000') {
        bodyParams += '&name_color=' + encodeURIComponent(nameColor);
    }
    
    console.log('Отправка VIP запроса с цветом:', nameColor);
    console.log('Body параметры:', bodyParams);
    
    fetch('/api/purchase_vip.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: bodyParams
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage('VIP статус успешно приобретен! VIP до: ' + new Date(data.vip_until).toLocaleString('ru-RU'), 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                alert('VIP статус успешно приобретен!\n\n' + 
                      'Сервер: ' + data.server_name + '\n' +
                      'VIP до: ' + new Date(data.vip_until).toLocaleString('ru-RU') + '\n\n' +
                      (data.note || ''));
                window.location.reload();
            }
        } else {
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage('Ошибка: ' + (data.error || 'Не удалось приобрести VIP статус'), 'error');
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось приобрести VIP статус'));
            }
            btnText.style.display = 'inline';
            btnSpinner.style.display = 'none';
        }
    })
    .catch(function(error) {
        console.error('Ошибка:', error);
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Ошибка при обработке запроса. Проверьте подключение к интернету.', 'error');
        } else {
            alert('Ошибка при обработке запроса. Проверьте подключение к интернету.');
        }
        btnText.style.display = 'inline';
        btnSpinner.style.display = 'none';
    });
}

