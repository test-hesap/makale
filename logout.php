<?php
require_once 'includes/config.php';

// Kullanıcı çevrimdışı olarak işaretle
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Beni hatırla token'ı sil
        if (isset($_COOKIE['remember_token'])) {
            // Veritabanından token'ı sil
            $deleteStmt = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
            $deleteStmt->execute([$_SESSION['user_id']]);
            
            // Çerezi temizle
            $cookie_params = session_get_cookie_params();
            
            // Modern format ile çerezi sil
            setcookie(
                'remember_token',
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => $cookie_params['domain'],
                    'secure' => $cookie_params['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            error_log("Logout: Remember token çerezi temizlendi");
        }
        
        // Debug için log
        error_log("Logout: Kullanıcı çıkış yaptı - ID: " . $_SESSION['user_id'] . ", Username: " . ($_SESSION['username'] ?? 'bilinmiyor'));
    } catch (PDOException $e) {
        error_log("Çıkış yaparken kullanıcı durumu güncellenirken hata: " . $e->getMessage());
    }
}

// Tüm session değişkenlerini temizle
$_SESSION = array();

// Session çerezini sil
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Oturumu sonlandır
session_destroy();

// Yeni bir oturum başlat (misafir olarak)
session_start();
session_regenerate_id(true);

// Ana sayfaya yönlendir
header('Location: /');
exit;
