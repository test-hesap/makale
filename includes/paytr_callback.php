<?php
require_once 'includes/config.php';

// PayTR API'den gelen verileri al
$merchant_oid = isset($_POST['merchant_oid']) ? $_POST['merchant_oid'] : '';
$status = isset($_POST['status']) ? $_POST['status'] : '';
$hash = isset($_POST['hash']) ? $_POST['hash'] : '';

// Ayarlardan PayTR bilgilerini al
$merchant_key = getSetting('paytr_merchant_key', '');
$merchant_salt = getSetting('paytr_merchant_salt', '');

// İşlem yoksa çık
if (empty($merchant_oid) || empty($status) || empty($hash)) {
    echo "PAYTR notification failed: missing parameters";
    exit;
}

// Hash doğrulama
$hash_str = $merchant_oid . $merchant_salt . $status . $_POST['total_amount'];
$hash_check = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));

if ($hash != $hash_check) {
    echo "PAYTR notification failed: wrong hash";
    exit;
}

// Ödeme kaydını bul
$stmt = $db->prepare("SELECT * FROM payment_transactions WHERE order_id = ? AND status = 'pending'");
$stmt->execute([$merchant_oid]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    echo "OK";
    exit;
}

try {
    // İşlemi güncelle
    if ($status == 'success') {
        // Başarılı ödeme
        $db->beginTransaction();
        
        // İşlemi tamamlandı olarak işaretle
        $update = $db->prepare("UPDATE payment_transactions SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $update->execute([$transaction['id']]);
        
        // Kullanıcı bilgilerini al
        $user_id = $transaction['user_id'];
        $package = $transaction['package'];
        
        // Süre hesapla
        if ($package == 'monthly') {
            $end_date = date('Y-m-d', strtotime('+1 month'));
        } else {
            $end_date = date('Y-m-d', strtotime('+1 year'));
        }
        
        // Kullanıcıyı premium yap
        $update_user = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
        $update_user->execute([$end_date, $user_id]);
        
        // Plan bilgisini tespit et
        $plan_name = ($package === 'monthly') ? 'Premium Aylık' : 'Premium Yıllık';
        $plan_duration = ($package === 'monthly') ? '1 ay' : '1 yıl';
        $payment_amount = $transaction['amount'];
        
        // Plan tablosunda kayıt var mı kontrol et
        $check_plan = $db->prepare("SELECT id FROM plans WHERE name = ?");
        $check_plan->execute([$plan_name]);
        $plan = $check_plan->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            // Plan yoksa ekle
            $insert_plan = $db->prepare("INSERT INTO plans (name, description, price, duration) VALUES (?, ?, ?, ?)");
            $insert_plan->execute([$plan_name, $plan_duration . ' süreli premium üyelik', $payment_amount, $plan_duration]);
            $plan_id = $db->lastInsertId();
        } else {
            $plan_id = $plan['id'];
        }
        
        // Abonelik kaydını ekle
        $insert_sub = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $insert_sub->execute([$user_id, $plan_id, 'active', date('Y-m-d'), $end_date]);
        
        $db->commit();
        
        error_log("PayTR payment successful for order: " . $merchant_oid);
    } else {
        // Başarısız ödeme
        $update = $db->prepare("UPDATE payment_transactions SET status = 'failed', updated_at = NOW() WHERE id = ?");
        $update->execute([$transaction['id']]);
        
        error_log("PayTR payment failed for order: " . $merchant_oid);
    }
} catch (PDOException $e) {
    // Hata durumunda
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PayTR notification error: " . $e->getMessage());
}

// PayTR'ye başarılı yanıt gönder
echo "OK";
exit;
?>
