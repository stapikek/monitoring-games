<?php
// api/payment_gateways/paypal_webhook.php

class PayPalWebhook {
    public static function captureOrder($db, $paymentDbId, $token, $settings) {
        $client_id = $settings['client_id'] ?? '';
        $client_secret = $settings['client_secret'] ?? '';
        $mode = $settings['mode'] ?? 'sandbox';
        
        if (empty($client_id) || empty($client_secret)) {
            return false;
        }
        
        $api_url = $mode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        // Получаем access token
        $access_token = self::getAccessToken($api_url, $client_id, $client_secret);
        if (!$access_token) {
            return false;
        }
        
        // Получаем ID заказа из metadata
        $stmt = $db->prepare("SELECT metadata FROM payments WHERE id = :id");
        $stmt->bindParam(':id', $paymentDbId);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment || empty($payment['metadata'])) {
            return false;
        }
        
        $metadata = json_decode($payment['metadata'], true);
        $order_id = $metadata['paypal_order_id'] ?? null;
        
        if (!$order_id) {
            return false;
        }
        
        // Захватываем платеж
        $ch = curl_init($api_url . '/v2/checkout/orders/' . $order_id . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300 && $response) {
            $result = json_decode($response, true);
            
            if (isset($result['status']) && $result['status'] == 'COMPLETED') {
                // Зачисляем средства на баланс
                $stmt = $db->prepare("SELECT user_id, amount FROM payments WHERE id = :id");
                $stmt->bindParam(':id', $paymentDbId);
                $stmt->execute();
                $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($paymentData) {
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':id', $paymentDbId);
                    $stmt->execute();
                    
                    $stmt = $db->prepare("UPDATE users SET balance = balance + :amount WHERE id = :user_id");
                    $stmt->bindParam(':amount', $paymentData['amount']);
                    $stmt->bindParam(':user_id', $paymentData['user_id']);
                    $stmt->execute();
                    
                    $db->commit();
                    return true;
                }
            }
        }
        
        return false;
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
