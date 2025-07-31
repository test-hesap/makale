<?php
// Özel ban çerezleri yardımcı sayfası
// Bu dosya login.php sayfasından dahil edilir

// Hata ayıklama
error_log("login-ban-cookies.php çalıştı - Kullanıcı ID: " . $user['id']);

// Ban bilgilerini al
$ban_stmt = $db->prepare("
    SELECT b.*, u.username as banned_by_username 
    FROM banned_users b 
    LEFT JOIN users u ON b.banned_by = u.id
    WHERE b.user_id = ? AND (b.expires_at IS NULL OR b.expires_at > NOW())
");
$ban_stmt->execute([$user['id']]);
$ban_info = $ban_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ban_info) {
                // Çerez süresini ban süresine göre ayarla
                if (isset($ban_info['expires_at']) && !empty($ban_info['expires_at'])) {
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
                
                // Çerezleri ayarla ve başarılı olup olmadığını kontrol et
                $reason = !empty($ban_info['reason']) ? $ban_info['reason'] : 'Belirtilmemiş';
                $set_reason = setcookie('ban_reason', $reason, $cookie_expiry, '/');
                
                $banned_by = !empty($ban_info['banned_by_username']) ? $ban_info['banned_by_username'] : 'Sistem';
                $set_by = setcookie('banned_by', $banned_by, $cookie_expiry, '/');
                
                $ban_date = !empty($ban_info['created_at']) ? $ban_info['created_at'] : date('Y-m-d H:i:s');
                $set_date = setcookie('ban_date', $ban_date, $cookie_expiry, '/');
                
                $ban_expires = !empty($ban_info['expires_at']) ? $ban_info['expires_at'] : '';
                $set_expires = setcookie('ban_expires', $ban_expires, $cookie_expiry, '/');
                
                $is_permanent = (!isset($ban_info['expires_at']) || $ban_info['expires_at'] === null) ? '1' : '0';
                $set_permanent = setcookie('ban_is_permanent', $is_permanent, $cookie_expiry, '/');
                
                $set_user_id = setcookie('banned_user_id', $user['id'], $cookie_expiry, '/');
                
                // Debug için çerezlerin ayarlanıp ayarlanmadığını kontrol et
                error_log("Ban çerezleri login-ban-cookies.php sayfasında ayarlandı:");
                error_log("banned_user_id: " . $user['id'] . " - Ayarlandı mı: " . ($set_user_id ? "Evet" : "Hayır"));
                error_log("ban_reason: " . $reason . " - Ayarlandı mı: " . ($set_reason ? "Evet" : "Hayır"));
                error_log("banned_by: " . $banned_by . " - Ayarlandı mı: " . ($set_by ? "Evet" : "Hayır"));
                error_log("ban_date: " . $ban_date . " - Ayarlandı mı: " . ($set_date ? "Evet" : "Hayır"));
                error_log("ban_expires: " . $ban_expires . " - Ayarlandı mı: " . ($set_expires ? "Evet" : "Hayır"));
                error_log("ban_is_permanent: " . $is_permanent . " - Ayarlandı mı: " . ($set_permanent ? "Evet" : "Hayır"));
                
                // Eğer herhangi bir çerez ayarlanamadıysa hata mesajı ekle
                if (!$set_reason || !$set_by || !$set_date || !$set_expires || !$set_permanent || !$set_user_id) {
                    error_log("UYARI: Bazı çerezler ayarlanamadı. Headers may already be sent hatası olabilir.");
                }
            } else {
                error_log("Ban bilgisi bulunamadı - Kullanıcı ID: " . $user['id']);
            }
            
            // Site URL'sini al
            $site_url = '';
            if (function_exists('getSetting')) {
                try {
                    $site_url = getSetting('site_url');
                } catch (Exception $e) {
                    error_log("getSetting fonksiyonu başarısız oldu: " . $e->getMessage());
                }
            }
            
            // Site URL hala boşsa, server bilgilerinden oluştur
            if (empty($site_url)) {
                $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            }
?>
