function showSettings(id, name, settings) {
    document.getElementById('modalTitle').textContent = 'Настройки: ' + name;
    document.getElementById('modalSystemId').value = id;
    document.getElementById('modalApiKey').value = settings.api_key || '';
    document.getElementById('modalSecretKey').value = settings.secret_key || '';
    document.getElementById('modalMerchantId').value = settings.merchant_id || '';
    document.getElementById('modalWebhookUrl').value = settings.webhook_url || '';
    document.getElementById('settingsModal').style.display = 'flex';
}

function closeSettings() {
    document.getElementById('settingsModal').style.display = 'none';
}

// Закрытие по клику вне модального окна
document.getElementById('settingsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSettings();
    }
});


