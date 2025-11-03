// HOSTING.PHP JavaScript

// Обработка звезд рейтинга
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('#rating-stars .star');
    const ratingInput = document.getElementById('rating-input');
    
    // Инициализация цвета звёзд при загрузке
    const currentRating = parseInt(ratingInput.value) || 1;
    stars.forEach((s, index) => {
        if (index < currentRating) {
            s.style.color = '#ffc107';
        } else {
            s.style.color = '#ddd';
        }
    });
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingInput.value = rating;
            
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.style.color = '#ffc107';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });
    });
    
    document.getElementById('rating-stars').addEventListener('mouseleave', function() {
        const currentRating = ratingInput.value;
        stars.forEach((s, index) => {
            if (index < currentRating) {
                s.style.color = '#ffc107';
            } else {
                s.style.color = '#ddd';
            }
        });
    });
    
    // Обработка отправки формы отзыва
    const reviewForm = document.getElementById('review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/api/add_hosting_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof showGlobalMessage === 'function') {
                        showGlobalMessage('Отзыв успешно добавлен!', 'success');
                    } else {
                        alert('Отзыв успешно добавлен!');
                    }
                    setTimeout(() => location.reload(), 1500);
                } else {
                    if (typeof showGlobalMessage === 'function') {
                        showGlobalMessage('Ошибка: ' + data.error, 'error');
                    } else {
                        alert('Ошибка: ' + data.error);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof showGlobalMessage === 'function') {
                    showGlobalMessage('Произошла ошибка при отправке отзыва', 'error');
                } else {
                    alert('Произошла ошибка при отправке отзыва');
                }
            });
        });
    }
    
    // Обработка модального окна подтверждения удаления отзыва
    let pendingDeleteId = null;
    
    window.showDeleteConfirm = function(reviewId) {
        pendingDeleteId = reviewId;
        document.getElementById('deleteConfirmModal').style.display = 'flex';
    };
    
    window.closeDeleteConfirm = function() {
        document.getElementById('deleteConfirmModal').style.display = 'none';
        pendingDeleteId = null;
    };
    
    window.confirmDelete = function() {
        if (pendingDeleteId) {
            document.getElementById('deleteReviewId').value = pendingDeleteId;
            document.getElementById('deleteReviewForm').submit();
        }
    };
    
    // Закрытие модального окна при клике вне его
    window.onclick = function(event) {
        const modal = document.getElementById('deleteConfirmModal');
        if (event.target === modal) {
            window.closeDeleteConfirm();
        }
    };
    
    // Закрытие по Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            window.closeDeleteConfirm();
        }
    });
});

