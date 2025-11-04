<?php
// api/payment_gateways/stripe_webhook.php

class StripeWebhook {
    public static function checkSessionStatus($db, $paymentDbId, $sessionId, $settings) {
        $secret_key = $settings['secret_key'] ?? '';
        
        if (empty($secret_key)) {
            return false;
        }
        
        // Проверяем статус сессии через Stripe API
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . $sessionId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            
            if (isset($result['payment_status']) && $result['payment_status'] == 'paid') {
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
}
