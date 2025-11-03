function editMap(id, name, code, image) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editCode').value = code;
    var editImage = document.getElementById('editImage');
    if (editImage) editImage.value = image || '';
    
    // Показываем превью если есть изображение
    var imagePreview = document.getElementById('imagePreview');
    var previewImg = document.getElementById('previewImg');
    
    if (image) {
        previewImg.src = image;
        imagePreview.style.display = 'block';
    } else {
        imagePreview.style.display = 'none';
    }
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});

// Обновление превью при изменении URL
document.getElementById('editImage').addEventListener('input', function() {
    var imagePreview = document.getElementById('imagePreview');
    var previewImg = document.getElementById('previewImg');
    
    if (this.value) {
        previewImg.src = this.value;
        imagePreview.style.display = 'block';
        
        // Обработка ошибки загрузки изображения
        previewImg.onerror = function() {
            imagePreview.style.display = 'none';
        };
    } else {
        imagePreview.style.display = 'none';
    }
});

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


