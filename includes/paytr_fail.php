<?php
require_once 'config.php';

// PayTR'den başarısız ödeme dönüşü
if (isset($_SESSION['payment_info'])) {
    $payment_info = $_SESSION['payment_info'];
    $transaction_id = $payment_info['transaction_id'] ?? 0;
    
    // İşlemi iptal edildi olarak işaretle
    if ($transaction_id) {
        $update_trans = $db->prepare("UPDATE payment_transactions SET status = 'failed', updated_at = NOW() WHERE id = ?");
        $update_trans->execute([$transaction_id]);
    }
    
    // Ödeme bilgilerini temizle
    unset($_SESSION['payment_info']);
    
    // Başarısız mesajı göster
    $_SESSION['error_message'] = "Ödeme işlemi iptal edildi veya tamamlanamadı.";
}

// Ödeme sonuç sayfasına yönlendir
header("Location: /odeme_sonuc.php?status=failed");
exit();
?>
