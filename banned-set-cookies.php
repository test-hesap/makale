<?php
// login.php ve ban_check.php dosyalarında kullanılan ayarlanmış çerezleri buraya aktaralım
// Bu dosya banned.php sayfasının başında dahil edilecek

// Hata ayıklama
error_log("banned-set-cookies.php dosyası yüklendi.");

// Çıktı tamponlamasını kontrol et ve başlatılmamışsa başlat
if (ob_get_level() == 0) {
    ob_start();
}

// Kullanıcı giriş yapmadan geliyorsa $_SESSION['user_id'] boş olur
// Çerezler aracılığıyla veritabanından ban bilgilerini alalım
if (isset($_COOKIE['banned_user_id']) && !empty($_COOKIE['banned_user_id'])) {
    $banned_user_id = $_COOKIE['banned_user_id'];
    
    // Veritabanı bağlantısı var mı kontrol et
    if (isset($db)) {
        error_log("banned-set-cookies.php: Veritabanı bağlantısı mevcut, ban bilgileri alınıyor - Kullanıcı ID: $banned_user_id");
        
        // Ban bilgilerini veritabanından al
        $ban_stmt = $db->prepare("
            SELECT b.*, u.username as banned_by_username 
            FROM banned_users b 
            LEFT JOIN users u ON b.banned_by = u.id
            WHERE b.user_id = ? AND (b.expires_at IS NULL OR b.expires_at > NOW())
        ");
        $ban_stmt->execute([$banned_user_id]);
        $db_ban_info = $ban_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_ban_info) {
            error_log("banned-set-cookies.php: Veritabanından ban bilgileri bulundu");
            
            // Çerez süresini belirle (1 yıl)
            $cookie_expiry = time() + (86400 * 365); 
            
            // Eğer ban süre ile sınırlıysa, ban süresine göre ayarla
            if (isset($db_ban_info['expires_at']) && !empty($db_ban_info['expires_at'])) {
                $ban_end_timestamp = strtotime($db_ban_info['expires_at']);
                if ($ban_end_timestamp > time()) {
                    $cookie_expiry = $ban_end_timestamp;
                }
            }
            
            // Çerezleri ayarla
            $reason = !empty($db_ban_info['reason']) ? $db_ban_info['reason'] : 'Belirtilmemiş';
            setcookie('ban_reason', $reason, $cookie_expiry, '/');
            
            $banned_by = !empty($db_ban_info['banned_by_username']) ? $db_ban_info['banned_by_username'] : 'Sistem';
            setcookie('banned_by', $banned_by, $cookie_expiry, '/');
            
            $ban_date = !empty($db_ban_info['created_at']) ? $db_ban_info['created_at'] : date('Y-m-d H:i:s');
            setcookie('ban_date', $ban_date, $cookie_expiry, '/');
            
            $ban_expires = !empty($db_ban_info['expires_at']) ? $db_ban_info['expires_at'] : '';
            setcookie('ban_expires', $ban_expires, $cookie_expiry, '/');
            
            $is_permanent = (!isset($db_ban_info['expires_at']) || $db_ban_info['expires_at'] === null) ? '1' : '0';
            setcookie('ban_is_permanent', $is_permanent, $cookie_expiry, '/');
            
            setcookie('banned_user_id', $banned_user_id, $cookie_expiry, '/');
            
            // Debug
            error_log("banned-set-cookies.php: Çerezler yeniden ayarlandı");
        } else {
            error_log("banned-set-cookies.php: Veritabanında ban bilgisi bulunamadı - Kullanıcı ID: $banned_user_id");
        }
    } else {
        error_log("banned-set-cookies.php: Veritabanı bağlantısı bulunamadı");
    }
} else {
    error_log("banned-set-cookies.php: banned_user_id çerezi bulunamadı");
}

// Tamponlamayı bitir
ob_end_flush();
?>
