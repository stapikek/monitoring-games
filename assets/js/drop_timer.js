// DROP_TIMER.PHP JavaScript

function updateTimer() {
    // Получаем текущую дату и время
    const now = new Date();
    
    // Создаем форматтер для МСК времени
    const mskFormatter = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Europe/Moscow',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    
    // Получаем текущую дату/время в МСК
    const mskParts = mskFormatter.formatToParts(now);
    
    // Создаем Date объект на основе МСК времени (в локальном времени)
    const mskYear = parseInt(mskParts.find(p => p.type === 'year').value);
    const mskMonth = parseInt(mskParts.find(p => p.type === 'month').value) - 1;
    const mskDay = parseInt(mskParts.find(p => p.type === 'day').value);
    const mskHour = parseInt(mskParts.find(p => p.type === 'hour').value);
    const mskMinute = parseInt(mskParts.find(p => p.type === 'minute').value);
    const mskSecond = parseInt(mskParts.find(p => p.type === 'second').value);
    
    const mskDate = new Date(mskYear, mskMonth, mskDay, mskHour, mskMinute, mskSecond);
    
    // Получаем день недели в МСК (0 = Воскресенье, 3 = Среда)
    const currentDay = mskDate.getDay();
    const currentHour = mskDate.getHours();
    
    // Считаем, сколько дней до следующей среды
    let daysUntilWednesday = 3 - currentDay; // 3 = Среда
    
    // Если уже прошла среда (после 4:00) или не среда, берем следующую среду
    if (currentDay === 3) {
        if (currentHour >= 4) {
            // Уже прошло 4:00 в среду, берем следующую среду
            daysUntilWednesday = 7;
        } else {
            // Еще не наступило 4:00 в среду, берем сегодня
            daysUntilWednesday = 0;
        }
    } else if (currentDay > 3) {
        // Уже прошла среда в этой неделе
        daysUntilWednesday += 7;
    }
    
    // Создаем дату следующего сброса в МСК
    const nextWednesday = new Date(mskDate);
    nextWednesday.setDate(nextWednesday.getDate() + daysUntilWednesday);
    nextWednesday.setHours(4, 0, 0, 0); // 4:00 МСК
    
    // Вычисляем разницу в миллисекундах
    const diff = nextWednesday.getTime() - mskDate.getTime();
    
    if (diff <= 0) {
        // Таймер истек, сбрасываем на следующую среду
        setTimeout(updateTimer, 1000);
        return;
    }
    
    // Вычисляем дни, часы, минуты, секунды
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    // Обновляем отображение
    document.getElementById('days').textContent = days;
    document.getElementById('hours').textContent = hours;
    document.getElementById('minutes').textContent = minutes;
    document.getElementById('seconds').textContent = seconds;
}

// Обновляем таймер каждую секунду
setInterval(updateTimer, 1000);
updateTimer();

