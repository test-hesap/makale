<?php
// Bu dosya session_init.php dosyasına eklenen bir include dosyasıdır
// Her sayfa yüklendiğinde kullanıcının banlı olup olmadığını kontrol eder

// Kullanıcı giriş yapmışsa ban kontrolü yap
if (isset($_SESSION['user_id'])) {
    $check_user_id = $_SESSION['user_id'];
    
    // Önce banned_users tablosunda kayıt var mı kontrol et
    $ban_check = $db->prepare("
        SELECT * FROM banned_users 
        WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $ban_check->execute([$check_user_id]);
    $is_banned = $ban_check->fetch(PDO::FETCH_ASSOC);
    
    // Kullanıcının status durumunu kontrol et
    $status_check = $db->prepare("SELECT status FROM users WHERE id = ?");
    $status_check->execute([$check_user_id]);
    $user_status = $status_check->fetch(PDO::FETCH_COLUMN);
    
    // Süresi dolmuş banları kontrol et ve kullanıcı durumunu güncelle
    if ($user_status === 'banned') {
        $expired_ban_check = $db->prepare("
            SELECT * FROM banned_users 
            WHERE user_id = ? AND expires_at IS NOT NULL AND expires_at <= NOW()
        ");
        $expired_ban_check->execute([$check_user_id]);
        $expired_ban = $expired_ban_check->fetch(PDO::FETCH_ASSOC);
        
        if ($expired_ban) {
            // Ban süresi dolmuş, kullanıcı durumunu güncelle
            try {
                // Kullanıcı durumunu aktif yap
                $update_user = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $update_user->execute([$check_user_id]);
                
                // Banned_users tablosundaki kaydı güncelle veya sil
                $update_ban = $db->prepare("UPDATE banned_users SET is_active = 0 WHERE user_id = ? AND expires_at <= NOW()");
                $update_ban->execute([$check_user_id]);
                
                // Kullanıcı durumunu güncelle
                $user_status = 'active';
                
                error_log("Ban süresi dolmuş kullanıcı durumu güncellendi: User ID: " . $check_user_id);
            } catch (Exception $e) {
                error_log("Ban durumu güncellenirken hata: " . $e->getMessage());
            }
        }
    }
    
    // Eğer kullanıcı banlıysa session'ı sonlandır ve banned sayfasına yönlendir
    if ($is_banned || $user_status === 'banned') {
        // Ban bilgilerini al (eğer ban kaydı varsa)
        $ban_info = null;
        if ($is_banned) {
            $ban_info = $is_banned;
        } else {
            $ban_stmt = $db->prepare("
                SELECT b.*, u.username as banned_by_username 
                FROM banned_users b 
                LEFT JOIN users u ON b.banned_by = u.id
                WHERE b.user_id = ? AND (b.expires_at IS NULL OR b.expires_at > NOW())
            ");
            $ban_stmt->execute([$check_user_id]);
            $ban_info = $ban_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
            // Ban bilgilerini çerezlere kaydet (banned.php sayfasında kullanabilmek için)
            if ($ban_info) {
                // Çerez süresini ban süresine göre ayarla
                if (isset($ban_info['expires_at']) && !empty($ban_info['expires_at'])) {
                    // Ban süresi belirtilmişse, çerez süresini ban bitiş tarihine ayarla
                    $ban_end_timestamp = strtotime($ban_info['expires_at']);
                    $cookie_expiry = $ban_end_timestamp;
                    
                    // Çerez süresinin geçerli olup olmadığını kontrol et (geçmişte olmamalı)
                    if ($cookie_expiry < time()) {
                        $cookie_expiry = time() + 3600; // Eğer ban süresi geçmişse, 1 saat süreyle çerezleri sakla
                    }
                } else {
                    // Ban süresizse, uzun bir süre için çerez ayarla (örneğin 1 yıl)
                    $cookie_expiry = time() + (86400 * 365); // 365 gün = 1 yıl
                }
                
                // Çerezleri ayarla (Eski format)
                error_log("Çerezler ayarlanıyor. Süre: " . date('Y-m-d H:i:s', $cookie_expiry));
                
                // Her bir çerez için ayrı kontrole yap ve başarılı olup olmadığını logla
                $reason = !empty($ban_info['reason']) ? $ban_info['reason'] : 'Belirtilmemiş';
                $set_reason = setcookie('ban_reason', $reason, $cookie_expiry, '/');
                error_log("ban_reason çerezi ayarlandı: " . ($set_reason ? "Başarılı" : "Başarısız"));
                
                $banned_by = !empty($ban_info['banned_by_username']) ? $ban_info['banned_by_username'] : 'Sistem';
                $set_by = setcookie('banned_by', $banned_by, $cookie_expiry, '/');
                error_log("banned_by çerezi ayarlandı: " . ($set_by ? "Başarılı" : "Başarısız"));
                
                $ban_date = !empty($ban_info['created_at']) ? $ban_info['created_at'] : date('Y-m-d H:i:s');
                $set_date = setcookie('ban_date', $ban_date, $cookie_expiry, '/');
                error_log("ban_date çerezi ayarlandı: " . ($set_date ? "Başarılı" : "Başarısız"));
                
                $ban_expires = !empty($ban_info['expires_at']) ? $ban_info['expires_at'] : '';
                $set_expires = setcookie('ban_expires', $ban_expires, $cookie_expiry, '/');
                error_log("ban_expires çerezi ayarlandı: " . ($set_expires ? "Başarılı" : "Başarısız"));
                
                $is_permanent = (!isset($ban_info['expires_at']) || $ban_info['expires_at'] === null) ? '1' : '0';
                $set_permanent = setcookie('ban_is_permanent', $is_permanent, $cookie_expiry, '/');
                error_log("ban_is_permanent çerezi ayarlandı: " . ($set_permanent ? "Başarılı" : "Başarısız"));
                
                $set_user_id = setcookie('banned_user_id', $check_user_id, $cookie_expiry, '/');
                error_log("banned_user_id çerezi ayarlandı: " . ($set_user_id ? "Başarılı" : "Başarısız"));
                
                // Debug için
                error_log("Ayarlanan çerezler:");
                error_log("banned_user_id: " . $check_user_id);
                error_log("ban_reason: " . $reason);
                error_log("banned_by: " . $banned_by);
                error_log("ban_date: " . $ban_date);
                error_log("ban_expires: " . $ban_expires);
                error_log("ban_is_permanent: " . (!isset($ban_info['expires_at']) || $ban_info['expires_at'] === null) ? '1' : '0');
            }        // Oturum sonlandır
        session_unset();
        session_destroy();
        
        // Remember token çerezini temizle
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Site URL'sini al
        $site_url = '';
        if (function_exists('getSetting')) {
            try {
                $site_url = getSetting('site_url');
            } catch (Exception $e) {
                // getSetting fonksiyonu başarısız olursa
                error_log("getSetting fonksiyonu başarısız oldu: " . $e->getMessage());
            }
        }
        
        // Site URL hala boşsa, server bilgilerinden oluştur
        if (empty($site_url)) {
            $site_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
        }
        
        // Başlıklar gönderilmemişse header ile yönlendir, aksi halde JavaScript ile yönlendir
        if (!headers_sent()) {
            header("Location: " . $site_url . "/banned.php");
            exit;
        } else {
            // Eğer başlıklar zaten gönderilmişse, JavaScript ile yönlendir
            echo '<script type="text/javascript">window.location.href="' . $site_url . '/banned.php";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . $site_url . '/banned.php"></noscript>';
            echo 'Hesabınız askıya alınmıştır. Yönlendiriliyorsunuz...';
            exit;
        }
    }
}
?>
