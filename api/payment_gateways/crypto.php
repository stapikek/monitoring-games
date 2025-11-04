<?php
// api/payment_gateways/crypto.php

class CryptoGateway {
    public static function createPayment($db, $paymentDbId, $paymentId, $amount, $settings) {
        $wallet_address = $settings['wallet_address'] ?? '';
        $network = $settings['network'] ?? 'bitcoin';
        
        if (empty($wallet_address)) {
            return '/payment.php?id=' . $paymentDbId;
        }
        
        // Сохраняем данные о криптовалютном платеже
        $metadata = json_encode([
            'wallet_address' => $wallet_address,
            'network' => $network,
            'amount_crypto' => null, // Можно добавить конвертацию
            'instructions' => 'Отправьте ' . number_format($amount, 2) . ' RUB эквивалента в ' . strtoupper($network) . ' на адрес: ' . $wallet_address
        ]);
        
        $stmt = $db->prepare("UPDATE payments SET metadata = :metadata, status = 'processing' WHERE id = :id");
        $stmt->bindParam(':metadata', $metadata);
        $stmt->bindParam(':id', $paymentDbId);
        $stmt->execute();
        
        // Перенаправляем на страницу с инструкциями
        return '/payment.php?id=' . $paymentDbId;
    }
}
