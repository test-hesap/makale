<?php
require_once 'includes/config.php';
require_once 'includes/turnstile.php'; // Turnstile fonksiyonlarını dahil et

// Kullanıcı zaten giriş yapmışsa, ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Canonical URL ekleyelim
$canonical_url = getSetting('site_url') . "/register";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    // Doğrulama kontrolleri
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurunuz.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    } elseif ($password !== $password_confirm) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        // Turnstile kontrolü
        $turnstileEnabled = isTurnstileEnabled('register');
        if ($turnstileEnabled) {
            $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
            if (!verifyTurnstile($turnstileToken)) {
                $error = 'Spam koruması doğrulaması başarısız oldu. Lütfen tekrar deneyiniz.';
            }
        }
        
        // Eğer Turnstile hatası yoksa devam et
        if (empty($error)) {
            try {
                // Kullanıcı adı ve e-posta kontrolü
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.';
                } else {                // Yeni kullanıcı oluştur
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Varsayılan avatar dosyası adı (sadece dosya adı, tam yol değil)
                    $default_avatar = 'default-avatar.jpg';
                    
                    // Varsayılan avatar dizinini kontrol et ve oluştur
                    $avatar_dir = 'uploads/avatars';
                    if (!is_dir($avatar_dir)) {
                        $old_umask = umask(0);
                        $mkdir_result = @mkdir($avatar_dir, 0777, true);
                        umask($old_umask);
                        
                        if (!$mkdir_result) {
                            error_log("Kayıt sırasında avatar dizini oluşturulamadı: " . $avatar_dir);
                            error_log("PHP işlem kullanıcısı: " . exec('whoami'));
                            error_log("Dizin izinleri (üst klasör): " . substr(sprintf('%o', fileperms(dirname($avatar_dir))), -4));
                        } else {
                            // LiteSpeed ve Apache için klasör izinlerini ayarla
                            @chmod($avatar_dir, 0777);
                            error_log("Avatar dizini kayıt sırasında oluşturuldu: " . $avatar_dir);
                        }
                    }
                      // Varsayılan avatar dosyasını oluştur (config.php'deki base64 kodlu avatar kullanılarak)
                    $default_avatar_path = $avatar_dir . '/' . $default_avatar;
                    if (!file_exists($default_avatar_path)) {
                        // Base64 kodlu avatar verisini çöz ve dosyaya kaydet
                        // DEFAULT_AVATAR_BASE64'ten data:image/png;base64, kısmını kaldır
                        $base64_str = DEFAULT_AVATAR_BASE64;
                        $base64_content = substr($base64_str, strpos($base64_str, ',') + 1);
                        $avatar_data = base64_decode($base64_content);
                        
                        $write_result = @file_put_contents($default_avatar_path, $avatar_data);
                        if (!$write_result) {
                            error_log("Default avatar dosyası oluşturulamadı: " . $default_avatar_path);
                        } else {
                            @chmod($default_avatar_path, 0666);
                            error_log("Default avatar dosyası base64'ten oluşturuldu: " . $default_avatar_path);
                        }
                    }
                    
                    // Kullanıcı IP adresini al
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    
                    $stmt = $db->prepare("INSERT INTO users (username, email, password, avatar, role, approved, can_post, status, last_ip) 
                                        VALUES (:username, :email, :password, :avatar, 'user', FALSE, FALSE, 'active', :ip_address)");
                    
                    $stmt->execute([
                        'username' => $username,
                        'email' => $email,
                        'password' => $password_hash,
                        'avatar' => $default_avatar,
                        'ip_address' => $ip_address
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = 'Kayıt başarıyla tamamlandı! Şimdi giriş yapabilirsiniz. Üyeliğiniz onaylandıktan sonra makale ekleyebileceksiniz.';
                        
                        // Admin bildirim sistemi için yeni üye bildirimi ekle
                        $user_id = $db->lastInsertId();
                        
                        // Admin bildirim sistemi yüklü mü kontrol et
                        if (file_exists('admin/includes/notifications.php')) {
                            require_once 'admin/includes/notifications.php';
                            // Yeni üye bildirimi ekle - profil resmi olmadan daha sade bir mesaj
                            $message = "$username adlı yeni bir kullanıcı kaydoldu";
                            $link = "/admin/users.php?id=$user_id";
                            // Yeni üye bildirimlerinde user_id son parametreye NULL vererek avatar kullanımını engelle
                            addAdminNotification('new_user', NULL, $message, $link, $user_id);
                        }
                    } else {
                        throw new Exception('Kayıt işlemi başarısız oldu.');
                    }
                }
            } catch(PDOException $e) {
                $error = 'Bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}
?>
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
                <h2 class="text-2xl font-bold dark:text-white">Kayıt Ol</h2>
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
                <p class="mt-2">                    <a href="/login" class="text-green-700 font-bold hover:text-green-800">
                        Giriş yapmak için tıklayın
                    </a>
                </p>
            </div>
            <?php else: ?>
            <form method="post" class="space-y-6" autocomplete="off">
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Kullanıcı Adı</label>
                    <input type="text" name="username" required autocomplete="username"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">E-posta</label>
                    <input type="email" name="email" required autocomplete="email"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Şifre</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600"
                           minlength="6">
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-200 mb-2">Şifre (Tekrar)</label>
                    <input type="password" name="password_confirm" required autocomplete="new-password"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-[#292929] dark:text-white dark:border-gray-600"
                           minlength="6">
                </div>
                
                <?php if (isTurnstileEnabled('register')): ?>
                <div class="my-4">
                    <?php echo turnstileWidget(); ?>
                </div>
                <?php endif; ?>
                
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Kayıt Ol
                </button>
                  <div class="text-center text-gray-600 dark:text-gray-300">
                    Zaten hesabınız var mı? 
                    <a href="/login" class="text-blue-500 hover:text-blue-600">Giriş Yap</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
