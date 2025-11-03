    </div>
    </main>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-left">
                    <h3><?php echo htmlspecialchars($site_logo_text ?? 'CS2 Мониторинг'); ?></h3>
                    <p>Найдите лучшие серверы Counter-Strike 2</p>
                </div>
                <div class="footer-center">
                    <div class="footer-links">
                        <a href="/">Главная</a>
                        <a href="/projects.php">Проекты</a>
                        <a href="/hostings.php">Хостинги</a>
                        <?php if (!$auth->isLoggedIn()): ?>
                            <a href="/login.php">Вход</a>
                        <?php else: ?>
                            <a href="/profile.php">Профиль</a>
                            <a href="/shop.php">Магазин</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="footer-right">
                    <p>&copy; <?php echo date('Y'); ?> Все права защищены</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Модальное окно для уведомлений -->
    <div id="globalMessageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border-radius: 12px; padding: 2rem; max-width: 400px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.3); border: 1px solid var(--border-color);">
            <h3 style="margin: 0 0 1rem 0; color: var(--text-primary); font-size: 1.25rem;">Сообщение</h3>
            <p id="globalMessageModalText" style="margin: 0 0 1.5rem 0; color: var(--text-secondary);"></p>
            <div style="display: flex; justify-content: flex-end;">
                <button onclick="closeGlobalMessageModal()" id="globalMessageModalBtn" style="padding: 0.75rem 1.5rem; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    OK
                </button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для подтверждений -->
    <div id="globalConfirmModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border-radius: 12px; padding: 2rem; max-width: 400px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.3); border: 1px solid var(--border-color);">
            <h3 style="margin: 0 0 1rem 0; color: var(--text-primary); font-size: 1.25rem;">Подтверждение</h3>
            <p id="globalConfirmModalText" style="margin: 0 0 1.5rem 0; color: var(--text-secondary);"></p>
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button id="globalConfirmModalNoBtn" style="padding: 0.75rem 1.5rem; color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; font-weight: 600; background: var(--bg-tertiary);">
                    Отмена
                </button>
                <button id="globalConfirmModalYesBtn" style="padding: 0.75rem 1.5rem; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; background: #ef4444;">
                    Да
                </button>
            </div>
        </div>
    </div>
    
    <script>
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
    
    // Переопределяем alert для всех страниц
    const originalAlert = window.alert;
    window.alert = function(message) {
        showGlobalMessage(message, 'info');
    };
    
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
    
    // Переопределяем confirm для всех страниц
    const originalConfirm = window.confirm;
    window.confirm = function(message) {
        let result = false;
        const callback = function(value) {
            result = value;
        };
        showGlobalConfirm(message, callback);
        // Ждем пока пользователь выберет
        return result;
    };
    
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
    </script>
    
    <script src="/assets/js/common.js"></script>
    
    <?php 
    // Подключаем дополнительные JS файлы если они указаны
    if (isset($additional_js)) {
        foreach ($additional_js as $js_file) {
            echo '<script src="' . htmlspecialchars($js_file) . '"></script>' . "\n    ";
        }
    }
    ?>
</body>
</html>


