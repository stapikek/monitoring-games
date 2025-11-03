            </div>
        </main>
    </div>
    
    <!-- Модальное окно для уведомлений -->
    <div id="globalMessageModal">
        <div>
            <h3>Сообщение</h3>
            <p id="globalMessageModalText"></p>
            <div class="modal-actions">
                <button onclick="closeGlobalMessageModal()" id="globalMessageModalBtn">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для подтверждений -->
    <div id="globalConfirmModal">
        <div>
            <h3>Подтверждение</h3>
            <p id="globalConfirmModalText"></p>
            <div class="modal-actions">
                <button id="globalConfirmModalNoBtn">Отмена</button>
                <button id="globalConfirmModalYesBtn">Да</button>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/admin.js"></script>
    
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

