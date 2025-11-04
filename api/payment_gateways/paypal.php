<?php
// api/payment_gateways/paypal.php

class PayPalGateway {
    public static function createPayment($db, $paymentDbId, $paymentId, $amount, $settings) {
        $client_id = $settings['client_id'] ?? '';
        $client_secret = $settings['client_secret'] ?? '';
        $mode = $settings['mode'] ?? 'sandbox';
        
        if (empty($client_id) || empty($client_secret)) {
            return '/payment.php?id=' . $paymentDbId;
        }
        
        $api_url = $mode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        // Получаем access token
        $token = self::getAccessToken($api_url, $client_id, $client_secret);
        if (!$token) {
            error_log("PayPal: Failed to get access token");
            return '/payment.php?id=' . $paymentDbId;
        }
        
        $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_success.php?payment_id=' . $paymentDbId;
        $cancel_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment.php?id=' . $paymentDbId;
        
        // Конвертируем в USD (примерно)
        $amount_usd = round($amount * 0.011, 2);
        
        // Создаем заказ через PayPal Orders API
        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($amount_usd, 2, '.', '')
                ],
                'description' => 'Пополнение баланса. Платеж #' . $paymentId,
                'custom_id' => $paymentId
            ]],
            'application_context' => [
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
                'brand_name' => 'CS2 Мониторинг'
            ]
        ];
        
        $ch = curl_init($api_url . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300 && $response) {
            $result = json_decode($response, true);
            if (isset($result['id']) && isset($result['links'])) {
                foreach ($result['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        // Сохраняем ID заказа PayPal
                        $metadata = json_encode([
                            'paypal_order_id' => $result['id'],
                            'amount_usd' => $amount_usd,
                            'status' => $result['status']
                        ]);
                        
                        $stmt = $db->prepare("UPDATE payments SET metadata = :metadata WHERE id = :id");
                        $stmt->bindParam(':metadata', $metadata);
                        $stmt->bindParam(':id', $paymentDbId);
                        $stmt->execute();
                        
                        return $link['href'];
                    }
                }
            }
        }
        
        error_log("PayPal API error: " . $response);
        return '/payment.php?id=' . $paymentDbId;
    }
    
    private static function getAccessToken($api_url, $client_id, $client_secret) {
        $ch = curl_init($api_url . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            return $result['access_token'] ?? null;
        }
        
        return null;
    }
}
