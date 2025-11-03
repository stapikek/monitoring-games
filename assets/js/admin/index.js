// Обработка форм очистки кеша
document.querySelectorAll('.clear-cache-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        showGlobalConfirm('Вы уверены, что хотите очистить весь кеш сайта?', function(confirmed) {
            if (confirmed) {
                form.submit();
            }
        });
    });
});


