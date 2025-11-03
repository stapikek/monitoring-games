// INDEX.PHP JavaScript

document.addEventListener('DOMContentLoaded', function() {
    const modeBtns = document.querySelectorAll('.mode-btn');
    const tagBtns = document.querySelectorAll('.tag-btn');
    
    // Обработка кликов по режимам
    modeBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const modeId = this.dataset.mode;
            document.getElementById('hidden_mode').value = modeId;
            
            // Обновляем активный класс
            modeBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Автоматически применяем фильтр
            document.getElementById('filterForm').submit();
        });
    });
    
    // Обработка кликов по тегам
    tagBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tagId = this.dataset.tag;
            document.getElementById('hidden_tag').value = tagId;
            
            // Обновляем активный класс
            tagBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Автоматически применяем фильтр
            document.getElementById('filterForm').submit();
        });
    });
    
    // Оптимизированная обработка прокрутки для filter-modes
    const filterModes = document.querySelector('.filter-modes');
    if (filterModes) {
        let isDown = false;
        let startX;
        let scrollLeft;
        let rafId;
        
        // Обработка нажатия мыши
        filterModes.addEventListener('mousedown', function(e) {
            // Не запускать драг на клике по кнопке
            if (e.target.closest('.mode-btn')) return;
            
            isDown = true;
            startX = e.pageX;
            scrollLeft = filterModes.scrollLeft;
            this.style.cursor = 'grabbing';
            this.style.userSelect = 'none';
        });
        
        // Обработка отпускания мыши
        const handleMouseUp = function() {
            isDown = false;
            filterModes.style.cursor = 'default';
            filterModes.style.userSelect = '';
            if (rafId) {
                cancelAnimationFrame(rafId);
            }
        };
        
        filterModes.addEventListener('mouseleave', handleMouseUp);
        filterModes.addEventListener('mouseup', handleMouseUp);
        
        // Оптимизированная обработка движения мыши с requestAnimationFrame
        filterModes.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            
            if (rafId) {
                cancelAnimationFrame(rafId);
            }
            
            rafId = requestAnimationFrame(function() {
                const x = e.pageX;
                const walk = (x - startX) * 1.5; // Оптимизированная скорость
                filterModes.scrollLeft = scrollLeft - walk;
            });
        });
        
        // Оптимизированная обработка колесика
        filterModes.addEventListener('wheel', function(e) {
            e.preventDefault();
            
            // Поддержка разных режимов прокрутки
            let delta = e.deltaY;
            if (e.deltaMode === 1) {
                delta *= 40; // Строки
            } else if (e.deltaMode === 2) {
                delta *= 120; // Страницы
            }
            
            filterModes.scrollLeft += delta * 0.5; // Плавная прокрутка
        }, { passive: false });
    }
    
    // Обработка слайдера - автоприменение с задержкой
    const slider = document.getElementById('min_players');
    let sliderTimeout;
    
    slider.addEventListener('input', function() {
        // Обновляем отображаемое значение
        document.querySelector('.slider-value').textContent = this.value;
        
        // Очищаем предыдущий таймер
        clearTimeout(sliderTimeout);
        
        // Устанавливаем новый таймер (автоприменение через 500ms после остановки)
        sliderTimeout = setTimeout(function() {
            document.getElementById('filterForm').submit();
        }, 500);
    });
    
    // Обработка поиска - автоприменение с задержкой
    const searchInput = document.querySelector('.filter-search input');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // Очищаем предыдущий таймер
            clearTimeout(searchTimeout);
            
            // Устанавливаем новый таймер (автоприменение через 800ms после остановки ввода)
            searchTimeout = setTimeout(function() {
                document.getElementById('filterForm').submit();
            }, 800);
        });
        
        // Применяем сразу при нажатии Enter
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout);
                document.getElementById('filterForm').submit();
            }
        });
    }
});

