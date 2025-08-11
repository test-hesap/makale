<?php
// Session başlatma - en üstte olmalı (sitemap gibi XML çıktısı olan sayfalar hariç)
if (!defined('NO_SESSION_START') && session_status() === PHP_SESSION_NONE) {
    // Session çerezinin tüm sitede çalışmasını sağlamak için path parametresini ayarla
    // Oturum süresini 30 gün olarak ayarla (beni hatırla çerezi ile aynı süre)
    $lifetime = 60 * 60 * 24 * 30; // 30 gün (saniye cinsinden)
    $session_params = session_get_cookie_params();
    session_set_cookie_params(
        $lifetime, 
        '/', // Tüm site için geçerli
        $session_params['domain'],
        $session_params['secure'],
        $session_params['httponly']
    );
    session_start();
    
    // Debug için oturum detaylarını logla
    error_log("Config: Session başlatıldı - ID: " . session_id() . ", Cookie lifetime: " . $lifetime);
}

date_default_timezone_set('Europe/Istanbul');

// Dil ayarlarını yapılandır
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Dil dosyasını yükle
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
require_once __DIR__ . '/lang/' . $currentLang . '.php';

// Türkçe dil desteği için locale ayarını yapılandırma
if ($currentLang == 'tr') {
    setlocale(LC_TIME, 'tr_TR.UTF-8', 'tr_TR', 'tr', 'turkish');
} else {
    setlocale(LC_TIME, 'en_US.UTF-8', 'en_US', 'en', 'english');
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'makale');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->query("SET CHARSET utf8mb4");
    
    // Helper fonksiyonlarını dahil et
    require_once __DIR__ . '/functions.php';
    
    // Reklam fonksiyonlarını dahil et (eğer varsa)
    if (file_exists(__DIR__ . '/ads.php')) {
        require_once __DIR__ . '/ads.php';
    }
    
    // Kullanıcı ban kontrolünü her sayfada yap
    if (session_status() === PHP_SESSION_ACTIVE && !defined('NO_BAN_CHECK')) {
        require_once __DIR__ . '/ban_check.php';
    }
    
    // Bakım modu kontrolü (admin sayfaları, API endpoint'leri ve bazı özel sayfalar hariç)
    if (!defined('NO_MAINTENANCE_CHECK')) {
        $current_script = basename($_SERVER['PHP_SELF']);
        $excluded_scripts = ['maintenance.php', 'maintenance_check.php', 'logout.php'];
        $is_admin_path = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
        $is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Admin kullanıcısı mı kontrol et
        $is_admin_user = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        
        if (!in_array($current_script, $excluded_scripts) && !$is_admin_path && !$is_ajax_request && !$is_admin_user) {
            try {
                $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('maintenance_mode', 'maintenance_end_time', 'maintenance_countdown_enabled')");
                $stmt->execute();
                
                $settings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['key']] = $row['value'];
                }
                
                $maintenance_mode = $settings['maintenance_mode'] ?? '0';
                $maintenance_end_time = $settings['maintenance_end_time'] ?? '';
                $maintenance_countdown_enabled = $settings['maintenance_countdown_enabled'] ?? '0';
                
                // Eğer geri sayım aktifse ve süre dolmuşsa bakım modunu kapat
                if ($maintenance_mode === '1' && $maintenance_countdown_enabled === '1' && !empty($maintenance_end_time)) {
                    $end_time = new DateTime($maintenance_end_time);
                    $now = new DateTime();
                    
                    if ($now >= $end_time) {
                        // Bakım modunu kapat
                        $update_stmt = $db->prepare("UPDATE settings SET value = '0' WHERE `key` = 'maintenance_mode'");
                        $update_stmt->execute();
                        $maintenance_mode = '0';
                        
                        // Log kaydı oluştur
                        $log_message = date('Y-m-d H:i:s') . " - Bakım modu otomatik olarak kapatıldı (config). Bitiş zamanı: " . $maintenance_end_time . "\n";
                        @file_put_contents(__DIR__ . '/../logs/maintenance.log', $log_message, FILE_APPEND | LOCK_EX);
                    }
                }
                
                if ($maintenance_mode === '1') {
                    // Hala bakım modundaysa maintenance.php'ye yönlendir
                    header('Location: /maintenance.php');
                    exit;
                }
            } catch (Exception $e) {
                // Hata durumunda sessizce devam et
                error_log("Maintenance check error: " . $e->getMessage());
            }
        }
    }
    
    // Site URL'sini al (canlı veya localhost)
    $site_url = getSetting('site_url') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);    // Avatar için hem lokal yol hem de tam URL tanımla
    define('DEFAULT_AVATAR_PATH', 'uploads/avatars/default-avatar.jpg');
    define('DEFAULT_AVATAR_URL', $site_url . '/' . 'uploads/avatars/default-avatar.jpg');
    
    // Base64 kodlu bir varsayılan avatar tanımla (dosya sisteminden bağımsız çalışır)
    define('DEFAULT_AVATAR_BASE64', 'data:image/png;base64,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');
    
    // Avatar dizini oluşturma girişimi (çalışsa da çalışmasa da sorun değil artık)
    $avatar_dir = 'uploads/avatars';
    if (!is_dir($avatar_dir)) {
        @mkdir($avatar_dir, 0777, true);
        @chmod($avatar_dir, 0777);
    }
    
    // Articles tablosuna featured_image sütunu ekleme
    // Sütun var mı kontrol et, yoksa ekle
    $checkColumn = $db->query("SHOW COLUMNS FROM articles LIKE 'featured_image'");
    if ($checkColumn->rowCount() == 0) {
        $db->exec("ALTER TABLE articles ADD COLUMN featured_image VARCHAR(255) DEFAULT NULL AFTER content");
    }
    
    // Users tablosuna premium üyelik sütunu ekleme
    // Kullanıcı premium durumu sütunu kontrolü
    $checkIsPremium = $db->query("SHOW COLUMNS FROM users LIKE 'is_premium'");
    if ($checkIsPremium->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN is_premium TINYINT(1) DEFAULT 0 AFTER role");
    }
    
    // Premium bitiş tarihi sütunu kontrolü
    $checkPremiumUntil = $db->query("SHOW COLUMNS FROM users LIKE 'premium_until'");
    if ($checkPremiumUntil->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN premium_until DATE DEFAULT NULL AFTER is_premium");
    }
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Helper fonksiyonlar
function clean($data, $preserve_quotes = false) {
    if ($preserve_quotes) {
        // Özel karakterleri koruyarak sadece XSS için temel temizlik yap
        return trim($data);
    } else {
        // Standart temizleme - tüm özel karakterleri HTML entities'e dönüştür
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// functions.php içinde getSetting tanımlı olduğundan burada tekrar tanımlamıyoruz
// Eğer burada tanımlanmış getSetting kullanılıyorsa ve functions.php içindekinden farklıysa,
// functions.php içindeki tanımı buradaki sorgu yapısına uygun güncellemeniz gerekir.

// Kullanıcının premium üye olup olmadığını kontrol eder
function checkPremiumStatus($user_id) {
    global $db;
    
    // Admin kullanıcılar her zaman premium içeriğe erişebilir
    if (isAdmin()) {
        return true;
    }
    
    $stmt = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Kullanıcı premium değilse veya premium süresi dolmuşsa false döner
    if (!$user || !$user['is_premium'] || ($user['premium_until'] && $user['premium_until'] < date('Y-m-d'))) {
        return false;
    }
    
    return true;
}

function generateSlug($str) {
    $str = mb_strtolower($str, 'UTF-8');
    
    // Türkçe karakterleri değiştir
    $tr = array('ş','Ş','ı','İ','ğ','Ğ','ü','Ü','ö','Ö','ç','Ç');
    $eng = array('s','s','i','i','g','g','u','u','o','o','c','c');
    $str = str_replace($tr, $eng, $str);
    
    // Sadece harf, rakam ve tire kalacak şekilde temizle
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    
    // Boşlukları tire ile değiştir
    $str = preg_replace('/\s+/', '-', trim($str));
    
    // Birden fazla tireyi tek tireye indir
    $str = preg_replace('/-+/', '-', $str);
    
    return trim($str, '-');
}

// Kullanıcı avatarını güncelleme ve oturum yönetimi için yardımcı fonksiyon
function ensureUserAvatar($user_id) {
    global $db;
    
    try {
        // Her zaman veritabanından en güncel avatar bilgisini alalım
        $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['avatar'])) {
            // Avatar değeri tam yol içeriyorsa, sadece dosya adını alıyoruz
            $avatar = $result['avatar'];
            if (strpos($avatar, 'uploads/avatars/') === 0) {
                $avatar = str_replace('uploads/avatars/', '', $avatar);
                
                // Veritabanını da hemen güncelle - tam yol kullanımını düzelt
                $updateStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $updateStmt->execute([$avatar, $user_id]);
                
                error_log("ensureUserAvatar: Avatar yolu veritabanında düzeltildi - $avatar [UserID: $user_id]");
            }
            
            // Mevcut session'daki avatarla farklıysa session'ı güncelle
            if (!isset($_SESSION['avatar']) || $_SESSION['avatar'] !== $avatar) {
                $_SESSION['avatar'] = $avatar;
                error_log("ensureUserAvatar: Session avatar güncellendi - $avatar [UserID: $user_id]");
            }
        } else {
            // Avatar null veya boş ise varsayılan avatar kullanılacak
            $_SESSION['avatar'] = 'default-avatar.jpg';
            
            // Veritabanında da varsayılan avatarı ayarla
            if ($result && ($result['avatar'] === null || $result['avatar'] === '')) {
                $updateStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $updateStmt->execute(['default-avatar.jpg', $user_id]);
                error_log("ensureUserAvatar: Boş avatar varsayılana ayarlandı [UserID: $user_id]");
            }
        }
          // Avatarın var olup olmadığını kontrol et
        // Çağrılan konumun admin dizini olup olmadığını kontrol et
        $in_admin = (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '/admin/') !== false || 
                    strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '\\admin\\') !== false);
        
        // Admin dizinindeyse veya değilse doğru yolu kullan
        $base_path = $in_admin ? '../' : '';
        $avatar_file = $base_path . 'uploads/avatars/' . $_SESSION['avatar'];

        error_log("ensureUserAvatar: Avatar dosyası kontrol ediliyor - Yol: $avatar_file, Admin dizininde: " . ($in_admin ? 'Evet' : 'Hayır'));
        
        if (!file_exists($avatar_file)) {
            // Avatarın varlığını kontrol et, yoksa varsayılan avatarı kopyala
            if ($_SESSION['avatar'] != 'default-avatar.jpg') {
                error_log("ensureUserAvatar: Avatar dosyası bulunamadı, varsayılana ayarlanıyor - $_SESSION[avatar] [UserID: $user_id]");
                $_SESSION['avatar'] = 'default-avatar.jpg';
                
                // Veritabanını da güncelle
                $updateStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $updateStmt->execute(['default-avatar.jpg', $user_id]);
            }
            
            // Varsayılan avatarın da olup olmadığını kontrol et
            if (!file_exists('uploads/avatars/default-avatar.jpg')) {
                // uploads/avatars klasörünün varlığını kontrol et
                if (!is_dir('uploads/avatars')) {
                    mkdir('uploads/avatars', 0777, true);
                }
                
                // varsayılan avatarı kopyala veya oluştur
                if (file_exists('assets/img/default-avatar.jpg')) {
                    copy('assets/img/default-avatar.jpg', 'uploads/avatars/default-avatar.jpg');
                    error_log("ensureUserAvatar: Varsayılan avatar kopyalandı");
                }
            }
        }
        
        return $_SESSION['avatar'];
    } catch (Exception $e) {
        error_log("ensureUserAvatar: HATA - " . $e->getMessage());
        // Hata durumunda varsayılan avatar döndür
        $_SESSION['avatar'] = 'default-avatar.jpg';
        return $_SESSION['avatar'];
    }
}

// CSRF koruması
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Premium üyelik kontrolü - geliştirilmiş ve güvenli
function isPremium($forceRefresh = false) {
    // Kullanıcı giriş yapmamışsa premium değil
    if (!isset($_SESSION['user_id'])) {
        error_log("isPremium: user_id session'da yok");
        return false;
    }
    
    // Debug bilgisi
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'bilinmeyen';
    $sessionIsPremium = $_SESSION['is_premium'] ?? 'tanımlanmamış';
    $sessionPremiumUntil = $_SESSION['premium_until'] ?? 'tanımlanmamış';
    
    error_log("isPremium kontrolü - user_id: $userId, username: $username, " . 
              "session is_premium: $sessionIsPremium, " .
              "session premium_until: $sessionPremiumUntil");
    
    // Her seferinde veritabanından en güncel bilgileri kontrol et - güvenilir olması için
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT id, username, is_premium, premium_until FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Kullanıcı bulunamadı
        if (!$user) {
            error_log("isPremium: UYARI! Kullanıcı ID: $userId veritabanında bulunamadı");
            $_SESSION['is_premium'] = 0;
            $_SESSION['premium_until'] = null;
            return false;
        }
        
        $dbIsPremium = (int)($user['is_premium'] ?? 0);
        $dbPremiumUntil = $user['premium_until'] ?? null;
        $dbUsername = $user['username'] ?? $username;
        
        error_log("isPremium: Veritabanı kontrolü - is_premium: $dbIsPremium, premium_until: " . ($dbPremiumUntil ?? 'null'));
        
        // Premium durumu ve tarih kontrolü
        $isPremiumActive = false;
        $currentDate = date('Y-m-d');
        $needsDatabaseUpdate = false;
        
        // Veritabanındaki premium durumunu kontrol et
        if ($dbIsPremium) {
            if ($dbPremiumUntil) {
                if ($dbPremiumUntil >= $currentDate) {
                    // Premium üyelik geçerli
                    error_log("isPremium: Premium üyelik aktif, bitiş tarihi: $dbPremiumUntil, bugün: $currentDate");
                    $isPremiumActive = true;
                } else {
                    // Premium süresi dolmuş, otomatik temizleme yap
                    error_log("isPremium: Premium üyelik süresi dolmuş, bitiş: $dbPremiumUntil, bugün: $currentDate");
                    try {
                        $updateStmt = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                        $updateStmt->execute([$userId]);
                        error_log("isPremium: Süresi dolmuş premium üyelik temizlendi, kullanıcı: $dbUsername (ID: $userId)");
                        $needsDatabaseUpdate = true;
                    } catch (PDOException $e) {
                        error_log("isPremium güncelleme hatası: " . $e->getMessage());
                    }
                }
            } else {
                // premium_until null ise ve is_premium=1 ise, hatalı durum temizle
                error_log("isPremium: Hatalı premium durum tespiti - is_premium=1 ama premium_until=null, kullanıcı: $dbUsername");
                try {
                    $updateStmt = $db->prepare("UPDATE users SET is_premium = 0 WHERE id = ?");
                    $updateStmt->execute([$userId]);
                    error_log("isPremium: Hatalı premium durum düzeltildi, kullanıcı: $dbUsername (ID: $userId)");
                    $needsDatabaseUpdate = true;
                } catch (PDOException $e) {
                    error_log("isPremium hata düzeltme hatası: " . $e->getMessage());
                }
            }
        } else {
            // is_premium=0 ama premium_until değeri varsa, tutarsız durum düzelt
            if ($dbPremiumUntil) {
                // Eğer premium_until bugünden sonra ise ve is_premium=0 ise, is_premium=1 yap
                if ($dbPremiumUntil >= $currentDate) {
                    error_log("isPremium: Tutarsız premium durum - is_premium=0 ama geçerli premium_until var: $dbPremiumUntil");
                    try {
                        $updateStmt = $db->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
                        $updateStmt->execute([$userId]);
                        $isPremiumActive = true;
                        error_log("isPremium: Tutarsız premium durum düzeltildi, is_premium=1 yapıldı. Kullanıcı: $dbUsername");
                        $needsDatabaseUpdate = true;
                    } catch (PDOException $e) {
                        error_log("isPremium tutarsızlık düzeltme hatası: " . $e->getMessage());
                    }
                } else {
                    // Geçmiş premium tarih varsa NULL yap
                    error_log("isPremium: Geçmiş premium tarih temizleniyor: $dbPremiumUntil");
                    try {
                        $updateStmt = $db->prepare("UPDATE users SET premium_until = NULL WHERE id = ?");
                        $updateStmt->execute([$userId]);
                        error_log("isPremium: Geçmiş premium tarih temizlendi, kullanıcı: $dbUsername (ID: $userId)");
                        $needsDatabaseUpdate = true;
                    } catch (PDOException $e) {
                        error_log("isPremium tarih temizleme hatası: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Değişiklik yapıldıysa veya zorla yenileme isteniyorsa güncellenmiş bilgileri al
        if ($needsDatabaseUpdate || $forceRefresh) {
            $refreshStmt = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
            $refreshStmt->execute([$userId]);
            $refreshedUser = $refreshStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($refreshedUser) {
                $dbIsPremium = (int)($refreshedUser['is_premium'] ?? 0);
                $dbPremiumUntil = $refreshedUser['premium_until'] ?? null;
                
                // Gerçek premium durumunu belirle
                $isPremiumActive = ($dbIsPremium && $dbPremiumUntil && $dbPremiumUntil >= $currentDate);
                
                error_log("isPremium: Güncellenmiş veritabanı verileri - is_premium: $dbIsPremium, premium_until: " . 
                         ($dbPremiumUntil ?? 'null') . ", isPremiumActive: " . ($isPremiumActive ? 'true' : 'false'));
            }
        }
        
        // Session'da ve veritabanında tutarsızlık var mı kontrol et
        if ($dbIsPremium != ($sessionIsPremium == 'tanımlanmamış' ? -1 : (int)$sessionIsPremium) || 
            $dbPremiumUntil != ($sessionPremiumUntil == 'tanımlanmamış' ? '!' : $sessionPremiumUntil)) {
            error_log("isPremium: Session ile veritabanı arasında tutarsızlık tespit edildi, session güncelleniyor. " .
                     "DB: is_premium=$dbIsPremium, premium_until=" . ($dbPremiumUntil ?? 'null') . " | " .
                     "SESSION: is_premium=$sessionIsPremium, premium_until=" . ($sessionPremiumUntil ?? 'null'));
        }
        
        // Session'a en son durumu kaydet
        $_SESSION['is_premium'] = $isPremiumActive ? 1 : 0;
        $_SESSION['premium_until'] = $isPremiumActive ? $dbPremiumUntil : null;
        
        error_log("isPremium sonuç: " . ($isPremiumActive ? 'Premium aktif' : 'Premium değil') .
                 ", session güncellendi - is_premium: " . $_SESSION['is_premium'] .
                 ", premium_until: " . ($_SESSION['premium_until'] ?? 'null'));
        
        return $isPremiumActive;
        
    } catch (PDOException $e) {
        error_log("isPremium veritabanı hatası: " . $e->getMessage());
        // Eğer veritabanı hatası varsa session bilgilerine güvenme
        if ($sessionIsPremium !== 'tanımlanmamış') {
            return (bool)(int)$sessionIsPremium;
        }
        return false;
    } catch (Exception $e) {
        error_log("isPremium beklenmeyen hata: " . $e->getMessage());
        return false;
    }
}

// Premium üyelik süresini al
function getPremiumUntil() {
    if (isset($_SESSION['premium_until'])) {
        return $_SESSION['premium_until'];
    }
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT premium_until FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['premium_until']) {
        $_SESSION['premium_until'] = $user['premium_until'];
        return $user['premium_until'];
    }
    
    return null;
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('CSRF token doğrulaması başarısız.');
    }
    return true;
}

// Auth fonksiyonları
function checkAuth($requireAdmin = false) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }

    if ($requireAdmin && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
        header('Location: /');
        exit;
    }

    return true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $db;
    if (!isLoggedIn()) return null;
    
    $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateSetting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");    $stmt->execute([$key, $value, $value]);
}

function showArticleAd($index) {
    // Admin kontrolü
    if (function_exists('isAdmin') && isAdmin()) {
        return false; // Admin kullanıcılara reklam gösterme
    }
    
    // Premium üyelik kontrolü
    if (function_exists('isPremium') && isPremium()) {
        return false; // Premium üyelere reklam gösterme
    }
    
    // Reklam durumunu kontrol et - hata durumunda varsayılan olarak aktif kabul et
    $ad_status = 'active'; // Varsayılan olarak aktif - misafirlere ve normal üyelere göstermek için
    
    try {
        $setting_ad_status = getSetting('ad_status');
        if ($setting_ad_status) {
            $ad_status = $setting_ad_status;
        }
    } catch (Exception $e) {
        error_log("showArticleAd: Reklam durumu alınırken hata: " . $e->getMessage());
    }
    
    // Reklam devre dışı bırakılmışsa gösterme
    if ($ad_status !== 'active') {
        return false;
    }

    // Reklam aralığını kontrol et
    $interval = (int)getSetting('ad_article_interval');
    if ($interval < 1) {
        $interval = 3; // varsayılan değer
    }

    // Bu indekste reklam gösterilmeli mi?
    if (($index + 1) % $interval === 0) {
        $ad_code = getSetting('ad_between_articles');
        if (!empty($ad_code)) {
            echo sprintf('
                <div class="ad-container ad-between-articles my-4 col-span-full">
                    <!-- Reklam: Makaleler Arası -->
                    %s
                </div>
            ', $ad_code);
            return true;
        }
        return false;
    }
    
    return false;
}

// Not: updateUserActivity fonksiyonu artık functions.php'de tanımlı

// Offline kullanıcıları güncelleme
function updateOfflineUsers($minutes) {
    global $db;
    
    // Geçerli zamanı al
    $now = new DateTime();
    
    // Belirtilen dakikadan daha uzun süredir aktif olmayan kullanıcıları güncelle
    $threshold = $now->modify("-$minutes minutes")->format('Y-m-d H:i:s');
    
    try {
        $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE last_activity < ?");
        $stmt->execute([$threshold]);
    } catch (PDOException $e) {
        error_log("updateOfflineUsers hatası: " . $e->getMessage());
    }
}    // Kullanıcı çevrimiçi ise aktiviteyi güncelle
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $db->prepare("UPDATE users SET last_activity = NOW(), is_online = 1 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log("Kullanıcı aktivitesi güncellenirken hata: " . $e->getMessage());
        }
    }
    
    // Offline kullanıcıları güncelle (5 dakikadan uzun süredir aktif olmayan)
    try {
        $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Offline kullanıcılar güncellenirken hata: " . $e->getMessage());
    }

// Premium üyeliklerin günlük kontrolü
function cleanExpiredPremiumUsers() {
    global $db;
    $today = date('Y-m-d');
    
    try {
        // Temizleme öncesi sayı
        $countBefore = $db->query("SELECT COUNT(*) AS count FROM users WHERE is_premium = 1 AND premium_until < '$today'")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($countBefore > 0) {
            // Süresi dolmuş premium üyelikleri temizle
            $stmt = $db->prepare("UPDATE users SET is_premium = 0 WHERE premium_until < ?");
            $stmt->execute([$today]);
            $affectedRows = $stmt->rowCount();
            
            error_log("cleanExpiredPremiumUsers: $affectedRows adet süresi dolmuş premium üyelik temizlendi. Tarih: $today");
        }
    } catch (PDOException $e) {
        error_log("cleanExpiredPremiumUsers hatası: " . $e->getMessage());
    }
}

// Her sayfanın yüklenmesinde %1 ihtimalle kontrolü çalıştır (her kullanıcı için değil, rastgele)
if (mt_rand(1, 100) === 1) {
    cleanExpiredPremiumUsers();
}

// AI Bot Ayarları
define('AI_BOT_ENABLED', true);
define('AI_BOT_DEFAULT_PROVIDER', 'gemini'); // gemini, grok, huggingface
define('AI_BOT_LOG_FILE', __DIR__ . '/../logs/ai_bot.log');

// API Keys - Artık admin panelinden yönetiliyor (ai_bot_settings.php)
// Fallback olarak burada tanımlanabilir, öncelik veritabanı ayarlarında
define('GEMINI_API_KEY', ''); // Google Gemini API Key - Varsayılan değer
define('GROK_API_KEY', ''); // xAI Grok API Key - Varsayılan değer
define('HUGGINGFACE_API_KEY', ''); // Hugging Face API Key - Varsayılan değer

// AI Bot Resim API Ayarları - Artık admin panelinden yönetiliyor
define('GOOGLE_SEARCH_API_KEY', ''); // Google Custom Search API Key - Varsayılan değer
define('GOOGLE_SEARCH_ENGINE_ID', ''); // Google Custom Search Engine ID - Varsayılan değer
define('UNSPLASH_ACCESS_KEY', ''); // Unsplash Access Key - Varsayılan değer
// Unsplash API anahtarı almak için: https://unsplash.com/developers

// AI Bot konu kategorileri - genişletilmiş liste
$ai_bot_topics = [
    // Teknoloji kategorileri
    'teknoloji' => [
        'yapay zeka ve günlük hayat', 'teknoloji trendleri', 'dijital dönüşüm stratejileri', 
        'siber güvenlik ipuçları', 'bulut teknolojileri', 'blockchain ve kripto paralar', 
        'büyük veri analizi', 'nesnelerin interneti (IoT)', 'artırılmış gerçeklik uygulamaları', 
        'sanal gerçeklik teknolojileri', 'akıllı ev sistemleri', 'endüstri 4.0 dönüşümü',
        '5G teknolojisi ve etkileri', 'robotik ve otomasyon', 'giyilebilir teknolojiler',
        'yazılım geliştirme trendleri', 'mobil uygulama yenilikleri', 'veri güvenliği',
        'sosyal medya algoritmaları', 'dijital pazarlama teknolojileri'
    ],
    
    // Sağlık kategorileri
    'sağlık' => [
        'dengeli beslenme stratejileri', 'evde yapılabilecek egzersizler', 'mental sağlık teknikleri', 
        'bağışıklık sistemini güçlendirme', 'sağlıklı yaşam alışkanlıkları', 'alternatif tıp uygulamaları',
        'doğal tedavi yöntemleri', 'sağlıklı kilo yönetimi', 'kalp sağlığını koruma', 'uyku kalitesini artırma',
        'stres yönetimi teknikleri', 'yoga ve meditasyon faydaları', 'sağlıklı yaşlanma', 'detoks yöntemleri',
        'beslenme ve genetik ilişkisi', 'vitamin ve minerallerin önemi', 'spor yaralanmalarını önleme',
        'su tüketiminin önemi', 'sağlıklı cilt bakımı', 'beyin sağlığını koruma yöntemleri'
    ],
    
    // Eğitim kategorileri
    'eğitim' => [
        'etkili öğrenme yöntemleri', 'online eğitim platformları', 'kişisel gelişim stratejileri', 
        'kariyer planlama teknikleri', 'beceri geliştirme yolları', 'yaratıcı düşünme teknikleri',
        'liderlik becerileri geliştirme', 'etkili iletişim stratejileri', 'yabancı dil öğrenme taktikleri',
        'hafıza geliştirme teknikleri', 'zaman yönetimi', 'etkili not alma yöntemleri',
        'sınav hazırlık stratejileri', 'motivasyon teknikleri', 'sunum becerileri geliştirme',
        'eleştirel düşünme', 'problem çözme becerileri', 'duygusal zeka geliştirme',
        'yaşam boyu öğrenme', 'dijital okuryazarlık becerileri'
    ],
    
    // Bilim kategorileri
    'bilim' => [
        'güncel bilimsel araştırmalar', 'önemli bilimsel keşifler', 'çevre sorunları ve çözümleri', 
        'uzay araştırmaları', 'doğa olayları ve açıklamaları', 'genetik bilimi ve uygulamaları',
        'kuantum fiziği ve günlük hayata etkileri', 'nöroloji araştırmaları', 'iklim değişikliği önlemleri',
        'astronomi keşifleri', 'yenilenebilir enerji kaynakları', 'biyolojik çeşitlilik',
        'evrim teorisi ve güncel bulgular', 'deniz bilimleri', 'arkeolojik keşifler',
        'tıbbi araştırma yenilikleri', 'nanoteknoloji uygulamaları', 'yapay et ve gıda teknolojileri',
        'doğa koruma stratejileri', 'sürdürülebilir yaşam uygulamaları'
    ],
    
    // Yaşam kategorileri
    'yaşam' => [
        'minimalist yaşam tarzı', 'sağlıklı ilişki dinamikleri', 'yaratıcı hobi fikirleri', 
        'dünya seyahat rotaları', 'farklı kültürlerin özellikleri', 'finansal özgürlük stratejileri',
        'ev dekorasyonu fikirleri', 'sürdürülebilir yaşam alışkanlıkları', 'evcil hayvan bakımı',
        'etkili ebeveynlik stratejileri', 'kişisel bakım rutinleri', 'çalışma-yaşam dengesi kurma',
        'mevsimsel yemek tarifleri', 'organizasyon ve düzen ipuçları', 'bitki yetiştirme rehberi',
        'kişisel stil geliştirme', 'doğa ile bağlantı kurma yolları', 'el sanatları ve DIY projeleri',
        'ekolojik yaşam uygulamaları', 'yerel kültür ve gelenekler'
    ],
    
    // Finans kategorileri
    'finans' => [
        'kişisel finans yönetimi', 'yatırım stratejileri', 'borç yönetimi', 'bütçe planlama teknikleri',
        'emeklilik planlaması', 'finansal okuryazarlık', 'borsa yatırım ipuçları', 'pasif gelir oluşturma',
        'kripto para yatırımları', 'gayrimenkul yatırımları', 'tasarruf stratejileri', 'vergi optimizasyonu',
        'sigorta çeşitleri ve önemi', 'finansal hedef belirleme', 'girişimcilik finansmanı',
        'ekonomik trendler analizi', 'finansal risk yönetimi', 'online kazanç yöntemleri',
        'finansal bağımsızlık yolları', 'küçük işletme finansmanı'
    ],
    
    // Spor kategorileri
    'spor' => [
        'evde fitness programları', 'doğru koşu teknikleri', 'güç antrenmanı temelleri', 'spor beslenmesi',
        'esneklik ve denge egzersizleri', 'dayanıklılık geliştirme', 'spor yaralanmalarını önleme',
        'spor psikolojisi teknikleri', 'farklı spor dalları tanıtımı', 'kardiyo egzersiz çeşitleri',
        'doğa sporları rehberi', 'su sporları temel bilgileri', 'takım sporları stratejileri',
        'sporda başarı hikayeleri', 'sporcu beslenmesi', 'ekstrem sporlar rehberi',
        'yoga ve pilates teknikleri', 'spor ekipmanları seçimi', 'amatör spor turnuvaları',
        'spor ve yaşam kalitesi ilişkisi'
    ],
    
    // Sanat ve Kültür kategorileri
    'sanat' => [
        'çağdaş sanat akımları', 'klasik sanat eserleri analizi', 'sinema tarihi ve eleştirisi',
        'müzik türleri ve etkileri', 'edebiyat klasikleri incelemesi', 'fotoğrafçılık teknikleri',
        'tiyatro ve sahne sanatları', 'mimari akımlar ve örnekleri', 'dünya müzeleri sanal turları',
        'kültürel miras koruma çalışmaları', 'geleneksel el sanatları', 'çizim ve resim teknikleri',
        'dans türleri ve tarihi', 'dijital sanat yöntemleri', 'sanat terapisi uygulamaları',
        'popüler kültür analizi', 'sanat koleksiyonculuğu', 'müzik enstrümanları tanıtımı',
        'film yapım süreçleri', 'sanatçı biyografileri'
    ],
    
    // Yemek ve Gastronomi kategorileri
    'yemek' => [
        'dünya mutfakları tanıtımı', 'kolay yemek tarifleri', 'vegan ve vejetaryen beslenme',
        'sağlıklı atıştırmalıklar', 'ev yapımı ekmek ve hamur işleri', 'fermantasyon teknikleri',
        'sürdürülebilir beslenme', 'yerel ürünler ve mevsimsellik', 'şef özel tarifleri',
        'yemek fotoğrafçılığı ipuçları', 'şarap ve yemek eşleştirme', 'kahve çeşitleri ve yapımı',
        'baharat kullanım rehberi', 'çay kültürü ve çeşitleri', 'özel diyet menü planlaması',
        'pişirme teknikleri ustalaşma', 'mutfak ekipmanları seçimi', 'tatlı yapım teknikleri',
        'uluslararası sokak lezzetleri', 'geleneksel fermente içecekler'
    ],
    
    // Psikoloji kategorileri
    'psikoloji' => [
        'psikolojik dayanıklılık geliştirme', 'bilişsel davranışçı teknikler', 'pozitif psikoloji uygulamaları',
        'travma ve iyileşme süreçleri', 'kişilik tipleri ve özellikleri', 'karar verme psikolojisi',
        'mutluluk bilimi ve uygulamaları', 'psikolojik iyi oluş teknikleri', 'öz-şefkat geliştirme',
        'ilişki psikolojisi', 'çocuk gelişim psikolojisi', 'duygusal zeka geliştirme',
        'stres ve kaygı yönetimi', 'motivasyon psikolojisi', 'sosyal psikoloji kavramları',
        'psikolojik manipülasyondan korunma', 'farkındalık ve mindfulness uygulamaları', 
        'davranış değiştirme stratejileri', 'bilinçaltı ve rüya analizi', 'nöropsikoloji keşifleri'
    ]
];
