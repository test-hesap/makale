<?php
require_once 'includes/config.php';
require_once 'includes/turnstile.php'; // Turnstile fonksiyonlarını dahil et
include_once 'includes/auto_unban.php'; // Ban süresi dolmuş kullanıcıları otomatik aktif et

// Kullanıcı zaten giriş yapmışsa, ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Canonical URL ekleyelim
$canonical_url = getSetting('site_url') . "/login";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurunuz.';
    } else {
        // Turnstile kontrolü
        $turnstileEnabled = isTurnstileEnabled('login');
        if ($turnstileEnabled) {
            $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
            if (!verifyTurnstile($turnstileToken)) {
                $error = 'Spam koruması doğrulaması başarısız oldu. Lütfen tekrar deneyiniz.';
            }
        }
        
        // Eğer Turnstile hatası yoksa devam et
        if (empty($error)) {
            $stmt = $db->prepare("SELECT id, username, email, password, role, approved, can_post, status, is_premium, premium_until FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                // Kullanıcı banlı mı kontrol et
                if ($user['status'] === 'banned' || isUserBanned($user['id'])) {
                    $error = 'Hesabınız yönetici tarafından askıya alınmıştır.';
                    
                    // Çıktı tamponlamayı başlat
                    ob_start();
                    
                    // Ban çerezlerini ayarlamak için oluşturduğumuz dosyayı dahil et
                    require_once 'login-ban-cookies.php';
                    
                    // Site URL'sini al
                    $site_url = '';
                    if (function_exists('getSetting')) {
                        try {
                            $site_url = getSetting('site_url');
                        } catch (Exception $e) {
                            // getSetting fonksiyonu başarısız olursa hata mesajını logla
                            error_log("getSetting fonksiyonu başarısız oldu: " . $e->getMessage());
                        }
                    }
                    
                    // Site URL hala boşsa, server bilgilerinden oluştur
                    if (empty($site_url)) {
                        $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
                    }
                    
                    // Tamponlamayı temizle
                    ob_end_clean();
                    
                    // Kullanıcıyı ban sayfasına yönlendir
                    header("Location: " . $site_url . "/banned.php");
                    exit;
                }
                
                // Kullanıcının avatar bilgisini de alalım
                $avatarStmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
                $avatarStmt->execute([$user['id']]);
                $avatarInfo = $avatarStmt->fetch(PDO::FETCH_ASSOC);
                // NULL veya boş değer olduğunda NULL olarak bırakıyoruz
                $avatar = !empty($avatarInfo['avatar']) ? $avatarInfo['avatar'] : null;// Tüm önemli kullanıcı bilgilerini session'a kaydet
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['can_post'] = $user['can_post'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['avatar'] = $avatar; // Avatar bilgisini session'a ekle            // Premium bilgilerini ve sosyal medya bilgilerini kontrol et ve kaydet
                $userInfoStmt = $db->prepare("SELECT is_premium, premium_until, bio, location, website, twitter, facebook, instagram, linkedin, youtube, tiktok, github, last_login, register_date FROM users WHERE id = ?");
                $userInfoStmt->execute([$user['id']]);
                $userInfo = $userInfoStmt->fetch(PDO::FETCH_ASSOC);
                
                // Sosyal medya ve biyografi bilgilerini session'a kaydet
                $_SESSION['user_bio'] = $userInfo['bio'] ?? '';
                $_SESSION['user_location'] = $userInfo['location'] ?? '';
                $_SESSION['user_website'] = $userInfo['website'] ?? '';
                $_SESSION['user_twitter'] = $userInfo['twitter'] ?? '';
                $_SESSION['user_facebook'] = $userInfo['facebook'] ?? '';
                $_SESSION['user_instagram'] = $userInfo['instagram'] ?? '';
                $_SESSION['user_linkedin'] = $userInfo['linkedin'] ?? '';
                $_SESSION['user_youtube'] = $userInfo['youtube'] ?? '';
                $_SESSION['user_tiktok'] = $userInfo['tiktok'] ?? '';
                $_SESSION['user_github'] = $userInfo['github'] ?? '';
                $_SESSION['register_date'] = $userInfo['register_date'] ?? '';
                $_SESSION['last_login'] = $userInfo['last_login'] ?? '';
                
                // Premium durumunu tutarlı hale getir
                $isPremium = (int)($premiumInfo['is_premium'] ?? 0);
                $premiumUntil = $premiumInfo['premium_until'] ?? null;
                $currentDate = date('Y-m-d');
                
                // Premium tarihi geçmiş mi kontrol et
                if ($isPremium && $premiumUntil && $premiumUntil < $currentDate) {
                    // Premium süresi dolmuş, otomatik düzelt
                    $updateStmt = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    $isPremium = 0;
                    $premiumUntil = null;
                    error_log("Login: Premium üyelik süresi dolmuş, sıfırlandı - Kullanıcı: " . $user['username']);
                }
                
                // Premium sütunları tutarsızsa düzelt
                if ($isPremium && !$premiumUntil) {
                    // is_premium=1 ama tarih yok, düzelt
                    $updateStmt = $db->prepare("UPDATE users SET is_premium = 0 WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    $isPremium = 0;
                    error_log("Login: Tutarsız premium durum düzeltildi - Kullanıcı: " . $user['username']);
                } elseif (!$isPremium && $premiumUntil && $premiumUntil >= $currentDate) {
                    // is_premium=0 ama geçerli tarih var, düzelt
                    $updateStmt = $db->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    $isPremium = 1;
                    error_log("Login: Tutarsız premium durum düzeltildi (tarih geçerli) - Kullanıcı: " . $user['username']);
                } elseif (!$isPremium && $premiumUntil && $premiumUntil < $currentDate) {
                    // is_premium=0 ve geçmiş tarih, tarihi temizle
                    $updateStmt = $db->prepare("UPDATE users SET premium_until = NULL WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    $premiumUntil = null;
                    error_log("Login: Geçmiş premium tarih temizlendi - Kullanıcı: " . $user['username']);
                }
                
                // Session'a güncel değerleri kaydet
                $_SESSION['is_premium'] = $isPremium;
                $_SESSION['premium_until'] = $premiumUntil;
                
                // Debug için log'a kaydet
                error_log("Login: Premium bilgileri yüklendi - Kullanıcı: " . $user['username'] . 
                        ", is_premium: " . $_SESSION['is_premium'] . 
                        ", premium_until: " . ($_SESSION['premium_until'] ?? 'null'));            // Veritabanında e-posta adresi varsa güncelle ve son giriş tarihini güncelle
                if (!empty($user['email'])) {
                    $update = $db->prepare("UPDATE users SET email = :email, last_login = NOW() WHERE id = :id");
                    $update->execute(['email' => $user['email'], 'id' => $user['id']]);
                    
                    // Session'da e-posta adresini özel olarak da kaydedelim
                    $_SESSION['user_email'] = $user['email'];
                    
                    // Debug için e-posta bilgisini log'a kaydedelim
                    error_log("Login: E-posta bilgisi session'a kaydedildi - Kullanıcı: " . $user['username'] . ", email: " . $user['email']);
                    
                    // Session'da son giriş tarihini de güncelle
                    $_SESSION['last_login'] = date('Y-m-d H:i:s');
                } else {
                    // Sadece son giriş tarihini güncelle
                    // Son giriş ve IP bilgisini güncelle
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    $update = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = :ip WHERE id = :id");
                    $update->execute([
                        'id' => $user['id'],
                        'ip' => $ip_address
                    ]);
                    
                    // Session'da son giriş tarihini de güncelle
                    $_SESSION['last_login'] = date('Y-m-d H:i:s');
                }
                
                // Beni hatırla seçeneği işaretlenmişse token oluştur
                if (isset($_POST['remember_me']) && $_POST['remember_me']) {
                    $token = generateRememberToken($user['id']);
                    
                    // Debug log
                    error_log("Login: Beni hatırla token oluşturuldu - User: " . $user['username'] . ", Token: " . substr($token, 0, 10) . "...");
                    
                    // Çerez ayarlarını al
                    $cookie_params = session_get_cookie_params();
                    
                    // Çerezi 30 gün süreyle ayarla (httponly ve secure)
                    setcookie(
                        'remember_token',
                        $token,
                        [
                            'expires' => time() + (86400 * 30), // 30 gün
                            'path' => '/', // Tüm site için geçerli
                            'domain' => $cookie_params['domain'],
                            'secure' => $cookie_params['secure'],
                            'httponly' => true, // JavaScript erişimini engeller
                            'samesite' => 'Lax' // CSRF koruması için
                        ]
                    );
                }
                
                // Tüm kullanıcıları ana sayfaya yönlendir
                header('Location: /');
            } else {
                $error = 'Geçersiz kullanıcı adı veya şifre.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - <?php echo getSetting('site_title'); ?></title>
    <?php include 'templates/header.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {},
            },
        }
    </script>
    <style>
        /* Firefox tarafından eklenen sarı arka planı önleme */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active,
        input:-moz-autofill,
        input:-moz-autofill:hover,
        input:-moz-autofill:focus {
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: inherit !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        /* Koyu mod için */
        .dark input:-webkit-autofill,
        .dark input:-webkit-autofill:hover,
        .dark input:-webkit-autofill:focus,
        .dark input:-webkit-autofill:active,
        .dark input:-moz-autofill,
        .dark input:-moz-autofill:hover,
        .dark input:-moz-autofill:focus {
            -webkit-box-shadow: 0 0 0 30px #292929 inset !important;
            -webkit-text-fill-color: white !important;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-800">
    <div class="min-h-[80vh] flex items-start justify-center pt-12">
        <div class="max-w-md w-full bg-white dark:bg-gray-700 rounded-lg shadow-lg p-8">
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold dark:text-white">Giriş Yap</h2>
                <p class="text-gray-600 dark:text-gray-300">
                    <?php echo getSetting('site_title'); ?>
                </p>
            </div>
            
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
            
            <form method="post" class="space-y-6" autocomplete="off">
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Kullanıcı Adı</label>
                    <input type="text" name="username" required autocomplete="username"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600">
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Şifre</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="remember_me" name="remember_me" 
                           class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600">
                    <label for="remember_me" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Beni Hatırla</label>
                </div>
                
                <?php if (isTurnstileEnabled('login')): ?>
                <div class="my-4">
                    <?php echo turnstileWidget(); ?>
                </div>
                <?php endif; ?>
                
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Giriş Yap
                </button>
                
                <div class="text-center text-gray-600 dark:text-gray-300 mt-4">
                    <a href="forgot_password.php" class="text-blue-500 hover:text-blue-600">
                        Şifremi Unuttum
                    </a>
                </div>
                
                <div class="text-center text-gray-600 dark:text-gray-300 mt-2">
                    Hesabınız yok mu? 
                    <a href="register.php" class="text-blue-500 hover:text-blue-600">Kayıt Ol</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
