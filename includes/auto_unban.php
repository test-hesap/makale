<?php
// Bu dosya ban süresi dolmuş kullanıcıları otomatik olarak aktif eder
// index.php, login.php ve banned.php gibi temel sayfalara include edilebilir

// Son kontrol zamanını çerezde saklayalım (çok sık çalışmasını engellemek için)
$last_check_cookie = isset($_COOKIE['auto_unban_last_check']) ? $_COOKIE['auto_unban_last_check'] : 0;
$current_time = time();

// Her 15 dakikada bir çalıştır (900 saniye)
if (($current_time - $last_check_cookie) > 900) {
    // Son kontrol zamanını güncelle
    setcookie('auto_unban_last_check', $current_time, time() + 86400, '/'); // 1 gün geçerli çerez
    
    try {
        global $db;
        
        // Süresi dolmuş ban kayıtlarını bul
        $stmt = $db->prepare("
            SELECT bu.user_id 
            FROM banned_users bu
            JOIN users u ON bu.user_id = u.id
            WHERE bu.expires_at IS NOT NULL 
            AND bu.expires_at <= NOW()
            AND u.status = 'banned'
        ");
        $stmt->execute();
        $expired_bans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($expired_bans)) {
            error_log("Auto Unban: " . count($expired_bans) . " adet süresi dolmuş ban bulundu.");
            
            // Her bir kullanıcı için ban kaldırma işlemini gerçekleştir
            foreach ($expired_bans as $ban) {
                // Kullanıcı durumunu güncelle
                $update_user = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $update_user->execute([$ban['user_id']]);
                
                // Banned_users tablosundaki kaydı güncelle (silmek yerine is_active=0 yap)
                $update_ban = $db->prepare("UPDATE banned_users SET is_active = 0 WHERE user_id = ? AND expires_at <= NOW()");
                $update_ban->execute([$ban['user_id']]);
                
                error_log("Auto Unban: Kullanıcı ID " . $ban['user_id'] . " ban süresi dolduğu için aktif edildi.");
            }
        }
    } catch (Exception $e) {
        error_log("Auto Unban hatası: " . $e->getMessage());
    }
}
?>
