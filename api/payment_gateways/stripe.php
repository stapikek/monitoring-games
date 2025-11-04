<?php
// api/payment_gateways/stripe.php

class StripeGateway {
    public static function createPayment($db, $paymentDbId, $paymentId, $amount, $settings) {
        $secret_key = $settings['secret_key'] ?? '';
        
        if (empty($secret_key)) {
            return '/payment.php?id=' . $paymentDbId;
        }
        
        $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_success.php?payment_id=' . $paymentDbId;
        
        // Конвертируем рубли в центы (Stripe работает с центами)
        // Предполагаем курс 1 RUB = 0.011 USD (примерно)
        $amount_usd = round($amount * 0.011 * 100); // в центах
        
        // Создаем Checkout Session через Stripe API
        $data = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Пополнение баланса',
                        'description' => 'Платеж #' . $paymentId
                    ],
                    'unit_amount' => $amount_usd
                ],
                'quantity' => 1
            ]],
            'mode' => 'payment',
            'success_url' => $return_url . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment.php?id=' . $paymentDbId,
            'metadata' => [
                'payment_id' => $paymentId,
                'payment_db_id' => $paymentDbId
            ]
        ];
        
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secret_key,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("Stripe cURL error: " . $curl_error);
            return '/payment.php?id=' . $paymentDbId . '&error=connection';
        }
        
        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['url'])) {
                // Сохраняем ID сессии Stripe
                $metadata = json_encode([
                    'stripe_session_id' => $result['id'],
                    'amount_usd' => $amount_usd
                ]);
                
                $stmt = $db->prepare("UPDATE payments SET metadata = :metadata WHERE id = :id");
                $stmt->bindParam(':metadata', $metadata);
                $stmt->bindParam(':id', $paymentDbId);
                $stmt->execute();
                
                return $result['url'];
            }
        }
        
        // Логируем ошибку для отладки
        error_log("Stripe API error (HTTP $http_code): " . $response);
        
        // Если это ошибка авторизации, значит ключ неверный
        if ($http_code == 401) {
            error_log("Stripe: Invalid API key");
        }
        
        return '/payment.php?id=' . $paymentDbId . '&error=api_error';
    }
}
