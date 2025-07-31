<?php
require_once 'config.php';

// PayTR'den başarılı ödeme dönüşü
if (isset($_SESSION['payment_info'])) {
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
            
            // Admin'e bildirim gönder
            require_once '../admin/includes/notifications.php';
            
            // Kullanıcı bilgilerini sorgula
            $user_query = $db->prepare("SELECT username, email FROM users WHERE id = ?");
            $user_query->execute([$user_id]);
            $user_data = $user_query->fetch(PDO::FETCH_ASSOC);
            
            $username = $user_data['username'] ?? 'Kullanıcı';
            $email = $user_data['email'] ?? '';
            $package_text = ($package === 'monthly') ? 'aylık' : 'yıllık';
            $amount_text = number_format($payment_amount, 2, ',', '.') . ' ₺';
            
            $notification_message = "$username adlı kullanıcı $package_text premium abonelik ($amount_text) başlattı.";
            addAdminNotification('new_subscription', $user_id, $notification_message, "/admin/payments.php", $transaction_id);
            
            // Kullanıcıya teşekkür e-postası gönder
            if (!empty($email)) {
                $site_name = getSetting('site_title', 'Site Adı');
                $site_url = getSetting('site_url', '');
                
                $subject = t('premium_thanks_email_subject', $site_name);
                
                $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
                $message .= '<h2 style="color: #333;">'. t('premium_thanks_email_title') .'</h2>';
                $message .= '<p>'. t('premium_thanks_email_greeting', $username) .'</p>';
                $message .= '<p>'. t('premium_thanks_email_message', $package_text, $amount_text) .'</p>';
                $message .= '<p>'. t('premium_thanks_email_expiry', formatTurkishDate($end_date)) .'</p>';
                $message .= '<p>'. t('premium_thanks_email_benefits') .'</p>';
                $message .= '<ul>';
                $message .= '<li>'. t('premium_benefit_1') .'</li>';
                $message .= '<li>'. t('premium_benefit_2') .'</li>';
                $message .= '<li>'. t('premium_benefit_3') .'</li>';
                $message .= '</ul>';
                $message .= '<p>'. t('premium_thanks_email_help') .'</p>';
                $message .= '<p>'. t('premium_thanks_email_signature', $site_name) .'</p>';
                $message .= '</div>';
                
                sendEmail($email, $subject, $message);
            }
            
            // Ödeme bilgilerini temizle
            unset($_SESSION['payment_info']);
            
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
        }
    }
}

// Anasayfaya yönlendir
header("Location: /odeme_sonuc.php?status=success");
exit();
?>
