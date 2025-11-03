let customGameCounter = 0;

function addCustomGame() {
    const input = document.getElementById('custom-game-input');
    const gameName = input.value.trim();
    
    if (!gameName) {
        showGlobalMessage('Введите название игры', 'warning');
        return;
    }
    
    customGameCounter++;
    const customGameId = 'custom_' + customGameCounter;
    
    // Создаем элемент для отображения кастомной игры
    const container = document.getElementById('custom-games-container');
    const gameDiv = document.createElement('div');
    gameDiv.id = 'custom-game-' + customGameId;
    gameDiv.className = 'custom-game-item';
    gameDiv.innerHTML = '<span>' + gameName + '</span>' +
                       '<button type="button" onclick="removeCustomGame(\'' + customGameId + '\')">Удалить</button>';
    container.appendChild(gameDiv);
    
    // Создаем скрытое поле для отправки на сервер
    const hiddenContainer = document.getElementById('custom-games-hidden');
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'custom_games[]';
    hiddenInput.value = gameName;
    hiddenInput.id = 'custom-hidden-' + customGameId;
    hiddenContainer.appendChild(hiddenInput);
    
    input.value = '';
}

function removeCustomGame(customGameId) {
    document.getElementById('custom-game-' + customGameId).remove();
    document.getElementById('custom-hidden-' + customGameId).remove();
}

function clearCustomGames() {
    document.getElementById('custom-games-container').innerHTML = '';
    document.getElementById('custom-games-hidden').innerHTML = '';
    document.getElementById('custom-game-input').value = '';
}

function showAddForm() {
    document.getElementById('modal-title').textContent = 'Добавить хостинг';
    document.getElementById('modal-action').value = 'add';
    document.getElementById('modal-id').value = '';
    document.getElementById('modal-form').reset();
    clearCustomGames();
    document.getElementById('modal').style.display = 'block';
}

function showEditForm(hosting) {
    document.getElementById('modal-title').textContent = 'Редактировать хостинг';
    document.getElementById('modal-action').value = 'edit';
    document.getElementById('modal-id').value = hosting.id;
    document.getElementById('modal-name').value = hosting.name || '';
    document.getElementById('modal-logo').value = hosting.logo || '';
    document.getElementById('modal-website').value = hosting.website_url || '';
    document.getElementById('modal-description').value = hosting.description || '';
    document.getElementById('modal-status').value = hosting.status || 'pending';
    document.getElementById('modal-sort').value = hosting.sort_order || 0;
    
    // Очищаем все чекбоксы
    document.querySelectorAll('input[name="games[]"]').forEach(cb => cb.checked = false);
    
    // Отмечаем выбранные игры
    if (hosting.games && Array.isArray(hosting.games)) {
        hosting.games.forEach(gameId => {
            const cb = document.querySelector('input[name="games[]"][value="' + gameId + '"]');
            if (cb) cb.checked = true;
        });
    }
    
    // Очищаем кастомные игры
    clearCustomGames();
    
    // Восстанавливаем кастомные игры
    if (hosting.custom_games && Array.isArray(hosting.custom_games)) {
        hosting.custom_games.forEach(gameName => {
            customGameCounter++;
            const customGameId = 'custom_' + customGameCounter;
            
            // Создаем элемент для отображения кастомной игры
            const container = document.getElementById('custom-games-container');
            const gameDiv = document.createElement('div');
            gameDiv.id = 'custom-game-' + customGameId;
            gameDiv.className = 'custom-game-item';
            gameDiv.innerHTML = '<span>' + gameName + '</span>' +
                               '<button type="button" onclick="removeCustomGame(\'' + customGameId + '\')">Удалить</button>';
            container.appendChild(gameDiv);
            
            // Создаем скрытое поле для отправки на сервер
            const hiddenContainer = document.getElementById('custom-games-hidden');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'custom_games[]';
            hiddenInput.value = gameName;
            hiddenInput.id = 'custom-hidden-' + customGameId;
            hiddenContainer.appendChild(hiddenInput);
        });
    }
    
    document.getElementById('modal').style.display = 'block';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
    clearCustomGames();
}

// Закрытие модального окна при клике вне его
window.onclick = function(event) {
    const modal = document.getElementById('modal');
    if (event.target === modal) {
        closeModal();
    }
}


