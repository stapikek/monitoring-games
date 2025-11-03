// Глобальные функции для показа уведомлений вместо alert
function showGlobalMessage(message, type = 'info') {
    const modal = document.getElementById('globalMessageModal');
    const messageText = document.getElementById('globalMessageModalText');
    const btn = document.getElementById('globalMessageModalBtn');
    
    messageText.textContent = message;
    
    if (type === 'success') {
        btn.style.background = '#10b981';
    } else if (type === 'error') {
        btn.style.background = '#ef4444';
    } else if (type === 'warning') {
        btn.style.background = '#f59e0b';
    } else {
        btn.style.background = '#667eea';
    }
    
    btn.textContent = 'OK';
    modal.style.display = 'flex';
}

function closeGlobalMessageModal() {
    document.getElementById('globalMessageModal').style.display = 'none';
}

// Глобальная функция для confirm
function showGlobalConfirm(message, callback) {
    const modal = document.getElementById('globalConfirmModal');
    const messageText = document.getElementById('globalConfirmModalText');
    const modalYesBtn = document.getElementById('globalConfirmModalYesBtn');
    const modalNoBtn = document.getElementById('globalConfirmModalNoBtn');
    
    messageText.textContent = message;
    modal.style.display = 'flex';
    
    // Удаляем старые обработчики
    const newYesBtn = modalYesBtn.cloneNode(true);
    const newNoBtn = modalNoBtn.cloneNode(true);
    modalYesBtn.parentNode.replaceChild(newYesBtn, modalYesBtn);
    modalNoBtn.parentNode.replaceChild(newNoBtn, modalNoBtn);
    
    // Добавляем новые обработчики
    newYesBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        if (callback) callback(true);
    });
    
    newNoBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        if (callback) callback(false);
    });
}

// Закрытие модального окна при клике вне его
window.addEventListener('click', function(event) {
    const modal = document.getElementById('globalMessageModal');
    const confirmModal = document.getElementById('globalConfirmModal');
    if (event.target === modal) {
        closeGlobalMessageModal();
    }
    if (event.target === confirmModal) {
        confirmModal.style.display = 'none';
    }
});

// Закрытие по Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeGlobalMessageModal();
        document.getElementById('globalConfirmModal').style.display = 'none';
    }
});

// Mobile menu toggle для админки
const adminMobileToggle = document.getElementById('admin-mobile-toggle');
const adminSidebar = document.getElementById('admin-sidebar');

if (adminMobileToggle && adminSidebar) {
    adminMobileToggle.addEventListener('click', function() {
        this.classList.toggle('active');
        adminSidebar.classList.toggle('active');
    });
    
    // Закрыть меню при клике вне его
    document.addEventListener('click', function(e) {
        if (!adminMobileToggle.contains(e.target) && !adminSidebar.contains(e.target)) {
            adminMobileToggle.classList.remove('active');
            adminSidebar.classList.remove('active');
        }
    });
    
    // Закрыть меню при клике на ссылку
    adminSidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            adminMobileToggle.classList.remove('active');
            adminSidebar.classList.remove('active');
        });
    });
}


