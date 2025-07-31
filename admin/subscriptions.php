<?php
// Çıktı önbelleklemeyi başlat
ob_start();

require_once '../includes/config.php';
checkAuth(true);

include 'includes/header.php';

// Abonelik işlemlerini loglama fonksiyonu
function logSubscriptionAction($message, $details = []) {
    $timestamp = date('Y-m-d H:i:s');
    $requestInfo = [
        'url' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $_GET['action'] ?? 'unknown',
        'admin_id' => $_SESSION['user_id'] ?? 'unknown',
        'admin_username' => $_SESSION['username'] ?? 'unknown'
    ];
    
    $logData = [
        'timestamp' => $timestamp,
        'message' => $message,
        'request' => $requestInfo,
    ];
    
    if (!empty($details)) {
        $logData['details'] = $details;
    }
    
    error_log("ABONELIK_LOG: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
}

// JavaScript hata yakalama
if (isset($_GET['log_js_error']) && $_GET['log_js_error'] == 1) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        error_log("JavaScript Hatası: " . json_encode($input, JSON_UNESCAPED_UNICODE));
        echo json_encode(['status' => 'logged']);
        exit;
    }
}

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Session'dan başarı mesajını al
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Mesajı sadece bir kere göster
}

// Session'dan hata mesajını al
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Mesajı sadece bir kere göster
}

// Abonelik silme
if ($action === 'delete' && isset($_GET['id'])) {
    error_log("Abonelik silme işlemi başlatılıyor... ID: " . $_GET['id']);
    logSubscriptionAction("Abonelik silme işlemi başlatıldı", ["id" => $_GET['id']]);
    try {
        // Her ihtimale karşı, önceden başlatılmış bir transaction varsa geri alalım
        if ($db->inTransaction()) {
            $db->rollBack();
            error_log("Abonelik silme: Aktif bir transaction'ın açık kaldığı tespit edildi ve geri alındı");
        }
        
        // Önce aboneliğin sahibini belirle
        $check_stmt = $db->prepare("SELECT s.user_id, s.id, u.username, u.is_premium, u.premium_until 
                                   FROM subscriptions s 
                                   JOIN users u ON s.user_id = u.id 
                                   WHERE s.id = ?");
        $check_stmt->execute([$_GET['id']]);
        $subscription = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug bilgisi
        error_log("Abonelik silme işlemi başlatıldı: " . print_r($subscription, true));
          if ($subscription) {
            $user_id = $subscription['user_id'];
            $username = $subscription['username'];
            
            // Debug bilgileri
            error_log("Silinecek aboneliğin sahibi: ID: $user_id, Kullanıcı: $username");
            error_log("Mevcut premium durumu: is_premium: " . ($subscription['is_premium'] ? 'Evet' : 'Hayır') . 
                     ", premium_until: " . ($subscription['premium_until'] ?? 'null'));
            
            // Transaction başlat
            $db->beginTransaction();
            
            // 1. Aboneliği sil
            $stmt = $db->prepare("DELETE FROM subscriptions WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            // 2. Kullanıcının premium durumunu güncelleme stratejisi için tüm diğer aboneliklerini kontrol et
            $active_check = $db->prepare("SELECT COUNT(*) as active_count FROM subscriptions WHERE user_id = ? AND status = 'active'");
            $active_check->execute([$user_id]);
            $active_count = $active_check->fetch(PDO::FETCH_ASSOC)['active_count'];
            
            error_log("Kalan aktif abonelik sayısı: $active_count");
            
            if ($active_count > 0) {
                // Sadece ücretli aktif abonelik varsa premium yapalım
                // Ücretli aktif abonelikleri sorgula
                $paid_subscription_check = $db->prepare("
                    SELECT s.id, s.end_date, p.price, p.name 
                    FROM subscriptions s 
                    JOIN plans p ON s.plan_id = p.id 
                    WHERE s.user_id = ? 
                    AND s.status = 'active' 
                    AND p.price > 0
                    ORDER BY s.end_date DESC
                    LIMIT 1");
                $paid_subscription_check->execute([$user_id]);
                $paid_subscription = $paid_subscription_check->fetch(PDO::FETCH_ASSOC);
                
                if ($paid_subscription) {
                    // Ücretli aktif abonelik bulundu
                    $premium_plan_name = $paid_subscription['name'];
                    $last_date = $paid_subscription['end_date'];
                    
                    error_log("Kalan ücretli aktif abonelik: {$premium_plan_name}, bitiş tarihi: {$last_date}");
                    
                    $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
                    $update_premium_stmt->execute([$last_date, $user_id]);
                    
                    $success = "Abonelik başarıyla silindi. Kullanıcının diğer ücretli abonelikleri olduğundan premium üyelik $last_date tarihine kadar devam edecektir.";
                    error_log("Kullanıcı ID: $user_id için premium üyelik tarihi güncellendi: $last_date (plan: {$premium_plan_name})");
                } else {
                    // Sadece ücretsiz abonulukler kalmış
                    error_log("Kullanıcının sadece ücretsiz aktif abonelikleri var, premium üyelik sıfırlanıyor.");
                    
                    $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                    $update_premium_stmt->execute([$user_id]);
                    
                    $success = "Abonelik başarıyla silindi. Kullanıcının sadece ücretsiz abonelikleri kaldığı için premium üyeliği sonlandırıldı.";
                }
            } else {
                // Aktif abonelik kalmadı, premium üyeliği tamamen sıfırla
                $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                $update_premium_stmt->execute([$user_id]);
                
                // Temizleme başarılı mı kontrol et
                $check_after = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
                $check_after->execute([$user_id]);
                $user_after = $check_after->fetch(PDO::FETCH_ASSOC);
                
                if ($user_after['is_premium'] == 0 && $user_after['premium_until'] === null) {
                    error_log("Premium üyelik başarıyla sıfırlandı: " . print_r($user_after, true));
                    
                    // Eğer mevcut oturum bu kullanıcıya aitse, oturum bilgilerini güncelle
                    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                        $_SESSION['is_premium'] = 0;
                        $_SESSION['premium_until'] = null;
                        error_log("Mevcut admin oturumunda bulunan kullanıcı için premium bilgileri de sıfırlandı: user_id=$user_id");
                    }
                } else {
                    error_log("DİKKAT: Premium üyelik sıfırlaması başarısız olmuş olabilir: " . print_r($user_after, true));
                }                // Transaction durumunu kontrol et ve commit et
                if ($db->inTransaction()) {
                    $db->commit();
                    error_log("Transaction committed before user session cleanup");
                }
                
                // Kullanıcı oturumlarını temizlemek için geliştirilen fonksiyonu kullan
                // premium_manager.php'deki clearUserSessions fonksiyonunu doğrudan dahil et
                require_once 'premium_manager.php';
                
                // Yeni bir transaction başlat
                $db->beginTransaction();
                
                // Artık fonksiyon kullanılabilir olacaktır
                if (function_exists('clearUserSessions')) {
                    clearUserSessions($user_id);
                    error_log("Kullanıcı ID: $user_id için oturumlar temizlendi");
                } else {
                    // Fonksiyon halen bulunamadıysa, durumu logla
                    error_log("HATA: clearUserSessions fonksiyonu bulunamadı! Oturum temizleme işlemi başarısız olabilir.");
                    
                    // Yedek bir oturum temizleme stratejisi uygula
                    try {
                        // 1. user_sessions tablosunu oluştur (yoksa) ve kullanıcının kayıtlarını sil
                        $db->query("CREATE TABLE IF NOT EXISTS user_sessions (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            session_id VARCHAR(255) NOT NULL,
                            ip_address VARCHAR(50),
                            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            KEY (user_id),
                            KEY (session_id)
                        )");
                        
                        $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$user_id]);
                        
                        // 2. Kullanıcı session_token kolonunu sıfırla (varsa)
                        $checkColumn = $db->query("SHOW COLUMNS FROM `users` LIKE 'session_token'");
                        if ($checkColumn->rowCount() > 0) {
                            $db->prepare("UPDATE users SET session_token = NULL WHERE id = ?")->execute([$user_id]);
                        }
                        
                        // 3. Kullanıcının premium bilgilerini tekrar kontrol et ve tutarlılık sağla
                        $checkPremium = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
                        $checkPremium->execute([$user_id]);
                        $premiumStatus = $checkPremium->fetch(PDO::FETCH_ASSOC);
                        
                        if ($premiumStatus['is_premium'] == 1 && (!$premiumStatus['premium_until'] || $premiumStatus['premium_until'] < date('Y-m-d'))) {
                            // Tutarsız bir premium durum varsa düzelt
                            $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?")->execute([$user_id]);
                            error_log("Kullanıcı ID: $user_id için tutarsız premium durum düzeltildi");
                        }
                        
                        error_log("Kullanıcı ID: $user_id için oturumlar yedek stratejiye göre temizlendi");
                    } catch (PDOException $e) {
                        error_log("Oturum temizleme hatası: " . $e->getMessage());
                    }
                }
                
                error_log("Kullanıcı ID: $user_id için premium üyelik tamamen sonlandırıldı.");
                
                // Session'a başarı mesajı kaydet                $_SESSION['success_message'] = 'Abonelik başarıyla silindi ve kullanıcının premium üyeliği tamamen sonlandırıldı.';
            }
            
            try {
                // Transaction'ı tamamla (aktif bir transaction varsa)
                if ($db->inTransaction()) {
                    $db->commit();
                    error_log("Transaction başarıyla commit edildi");
                } else {
                    error_log("Commit yapılmadı çünkü aktif bir transaction yok");
                }
            } catch (Exception $transactionEx) {
                error_log("Transaction commit hatası: " . $transactionEx->getMessage());
                // Hata gösterme ama işleme devam et - bu noktada kesintisiz işlem önemli
            }
            
            // İşlem başarılı logunu kaydet
            logSubscriptionAction("Abonelik silme işlemi başarıyla tamamlandı", [
                "user_id" => $user_id,
                "username" => $username,
                "id" => $_GET['id']
            ]);
            
            // Yönlendirme yap
            header('Location: subscriptions.php');
            exit();
        } else {
            $error = 'Abonelik bulunamadı.';
            error_log("HATA: Silinecek abonelik bulunamadı - ID: " . $_GET['id']);
        }    }catch(PDOException $e) {        // Hata durumunda transaction'ı geri al
        if ($db->inTransaction()) {
            $db->rollBack();
            error_log("Abonelik silme hatası: Transaction geri alındı");
        } else {
            error_log("Abonelik silme hatası: Aktif transaction bulunamadı, rollback yapılmadı");
        }
          // Daha kullanıcı dostu hata mesajı
        $hata_mesaji = $e->getMessage();
        
        // Detaylı log kayıtları
        error_log("PDOException detayları: " . print_r($e, true));
        error_log("Hata mesajı: " . $hata_mesaji);
        error_log("Hata kodu: " . $e->getCode());
        error_log("Hata dosyası: " . $e->getFile() . " - Satır: " . $e->getLine());
        
        // Özel log kaydı
        logSubscriptionAction("Abonelik silme işleminde PDO hatası", [
            "error_message" => $hata_mesaji,
            "error_code" => $e->getCode(),
            "id" => $_GET['id'] ?? 'bilinmiyor'
        ]);        if (stripos($hata_mesaji, "no active transaction") !== false) {
            $error = "Abonelik silinirken bir veritabanı işlemi hatası oluştu. İşlem tamamlanmış olabilir, sayfayı yenileyip kontrol ediniz.";
            // Kullanıcıyı liste sayfasına yönlendir
            $_SESSION['error_message'] = $error;
            header('Location: subscriptions.php');
            exit();
        } else {
            $error = 'Abonelik silinirken bir hata oluştu: ' . $e->getMessage();
        }
        
        error_log("Abonelik silme hatası: " . $e->getMessage());    } catch(Exception $e) {
        // Diğer hatalar için
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Beklenmeyen bir hata oluştu: ' . $e->getMessage();
        error_log("Abonelik silme - beklenmeyen hata: " . $e->getMessage());
        error_log("Beklenmeyen hata detayları: " . print_r($e, true));
        
        // Özel log kaydı
        logSubscriptionAction("Abonelik silme işleminde beklenmeyen hata", [
            "error_message" => $e->getMessage(),
            "error_trace" => $e->getTraceAsString(),
            "id" => $_GET['id'] ?? 'bilinmiyor'
        ]);
    }
}

// Abonelik güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $user_id = (int)$_POST['user_id'];
    $plan_id = (int)$_POST['plan_id'];
    $status = clean($_POST['status']);
    $end_date = $_POST['end_date'];
    $id = (int)$_POST['id'];
    
    if (empty($user_id) || empty($plan_id) || empty($end_date)) {
        $error = getActiveLang() == 'en' ? 'Fill in all fields.' : 'Tüm alanları doldurunuz.';
    } else {
        try {
            // Açık kalmış bir transaction varsa önce temizleyelim
            if ($db->inTransaction()) {
                $db->rollBack();
                error_log("Abonelik güncelleme: Açık kalmış bir transaction tespit edildi ve geri alındı");
            }
            
            // İşlemi bir transaction içinde yapalım ki tutarlılık sağlayalım
            $db->beginTransaction();
            
            // Öncelikle plan tipini kontrol et
            $plan_check = $db->prepare("SELECT * FROM plans WHERE id = ?");
            $plan_check->execute([$plan_id]);
            $plan = $plan_check->fetch(PDO::FETCH_ASSOC);
            
            // Ücretsiz plan kontrolü
            $is_free_plan = ($plan && $plan['price'] <= 0 && 
                            !(strtolower($plan['name']) === 'premium hediye' || 
                             stripos($plan['name'], 'premium hediye') !== false));
            
            error_log("Abonelik güncelleme - Plan: " . $plan['name'] . ", Ücret: " . $plan['price'] . ", Ücretsiz mi: " . ($is_free_plan ? "Evet" : "Hayır"));
            
            // 1. Aboneliği güncelle
            $stmt = $db->prepare("UPDATE subscriptions SET user_id = ?, plan_id = ?, status = ?, end_date = ? WHERE id = ?");
            $stmt->execute([$user_id, $plan_id, $status, $end_date, $id]);
            
            // 2. Premium durumunu düzenle - aktifliğe ve plan tipine göre
            if ($status === 'active') {
                if ($is_free_plan) {
                    // Ücretsiz plan için premium yapmıyoruz
                    error_log("Ücretsiz plan güncellendiği için kullanıcı premium yapılmadı (user_id: $user_id)");
                    $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                    $update_premium_stmt->execute([$user_id]);
                } else {
                    // Ücretli plan için premium yap
                    $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
                    $update_premium_stmt->execute([$end_date, $user_id]);
                    error_log("Ücretli/Premium plan güncellendiği için kullanıcı premium yapıldı (user_id: $user_id)");
                }
            } else {
                // Eğer abonelik aktif değilse ve başka aktif abonelik yoksa premium üyeliği de pasifleştir
                $active_check = $db->prepare("SELECT COUNT(*) as active_count FROM subscriptions WHERE user_id = ? AND status = 'active' AND id != ?");
                $active_check->execute([$user_id, $id]);
                $has_active = $active_check->fetch(PDO::FETCH_ASSOC)['active_count'] > 0;
                
                if (!$has_active) {
                    $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                    $update_premium_stmt->execute([$user_id]);
                }
            }
            
            // 3. Transaction'ı tamamla
            $db->commit();
              // 4. Log kaydı
            error_log("Admin tarafından kullanıcı ID: $user_id için abonelik güncellendi ve premium üyelik senkronize edildi. Durum: $status, Plan türü: " . ($is_free_plan ? "Ücretsiz" : "Ücretli/Premium") . ", Bitiş tarihi: $end_date");
            
            // Form yeniden göndermeyi önlemek için yönlendirme yapılıyor
            $_SESSION['success_message'] = getActiveLang() == 'en' ? 
                'Subscription updated successfully and user\'s premium status has been synchronized.' : 
                'Abonelik başarıyla güncellendi ve kullanıcının premium durumu senkronize edildi.';
            header('Location: subscriptions.php');
            exit();
        } catch(PDOException $e) {
            // Hata durumunda transaction'ı geri al
            $db->rollBack();
            $error = getActiveLang() == 'en' ? 
                'An error occurred while updating the subscription: ' . $e->getMessage() : 
                'Abonelik güncellenirken bir hata oluştu: ' . $e->getMessage();
            error_log("Abonelik güncelleme hatası: " . $e->getMessage());
        }
    }
}

// Yeni abonelik ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $user_id = (int)$_POST['user_id'];
    $plan_id = (int)$_POST['plan_id'];
    $status = 'active';
    
    // Plan bilgilerini al
    $check_plan = $db->prepare("SELECT * FROM plans WHERE id = ?");
    $check_plan->execute([$plan_id]);
    $plan = $check_plan->fetch(PDO::FETCH_ASSOC);
      // Premium Hediye planını tespit et
    $is_premium_gift = ($plan && (strtolower($plan['name']) === 'premium hediye' || 
                                 stripos($plan['name'], 'premium hediye') !== false));
    
    // Eğer plan ücretsizse (fiyat 0 ise) ve Premium Hediye değilse, end_date NULL olarak ayarlanacak
    $is_free_plan = ($plan && $plan['price'] <= 0 && !$is_premium_gift);
      // Plan türüne göre bitiş tarihini belirle
    $is_monthly_plan = ($plan && (stripos($plan['name'], 'aylık') !== false || stripos($plan['name'], 'aylik') !== false));
    $is_yearly_plan = ($plan && (stripos($plan['name'], 'yıllık') !== false || stripos($plan['name'], 'yillik') !== false));
    $is_6month_plan = ($plan && (stripos($plan['name'], '6 ay') !== false || stripos($plan['name'], '6ay') !== false || 
                                stripos($plan['name'], 'altı ay') !== false));
      // Premium Hediye planı için kullanıcının seçtiği tarihi kullan
    if ($is_premium_gift) {
        // Premium Hediye için formdan gelen tarihi kullan
        $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
        error_log("Premium Hediye planı seçildi. Kullanıcı tarafından seçilen bitiş tarihi: $end_date");
    }
    // Ücretsiz plan veya standart süreli planlar için tarih otomatik belirlenecek
    else if ($is_free_plan || $is_monthly_plan || $is_yearly_plan || $is_6month_plan) {
        if ($is_monthly_plan) {
            // Aylık plan için 1 ay
            $default_end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            $end_date = $default_end_date;
            error_log("Aylık plan seçildi. Varsayılan bitiş tarihi: $end_date");
        } else if ($is_yearly_plan) {
            // Yıllık plan için 1 yıl
            $default_end_date = date('Y-m-d H:i:s', strtotime('+1 year'));
            $end_date = $default_end_date;
            error_log("Yıllık plan seçildi. Varsayılan bitiş tarihi: $end_date");
        } else if ($is_6month_plan) {
            // 6 aylık plan için 6 ay
            $default_end_date = date('Y-m-d H:i:s', strtotime('+6 months'));
            $end_date = $default_end_date;
            error_log("6 Aylık plan seçildi. Varsayılan bitiş tarihi: $end_date");
        } else {
            $end_date = null; // Ücretsiz plan için tarih NULL
        }
    } else {
        // Diğer planlar için tarih alanından al
        $end_date = $_POST['end_date'] ?? null; // Ücretli plan için tarih gerekli
    }      // Gerekli kontroller
    if (empty($user_id) || empty($plan_id) || (empty($end_date) && !$is_free_plan && !$is_monthly_plan && !$is_yearly_plan && !$is_6month_plan)) {
        $error = getActiveLang() == 'en' ? 'Fill in all fields.' : 'Tüm alanları doldurunuz.';
    } else {
        try {
            // Açık kalmış bir transaction varsa önce temizleyelim
            if ($db->inTransaction()) {
                $db->rollBack();
                error_log("Abonelik ekleme: Açık kalmış bir transaction tespit edildi ve geri alındı");
            }
            
            // İşlemi bir transaction içinde yapalım ki tutarlılık sağlayalım
            $db->beginTransaction();
            // Plan türüne göre log kaydı
            if ($is_premium_gift) {
                error_log("Premium Hediye planı: Bitiş tarihi $end_date olarak ayarlandı ve durumu aktif olarak işaretlendi.");
            } else {
                error_log("Plan için bitiş tarihi: " . ($end_date ? $end_date : "NULL") . " (Ücretsiz plan: " . ($is_free_plan ? "Evet" : "Hayır") . ")");
            }
              // Premium plan fiyatlarını ayarlardan güncelleyelim
            if ($is_monthly_plan || $is_yearly_plan) {
                // Premium ayarlarını al
                $premium_settings = [];
                $settings_stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('premium_monthly_price', 'premium_yearly_price')");
                while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $premium_settings[$row['key']] = $row['value'];
                }
                
                // Plan fiyatını güncelle
                if ($is_monthly_plan && isset($premium_settings['premium_monthly_price']) && 
                    $plan['price'] != floatval($premium_settings['premium_monthly_price'])) {
                    // Aylık plan fiyatını güncelle
                    $update_price = $db->prepare("UPDATE plans SET price = ? WHERE id = ?");
                    $update_price->execute([
                        floatval($premium_settings['premium_monthly_price']), 
                        $plan_id
                    ]);
                    error_log("Aylık premium plan fiyatı güncellendi: " . $premium_settings['premium_monthly_price']);
                } 
                else if ($is_yearly_plan && isset($premium_settings['premium_yearly_price']) && 
                        $plan['price'] != floatval($premium_settings['premium_yearly_price'])) {
                    // Yıllık plan fiyatını güncelle
                    $update_price = $db->prepare("UPDATE plans SET price = ? WHERE id = ?");
                    $update_price->execute([
                        floatval($premium_settings['premium_yearly_price']), 
                        $plan_id
                    ]);
                    error_log("Yıllık premium plan fiyatı güncellendi: " . $premium_settings['premium_yearly_price']);
                }
            }

            // 1. Yeni aboneliği ekle
            $stmt = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, status, end_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $plan_id, $status, $end_date]);
            
            // 2. Kullanıcının premium üyelik durumunu güncelle
            // Eğer ücretsiz plan ise premium yapma, diğer planlarda premium yap
            if ($is_free_plan) {
                // Ücretsiz planlarda kullanıcı premium yapılmamalı
                error_log("Ücretsiz plan eklendiği için kullanıcı premium yapılmadı (user_id: $user_id)");
            } else {
                // Ücretli planlar için premium yap
                $update_premium_stmt = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
                $update_premium_stmt->execute([$end_date, $user_id]);
                error_log("Kullanıcı premium yapıldı. Bitiş tarihi: $end_date (user_id: $user_id)");
            }
            
            // 3. Transaction'ı tamamla
            $db->commit();
            
            $success = $is_free_plan ? 
                (getActiveLang() == 'en' ? 'Free subscription added successfully.' : 'Ücretsiz abonelik başarıyla eklendi.') : 
                (getActiveLang() == 'en' ? 'Subscription added successfully and user\'s premium membership has been activated.' : 'Abonelik başarıyla eklendi ve kullanıcının premium üyeliği aktifleştirildi.');
              // 4. Log kaydı
            error_log("Admin tarafından kullanıcı ID: $user_id için abonelik eklendi. Plan türü: " . 
                     ($is_free_plan ? "Ücretsiz" : "Ücretli/Premium") . 
                     " - Bitiş tarihi: " . ($end_date ?: "NULL"));
            
            // Form yeniden göndermeyi önlemek için yönlendirme yapılıyor
            $_SESSION['success_message'] = $success;
            header('Location: subscriptions.php');
            exit();
        } catch(PDOException $e) {
            // Hata durumunda transaction'ı geri al
            $db->rollBack();
            $error = getActiveLang() == 'en' ? 
                'An error occurred while adding the subscription: ' . $e->getMessage() : 
                'Abonelik eklenirken bir hata oluştu: ' . $e->getMessage();
            error_log("Abonelik ekleme hatası: " . $e->getMessage());
        }
    }
}

// Premium üyeleri kontrol et
$premium_users_exist = false;
try {
    $check_stmt = $db->query("SELECT COUNT(*) as premium_count FROM users WHERE is_premium = 1");
    $premium_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['premium_count'];
    $premium_users_exist = $premium_count > 0;
} catch(PDOException $e) {
    $error = getActiveLang() == 'en' ? 
        'An error occurred while checking premium users: ' . $e->getMessage() : 
        'Premium üyeler kontrol edilirken bir hata oluştu: ' . $e->getMessage();
}

// Abonelik listesi
// Hem normal abonelikler hem de premium üyeler
try {
    // Normal abonelikler ve Premium üyeleri daha net gösterme
    $subscriptions = $db->query("
        SELECT 
            s.id,
            s.user_id,
            s.plan_id,
            s.status,
            s.start_date,
            s.end_date,
            u.username,
            u.email,
            u.is_premium,
            u.premium_until,
            p.name as plan_name,
            p.price,
            'subscription' as type
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN plans p ON s.plan_id = p.id
        
        UNION ALL
        
        -- Premium üyeler (abonelikle eşleşmese bile)
        SELECT 
            NULL as id,
            u.id as user_id,
            0 as plan_id,
            CASE WHEN u.premium_until >= CURRENT_DATE THEN 'active' ELSE 'expired' END as status,
            NULL as start_date,
            u.premium_until as end_date,
            u.username,
            u.email,
            u.is_premium,
            u.premium_until,
            'Premium Üyelik' as plan_name,
            0 as price,
            'premium' as type
        FROM users u
        WHERE u.is_premium = 1
          AND NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id = u.id)
        
        ORDER BY end_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Premium durumunun tutarlılığını kontrol et
    foreach($subscriptions as $key => $subscription) {
        // Abonelik tipine göre premium durumu kontrolü
        if ($subscription['type'] === 'subscription') {
            // Aboneliği var, premium durumu doğru mu?
            $isPremiumInDb = (int)$subscription['is_premium'];
            $hasPremiumEndDate = !empty($subscription['premium_until']);
            $isActive = $subscription['status'] === 'active';
            
            // Tutarsızlık varsa logla
            if ($isActive && (!$isPremiumInDb || !$hasPremiumEndDate)) {
                error_log("Tutarsızlık tespit edildi: Abonelik aktif ama kullanıcı premium değil - user_id: " . $subscription['user_id']);
            } elseif (!$isActive && $isPremiumInDb) {
                error_log("Tutarsızlık tespit edildi: Abonelik aktif değil ama kullanıcı premium - user_id: " . $subscription['user_id']);
            }
        }
    }
} catch(PDOException $e) {
    $error = 'Abonelik listesi alınırken bir hata oluştu: ' . $e->getMessage();
    $subscriptions = [];
}

// Kullanıcı listesi
$users = $db->query("SELECT id, username, email FROM users")->fetchAll(PDO::FETCH_ASSOC);

// Premium fiyat ayarlarını al
$premium_settings = [];
$settings_stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('premium_monthly_price', 'premium_yearly_price', 'premium_yearly_discount')");
while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $premium_settings[$row['key']] = $row['value'];
}

// Tekrarlanan premium plan kayıtlarını temizle
try {
    // 1. Standart plan isimleri tanımla
    $standard_plans = [
        'monthly' => ['name' => 'Premium Aylık', 'description' => '1 ay süreli premium üyelik'],
        'yearly' => ['name' => 'Premium Yıllık', 'description' => '1 yıl süreli premium üyelik'],
        'gift' => ['name' => 'Premium Hediye', 'description' => 'Hediye premium üyelik'],
        'free' => ['name' => 'Ücretsiz', 'description' => 'Ücretsiz üyelik']
    ];
    
    // 2. Fiyatları belirle
    $monthly_price = floatval($premium_settings['premium_monthly_price'] ?? 29.99);
    $yearly_price = floatval($premium_settings['premium_yearly_price'] ?? 239.99);
    
    // 3. "Premium Üyelik" tarzı isimler içeren tekrarlı planları bul
    $db->beginTransaction();
    
    // 3.1 Önce standart planları ekle/güncelle
    foreach ($standard_plans as $key => $plan_data) {
        $price = 0;
        if ($key == 'monthly') {
            $price = $monthly_price;
        } else if ($key == 'yearly') {
            $price = $yearly_price;
        }
        
        $check = $db->prepare("SELECT id, price, name FROM plans WHERE name = ?");
        $check->execute([$plan_data['name']]);
        $plan = $check->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            // Plan yoksa ekle
            $stmt = $db->prepare("INSERT INTO plans (name, description, price, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$plan_data['name'], $plan_data['description'], $price]);
            error_log("Plan oluşturuldu: " . $plan_data['name'] . " - " . $price . " TL");
        } else if (($key == 'monthly' || $key == 'yearly') && $plan['price'] != $price) {
            // Mevcut planın fiyatını güncelle
            $stmt = $db->prepare("UPDATE plans SET price = ? WHERE id = ?");
            $stmt->execute([$price, $plan['id']]);
            error_log("Plan fiyatı güncellendi: " . $plan_data['name'] . " - " . $price . " TL (önceki: " . $plan['price'] . " TL)");
        }
    }
    
    // 3.2 Tekrarlı veya gereksiz "Premium Üyelik" planlarını bul
    $redundant_plans = $db->query("
        SELECT p.* FROM plans p 
        WHERE (p.name LIKE 'Premium Üyelik%' OR p.name LIKE 'Premium Üye%')
        AND p.name NOT IN ('Premium Aylık', 'Premium Yıllık', 'Premium Hediye')
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($redundant_plans) > 0) {
        error_log("Tekrarlı planlar tespit edildi: " . count($redundant_plans) . " adet");
        
        // Tekrarlı planların aboneliklerini standart planlara taşı
        foreach ($redundant_plans as $plan) {
            // Planın fiyatına bakarak aylık mı yıllık mı olduğunu belirle
            $is_monthly = stripos($plan['name'], 'ay') !== false || $plan['price'] <= 50;
            $target_plan_id = 0;
            
            // Hedef planı belirle
            $target_plan_name = $is_monthly ? 'Premium Aylık' : 'Premium Yıllık';
            $check = $db->prepare("SELECT id FROM plans WHERE name = ?");
            $check->execute([$target_plan_name]);
            $target_plan = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($target_plan) {
                $target_plan_id = $target_plan['id'];
                
                // Abonelikleri hedef plana taşı
                $update = $db->prepare("UPDATE subscriptions SET plan_id = ? WHERE plan_id = ?");
                $update->execute([$target_plan_id, $plan['id']]);
                error_log("Abonelikler taşındı: " . $plan['name'] . " -> " . $target_plan_name);
                
                // Tekrarlı planı sil
                $delete = $db->prepare("DELETE FROM plans WHERE id = ?");                $delete->execute([$plan['id']]);
                error_log("Plan silindi: " . $plan['name'] . " (ID: " . $plan['id'] . ")");
            }
        }
    }
    
    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Plan temizleme hatası: " . $e->getMessage());
}

// Plan listesi - temizlenmiş ve güncellenmiş planları al
$plans = $db->query("SELECT id, name, price, features FROM plans")->fetchAll(PDO::FETCH_ASSOC);

// Premium planlarını güncelle
foreach ($plans as $key => $plan) {
    // Aylık Premium Planı güncelle
    if (stripos($plan['name'], 'Premium Aylık') !== false || 
        (stripos($plan['name'], 'Premium') !== false && stripos($plan['name'], 'Aylık') !== false)) {
        $plans[$key]['price'] = floatval($premium_settings['premium_monthly_price'] ?? $plan['price']);
    }
    // Yıllık Premium Planı güncelle
    else if (stripos($plan['name'], 'Premium Yıllık') !== false || 
        (stripos($plan['name'], 'Premium') !== false && stripos($plan['name'], 'Yıllık') !== false)) {
        $plans[$key]['price'] = floatval($premium_settings['premium_yearly_price'] ?? $plan['price']);
    }
}
?>

<div class="flex-1 min-h-screen">
    <div class="max-w-full mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                <?php echo isset($lang['admin_subscription_management']) ? $lang['admin_subscription_management'] : 'Abonelik Yönetimi'; ?>
            </h1>
            <div class="flex gap-2">
                <a href="premium_manager.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                    <i class="fas fa-crown mr-2"></i> <?php echo isset($lang['admin_premium_management']) ? $lang['admin_premium_management'] : 'Premium Üyelik Yönetimi'; ?>
                </a>
                <button type="button" id="addSubscriptionBtn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i> <?php echo isset($lang['admin_new_subscription']) ? $lang['admin_new_subscription'] : 'Yeni Abonelik Ekle'; ?>
                </button>
            </div>
        </div>

        <main class="mx-auto px-4 py-8">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Premium Üyelik Bilgilendirme Paneli -->                <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                    <h3 class="text-lg font-medium text-blue-700 dark:text-blue-300 mb-2"><?php echo isset($lang['admin_premium_relationship']) ? $lang['admin_premium_relationship'] : 'Premium Üyelik ve Abonelik İlişkisi'; ?></h3>
                    <ul class="list-disc list-inside space-y-1 text-blue-600 dark:text-blue-400">
                        <li><?php echo isset($lang['admin_subscription_auto_premium']) ? $lang['admin_subscription_auto_premium'] : 'Abonelik eklendiğinde kullanıcı otomatik olarak premium üye yapılır.'; ?></li>
                        <li><?php echo isset($lang['admin_subscription_remove_premium']) ? $lang['admin_subscription_remove_premium'] : 'Abonelik silindiğinde, kullanıcının başka aktif aboneliği yoksa premium üyeliği de sonlandırılır.'; ?></li>
                        <li><?php echo isset($lang['admin_subscription_sessions_cleared']) ? $lang['admin_subscription_sessions_cleared'] : 'Premium üyeliği sonlandırılan kullanıcının tüm oturumları otomatik temizlenir.'; ?></li>
                        <li><?php echo isset($lang['admin_subscription_detailed_operations']) ? $lang['admin_subscription_detailed_operations'] : 'Premium üyelikle ilgili detaylı işlemler için'; ?> <a href="premium_manager.php" class="text-blue-800 dark:text-blue-400 underline font-medium hover:text-blue-700 dark:hover:text-blue-300"><?php echo isset($lang['admin_premium_management']) ? $lang['admin_premium_management'] : 'Premium Üyelik Yönetimi'; ?></a> <?php echo isset($lang['admin_subscription_detailed_operations_page']) ? $lang['admin_subscription_detailed_operations_page'] : 'sayfasını kullanabilirsiniz.'; ?></li>
                    </ul>
                </div>

        <?php if ($action === 'edit' && isset($_GET['id'])): ?>
            <?php
            $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-200">
                            <?php echo isset($lang['admin_subscription_edit']) ? $lang['admin_subscription_edit'] : 'Abonelik Düzenle'; ?>
                        </h3>
                    </div>
                    <div class="p-6">                        <form method="post" action="subscriptions.php">
                            <input type="hidden" name="id" value="<?php echo $subscription['id']; ?>">
                            
                            <div class="mb-4">
                                <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Kullanıcı:</label>
                                <select class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                        id="user_id" name="user_id" required>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $subscription['user_id'] === $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo clean($user['username']); ?> (<?php echo clean($user['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                              <div class="mb-4">                                <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Plan:</label>
                                <div class="mt-4 space-y-4">
                                    <?php foreach ($plans as $plan): ?>
                                    <div class="relative flex items-start">
                                        <div class="flex items-center h-5">
                                            <input type="radio" 
                                                   name="plan_id" 
                                                   id="plan_<?php echo $plan['id']; ?>" 
                                                   value="<?php echo $plan['id']; ?>" 
                                                   <?php echo ($subscription['plan_id'] ?? 0) === $plan['id'] ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-blue-600 dark:text-blue-400 border-gray-300 dark:border-gray-600 focus:ring-blue-500 dark:bg-gray-700" 
                                                   required>
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="plan_<?php echo $plan['id']; ?>" class="font-medium text-gray-700 dark:text-gray-200">
                                                <?php echo clean($plan['name']); ?> 
                                                <?php if ($plan['price'] > 0): ?>
                                                    <span class="text-blue-600 dark:text-blue-400 font-semibold"><?php echo number_format($plan['price'], 2); ?> TL</span>
                                                <?php else: ?>
                                                    <?php echo isset($lang['admin_subscription_free']) ? $lang['admin_subscription_free'] : 'Ücretsiz'; ?>
                                                <?php endif; ?>
                                            </label>
                                            <p class="text-gray-500 dark:text-gray-400">
                                                <?php echo nl2br(clean($plan['features'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                          <div class="mb-3">
                            <label for="status" class="form-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo isset($lang['admin_subscription_status']) ? $lang['admin_subscription_status'] : 'Durum'; ?>:</label>
                            <select class="form-control mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="status" name="status" required>
                                <option value="active" <?php echo $subscription['status'] === 'active' ? 'selected' : ''; ?>><?php echo isset($lang['admin_subscription_active']) ? $lang['admin_subscription_active'] : 'Aktif'; ?></option>
                                <option value="expired" <?php echo $subscription['status'] === 'expired' ? 'selected' : ''; ?>><?php echo isset($lang['admin_subscription_expired']) ? $lang['admin_subscription_expired'] : 'Sona Ermiş'; ?></option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="end_date" class="form-label block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo isset($lang['admin_subscription_end_date']) ? $lang['admin_subscription_end_date'] : 'Bitiş Tarihi'; ?>:</label>
                            <input type="datetime-local" class="form-control mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="end_date" name="end_date" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($subscription['end_date'])); ?>" required>
                        </div>
                          <button type="submit" name="update" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i> <?php echo isset($lang['admin_subscription_update']) ? $lang['admin_subscription_update'] : 'Güncelle'; ?>
                        </button>
                        <a href="subscriptions.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-times mr-2"></i> <?php echo isset($lang['admin_subscription_cancel']) ? $lang['admin_subscription_cancel'] : 'İptal'; ?>
                        </a>
                    </form>                </div>
            </div>
        <?php else: ?>            <?php error_log("Abonelik tablosu gösterilecek"); ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_id']) ? $lang['admin_subscription_id'] : 'ID'; ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_user']) ? $lang['admin_subscription_user'] : 'Kullanıcı'; ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_plan']) ? $lang['admin_subscription_plan'] : 'Plan'; ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_status']) ? $lang['admin_subscription_status'] : 'Durum'; ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_start']) ? $lang['admin_subscription_start'] : 'Başlangıç'; ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_end']) ? $lang['admin_subscription_end'] : 'Bitiş'; ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo isset($lang['admin_subscription_actions']) ? $lang['admin_subscription_actions'] : 'İşlemler'; ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"><?php foreach ($subscriptions as $subscription): ?>
                                <tr class="<?php echo $subscription['type'] === 'premium' ? 'bg-blue-50' : ''; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">                                        <?php if ($subscription['type'] === 'premium'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                <i class="fas fa-crown text-yellow-500 mr-1"></i> <?php echo isset($lang['admin_subscription_premium']) ? $lang['admin_subscription_premium'] : 'Premium'; ?>
                                            </span>
                                        <?php else: ?>                                            <div class="flex items-center">
                                                <span class="mr-2 dark:text-gray-200"><?php echo $subscription['id']; ?></span>
                                                <?php 
                                                // Premium Hediye planı kontrolü
                                                $isPremiumGift = stripos($subscription['plan_name'], 'premium hediye') !== false;
                                                
                                                // Premium üye etiketi gösterme koşulu: Premium üye VEYA Premium Hediye planı
                                                if (!empty($subscription['is_premium']) && ($subscription['price'] > 0 || $isPremiumGift)): 
                                                ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                                    <i class="fas fa-crown text-yellow-500 mr-1"></i> <?php echo isset($lang['admin_subscription_premium_member']) ? $lang['admin_subscription_premium_member'] : 'Premium Üye'; ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo clean($subscription['username']); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo clean($subscription['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo clean($subscription['plan_name']); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo number_format($subscription['price'], 2); ?> TL</div>
                                    </td>                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($subscription['status'] === 'active'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                <i class="fas fa-check-circle mr-1"></i> <?php echo isset($lang['admin_subscription_active']) ? $lang['admin_subscription_active'] : 'Aktif'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                                <i class="fas fa-exclamation-circle mr-1"></i> <?php echo isset($lang['admin_subscription_expired']) ? $lang['admin_subscription_expired'] : 'Sona Ermiş'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php if ($subscription['start_date']): ?>
                                            <?php echo date('d.m.Y H:i', strtotime($subscription['start_date'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php if ($subscription['end_date']): ?>
                                            <?php echo date('d.m.Y H:i', strtotime($subscription['end_date'])); ?>
                                        <?php else: ?>
                                            <?php if ($subscription['price'] <= 0): ?>
                                                <span class="text-green-600 dark:text-green-400 font-medium"><?php echo isset($lang['admin_subscription_unlimited']) ? $lang['admin_subscription_unlimited'] : 'Süresiz'; ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($subscription['type'] === 'premium'): ?>
                                            <!-- Premium üyeler için özel işlem butonları -->
                                            <a href="../admin/premium_manager.php?action=edit&user_id=<?php echo $subscription['user_id']; ?>" 
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-edit mr-1"></i> Düzenle
                                            </a>
                                        <?php else: ?>
                                            <div class="flex space-x-2 justify-end">
                                                <a href="?action=edit&id=<?php echo $subscription['id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                    <i class="fas fa-edit mr-1"></i> <?php echo isset($lang['admin_subscription_edit']) ? $lang['admin_subscription_edit'] : 'Düzenle'; ?>
                                                </a>
                                                <a href="?action=delete&id=<?php echo $subscription['id']; ?>" 
                                                   onclick="return confirm('<?php echo isset($lang['admin_subscription_confirm_delete']) ? $lang['admin_subscription_confirm_delete'] : 'Bu aboneliği silmek istediğinizden emin misiniz?'; ?>')"
                                                   class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                    <i class="fas fa-trash mr-1"></i> <?php echo isset($lang['admin_subscription_delete']) ? $lang['admin_subscription_delete'] : 'Sil'; ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>            </main>
        </div>
    </div>
    <?php error_log("HTML ana yapı tamamlandı"); ?>    <!-- Yeni Abonelik Ekleme Modal -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity hidden" id="addSubscriptionModal">
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-200"><?php echo isset($lang['admin_new_subscription']) ? $lang['admin_new_subscription'] : 'Yeni Abonelik Ekle'; ?></h3>
                                <div class="mt-4">                                    <form method="post" action="subscriptions.php">
                                        <div class="mb-4">
                                            <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo isset($lang['admin_subscription_user']) ? $lang['admin_subscription_user'] : 'Kullanıcı'; ?>:</label>
                                            <select class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                                    id="user_id" name="user_id" required>
                                                <option value=""><?php echo isset($lang['admin_subscription_select_user']) ? $lang['admin_subscription_select_user'] : 'Kullanıcı Seçin'; ?></option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo clean($user['username']); ?> (<?php echo clean($user['email']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo isset($lang['admin_subscription_plan']) ? $lang['admin_subscription_plan'] : 'Plan'; ?>:</label>
                                            <select class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"  
                                                    id="plan_id" name="plan_id" required>                                                <option value=""><?php echo isset($lang['admin_subscription_select_plan']) ? $lang['admin_subscription_select_plan'] : 'Plan Seçin'; ?></option>                                                <?php foreach ($plans as $plan): ?>
                                                    <option value="<?php echo $plan['id']; ?>" 
                                                            data-is-free="<?php echo ($plan['price'] <= 0) ? '1' : '0'; ?>"
                                                            data-plan-name="<?php echo clean($plan['name']); ?>"
                                                            data-plan-price="<?php echo $plan['price']; ?>">
                                                        <?php if ($plan['price'] <= 0): ?>
                                                            <?php echo clean($plan['name']); ?> (Ücretsiz)
                                                        <?php else: ?>
                                                            <?php echo clean($plan['name']); ?> (<?php echo number_format($plan['price'], 2); ?> TL)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                          <div class="mb-4" id="end_date_container">
                                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo isset($lang['admin_subscription_end_date']) ? $lang['admin_subscription_end_date'] : 'Bitiş Tarihi'; ?>:</label>
                                            <input type="datetime-local" 
                                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                                   id="end_date" name="end_date" required>
                                        </div>
                                        
                                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                            <button type="submit" name="add" 
                                                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 dark:bg-blue-700 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm">
                                                <i class="fas fa-plus mr-1"></i> <?php echo isset($lang['admin_subscription_add']) ? $lang['admin_subscription_add'] : 'Ekle'; ?>
                                            </button>
                                            <button type="button" 
                                                    class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:w-auto sm:text-sm closeModal">
                                                <?php echo isset($lang['admin_subscription_cancel']) ? $lang['admin_subscription_cancel'] : 'İptal'; ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <script>
        console.log("JavaScript yükleniyor...");
        
        // JavaScript hata yakalama
        window.onerror = function(message, source, lineno, colno, error) {
            console.error("JavaScript Hatası:", message, "Kaynak:", source, "Satır:", lineno);
            
            // Sunucu tarafına hata bildirimi gönder
            fetch('?log_js_error=1', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message, source, lineno, colno, stack: error ? error.stack : null})
            });
            
            return false;
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM yüklendi");
            // Modal açma
            document.getElementById('addSubscriptionBtn').addEventListener('click', function() {
                var modal = document.getElementById('addSubscriptionModal');
                modal.classList.remove('hidden');
                  // Modal açılınca form değerlerini sıfırla
                var planSelect = document.getElementById('plan_id');
                var userSelect = document.getElementById('user_id');
                var endDateInput = document.getElementById('end_date');
                
                if (planSelect) planSelect.selectedIndex = 0;
                if (userSelect) userSelect.selectedIndex = 0;
                if (endDateInput) endDateInput.value = '';
                
                // Modal açılınca planları kontrol et
                setTimeout(checkPlanForDateField, 100);
            });
            
            // Modal kapatma
            document.querySelectorAll('.closeModal').forEach(function(element) {
                element.addEventListener('click', function() {
                    document.getElementById('addSubscriptionModal').classList.add('hidden');
                });
            });
            
            // Modal dışına tıklayınca kapatma
            window.addEventListener('click', function(event) {
                var modal = document.getElementById('addSubscriptionModal');
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });            // Plan değişikliğini kontrol eden fonksiyon
            function checkPlanForDateField() {
                var planSelect = document.getElementById('plan_id');
                var endDateContainer = document.getElementById('end_date_container');
                var endDateInput = document.getElementById('end_date');
                
                if (!planSelect || !endDateContainer || !endDateInput) return;
                
                var selectedOption = planSelect.options[planSelect.selectedIndex];
                if (!selectedOption) return;
                
                var planText = selectedOption.textContent;
                var planName = selectedOption.getAttribute('data-plan-name') || '';
                  // Plan türüne göre tarih alanını gizle veya göster
                var isFree = selectedOption.getAttribute('data-is-free') === '1' || 
                             planText.indexOf('Ücretsiz') !== -1 || 
                             planText.indexOf('(0.00 TL)') !== -1;
                             
                var isPremiumGift = planName.toLowerCase().indexOf('premium hediye') !== -1 || 
                                   planText.toLowerCase().indexOf('premium hediye') !== -1;
                
                var isMonthly = planName.toLowerCase().indexOf('aylık') !== -1 || 
                              planName.toLowerCase().indexOf('aylik') !== -1 ||
                              planText.toLowerCase().indexOf('aylık') !== -1;
                              
                var isYearly = planName.toLowerCase().indexOf('yıllık') !== -1 || 
                              planName.toLowerCase().indexOf('yillik') !== -1 || 
                              planText.toLowerCase().indexOf('yıllık') !== -1;
                
                var is6Month = planName.toLowerCase().indexOf('6 ay') !== -1 || 
                              planName.toLowerCase().indexOf('6ay') !== -1 ||
                              planText.toLowerCase().indexOf('6 ay') !== -1 ||
                              planName.toLowerCase().indexOf('altı ay') !== -1;
                  // Plan tipine göre otomatik süre ata
                var defaultDate = new Date();
                
                if (isPremiumGift) {
                    // Premium Hediye planı için takvimi göster, manuel tarih seçimi için
                    endDateContainer.style.display = 'block';
                    endDateInput.setAttribute('required', 'required');
                    
                    // Varsayılan olarak 30 gün sonrasını öner
                    defaultDate.setDate(defaultDate.getDate() + 30);
                    endDateInput.value = defaultDate.toISOString().slice(0, 16);
                    console.log("Premium Hediye planı seçildi. Önerilen bitiş tarihi: " + endDateInput.value);
                } else if (isFree || isMonthly || isYearly || is6Month) {
                    // Tarih alanını gizle
                    endDateContainer.style.display = 'none';
                    endDateInput.removeAttribute('required');
                    
                    if (isMonthly) {
                        // Aylık plan için 1 ay ekle
                        defaultDate.setMonth(defaultDate.getMonth() + 1);
                        endDateInput.value = defaultDate.toISOString().slice(0, 16);
                        console.log("Aylık plan seçildi. Bitiş tarihi: " + endDateInput.value);
                    } else if (isYearly) {
                        // Yıllık plan için 1 yıl ekle
                        defaultDate.setFullYear(defaultDate.getFullYear() + 1);
                        endDateInput.value = defaultDate.toISOString().slice(0, 16);
                        console.log("Yıllık plan seçildi. Bitiş tarihi: " + endDateInput.value);
                    } else if (is6Month) {
                        // 6 aylık plan için 6 ay ekle
                        defaultDate.setMonth(defaultDate.getMonth() + 6);
                        endDateInput.value = defaultDate.toISOString().slice(0, 16);
                        console.log("6 Aylık plan seçildi. Bitiş tarihi: " + endDateInput.value);
                    } else if (isFree) {
                        // Ücretsiz plan için tarih alanını temizle (NULL olarak gönderilecek)
                        endDateInput.value = '';
                    }
                } else {
                    // Diğer planlar için tarih alanını göster
                    endDateContainer.style.display = 'block';
                    endDateInput.setAttribute('required', 'required');
                    
                    // Varsayılan olarak 1 ay
                    defaultDate.setMonth(defaultDate.getMonth() + 1);
                      // Tarih formatını ayarla
                    endDateInput.value = defaultDate.toISOString().slice(0, 16);
                }
            }
            
            // Plan değiştiğinde tarih alanını göster/gizle
            var planSelect = document.getElementById('plan_id');
            if (planSelect) {
                planSelect.addEventListener('change', checkPlanForDateField);
                
                // Sayfa yüklendiğinde de kontrol et
                checkPlanForDateField();
            }
        });
    </script>

<?php include 'includes/footer.php'; ?>
<?php
// Çıktı önbelleklemeyi bitir ve gönder
ob_end_flush();
?>
