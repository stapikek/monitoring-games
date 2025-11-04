<?php
// api/payment_gateways/yookassa_webhook.php

class YooKassaWebhook {
    public static function checkPaymentStatus($db, $paymentDbId, $settings) {
        $shop_id = $settings['shop_id'] ?? '';
        $secret_key = $settings['secret_key'] ?? '';
        
        if (empty($shop_id) || empty($secret_key)) {
            return false;
        }
        
        // Получаем ID платежа ЮKassa из metadata
        $stmt = $db->prepare("SELECT metadata FROM payments WHERE id = :id");
        $stmt->bindParam(':id', $paymentDbId);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment || empty($payment['metadata'])) {
            return false;
        }
        
        $metadata = json_decode($payment['metadata'], true);
        $yookassa_payment_id = $metadata['yookassa_payment_id'] ?? null;
        
        if (!$yookassa_payment_id) {
            return false;
        }
        
        // Проверяем статус платежа через API ЮKassa
        $ch = curl_init('https://api.yookassa.ru/v3/payments/' . $yookassa_payment_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $shop_id . ':' . $secret_key);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $response) {
            $result = json_decode($response, true);
            
            if (isset($result['status']) && $result['status'] == 'succeeded') {
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
    
    public static function handleWebhook($db) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['event']) || $data['event'] !== 'payment.succeeded') {
            return;
        }
        
        $payment_id = $data['object']['id'] ?? null;
        if (!$payment_id) {
            return;
        }
        
        // Ищем платеж по ID ЮKassa
        $stmt = $db->prepare("SELECT id, user_id, amount, status FROM payments WHERE metadata LIKE :search");
        $search = '%"yookassa_payment_id":"' . $payment_id . '"%';
        $stmt->bindParam(':search', $search);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment || $payment['status'] == 'completed') {
            return;
        }
        
        // Зачисляем средства
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $payment['id']);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE users SET balance = balance + :amount WHERE id = :user_id");
        $stmt->bindParam(':amount', $payment['amount']);
        $stmt->bindParam(':user_id', $payment['user_id']);
        $stmt->execute();
        
        $db->commit();
    }
}
