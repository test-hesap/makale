<?php
require_once 'includes/config.php';
require_once 'includes/turnstile.php'; // Turnstile fonksiyonlarını dahil et

// Kullanıcı zaten giriş yapmışsa, ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Canonical URL ekleyelim
$canonical_url = getSetting('site_url') . "/forgot_password.php";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Doğrulama kontrolleri
    if (empty($email)) {
        $error = 'Lütfen e-posta adresinizi giriniz.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
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
                    // Eski tokenları temizle
                    $cleanStmt = $db->prepare("DELETE FROM password_resets WHERE email = ? OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $cleanStmt->execute([$email]);
                    
                    // Yeni token oluştur
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Token'ı veritabanına kaydet
                    $tokenStmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $tokenStmt->execute([$email, $token, $expires]);
                    
                    // Şifre sıfırlama bağlantısını oluştur
                    $site_url = getSetting('site_url');
                    if (empty($site_url) || $site_url == '/') {
                        // Site URL ayarlanmamışsa protokol ve host bilgisini alalım
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $site_url = $protocol . $_SERVER['HTTP_HOST'];
                    }
                    
                    // Eğer site_url / ile bitiyorsa, fazladan / eklememek için kontrol edelim
                    if (substr($site_url, -1) === '/') {
                        $resetLink = $site_url . "reset_password.php?token=" . $token;
                    } else {
                        $resetLink = $site_url . "/reset_password.php?token=" . $token;
                    }
                    
                    // E-posta içeriğini hazırla
                    $subject = getSetting('site_title') . " - Şifre Sıfırlama";
                    $message = "
                    <html>
                    <head>
                        <title>Şifre Sıfırlama</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #f5f5f5; padding: 10px; border-radius: 5px; }
                            .button { display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                            .footer { margin-top: 30px; font-size: 12px; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>" . getSetting('site_title') . " - Şifre Sıfırlama</h2>
                            </div>
                            <p>Merhaba <strong>{$user['username']}</strong>,</p>
                            <p>Hesabınız için bir şifre sıfırlama talebinde bulundunuz. Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayınız:</p>
                            <p><a href='{$resetLink}' class='button'>Şifremi Sıfırla</a></p>
                            <p>Veya aşağıdaki bağlantıyı tarayıcınıza kopyalayabilirsiniz:</p>
                            <p style='word-break: break-all;'><a href='{$resetLink}'>{$resetLink}</a></p>
                            <p>Bu bağlantı <strong>1 saat</strong> boyunca geçerli olacaktır.</p>
                            <p>Eğer bu isteği siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
                            <div class='footer'>
                                <p>Saygılarımızla,<br><strong>" . getSetting('site_title') . " Ekibi</strong></p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // E-postayı gönder
                    if (sendEmail($email, $subject, $message)) {
                        $success = 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen e-postanızı kontrol ediniz.';
                    } else {
                        $error = 'E-posta gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
                    }
                } else {
                    // Güvenlik için kullanıcı bulunamasa bile başarı mesajı göster
                    $success = 'Eğer bu e-posta adresi sistemimizde kayıtlıysa, şifre sıfırlama bağlantısı gönderilecektir.';
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
    <title>Şifremi Unuttum - <?php echo getSetting('site_title'); ?></title>
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
                <h2 class="text-2xl font-bold dark:text-white">Şifremi Unuttum</h2>
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
                <p class="mt-2">
                    <a href="login.php" class="text-green-700 font-bold hover:text-green-800">
                        Giriş sayfasına dön
                    </a>
                </p>
            </div>
            <?php else: ?>
            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">E-posta Adresiniz</label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <?php if (isTurnstileEnabled('login')): ?>
                <div class="my-4">
                    <?php echo turnstileWidget(); ?>
                </div>
                <?php endif; ?>
                
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Şifre Sıfırlama Bağlantısı Gönder
                </button>
                
                <div class="text-center text-gray-600 dark:text-gray-300">
                    <a href="login.php" class="text-blue-500 hover:text-blue-600">Giriş sayfasına dön</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
