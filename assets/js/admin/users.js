// Обработка форм удаления
document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = this.getAttribute('data-message') || 'Вы уверены?';
        showGlobalConfirm(message, function(result) {
            if (result) {
                form.submit();
            }
        });
    });
});


