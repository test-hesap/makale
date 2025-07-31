<?php
// Çıktı tamponlamasını başlat
ob_start();

// Oturum başlat (eğer başlatılmamışsa)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'includes/config.php';

// Ban çerezlerini tekrar ayarlamak için oluşturduğumuz dosyayı dahil et
require_once 'banned-set-cookies.php';

// Dil dosyasını dahil et
require_once 'includes/functions.php';

// Sayfa başlığı
$page_title = __('account_suspended');
$meta_description = __('account_suspended_meta');

// Çerez bilgilerini logla (her zaman)
error_log("banned.php sayfası yüklendi. Mevcut çerezler: " . print_r($_COOKIE, true));

// Kullanıcı bilgileri ve ban durumu
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_COOKIE['banned_user_id']) ? $_COOKIE['banned_user_id'] : 0);
$ban_info = null;

// IP adresini al
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

// Ban bilgisini al (hem kullanıcı ID hem de IP adresinden)
if ($user_id > 0) {
    // Önce süresi dolmuş banı kontrol et
    $expired_check = $db->prepare("
        SELECT user_id FROM banned_users 
        WHERE user_id = ? AND expires_at IS NOT NULL AND expires_at <= NOW()
    ");
    $expired_check->execute([$user_id]);
    $expired_ban = $expired_check->fetch(PDO::FETCH_ASSOC);
    
    if ($expired_ban) {
        // Ban süresi dolmuş, kullanıcı durumunu güncelle
        try {
            // Kullanıcı durumunu aktif yap
            $update_user = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $update_user->execute([$user_id]);
            
            // Banned_users tablosundaki kaydı güncelle
            $update_ban = $db->prepare("UPDATE banned_users SET is_active = 0 WHERE user_id = ? AND expires_at <= NOW()");
            $update_ban->execute([$user_id]);
            
            // Çerezleri temizle
            setcookie('ban_reason', '', time() - 3600, '/');
            setcookie('banned_by', '', time() - 3600, '/');
            setcookie('ban_date', '', time() - 3600, '/');
            setcookie('ban_expires', '', time() - 3600, '/');
            setcookie('ban_is_permanent', '', time() - 3600, '/');
            setcookie('banned_user_id', '', time() - 3600, '/');
            
            // Ana sayfaya yönlendir
            $site_url = '';
            if (function_exists('getSetting')) {
                try {
                    $site_url = getSetting('site_url');
                } catch (Exception $e) {
                    error_log("getSetting fonksiyonu başarısız oldu: " . $e->getMessage());
                }
            }
            
            if (empty($site_url)) {
                $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            }
            
            header("Location: " . $site_url);
            exit;
        } catch (Exception $e) {
            error_log("Ban durumu güncellenirken hata: " . $e->getMessage());
        }
    }
    
    // Kullanıcı ID'ye göre ban bilgisini al
    $stmt = $db->prepare("
        SELECT b.*, u.username as banned_by_username 
        FROM banned_users b 
        JOIN users u ON b.banned_by = u.id
        WHERE b.user_id = ? AND (b.expires_at IS NULL OR b.expires_at > NOW())
    ");
    $stmt->execute([$user_id]);
    $ban_info = $stmt->fetch(PDO::FETCH_ASSOC);
} 

// Eğer kullanıcıya göre ban bilgisi bulunamadıysa, IP'ye göre kontrol et
if (!$ban_info && !empty($ip_address)) {
    $stmt = $db->prepare("
        SELECT b.*, u.username as banned_by_username 
        FROM banned_users b 
        JOIN users u ON b.banned_by = u.id
        WHERE b.ip_address = ? AND b.is_ip_banned = 1 AND (b.expires_at IS NULL OR b.expires_at > NOW())
    ");
    $stmt->execute([$ip_address]);
    $ban_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Eğer veritabanından ban bilgisi alınamadıysa, çerezlerden al
if (!$ban_info) {
    error_log("Veritabanından ban bilgisi alınamadı, çerezleri kontrol ediyorum...");
    $cookie_ban_info = [];
    
    // Çerezlerden gelen ban bilgilerini al
    if (isset($_COOKIE['ban_reason'])) {
        $cookie_ban_info['reason'] = $_COOKIE['ban_reason'];
    }
    
    if (isset($_COOKIE['banned_by'])) {
        $cookie_ban_info['banned_by_username'] = $_COOKIE['banned_by'];
    }
    
    if (isset($_COOKIE['ban_date'])) {
        $cookie_ban_info['created_at'] = $_COOKIE['ban_date'];
    }
    
    if (isset($_COOKIE['ban_expires'])) {
        $cookie_ban_info['expires_at'] = $_COOKIE['ban_expires'];
    }
    
    if (isset($_COOKIE['ban_is_permanent']) && $_COOKIE['ban_is_permanent'] == '1') {
        $cookie_ban_info['is_permanent'] = true;
    }
    
    // Eğer çerezlerden bilgi alındıysa ban_info'yu güncelle
    if (!empty($cookie_ban_info)) {
        $ban_info = $cookie_ban_info;
    }
}

// Debug bilgisini ekleyelim (geliştirme ortamında açık, production'da kapalı olmalı)
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Dil tercihini kaydet
$current_lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';

// Oturumu sonlandır (eğer varsa)
if (session_status() === PHP_SESSION_ACTIVE) {
    // Kullanıcı ID'sini çerezde sakla, banned sayfasında ban bilgisini gösterebilmek için
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        setcookie('banned_user_id', $_SESSION['user_id'], time() + 3600, '/');
    }
    session_unset();
    session_destroy();
}

// Yeni oturum başlat ve dili ayarla
session_start();
$_SESSION['lang'] = $current_lang;

// Header'ı dahil et
require_once 'templates/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-gray-50 dark:bg-gray-900 mt-12">
    <div class="max-w-4xl w-full">
        <!-- Ana Kart -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden transform transition-all">
            <!-- Üst Bölüm - Kırmızı Banner -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 dark:from-red-700 dark:to-red-800 p-6 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-full opacity-10">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="w-full h-full">
                        <path d="M12 1v12m0 4v6M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                
                <div class="relative z-10 flex flex-col items-center">
                    <div class="bg-white/20 dark:bg-white/10 rounded-full p-3 backdrop-blur-sm">
                        <svg class="h-14 w-14 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h1 class="mt-4 text-3xl font-bold text-white tracking-tight"><?php echo __('account_suspended'); ?></h1>
                    <p class="mt-2 text-white/90 text-center max-w-lg">
                        <?php echo __('account_suspended_message'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Detaylar Bölümü -->
            <div class="p-8">
                <?php 
                // Debug bilgilerini göster
                if (isset($debug_mode) && $debug_mode): ?>
                <div class="bg-yellow-50 dark:bg-yellow-900/30 p-4 mb-6 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <h3 class="text-yellow-700 dark:text-yellow-300 font-semibold mb-2">Debug Bilgileri:</h3>
                    <div class="overflow-auto max-h-80 text-xs font-mono bg-white dark:bg-gray-800 p-2 rounded">
                        <p>URL: <?php echo $_SERVER['REQUEST_URI']; ?></p>
                        <p>HTTP_HOST: <?php echo $_SERVER['HTTP_HOST']; ?></p>
                        <p>IP Adresi: <?php echo $ip_address; ?></p>
                        <p>Kullanıcı ID: <?php echo $user_id; ?></p>
                        <p>Session Aktif: <?php echo (session_status() === PHP_SESSION_ACTIVE) ? 'Evet' : 'Hayır'; ?></p>
                        <p>Headers Sent: <?php echo headers_sent() ? 'Evet' : 'Hayır'; ?></p>
                        
                        <p class="mt-2 font-semibold">Çerezler:</p>
                        <pre><?php 
                        if (!empty($_COOKIE)) {
                            print_r($_COOKIE);
                        } else {
                            echo "Çerez bilgisi bulunamadı!";
                        }
                        ?></pre>
                        
                        <p class="mt-2 font-semibold">Ban Bilgileri:</p>
                        <pre><?php 
                        if (!empty($ban_info)) {
                            print_r($ban_info);
                        } else {
                            echo "Ban bilgisi bulunamadı!";
                        }
                        ?></pre>
                        
                        <?php if (isset($ban_info['is_test_data']) && $ban_info['is_test_data']): ?>
                        <p class="mt-2 text-red-500 font-semibold">UYARI: Şu anda test verileri görüntüleniyor! Gerçek ban bilgileri yok.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <h4 class="text-yellow-700 dark:text-yellow-300 font-semibold">Sorun Giderme:</h4>
                        <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-2">
                            <li>Çerezler görünmüyorsa, tarayıcınızın çerezleri kabul ettiğinden emin olun.</li>
                            <li>Ban kontrolü yapılan sayfada (ban_check.php) çerezlerin doğru ayarlandığından emin olun.</li>
                            <li>Çerezleri ayarlamaya çalışırken headers_sent hatası olabilir. Sayfanın en üstünde çıktı olmadığından emin olun.</li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($ban_info): ?>
                <div class="bg-white dark:bg-gray-700 rounded-xl shadow-lg p-6 border border-gray-100 dark:border-gray-600">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                        <svg class="h-6 w-6 mr-2 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <?php echo __('suspension_details'); ?>
                    </h2>
                    
                    <?php if (isset($ban_info['reason']) && $ban_info['reason']): ?>
                    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 rounded-lg border-l-4 border-red-500">
                        <h3 class="text-lg font-semibold text-red-700 dark:text-red-300 mb-2"><?php echo __('ban_reason'); ?></h3>
                        <p class="text-red-700 dark:text-red-300 text-base">
                            <?php echo htmlspecialchars($ban_info['reason']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Ban Tarihi -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo __('suspension_date'); ?></h3>
                                <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    <?php echo isset($ban_info['created_at']) ? date('d.m.Y H:i', strtotime($ban_info['created_at'])) : 'Bilinmiyor'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Ban Süresi -->
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo __('ban_duration'); ?></h3>
                                <p class="mt-1 text-base font-semibold <?php echo (!isset($ban_info['expires_at']) || empty($ban_info['expires_at'])) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'; ?>">
                                    <?php 
                                    if (isset($ban_info['expires_at']) && $ban_info['expires_at']) {
                                        // Ban bitiş tarihi varsa
                                        $expires = strtotime($ban_info['expires_at']);
                                        $now = time();
                                        $diff = $expires - $now;
                                        
                                        if ($diff <= 0) {
                                            echo __('expired');
                                        } else {
                                            // Kalan süreyi hesapla
                                            $days = floor($diff / (60 * 60 * 24));
                                            $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                                            $minutes = floor(($diff % (60 * 60)) / 60);
                                            
                                            if ($days > 365) {
                                                $years = floor($days / 365);
                                                $days = $days % 365;
                                                echo "$years " . ($years > 1 ? __('years') : __('year')) . " ";
                                                if ($days > 0) echo "$days " . __('days') . " ";
                                            } else if ($days > 30) {
                                                $months = floor($days / 30);
                                                $days = $days % 30;
                                                echo "$months " . ($months > 1 ? __('months') : __('month')) . " ";
                                                if ($days > 0) echo "$days " . __('days') . " ";
                                            } else if ($days > 0) {
                                                echo "$days " . __('days') . " ";
                                                if ($hours > 0) echo "$hours " . __('hours') . " ";
                                            } else if ($hours > 0) {
                                                echo "$hours " . __('hours') . " ";
                                                if ($minutes > 0) echo "$minutes " . __('minutes') . " ";
                                            } else {
                                                echo "$minutes " . __('minutes') . " ";
                                            }
                                            
                                            echo "<span class=\"block mt-1 text-sm text-gray-500 dark:text-gray-400\">(". date('d.m.Y H:i', $expires) .")</span>";
                                        }
                                    } else {
                                        // Bitiş tarihi yoksa süresiz
                                        echo "<span class=\"text-red-600 dark:text-red-400 font-bold\">" . __('permanent') . "</span>";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (isset($ban_info['banned_by_username'])): ?>
                        <!-- Ban Uygulayan -->
                        <div class="flex items-start md:col-span-2">
                            <div class="flex-shrink-0 mt-1">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo __('banned_by'); ?></h3>
                                <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($ban_info['banned_by_username']); ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Alt Bilgi -->
                <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                    <p class="text-gray-600 dark:text-gray-400 text-center">
                        <?php echo __('ban_contact_info'); ?>
                    </p>
                    
                    <div class="mt-6 text-center flex flex-wrap justify-center gap-4">
                        <a href="<?php 
                        $site_url = '';
                        if (function_exists('getSetting')) {
                            try {
                                $site_url = getSetting('site_url');
                            } catch (Exception $e) {
                                error_log("getSetting fonksiyonu başarısız oldu: " . $e->getMessage());
                            }
                        }
                        
                        if (empty($site_url)) {
                            $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
                        }
                        
                        echo $site_url; 
                        ?>" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg class="mr-2 -ml-1 w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <?php echo __('return_home'); ?>
                        </a>
                        
                        <a href="<?php echo $site_url . '/iletisim.php'; ?>" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                            <svg class="mr-2 -ml-1 w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <?php echo __('contact_admin'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'templates/footer.php';

// Eğer başlatılmış bir çıktı tamponlama varsa, sonlandır
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
