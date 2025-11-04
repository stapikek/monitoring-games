<?php
// api/payment_gateways/yookassa.php

class YooKassaGateway {
    public static function createPayment($db, $paymentDbId, $paymentId, $amount, $settings) {
        $shop_id = $settings['shop_id'] ?? '';
        $secret_key = $settings['secret_key'] ?? '';
        
        if (empty($shop_id) || empty($secret_key)) {
            return '/payment.php?id=' . $paymentDbId;
        }
        
        $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_success.php?payment_id=' . $paymentDbId;
        
        // Создаем платеж через API ЮKassa
        $data = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'RUB'
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $return_url
            ],
            'capture' => true,
            'description' => 'Пополнение баланса. Платеж #' . $paymentId,
            'metadata' => [
                'payment_id' => $paymentId,
                'payment_db_id' => $paymentDbId
            ]
        ];
        
        $ch = curl_init('https://api.yookassa.ru/v3/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $shop_id . ':' . $secret_key);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotence-Key: ' . $paymentId
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['confirmation']['confirmation_url'])) {
                // Сохраняем ID платежа ЮKassa
                $metadata = json_encode([
                    'yookassa_payment_id' => $result['id'],
                    'status' => $result['status']
                ]);
                
                $stmt = $db->prepare("UPDATE payments SET metadata = :metadata, payment_id = :yookassa_id WHERE id = :id");
                $stmt->bindParam(':metadata', $metadata);
                $stmt->bindParam(':yookassa_id', $result['id']);
                $stmt->bindParam(':id', $paymentDbId);
                $stmt->execute();
                
                return $result['confirmation']['confirmation_url'];
            }
        }
        
        // Если ошибка, возвращаем страницу оплаты
        error_log("YooKassa API error: " . $response);
        return '/payment.php?id=' . $paymentDbId;
    }
}
