<?php
// Session kontrolü ve kullanıcı işlemleri - sadece PHP mantığı
// Bu dosya HTML çıktısından ÖNCE include edilecek

// Gerekli dosyaları dahil et
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ads.php';
include_once __DIR__ . '/auto_unban.php'; // Ban süresi dolmuş kullanıcıları otomatik aktif et

// Session zaten config.php'de başlatıldı

// IP ban kontrolü
$ip_address = $_SERVER['REMOTE_ADDR'];
if (isIPBanned($ip_address)) {
    // IP adresi banlanmışsa oturumu sonlandır ve banlandı sayfasına yönlendir
    session_destroy();
    header("Location: " . SITE_URL . "/banned.php");
    exit;
}

// Beni hatırla çerezini kontrol et (kullanıcı giriş yapmamışsa)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    global $db; // $db değişkenine global olarak erişim sağla
    $token = $_COOKIE['remember_token'];
    
    error_log("Session Init: 'remember_token' çerezi bulundu - Token: " . substr($token, 0, 10) . "...");
    
    // Token'ı doğrula
    $user_id = validateRememberToken($token);
    
    if ($user_id) {
        error_log("Session Init: Token geçerli, kullanıcı ID: " . $user_id . " için oturum başlatılıyor");
        
        // Token geçerli, kullanıcının TÜM bilgilerini al ve oturum başlat
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Kullanıcının banlı olup olmadığını kontrol et
            if ($user['status'] === 'banned' || isUserBanned($user['id'])) {
                session_destroy();
                header("Location: " . SITE_URL . "/banned.php");
                exit;
            }
            
            // Temel kullanıcı bilgilerini kaydet
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'] ?? null;
            $_SESSION['approved'] = $user['approved'] ?? 1;
            $_SESSION['can_post'] = $user['can_post'] ?? 1;
            $_SESSION['status'] = $user['status'] ?? 'active';
            $_SESSION['is_premium'] = $user['is_premium'] ?? 0;
            $_SESSION['premium_until'] = $user['premium_until'] ?? null;
            
            // Ekstra profil bilgilerini kaydet
            $_SESSION['user_bio'] = $user['bio'] ?? '';
            $_SESSION['user_location'] = $user['location'] ?? '';
            $_SESSION['user_website'] = $user['website'] ?? '';
            $_SESSION['user_twitter'] = $user['twitter'] ?? '';
            $_SESSION['user_facebook'] = $user['facebook'] ?? '';
            $_SESSION['user_instagram'] = $user['instagram'] ?? '';
            $_SESSION['user_linkedin'] = $user['linkedin'] ?? '';
            $_SESSION['user_youtube'] = $user['youtube'] ?? '';
            $_SESSION['user_tiktok'] = $user['tiktok'] ?? '';
            $_SESSION['user_github'] = $user['github'] ?? '';
            $_SESSION['register_date'] = $user['register_date'] ?? '';
            $_SESSION['last_login'] = date('Y-m-d H:i:s'); // Şimdiki zaman
            
            // Kullanıcıyı çevrimiçi olarak işaretle
            try {
                $updateStmt = $db->prepare("UPDATE users SET is_online = 1, last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
            } catch (Exception $e) {
                error_log("Session Init: Kullanıcı durumu güncellenirken hata - " . $e->getMessage());
            }
            
            error_log("Session Init: Kullanıcı beni hatırla token ile giriş yaptı - " . $user['username'] . " (ID: " . $user['id'] . ")");
            
            // Token'ı yenile (süresi uzat)
            $newToken = generateRememberToken($user_id);
            
            // Çerez ayarlarını al
            $cookie_params = session_get_cookie_params();
            
            // Yeni çerezi ayarla
            setcookie(
                'remember_token',
                $newToken,
                [
                    'expires' => time() + (86400 * 30), // 30 gün
                    'path' => '/',
                    'domain' => $cookie_params['domain'],
                    'secure' => $cookie_params['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
            error_log("Session Init: Remember token yenilendi - User: " . $user['username']);
        }
    } else {
        // Geçersiz veya süresi dolmuş token, çerezi temizle
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

// Debug için oturum bilgilerini logla
error_log("Session Init: Session ID = " . session_id());
error_log("Session Init: Session user_id = " . ($_SESSION['user_id'] ?? 'yok'));

// Kullanıcı giriş yapmışsa bilgilerini güncelle
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    global $db;
    
    // Kullanıcının mevcut bilgilerini al
    $stmt = $db->prepare("SELECT id, username, email, role, avatar, is_premium, premium_until FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Avatar yönetimi
        ensureUserAvatar($user['id']);
        
        // Oturum bilgilerini güncelle
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_premium'] = $user['is_premium'];
        $_SESSION['premium_until'] = $user['premium_until'];
        
        error_log("Session Init: Kullanıcı bilgileri yenilendi - " . $user['username']);
    } else {
        // Kullanıcı veritabanında bulunamadı - oturumu temizle
        error_log("Session Init: Kullanıcı ID: " . $_SESSION['user_id'] . " veritabanında bulunamadı!");
        session_unset();
        session_destroy();
    }
    
    if (isset($_SESSION['user_id'])) {
        updateUserActivity($_SESSION['user_id']);
        
        // Premium üyelik bilgilerini kontrol et
        if (!isset($_SESSION['premium_checked'])) {
            $checkStmt = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
            $checkStmt->execute([$_SESSION['user_id']]);
            $premiumInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($premiumInfo) {
                $_SESSION['is_premium'] = (int)$premiumInfo['is_premium'];
                $_SESSION['premium_until'] = $premiumInfo['premium_until'];
                $_SESSION['premium_checked'] = true;
            }
        }
    }
} else {
    // Kullanıcı giriş yapmamışsa misafir olarak izle
    trackGuests();
    
    // Bot kontrolü ve izlemesi yap
    trackBots();
}

// CSRF token oluştur (oturum başlatıldıktan sonra)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
