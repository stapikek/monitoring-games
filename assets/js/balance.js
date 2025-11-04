// balance.php - специфичный JavaScript для страницы пополнения баланса
function setAmount(amount) {
    document.getElementById('amount').value = amount;
}

// Показываем описание при выборе платежной системы
document.addEventListener('DOMContentLoaded', function() {
    var paymentSelect = document.getElementById('payment_system');
    if (paymentSelect) {
        paymentSelect.addEventListener('change', function() {
            // Скрываем все описания
            document.querySelectorAll('[id^="desc-"]').forEach(function(el) {
                el.style.display = 'none';
            });
            
            // Показываем описание выбранной системы
            var desc = document.getElementById('desc-' + this.value);
            if (desc) {
                desc.style.display = 'block';
            }
        });
        
        // Показываем описание при загрузке
        paymentSelect.dispatchEvent(new Event('change'));
    }
});

function addBalance(event) {
    event.preventDefault();
    
    var amount = parseFloat(document.getElementById('amount').value);
    var paymentSystemId = document.getElementById('payment_system').value;
    
    if (!amount || amount < 1) {
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Минимальная сумма пополнения: 1 ₽', 'warning');
        } else {
            alert('Минимальная сумма пополнения: 1 ₽');
        }
        return;
    }
    
    if (amount > 100000) {
        if (typeof showGlobalMessage === 'function') {
            showGlobalMessage('Максимальная сумма пополнения: 100,000 ₽', 'warning');
        } else {
            alert('Максимальная сумма пополнения: 100,000 ₽');
        }
        return;
    }
    
    if (typeof showGlobalMessage !== 'function') {
        if (!confirm('Пополнить баланс на ' + amount.toFixed(2) + ' ₽?')) {
            return;
        }
    }
    
    var btnText = document.getElementById('balance-btn-text');
    var btnSpinner = document.getElementById('balance-btn-spinner');
    
    btnText.style.display = 'none';
    btnSpinner.style.display = 'inline';
    
    fetch('/api/create_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'amount=' + amount + '&payment_system_id=' + paymentSystemId
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            if (data.payment_url) {
                // Перенаправляем на страницу оплаты
                window.location.href = data.payment_url;
            } else {
                if (typeof showGlobalMessage === 'function') {
                    showGlobalMessage('Платеж создан, но URL оплаты не получен. Проверьте настройки платежной системы.', 'error');
                } else {
                    alert('Платеж создан, но URL оплаты не получен. Проверьте настройки платежной системы.');
                }
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
            }
        } else {
            if (typeof showGlobalMessage === 'function') {
                showGlobalMessage('Ошибка: ' + (data.error || 'Не удалось создать платеж'), 'error');
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось создать платеж'));
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

