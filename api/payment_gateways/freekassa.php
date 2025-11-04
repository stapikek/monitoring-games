<?php
// api/payment_gateways/freekassa.php

class FreeKassaGateway {
    public static function createPayment($db, $paymentDbId, $paymentId, $amount, $settings) {
        $merchant_id = $settings['merchant_id'] ?? '';
        $secret_key = $settings['secret_key'] ?? '';
        $shop_id = $settings['shop_id'] ?? $merchant_id;
        
        if (empty($merchant_id) || empty($secret_key)) {
            // Возвращаем страницу оплаты, если настройки не заполнены
            return '/payment.php?id=' . $paymentDbId;
        }
        
        // Формируем подпись для FreeKassa
        $sign = md5($merchant_id . ':' . $amount . ':' . $secret_key . ':' . $paymentId);
        
        // URL для редиректа на FreeKassa
        $success_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_success.php?payment_id=' . $paymentDbId;
        $fail_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment.php?id=' . $paymentDbId . '&status=failed';
        $notification_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/payment_gateways/freekassa_webhook.php';
        
        // Сохраняем данные для вебхука
        $metadata = json_encode([
            'merchant_id' => $merchant_id,
            'shop_id' => $shop_id,
            'sign' => $sign
        ]);
        
        $stmt = $db->prepare("UPDATE payments SET metadata = :metadata WHERE id = :id");
        $stmt->bindParam(':metadata', $metadata);
        $stmt->bindParam(':id', $paymentDbId);
        $stmt->execute();
        
        // Формируем URL для оплаты
        $payment_url = 'https://pay.freekassa.ru/?m=' . $merchant_id . 
                      '&oa=' . $amount . 
                      '&o=' . $paymentId . 
                      '&s=' . $sign . 
                      '&us_payment_id=' . $paymentDbId;
        
        return $payment_url;
    }
}
