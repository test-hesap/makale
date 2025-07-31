<?php
require_once 'includes/config.php';

// Durum parametresini al
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Sayfa başlığını ayarla
$page_title = "Ödeme Sonucu - " . getSetting('site_name', 'Site Adı');

// PayTR veya iyzico'dan gelen ödeme bilgileri
if ($status === 'success' && isset($_SESSION['payment_info'])) {
    $payment_info = $_SESSION['payment_info'];
    $transaction_id = $payment_info['transaction_id'] ?? 0;
    $package = $payment_info['package'] ?? 'monthly';
    $order_id = $payment_info['order_id'] ?? '';
    $end_date = $payment_info['end_date'] ?? '';
    
    // Transaction bilgilerini al
    $stmt = $db->prepare("SELECT * FROM payment_transactions WHERE id = ? AND order_id = ?");
    $stmt->execute([$transaction_id, $order_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction && $transaction['status'] === 'pending') {
        try {
            // Transaction başlat
            $db->beginTransaction();
            
            // İşlemi tamamlandı olarak işaretle
            $update_trans = $db->prepare("UPDATE payment_transactions SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $update_trans->execute([$transaction_id]);
            
            // Kullanıcıyı premium yap
            $user_id = $transaction['user_id'];
            
            // Plan bilgisini tespit et
            $plan_name = ($package === 'monthly') ? 'Premium Aylık' : 'Premium Yıllık';
            $plan_duration = ($package === 'monthly') ? '1 ay' : '1 yıl';
            $payment_amount = $transaction['amount'];
            
            // Plan tablosunda kayıt var mı kontrol et
            $check_plan = $db->prepare("SELECT id, price FROM plans WHERE name = ?");
            $check_plan->execute([$plan_name]);
            $plan = $check_plan->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                // Plan yoksa ekle
                $insert_plan = $db->prepare("INSERT INTO plans (name, description, price, duration) VALUES (?, ?, ?, ?)");
                $insert_plan->execute([$plan_name, $plan_duration . ' süreli premium üyelik', $payment_amount, $plan_duration]);
                $plan_id = $db->lastInsertId();
            } else {
                // Plan var ama fiyatı güncel değilse güncelle
                $plan_id = $plan['id'];
                if ($plan['price'] != $payment_amount) {
                    $update_price = $db->prepare("UPDATE plans SET price = ? WHERE id = ?");
                    $update_price->execute([$payment_amount, $plan_id]);
                }
            }
            
            // Abonelik kaydını ekle
            $insert_sub = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $insert_sub->execute([$user_id, $plan_id, 'active', date('Y-m-d'), $end_date]);
            
            // Kullanıcı premium bilgilerini güncelle
            $update_user = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
            $update_user->execute([$end_date, $user_id]);
            
            // Transactionı tamamla
            $db->commit();
            
            // Session bilgilerini güncelle
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['is_premium'] = 1;
                $_SESSION['premium_until'] = $end_date;
            }
            
            // Başarılı mesajı göster
            $_SESSION['success_message'] = "Ödemeniz başarıyla tamamlandı! Premium üyeliğiniz aktif edildi.";
            
        } catch (PDOException $e) {
            // Hata durumunda transaction'ı geri al
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            // Hata mesajı
            $_SESSION['error_message'] = "Ödemeniz alındı ancak işlenirken bir hata oluştu. Lütfen yönetici ile iletişime geçin.";
            error_log("Premium ödeme hatası: " . $e->getMessage());
            
            // Durum bilgisini güncelle
            $status = 'failed';
        }
    }
    
    // Ödeme bilgilerini temizle
    unset($_SESSION['payment_info']);
} else if ($status === 'failed' && isset($_SESSION['payment_info'])) {
    // Başarısız ödeme işlemi
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

// Sayfayı göster
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold mb-6 text-center border-b pb-4">Ödeme Sonucu</h1>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['error_message']; ?></p>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success_message']; ?></p>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <div class="text-center">
            <?php if ($status === 'success'): ?>
                <div class="mb-8">
                    <svg class="h-24 w-24 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h2 class="text-2xl font-bold mt-4 text-green-600">Ödeme Başarılı!</h2>
                    <p class="text-gray-600 mt-2">Premium üyeliğiniz başarıyla aktifleştirildi.</p>
                </div>
                
                <div class="mt-6">
                    <p class="text-gray-700 mb-2">Premium hesabınızla tüm içeriklere erişebilir ve özel özelliklerden faydalanabilirsiniz.</p>
                    
                    <div class="flex justify-center space-x-4 mt-6">
                        <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                            Ana Sayfaya Dön
                        </a>
                        <a href="profile.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                            Profilimi Görüntüle
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-8">
                    <svg class="h-24 w-24 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h2 class="text-2xl font-bold mt-4 text-red-600">Ödeme Başarısız!</h2>
                    <p class="text-gray-600 mt-2">Ödeme işlemi tamamlanamadı veya iptal edildi.</p>
                </div>
                
                <div class="mt-6">
                    <p class="text-gray-700 mb-6">Bir sorun oluştu. Lütfen tekrar deneyiniz veya farklı bir ödeme yöntemi seçiniz.</p>
                    
                    <div class="flex justify-center space-x-4">
                        <a href="odeme.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                            Tekrar Dene
                        </a>
                        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                            Ana Sayfaya Dön
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
