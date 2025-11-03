// ADD_SERVER.PHP JavaScript

document.addEventListener('DOMContentLoaded', function() {
    const ipInput = document.getElementById('ip');
    const portInput = document.getElementById('port');
    
    ipInput.addEventListener('blur', function() {
        const value = this.value.trim();
        
        // Проверяем формат IP:PORT
        if (value.includes(':')) {
            const parts = value.split(':');
            const ip = parts[0].trim();
            const port = parts[1].trim();
            
            // Проверяем валидность IP и порта
            const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
            const portRegex = /^\d+$/;
            
            if (ipRegex.test(ip) && portRegex.test(port)) {
                // Проверяем диапазон порта
                const portNum = parseInt(port);
                if (portNum >= 1 && portNum <= 65535) {
                    ipInput.value = ip;
                    portInput.value = port;
                }
            }
        }
    });
});

