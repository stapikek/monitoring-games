function showSettings(id, name, type, settings) {
    document.getElementById('modalTitle').textContent = 'Настройки: ' + name;
    document.getElementById('modalSystemId').value = id;
    
    const fieldsContainer = document.getElementById('settingsFields');
    fieldsContainer.innerHTML = '';
    
    // Определяем поля в зависимости от типа платежной системы
    const fieldDefinitions = getFieldsForType(type);
    
    fieldDefinitions.forEach(field => {
        const group = document.createElement('div');
        group.className = 'modal-form-group';
        
        const label = document.createElement('label');
        label.textContent = field.label + (field.required ? ' *' : '') + ':';
        
        let input;
        if (field.type === 'select') {
            input = document.createElement('select');
            input.name = field.name;
            input.id = 'modal' + field.name.charAt(0).toUpperCase() + field.name.slice(1);
            
            if (field.options) {
                field.options.forEach(option => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option.value;
                    optionElement.textContent = option.label;
                    input.appendChild(optionElement);
                });
            }
        } else {
            input = document.createElement('input');
            input.type = field.type || 'text';
            input.name = field.name;
            input.id = 'modal' + field.name.charAt(0).toUpperCase() + field.name.slice(1);
            
            if (field.placeholder) {
                input.placeholder = field.placeholder;
            }
        }
        
        // Заполняем значение из settings
        if (settings && settings[field.name] !== undefined) {
            input.value = settings[field.name];
        }
        
        group.appendChild(label);
        group.appendChild(input);
        
        if (field.help) {
            const help = document.createElement('small');
            help.className = 'form-help';
            help.textContent = field.help;
            group.appendChild(help);
        }
        
        fieldsContainer.appendChild(group);
    });
    
    document.getElementById('settingsModal').style.display = 'flex';
}

function getFieldsForType(type) {
    const fields = {
        'freekassa': [
            {
                name: 'merchant_id',
                label: 'Merchant ID',
                type: 'text',
                required: true,
                help: 'ID вашего магазина в FreeKassa'
            },
            {
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                required: true,
                help: 'Секретный ключ для подписи запросов'
            },
            {
                name: 'secret_key2',
                label: 'Secret Key 2',
                type: 'password',
                required: true,
                help: 'Второй секретный ключ для IPN уведомлений'
            },
            {
                name: 'shop_id',
                label: 'Shop ID',
                type: 'text',
                required: true,
                help: 'ID магазина (обычно совпадает с Merchant ID)'
            }
        ],
        'yookassa': [
            {
                name: 'shop_id',
                label: 'Shop ID',
                type: 'text',
                required: true,
                help: 'ID магазина в ЮKassa'
            },
            {
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                required: true,
                help: 'Секретный ключ из личного кабинета ЮKassa'
            },
            {
                name: 'webhook_url',
                label: 'Webhook URL',
                type: 'text',
                required: false,
                placeholder: 'https://domen.pw/api/payment/yookassa/webhook.php',
                help: 'URL для уведомлений о платежах (укажите в настройках ЮKassa)'
            }
        ],
        'stripe': [
            {
                name: 'publishable_key',
                label: 'Publishable Key',
                type: 'text',
                required: true,
                help: 'Публичный ключ из панели Stripe (начинается с pk_)'
            },
            {
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                required: true,
                help: 'Секретный ключ из панели Stripe (начинается с sk_)'
            },
            {
                name: 'webhook_secret',
                label: 'Webhook Secret',
                type: 'password',
                required: false,
                help: 'Секрет вебхука для проверки подлинности уведомлений'
            }
        ],
        'paypal': [
            {
                name: 'client_id',
                label: 'Client ID',
                type: 'text',
                required: true,
                help: 'Client ID из панели разработчика PayPal'
            },
            {
                name: 'client_secret',
                label: 'Client Secret',
                type: 'password',
                required: true,
                help: 'Client Secret из панели разработчика PayPal'
            },
            {
                name: 'mode',
                label: 'Режим работы',
                type: 'select',
                required: true,
                options: [
                    { value: 'sandbox', label: 'Sandbox (тестовый)' },
                    { value: 'live', label: 'Live (боевой)' }
                ],
                help: 'Sandbox - для тестирования, Live - для реальных платежей'
            }
        ],
        'crypto': [
            {
                name: 'wallet_address',
                label: 'Адрес кошелька',
                type: 'text',
                required: true,
                help: 'Адрес криптовалютного кошелька для приема платежей'
            },
            {
                name: 'network',
                label: 'Сеть',
                type: 'select',
                required: true,
                options: [
                    { value: 'bitcoin', label: 'Bitcoin' },
                    { value: 'ethereum', label: 'Ethereum' },
                    { value: 'usdt', label: 'USDT (TRC20)' },
                    { value: 'usdt_erc20', label: 'USDT (ERC20)' },
                    { value: 'litecoin', label: 'Litecoin' }
                ],
                help: 'Выберите криптовалютную сеть'
            },
            {
                name: 'api_key',
                label: 'API Key (опционально)',
                type: 'text',
                required: false,
                help: 'API ключ для интеграции с сервисом отслеживания транзакций (если используется)'
            }
        ],
        'bank_transfer': [
            {
                name: 'account_number',
                label: 'Номер счета',
                type: 'text',
                required: true,
                help: 'Расчетный счет организации'
            },
            {
                name: 'bank_name',
                label: 'Название банка',
                type: 'text',
                required: true,
                help: 'Полное название банка'
            },
            {
                name: 'bik',
                label: 'БИК',
                type: 'text',
                required: true,
                help: 'Банковский идентификационный код'
            },
            {
                name: 'inn',
                label: 'ИНН',
                type: 'text',
                required: true,
                help: 'Идентификационный номер налогоплательщика'
            },
            {
                name: 'recipient_name',
                label: 'Получатель',
                type: 'text',
                required: true,
                help: 'Наименование организации-получателя'
            }
        ]
    };
    
    return fields[type] || [
        {
            name: 'api_key',
            label: 'API Key',
            type: 'text',
            required: false
        },
        {
            name: 'secret_key',
            label: 'Secret Key',
            type: 'password',
            required: false
        },
        {
            name: 'merchant_id',
            label: 'Merchant ID',
            type: 'text',
            required: false
        },
        {
            name: 'webhook_url',
            label: 'Webhook URL',
            type: 'text',
            required: false,
            placeholder: 'https://domen.pw/api/payment/webhook/'
        }
    ];
}

function closeSettings() {
    document.getElementById('settingsModal').style.display = 'none';
    document.getElementById('settingsForm').reset();
}

// Закрытие по клику вне модального окна
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('settingsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSettings();
            }
        });
    }
});


