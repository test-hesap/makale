<?php
require_once 'includes/config.php';
require_once 'includes/turnstile.php'; // Turnstile fonksiyonlarını dahil et

// Kullanıcı zaten giriş yapmışsa, ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Canonical URL ekleyelim
$canonical_url = getSetting('site_url') . "/reset_password.php";

$error = '';
$success = '';
$validToken = false;
$email = '';

// Token kontrolü
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Token'ın geçerli olup olmadığını kontrol et
        $stmt = $db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reset && !$reset['used'] && strtotime($reset['expires_at']) > time()) {
            $validToken = true;
            $email = $reset['email'];
        } else {
            $error = 'Geçersiz veya süresi dolmuş şifre sıfırlama bağlantısı.';
        }
    } catch(Exception $e) {
        error_log("Şifre sıfırlama token kontrolü hatası: " . $e->getMessage());
        $error = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
    }
} else {
    $error = 'Geçersiz şifre sıfırlama bağlantısı.';
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    // Doğrulama kontrolleri
    if (empty($password) || empty($password_confirm)) {
        $error = 'Lütfen tüm alanları doldurunuz.';
    } elseif ($password !== $password_confirm) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
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
            try {
                // Kullanıcının varlığını kontrol et
                $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Şifreyi güncelle
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$password_hash, $user['id']]);
                    
                    // Token'ı kullanıldı olarak işaretle
                    $tokenStmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                    $tokenStmt->execute([$token]);
                    
                    $success = 'Şifreniz başarıyla güncellendi. Şimdi giriş yapabilirsiniz.';
                    $validToken = false; // Formu gizlemek için
                } else {
                    $error = 'Kullanıcı bulunamadı.';
                }
            } catch(Exception $e) {
                error_log("Şifre sıfırlama hatası: " . $e->getMessage());
                $error = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
            }
        }
    }
}
?>
<?php include 'templates/header.php'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - <?php echo getSetting('site_title'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {},
            },
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-800">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white dark:bg-gray-700 rounded-lg shadow-lg p-8">
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold dark:text-white">Şifre Sıfırlama</h2>
                <p class="text-gray-600 dark:text-gray-300">
                    <?php echo getSetting('site_title'); ?>
                </p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
                <?php if (!$validToken): ?>
                <p class="mt-2">
                    <a href="forgot_password.php" class="text-red-700 font-bold hover:text-red-800">
                        Yeni bir şifre sıfırlama bağlantısı almak için tıklayın
                    </a>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
                <p class="mt-2">
                    <a href="login.php" class="text-green-700 font-bold hover:text-green-800">
                        Giriş yapmak için tıklayın
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
            <form method="post" class="space-y-6">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Yeni Şifre</label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600">
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Şifre Tekrar</label>
                    <input type="password" name="password_confirm" required 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600">
                </div>
                
                <?php if (isTurnstileEnabled('login')): ?>
                <div class="my-4">
                    <?php echo turnstileWidget(); ?>
                </div>
                <?php endif; ?>
                
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Şifreyi Sıfırla
                </button>
            </form>
            <?php elseif (!$success): ?>
            <div class="text-center text-gray-600 dark:text-gray-300">
                <a href="forgot_password.php" class="text-blue-500 hover:text-blue-600">
                    Yeni bir şifre sıfırlama bağlantısı alın
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
