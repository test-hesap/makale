<?php
/**
 * Site genelinde kullanılan fonksiyonlar
 */

// Aktif dili ayarlar ve döndürür
if (!function_exists('getActiveLang')) {
    function getActiveLang() {
        // Önce oturumda dil kontrolü yap
        if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['tr', 'en'])) {
            return $_SESSION['lang'];
        }
        
        // Oturumda yoksa çerezde dil kontrolü yap
        if (isset($_COOKIE['user_lang']) && in_array($_COOKIE['user_lang'], ['tr', 'en'])) {
            return $_COOKIE['user_lang'];
        }
        
        return 'tr'; // Varsayılan dil Türkçe
    }
}

// Dil değiştirme işlemi
if (!function_exists('switchLang')) {
    function switchLang($lang) {
        if (in_array($lang, ['tr', 'en'])) {
            $_SESSION['lang'] = $lang;
            // Dil tercihini çereze de kaydet (1 yıl süreyle)
            setcookie('user_lang', $lang, time() + 31536000, '/');
        }
    }
}

// Metinleri çevirir
if (!function_exists('__')) {
    function __($key) {
        global $lang;
        $activeLang = getActiveLang();
        
        // Dil dosyasını dahil et (eğer yüklenmemişse)
        static $langLoaded = false;
        if (!$langLoaded) {
            include_once __DIR__ . '/lang/' . $activeLang . '.php';
            $langLoaded = true;
        }
        
        // Çeviriyi döndür, yoksa anahtarı döndür
        return $lang[$key] ?? $key;
    }
}

// t() fonksiyonu için alias - İngilizce dil desteği için - Formatlama desteği ile
if (!function_exists('t')) {
    function t($key, ...$args) {
        $text = __($key);
        if (!empty($args)) {
            return vsprintf($text, $args);
        }
        return $text;
    }
}

// Beni Hatırla - Token Oluşturma
if (!function_exists('generateRememberToken')) {
    function generateRememberToken($user_id) {
        $token = bin2hex(random_bytes(32)); // Güvenli bir token oluştur
        $expires = date('Y-m-d H:i:s', strtotime('+30 days')); // 30 günlük bir süre
        
        global $db;
        // Varolan token'ı sil
        $deleteStmt = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
        $deleteStmt->execute([$user_id]);
        
        // Yeni token oluştur
        $insertStmt = $db->prepare("INSERT INTO user_remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
        $insertStmt->execute([$user_id, $token, $expires]);
        
        return $token;
    }
}

// Beni Hatırla - Token Kontrol
if (!function_exists('validateRememberToken')) {
    function validateRememberToken($token) {
        global $db;
        
        try {
            // Token'ı sorgula
            $stmt = $db->prepare("SELECT user_id, expires FROM user_remember_tokens WHERE token = ? LIMIT 1");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("validateRememberToken: Token bulunamadı - " . substr($token, 0, 10) . "...");
                return false; // Token bulunamadı
            }
            
            error_log("validateRememberToken: Token bulundu - User ID: " . $result['user_id'] . ", Expires: " . $result['expires']);
            
            // Kullanıcı varlığını kontrol et - sadece kullanıcının var olup olmadığını kontrol et, durumunu değil
            $userStmt = $db->prepare("SELECT id, username, status FROM users WHERE id = ?");
            $userStmt->execute([$result['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                error_log("validateRememberToken: Token geçerli ama kullanıcı bulunamadı - User ID: " . $result['user_id']);
                return false;
            }
            
            // Kullanıcı banlı ancak banın süresi dolmuş mu kontrol et
            if ($user['status'] === 'banned') {
                $banCheckStmt = $db->prepare("
                    SELECT * FROM banned_users 
                    WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $banCheckStmt->execute([$result['user_id']]);
                $activeBan = $banCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                // Aktif ban yoksa kullanıcı durumunu güncelle
                if (!$activeBan) {
                    $updateStmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $updateStmt->execute([$result['user_id']]);
                    error_log("validateRememberToken: Kullanıcının ban süresi dolmuş, durumu aktif yapıldı - User ID: " . $result['user_id']);
                } else {
                    error_log("validateRememberToken: Kullanıcı hala banlı - User ID: " . $result['user_id']);
                    return false;
                }
            }
        } catch (Exception $e) {
            error_log("validateRememberToken: Hata - " . $e->getMessage());
            return false;
        }
        
        // Token'ın süresi dolmuş mu kontrol et
        if (strtotime($result['expires']) < time()) {
            // Süresi dolmuş token'ı sil
            $deleteStmt = $db->prepare("DELETE FROM user_remember_tokens WHERE token = ?");
            $deleteStmt->execute([$token]);
            return false;
        }
        
        return $result['user_id'];
    }
}

// Türkçe ay adları
if (!function_exists('turkishMonth')) {
    function turkishMonth($month) {
        $months = [
            'January' => 'Ocak',
            'February' => 'Şubat',
            'March' => 'Mart',
            'April' => 'Nisan',
            'May' => 'Mayıs',
            'June' => 'Haziran',
            'July' => 'Temmuz',
            'August' => 'Ağustos',
            'September' => 'Eylül',
            'October' => 'Ekim',
            'November' => 'Kasım',
            'December' => 'Aralık'
        ];
        return $months[$month] ?? $month;
    }
}

// Tarih formatı (dil desteği ile)
if (!function_exists('formatTurkishDate')) {
    function formatTurkishDate($date) {
        if (empty($date)) return '';
        $timestamp = strtotime($date);
        if ($timestamp === false) return '';
        
        $lang = getActiveLang();
        
        if ($lang == 'tr') {
            $format = date('d F Y', $timestamp);
            $parts = explode(' ', $format);
            $parts[1] = turkishMonth($parts[1]);
            return implode(' ', $parts);
        } else {
            return date('d F Y', $timestamp);
        }
    }
}

if (!function_exists('formatDate')) {
    /**
     * Veritabanından gelen tarihi formatlar ve gün farkını hesaplar
     * 
     * @param string $date Veritabanından gelen tarih
     * @param bool $showTime Saat gösterilsin mi
     * @param bool $showDiff Gün farkı gösterilsin mi
     * @return string Formatlanmış tarih
     */
    function formatDate($date, $showTime = false, $showDiff = true) {
        if (empty($date)) return '-';
        
        // Geçerli tarih formatını kontrol et
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return getActiveLang() == 'tr' ? 'Geçersiz tarih' : 'Invalid date';
        }
        
        // Şimdi
        $now = time();
        
        // Format
        $format = $showTime ? 'd.m.Y H:i' : 'd.m.Y';
        $formatted_date = date($format, $timestamp);
        
        // Gün farkı hesaplama
        if ($showDiff) {
            $diff_seconds = $now - $timestamp;
            $diff_minutes = floor($diff_seconds / 60);
            $diff_hours = floor($diff_seconds / 3600);
            $diff_days = floor($diff_seconds / 86400);
            $diff_months = floor($diff_days / 30);
            $diff_years = floor($diff_days / 365);
            
            $lang = getActiveLang();
            
            if ($lang == 'tr') {
                if ($diff_years > 0) {
                    $remaining_months = floor(($diff_days - ($diff_years * 365)) / 30);
                    $diff_string = $diff_years . ' yıl' . ($remaining_months > 0 ? ', ' . $remaining_months . ' ay' : '') . ' önce';
                } elseif ($diff_months > 0) {
                    $remaining_days = $diff_days - ($diff_months * 30);
                    $diff_string = $diff_months . ' ay' . ($remaining_days > 0 ? ', ' . $remaining_days . ' gün' : '') . ' önce';
                } elseif ($diff_days > 0) {
                    $diff_string = $diff_days . ' gün önce';
                } elseif ($diff_hours > 0) {
                    $diff_string = $diff_hours . ' saat önce';
                } elseif ($diff_minutes > 0) {
                    $diff_string = $diff_minutes . ' dakika önce';
                } else {
                    $diff_string = 'Az önce';
                }
            } else {
                if ($diff_years > 0) {
                    $remaining_months = floor(($diff_days - ($diff_years * 365)) / 30);
                    $diff_string = $diff_years . ' year' . ($diff_years > 1 ? 's' : '') . ($remaining_months > 0 ? ', ' . $remaining_months . ' month' . ($remaining_months > 1 ? 's' : '') : '') . ' ago';
                } elseif ($diff_months > 0) {
                    $remaining_days = $diff_days - ($diff_months * 30);
                    $diff_string = $diff_months . ' month' . ($diff_months > 1 ? 's' : '') . ($remaining_days > 0 ? ', ' . $remaining_days . ' day' . ($remaining_days > 1 ? 's' : '') : '') . ' ago';
                } elseif ($diff_days > 0) {
                    $diff_string = $diff_days . ' day' . ($diff_days > 1 ? 's' : '') . ' ago';
                } elseif ($diff_hours > 0) {
                    $diff_string = $diff_hours . ' hour' . ($diff_hours > 1 ? 's' : '') . ' ago';
                } elseif ($diff_minutes > 0) {
                    $diff_string = $diff_minutes . ' minute' . ($diff_minutes > 1 ? 's' : '') . ' ago';
                } else {
                    $diff_string = 'Just now';
                }
            }
            
            return $formatted_date . ' (' . $diff_string . ')';
        }
        
        return $formatted_date;
    }
}

if (!function_exists('timeAgo')) {
    /**
     * Verilen tarihten şu ana kadar geçen zamanı metin olarak döndürür
     * 
     * @param string $datetime Veritabanından gelen tarih
     * @return string Geçen zaman metni
     */
    function timeAgo($datetime) {
        if (empty($datetime)) return '-';
        
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Geçersiz tarih';
        }
        
        // Şimdi
        $now = time();
        
        // Zaman farkı hesaplama (saniye)
        $diff_seconds = $now - $timestamp;
        $diff_minutes = floor($diff_seconds / 60);
        $diff_hours = floor($diff_seconds / 3600);
        $diff_days = floor($diff_seconds / 86400);
        $diff_months = floor($diff_days / 30);
        $diff_years = floor($diff_days / 365);
        
        $lang = getActiveLang();
        
        if ($lang == 'tr') {
            if ($diff_years > 0) {
                $remaining_months = floor(($diff_days - ($diff_years * 365)) / 30);
                return $diff_years . ' yıl' . ($remaining_months > 0 ? ', ' . $remaining_months . ' ay' : '') . ' önce';
            } elseif ($diff_months > 0) {
                $remaining_days = $diff_days - ($diff_months * 30);
                return $diff_months . ' ay' . ($remaining_days > 0 ? ', ' . $remaining_days . ' gün' : '') . ' önce';
            } elseif ($diff_days > 0) {
                return $diff_days . ' gün önce';
            } elseif ($diff_hours > 0) {
                return $diff_hours . ' saat önce';
            } elseif ($diff_minutes > 0) {
                return $diff_minutes . ' dakika önce';
            } else {
                return 'Az önce';
            }
        } else {
            if ($diff_years > 0) {
                $remaining_months = floor(($diff_days - ($diff_years * 365)) / 30);
                return $diff_years . ' year' . ($diff_years > 1 ? 's' : '') . ($remaining_months > 0 ? ', ' . $remaining_months . ' month' . ($remaining_months > 1 ? 's' : '') : '') . ' ago';
            } elseif ($diff_months > 0) {
                $remaining_days = $diff_days - ($diff_months * 30);
                return $diff_months . ' month' . ($diff_months > 1 ? 's' : '') . ($remaining_days > 0 ? ', ' . $remaining_days . ' day' . ($remaining_days > 1 ? 's' : '') : '') . ' ago';
            } elseif ($diff_days > 0) {
                return $diff_days . ' day' . ($diff_days > 1 ? 's' : '') . ' ago';
            } elseif ($diff_hours > 0) {
                return $diff_hours . ' hour' . ($diff_hours > 1 ? 's' : '') . ' ago';
            } elseif ($diff_minutes > 0) {
                return $diff_minutes . ' minute' . ($diff_minutes > 1 ? 's' : '') . ' ago';
            } else {
                return 'Just now';
            }
        }
    }
}

if (!function_exists('getSetting')) {
    /**
     * Veritabanından ayar değeri getirir
     * 
     * @param string $key Ayar anahtarı
     * @param mixed $default Ayar bulunamazsa dönecek varsayılan değer
     * @return mixed Ayar değeri
     */
    function getSetting($key, $default = '') {
        global $db;
        
        try {
            // Önce `key` sütunu ile deneyin, çünkü admin paneli bunu kullanıyor
            $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['value'])) {
                return $result['value'];
            }
            
            // `key` ile bulunamazsa, `name` sütununu deneyin (eski bir sürüm için)
            $stmt = $db->prepare("SELECT value FROM settings WHERE name = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['value'])) {
                return $result['value'];
            }
        } catch (PDOException $e) {
            error_log("getSetting fonksiyonunda hata: " . $e->getMessage());
        }
        
        return $default;
    }
}

if (!function_exists('updateSetting')) {
    /**
     * Veritabanında ayar değerini günceller veya ekler
     * 
     * @param string $key Ayar anahtarı
     * @param mixed $value Yeni değer
     * @return bool Başarılı olup olmadığı
     */
    function updateSetting($key, $value) {
        global $db;
        
        try {
            // Ayar var mı kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE name = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                // Varsa güncelle
                $stmt = $db->prepare("UPDATE settings SET value = :value WHERE name = :key");
            } else {
                // Yoksa ekle
                $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (:key, :value)");
            }
            
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("updateSetting fonksiyonunda hata: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('setSetting')) {
    /**
     * Veritabanında ayar değerini günceller veya ekler (updateSetting ile aynı işlevi görür)
     * 
     * @param string $key Ayar anahtarı
     * @param mixed $value Yeni değer
     * @return bool Başarılı olup olmadığı
     */
    function setSetting($key, $value) {
        return updateSetting($key, $value);
    }
}

if (!function_exists('showAdFromSettings')) {
    /**
     * Reklamları ayarlardan gösterir
     * 
     * @param string $position Reklamın konumu
     * @return string Reklam HTML'i
     */
    function showAdFromSettings($position) {
        global $db;
        
        // Reklamlar aktif mi kontrol et
        if (getSetting('ad_status') !== 'active') {
            return '';
        }
        
        try {
            $ad_key = 'ad_' . $position;
            $ad_content = getSetting($ad_key);
            
            if (!empty($ad_content)) {
                return '<div class="ad-container ad-' . $position . '">' . $ad_content . '</div>';
            }
        } catch (Exception $e) {
            error_log("showAdFromSettings fonksiyonunda hata: " . $e->getMessage());
        }
        
        return '';
    }
}

if (!function_exists('getUserAvatar')) {
    /**
     * Kullanıcının avatar bilgisini alır ve varsayılan avatar mantığını uygular
     * NULL veya boş avatar olması durumunda varsayılan avatarı döndürür
     * 
     * @param mixed $avatar Kullanıcının avatar değeri
     * @return string Avatar dosya yolu
     */
    function getUserAvatar($avatar) {
        // Avatar NULL veya boş ise varsayılan avatar kullan
        if (empty($avatar)) {
            // Config.php'de tanımlanan default avatar yolunu kullan
            return DEFAULT_AVATAR_PATH;
        }
        
        return $avatar;
    }
}

if (!function_exists('getUserAvatarUrl')) {
    /**
     * Kullanıcının avatar URL'sini döndürür
     * Avatar göstermek için HTML src özelliğinde kullanılabilir
     * 
     * @param mixed $avatar Kullanıcının avatar değeri
     * @param bool $checkExistence Dosyanın varlığını kontrol et
     * @return string Avatar tam URL'si
     */
    function getUserAvatarUrl($avatar = null, $checkExistence = true) {
        global $db;
        
        // Site URL'ini al
        $site_url = getSetting('site_url');
        if (empty($site_url)) {
            $site_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
        }
        
        // Avatar null ise ve session varsa session'dan al
        if (empty($avatar) && isset($_SESSION['avatar'])) {
            $avatar = $_SESSION['avatar'];
        }
        
        // Avatar hala null ise varsayılanı kullan
        if (empty($avatar)) {
            // Varsayılan avatar URL'sini döndür (tam yol)
            return $site_url . '/' . DEFAULT_AVATAR_PATH;
        }
        
        // Avatar dosya adı mı yoksa tam yol mu kontrol et
        if (strpos($avatar, 'http://') !== 0 && strpos($avatar, 'https://') !== 0) {
            // Tam yol değilse, uploads/avatars/ altında ara
            $avatarPath = 'uploads/avatars/' . basename($avatar);
            
            // Dosya varlığını kontrol et
            if ($checkExistence) {
                $docRoot = $_SERVER['DOCUMENT_ROOT'];
                $fullPath = $docRoot . '/' . $avatarPath;
                
                if (!file_exists($fullPath)) {
                    error_log("Avatar dosyası bulunamadı: $fullPath");
                    return $site_url . '/' . DEFAULT_AVATAR_PATH;
                }
            }
            
            return $site_url . '/' . $avatarPath;
        }
        
        // Zaten tam URL ise aynen döndür
        return $avatar;
    }
}

if (!function_exists('getAvatarBase64')) {
    /**
     * Kullanıcının avatarını base64 kodlu olarak döndürür
     * Bu fonksiyon dosya sistemi izinleri ve yol sorunlarını aşmak için kullanılır
     * 
     * @param string $avatar_path Avatar yolu (dosya adı veya tam yol)
     * @return string Base64 kodlu avatar görüntüsü
     */
    function getAvatarBase64($avatar_path) {
        // Varsayılan base64 kodlu avatar
        $default_base64 = DEFAULT_AVATAR_BASE64;
        
        // avatar_path sadece dosya adıysa (örneğin default-avatar.jpg), tam yolu oluştur
        if (!str_contains($avatar_path, '/') && !str_contains($avatar_path, '\\')) {
            $avatar_path = 'uploads/avatars/' . $avatar_path;
        }
        
        // Dosya yoksa veya okunamazsa varsayılan base64 kodlu avatarı döndür
        if (!file_exists($avatar_path) || !is_readable($avatar_path)) {
            error_log("Avatar dosyası bulunamadı veya okunamadı: " . $avatar_path);
            return $default_base64;
        }
        
        try {
            // Dosyayı oku ve MIME tipini belirle
            $avatar_data = file_get_contents($avatar_path);
            if ($avatar_data === false) {
                error_log("Avatar dosyası okunamadı: " . $avatar_path);
                return $default_base64;
            }
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->buffer($avatar_data) ?: 'image/jpeg';
            
            // Base64 kodlama
            $base64 = base64_encode($avatar_data);
            return "data:{$mime_type};base64,{$base64}";
        } catch (Exception $e) {
            error_log("Avatar base64 kodlama hatası: " . $e->getMessage());
            return $default_base64;
        }
    }
}

if (!function_exists('getAvatarUrl')) {
    /**
     * Kullanıcı avatarı için URL döndürür
     * Eğer avatar dosya yolu bulunamazsa, varsayılan avatarı döndürür
     * 
     * @param string $avatar_path Avatar yolu (dosya adı veya tam yol)
     * @return string Avatar URL'si
     */
    function getAvatarUrl($avatar_path) {
        // Avatar sadece dosya adıysa (örneğin default-avatar.jpg), URL yolunu oluştur
        if (!str_contains($avatar_path, '/') && !str_contains($avatar_path, '\\')) {
            // Site URL'sini al
            $site_url = getSetting('site_url') ?: 
                        ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                         "://" . $_SERVER['HTTP_HOST']);
            
            return $site_url . '/uploads/avatars/' . $avatar_path;
        }
        
        return $avatar_path;
    }
}

/**
 * Kullanıcının çevrimiçi durumunu günceller - config.php içerisinde zaten tanımlanmış
 */
function updateUserActivity($user_id) {
    global $db;
    
    if (!$user_id) return;
    
    try {
        $stmt = $db->prepare("UPDATE users SET last_activity = NOW(), is_online = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        // Hata yönetimi
        error_log("Kullanıcı aktivitesi güncellenirken hata: " . $e->getMessage());
    }
}

/**
 * Çevrimiçi kullanıcıları temizler
 * Son 5 dakikadır aktif olmayan kullanıcıları çevrimdışı olarak işaretler
 * 
 * @return void
 */
function cleanupOnlineUsers() {
    global $db;
    
    try {
        // 5 dakikadır aktif olmayan kullanıcıları çevrimdışı olarak işaretle
        $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute();
    } catch (Exception $e) {
        // Hata yönetimi
        error_log("Çevrimiçi kullanıcılar temizlenirken hata: " . $e->getMessage());
    }
}

/**
 * Çevrimiçi kullanıcıları getirir
 * 
 * @param int $limit Maksimum gösterilecek kullanıcı sayısı
 * @return array Çevrimiçi kullanıcılar listesi
 */
function getOnlineUsers($limit = 20) {
    global $db;
    
    // Önce temizleme işlemi yap
    cleanupOnlineUsers();
    
    try {
        $stmt = $db->prepare("
            SELECT id, username, avatar, is_premium 
            FROM users 
            WHERE is_online = 1 
            ORDER BY last_activity DESC 
            LIMIT ?
        ");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Hata yönetimi
        error_log("Çevrimiçi kullanıcılar getirilirken hata: " . $e->getMessage());
        return [];
    }
}

/**
 * Toplam çevrimiçi kullanıcı sayısını getirir
 * 
 * @return int Çevrimiçi kullanıcı sayısı
 */
function getTotalOnlineUsers() {
    global $db;
    
    // Önce temizleme işlemi yap
    cleanupOnlineUsers();
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_online = 1");
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        // Hata yönetimi
        error_log("Toplam çevrimiçi kullanıcı sayısı alınırken hata: " . $e->getMessage());
        return 0;
    }
}

/**
 * Kullanıcının çevrimiçi durumunu kontrol eder
 * 
 * @param int $user_id Kullanıcı ID veya son aktivite zamanı
 * @param string|null $last_activity Son aktivite zamanı (opsiyonel)
 * @return bool Kullanıcı çevrimiçi mi
 */
function isUserOnline($user_id, $last_activity = null) {
    global $db;
    
    // Son aktivite zamanı verilmişse, zaman farkını kontrol et
    if ($last_activity) {
        $activity_time = strtotime($last_activity);
        $current_time = time();
        return ($current_time - $activity_time) <= (5 * 60); // 5 dakika
    }
    
    // Veritabanından kontrol et
    try {
        $stmt = $db->prepare("SELECT is_online FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Kullanıcı durumu kontrol edilirken hata: ' . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının çevrimiçi durumu için HTML döndürür
 * 
 * @param bool $is_online Kullanıcı çevrimiçi mi
 * @return string HTML kodu
 */
function getUserStatusHtml($is_online) {
    if ($is_online) {
        return '<span class="inline-block w-3 h-3 bg-green-500 dark:bg-green-500 rounded-full" title="Çevrimiçi"></span>';
    } else {
        return '<span class="inline-block w-3 h-3 bg-red-500 dark:bg-gray-600 rounded-full" title="Çevrimdışı"></span>';
    }
}

/**
 * Misafir kullanıcıları izler ve sayılarını hesaplar
 * 
 * @return void
 */
function trackGuests() {
    global $db;
    
    if (!isset($_SESSION['guest_tracked'])) {
        // Benzersiz bir misafir ID'si oluştur
        if (!isset($_SESSION['guest_id'])) {
            $_SESSION['guest_id'] = uniqid('guest_');
        }
        
        // Misafiri online_guests tablosuna ekle
        try {
            // Önce eski kayıtları temizle
            cleanupGuests();
            
            // Misafiri ekle
            $stmt = $db->prepare("INSERT INTO online_guests (guest_id, last_activity, ip_address) VALUES (?, NOW(), ?)");
            $stmt->execute([
                $_SESSION['guest_id'],
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['guest_tracked'] = true;
        } catch (Exception $e) {
            error_log("Misafir kullanıcı izlenirken hata: " . $e->getMessage());
            
            // Tablo yoksa oluştur
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS online_guests (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        guest_id VARCHAR(50) NOT NULL,
                        last_activity TIMESTAMP NOT NULL,
                        ip_address VARCHAR(45) NOT NULL,
                        UNIQUE KEY (guest_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                
                // Tekrar eklemeyi dene
                $stmt = $db->prepare("INSERT INTO online_guests (guest_id, last_activity, ip_address) VALUES (?, NOW(), ?)");
                $stmt->execute([
                    $_SESSION['guest_id'],
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $_SESSION['guest_tracked'] = true;
            } catch (Exception $e2) {
                error_log("Misafir tablosu oluşturulurken hata: " . $e2->getMessage());
            }
        }
    } else {
        // Misafirin son aktivitesini güncelle
        try {
            $stmt = $db->prepare("UPDATE online_guests SET last_activity = NOW() WHERE guest_id = ?");
            $stmt->execute([$_SESSION['guest_id']]);
        } catch (Exception $e) {
            error_log("Misafir aktivitesi güncellenirken hata: " . $e->getMessage());
        }
    }
}

/**
 * Eski misafir kayıtlarını temizler
 * 
 * @return void
 */
function cleanupGuests() {
    global $db;
    
    try {
        // 5 dakikadan eski misafir kayıtlarını sil
        $stmt = $db->prepare("DELETE FROM online_guests WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Misafir kayıtları temizlenirken hata: " . $e->getMessage());
    }
}

/**
 * Çevrimiçi misafir sayısını getirir
 * 
 * @return int Misafir sayısı
 */
function getGuestCount() {
    global $db;
    
    try {
        // Tabloyu kontrol et
        $result = $db->query("SHOW TABLES LIKE 'online_guests'");
        if ($result->rowCount() == 0) {
            return 0;
        }
        
        // Eski kayıtları temizle
        cleanupGuests();
        
        // Misafir sayısını al
        $stmt = $db->prepare("SELECT COUNT(*) FROM online_guests");
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Misafir sayısı alınırken hata: " . $e->getMessage());
        return 0;
    }
}

/**
 * Ziyaretçinin bot olup olmadığını kontrol eder
 * 
 * @return bool|string Bot ise bot adını, değilse false döner
 */
function isBot() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Yaygın botların listesi ve isimleri
    $bot_list = [
        'Googlebot' => 'Google',
        'Bingbot' => 'Bing',
        'Slurp' => 'Yahoo',
        'DuckDuckBot' => 'DuckDuckGo',
        'Baiduspider' => 'Baidu',
        'YandexBot' => 'Yandex',
        'facebot' => 'Facebook',
        'facebookexternalhit' => 'Facebook',
        'ia_archiver' => 'Alexa',
        'Twitterbot' => 'Twitter',
        'WhatsApp' => 'WhatsApp',
        'Applebot' => 'Apple',
        'Sogou' => 'Sogou',
        'Exabot' => 'Exalead',
        'AhrefsBot' => 'Ahrefs',
        'MJ12bot' => 'Majestic',
        'SemrushBot' => 'SEMrush',
        'BLEXBot' => 'Webmeup',
        'YisouSpider' => 'Yisou',
        'Scrapy' => 'Scrapy',
        'crawler' => 'Crawler',
        'spider' => 'Spider',
        'bot' => 'Bot',
        'archive' => 'Archive',
        'Lighthouse' => 'Google Lighthouse'
    ];
    
    foreach ($bot_list as $bot_keyword => $bot_name) {
        if (stripos($user_agent, $bot_keyword) !== false) {
            return $bot_name;
        }
    }
    
    return false;
}

/**
 * Bot ziyaretçileri izler ve sayılarını hesaplar
 * 
 * @return void
 */
function trackBots() {
    global $db;
    
    // Bot kontrolü yap
    $bot_name = isBot();
    if (!$bot_name) {
        return; // Bot değilse işlem yapma
    }
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    try {
        // Tabloyu kontrol et ve gerekirse oluştur
        $result = $db->query("SHOW TABLES LIKE 'online_bots'");
        if ($result->rowCount() == 0) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS online_bots (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bot_name VARCHAR(100) NOT NULL,
                    user_agent VARCHAR(255) NOT NULL,
                    last_activity TIMESTAMP NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    visit_count INT DEFAULT 1,
                    UNIQUE KEY (user_agent(100), ip_address)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
        
        // Önce eski kayıtları temizle
        cleanupBots();
        
        // Bot zaten kayıtlı mı kontrol et
        $stmt = $db->prepare("SELECT id, visit_count FROM online_bots WHERE user_agent = ? AND ip_address = ?");
        $stmt->execute([$user_agent, $ip_address]);
        $bot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bot) {
            // Bot zaten kayıtlıysa güncelle
            $visit_count = $bot['visit_count'] + 1;
            $stmt = $db->prepare("UPDATE online_bots SET last_activity = NOW(), visit_count = ? WHERE id = ?");
            $stmt->execute([$visit_count, $bot['id']]);
        } else {
            // Bot kayıtlı değilse ekle
            $stmt = $db->prepare("INSERT INTO online_bots (bot_name, user_agent, last_activity, ip_address) VALUES (?, ?, NOW(), ?)");
            $stmt->execute([$bot_name, $user_agent, $ip_address]);
        }
    } catch (Exception $e) {
        error_log("Bot izlenirken hata: " . $e->getMessage());
    }
}

/**
 * Eski bot kayıtlarını temizler
 * 
 * @return void
 */
function cleanupBots() {
    global $db;
    
    try {
        // 15 dakikadan eski bot kayıtlarını sil
        $stmt = $db->prepare("DELETE FROM online_bots WHERE last_activity < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Bot kayıtları temizlenirken hata: " . $e->getMessage());
    }
}

/**
 * Çevrimiçi bot sayısını getirir
 * 
 * @return int Bot sayısı
 */
function getBotCount() {
    global $db;
    
    try {
        // Tabloyu kontrol et
        $result = $db->query("SHOW TABLES LIKE 'online_bots'");
        if ($result->rowCount() == 0) {
            return 0;
        }
        
        // Eski kayıtları temizle
        cleanupBots();
        
        // Bot sayısını al
        $stmt = $db->prepare("SELECT COUNT(*) FROM online_bots");
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Bot sayısı alınırken hata: " . $e->getMessage());
        return 0;
    }
}

/**
 * Çevrimiçi botları getirir
 * 
 * @param int $limit Maksimum gösterilecek bot sayısı
 * @return array Çevrimiçi botlar listesi
 */
function getOnlineBots($limit = 10) {
    global $db;
    
    // Önce temizleme işlemi yap
    cleanupBots();
    
    try {
        // Tabloyu kontrol et
        $result = $db->query("SHOW TABLES LIKE 'online_bots'");
        if ($result->rowCount() == 0) {
            return [];
        }
        
        $stmt = $db->prepare("
            SELECT bot_name, user_agent, last_activity, ip_address, visit_count
            FROM online_bots 
            ORDER BY last_activity DESC 
            LIMIT ?
        ");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Hata yönetimi
        error_log("Çevrimiçi botlar getirilirken hata: " . $e->getMessage());
        return [];
    }
}

if (!function_exists('sendEmail')) {
    /**
     * SMTP kullanarak e-posta gönderir
     * 
     * @param string $to Alıcı e-posta adresi
     * @param string $subject E-posta konusu
     * @param string $message E-posta içeriği (HTML olabilir)
     * @param string $from_email Gönderen e-posta adresi (ayarlardan alınacak)
     * @param string $from_name Gönderen adı (ayarlardan alınacak)
     * @return bool Başarılı olup olmadığı
     */
    function sendEmail($to, $subject, $message, $from_email = '', $from_name = '') {
        // SMTP ayarlarını veritabanından al
        $smtp_enabled = getSetting('smtp_enabled', '0');
        
        // SMTP etkin değilse PHP'nin varsayılan mail() fonksiyonunu kullan
        if ($smtp_enabled != '1') {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            
            // Gönderen bilgisi
            if (empty($from_email)) $from_email = getSetting('smtp_from_email', 'info@example.com');
            if (empty($from_name)) $from_name = getSetting('smtp_from_name', getSetting('site_title', 'Site Adı'));
            
            $headers .= "From: $from_name <$from_email>" . "\r\n";
            
            return mail($to, $subject, $message, $headers);
        }
        
        // SMTP ayarlarını al
        $smtp_host = getSetting('smtp_host', 'smtp.example.com');
        $smtp_port = getSetting('smtp_port', '587');
        $smtp_secure = getSetting('smtp_secure', 'tls');
        $smtp_username = getSetting('smtp_username', '');
        $smtp_password = getSetting('smtp_password', '');
        
        if (empty($from_email)) $from_email = getSetting('smtp_from_email', 'info@example.com');
        if (empty($from_name)) $from_name = getSetting('smtp_from_name', getSetting('site_title', 'Site Adı'));
        
        // PHPMailer sınıfı var mı kontrol et
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // PHPMailer yüklü değilse, Composer autoload'u dene
            $autoloadPaths = [
                __DIR__ . '/../vendor/autoload.php',
                __DIR__ . '/../../vendor/autoload.php'
            ];
            
            $loaded = false;
            foreach ($autoloadPaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $loaded = true;
                    break;
                }
            }
            
            // PHPMailer yüklenemezse, normal mail() fonksiyonunu kullan
            if (!$loaded || !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log("PHPMailer sınıfı bulunamadı. Varsayılan mail() fonksiyonu kullanılıyor.");
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: $from_name <$from_email>" . "\r\n";
                
                return mail($to, $subject, $message, $headers);
            }
        }
        
        // PHPMailer ile e-posta gönder
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Sunucu ayarları
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->Port = $smtp_port;
            
            if ($smtp_secure !== 'none') {
                $mail->SMTPSecure = $smtp_secure;
            }
            
            // Kimlik doğrulama
            if (!empty($smtp_username) && !empty($smtp_password)) {
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_username;
                $mail->Password = $smtp_password;
            }
            
            // Gönderici ve alıcı
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            
            // İçerik
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Gönder
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("E-posta gönderme hatası: " . $e->getMessage());
            return false;
        }
    }
}

// Kullanıcının engelleme durumunu kontrol et
function isUserBlocked($blocker_id, $blocked_id) {
    global $db;
    
    // Admin kullanıcılar asla engellenmez
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$blocked_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['role'] === 'admin') {
        return false;
    }
    
    $stmt = $db->prepare("SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$blocker_id, $blocked_id]);
    return $stmt->rowCount() > 0;
}

// Kullanıcıyı engelle
function blockUser($blocker_id, $blocked_id, $reason = null) {
    global $db;
    
    // Admin kullanıcılar engellenemez
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$blocked_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['role'] === 'admin') {
        return false;
    }
    
    // Kendini engelleyemezsin
    if ($blocker_id == $blocked_id) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO user_blocks (blocker_id, blocked_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$blocker_id, $blocked_id, $reason]);
        return true;
    } catch (PDOException $e) {
        error_log("Kullanıcı engelleme hatası: " . $e->getMessage());
        return false;
    }
}

// Kullanıcı engelini kaldır
function unblockUser($blocker_id, $blocked_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$blocker_id, $blocked_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Kullanıcı engel kaldırma hatası: " . $e->getMessage());
        return false;
    }
}

// Kullanıcının engellediği kişileri listele
function getBlockedUsers($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT b.*, u.username, u.avatar 
            FROM user_blocks b
            JOIN users u ON b.blocked_id = u.id
            WHERE b.blocker_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Engellenen kullanıcıları listeleme hatası: " . $e->getMessage());
        return [];
    }
}

// Admin tarafından kullanıcı banlama
function banUser($user_id, $banned_by, $reason = null, $ip_address = null, $is_ip_banned = 0, $expires_at = null) {
    global $db;
    
    // Admin kullanıcılar banlanamaz
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['role'] === 'admin') {
        return false;
    }
    
    // Kendini banlayamazsın
    if ($user_id == $banned_by) {
        return false;
    }
    
    try {
        // Önce mevcut ban kaydını kontrol et
        $stmt = $db->prepare("SELECT id FROM banned_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Ban kaydı güncelle
            $stmt = $db->prepare("
                UPDATE banned_users 
                SET reason = ?, ip_address = ?, is_ip_banned = ?, expires_at = ?, banned_by = ?, is_active = 1
                WHERE user_id = ?
            ");
            $stmt->execute([$reason, $ip_address, $is_ip_banned, $expires_at, $banned_by, $user_id]);
        } else {
            // Yeni ban kaydı oluştur
            $stmt = $db->prepare("
                INSERT INTO banned_users (user_id, banned_by, reason, ip_address, is_ip_banned, expires_at, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$user_id, $banned_by, $reason, $ip_address, $is_ip_banned, $expires_at]);
        }
        
        // Kullanıcı durumunu güncelle
        $stmt = $db->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $result = $stmt->execute([$user_id]);
        
        if (!$result) {
            error_log("Kullanıcı durumu güncellenirken hata: " . print_r($stmt->errorInfo(), true));
            return false;
        }
        
        // Kullanıcının aktif oturumlarını sonlandır
        terminateUserSessions($user_id);
        
        // İşlem başarılı olduysa günlük kaydı tutalım
        error_log("Kullanıcı ID: $user_id, banned_by: $banned_by başarıyla banlandı. Status 'banned' olarak güncellendi.");
        
        return true;
    } catch (PDOException $e) {
        error_log("Kullanıcı banlama hatası: " . $e->getMessage());
        return false;
    }
}

// Kullanıcının tüm aktif oturumlarını sonlandır
function terminateUserSessions($user_id) {
    global $db;
    
    try {
        // user_sessions tablosu varsa oturumları sonlandır
        $check = $db->query("SHOW TABLES LIKE 'user_sessions'");
        if ($check->rowCount() > 0) {
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        // remember_tokens tablosu varsa tüm remember token'ları sil
        $check = $db->query("SHOW TABLES LIKE 'user_remember_tokens'");
        if ($check->rowCount() > 0) {
            $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Kullanıcı oturumlarını sonlandırma hatası: " . $e->getMessage());
        return false;
    }
}

// Ban kaldırma
function unbanUser($user_id) {
    global $db;
    
    try {
        // Ban kaydını sil
        $stmt = $db->prepare("DELETE FROM banned_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Kullanıcı durumunu aktif yap
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Kullanıcı ban kaldırma hatası: " . $e->getMessage());
        return false;
    }
}

// Banlanan kullanıcıları listele
function getBannedUsers() {
    global $db;
    
    try {
        // Önce süresi dolmuş banları otomatik olarak güncelle
        updateExpiredBans();
        
        $stmt = $db->prepare("
            SELECT b.*, 
                   u.username, u.email, u.avatar,
                   a.username as banned_by_username
            FROM banned_users b
            JOIN users u ON b.user_id = u.id
            JOIN users a ON b.banned_by = a.id
            WHERE (b.expires_at IS NULL OR b.expires_at > NOW()) AND (b.is_active = 1 OR b.is_active IS NULL)
            ORDER BY b.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Banlanan kullanıcıları listeleme hatası: " . $e->getMessage());
        return [];
    }
}

// Süresi dolmuş banları güncelle
function updateExpiredBans() {
    global $db;
    
    try {
        // is_active sütunu var mı kontrol et
        $check_column = $db->query("SHOW COLUMNS FROM banned_users LIKE 'is_active'");
        $column_exists = $check_column->rowCount() > 0;
        
        // Sütun yoksa işlem yapma
        if (!$column_exists) {
            return;
        }
        
        // Süresi dolmuş banları güncelle
        $stmt = $db->prepare("
            UPDATE banned_users 
            SET is_active = 0 
            WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND (is_active = 1 OR is_active IS NULL)
        ");
        $stmt->execute();
        
        // Kullanıcı durumlarını güncelle
        $stmt = $db->prepare("
            UPDATE users u
            JOIN banned_users b ON u.id = b.user_id
            SET u.status = 'active'
            WHERE b.expires_at IS NOT NULL 
            AND b.expires_at <= NOW()
            AND u.status = 'banned'
            AND (b.is_active = 0 OR b.is_active IS NULL)
        ");
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Süresi dolmuş banları güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

// IP adresinin banlı olup olmadığını kontrol et
function isIPBanned($ip) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM banned_users 
            WHERE ip_address = ? AND is_ip_banned = 1 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$ip]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    } catch (PDOException $e) {
        error_log("IP ban kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

// Kullanıcının banlı olup olmadığını kontrol et
function isUserBanned($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM banned_users 
            WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    } catch (PDOException $e) {
        error_log("Kullanıcı ban kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * E-posta sağlayıcısının geçerli olup olmadığını kontrol eder
 * @param string $email E-posta adresi
 * @return bool Geçerli sağlayıcıysa true, değilse false
 */
function isValidEmailProvider($email) {
    // E-posta adresinden domain kısmını al
    $emailParts = explode('@', $email);
    if (count($emailParts) !== 2) {
        return false;
    }
    
    $domain = strtolower(trim($emailParts[1]));
    
    // Geçerli üst düzey domain uzantıları
    $validTLDs = [
        '.com', '.net', '.org', '.edu', '.gov', '.mil',
        '.co.uk', '.com.tr', '.net.tr', '.org.tr', '.edu.tr',
        '.de', '.fr', '.it', '.es', '.nl', '.be', '.ch',
        '.ru', '.ua', '.pl', '.cz', '.sk', '.hu', '.ro',
        '.bg', '.hr', '.si', '.rs', '.ba', '.mk', '.me',
        '.ca', '.au', '.nz', '.in', '.jp', '.kr', '.cn',
        '.hk', '.sg', '.my', '.th', '.ph', '.id', '.vn',
        '.br', '.ar', '.mx', '.cl', '.co', '.pe', '.ve',
        '.za', '.eg', '.ma', '.ng', '.ke', '.gh', '.tz',
        '.info', '.biz', '.name', '.pro', '.mobi', '.tel',
        '.xyz', '.online', '.site', '.website', '.store',
        '.tech', '.space', '.club', '.top', '.world'
    ];
    
    // Geçerli e-posta sağlayıcıları listesi
    $validProviders = [
        // Popüler sağlayıcılar
        'gmail.com', 'googlemail.com',
        'hotmail.com', 'outlook.com', 'live.com', 'msn.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'yahoo.com.tr',
        'yandex.com', 'yandex.ru', 'yandex.com.tr',
        'icloud.com', 'me.com', 'mac.com',
        'aol.com',
        'protonmail.com', 'pm.me',
        'tutanota.com',
        'mail.ru',
        
        // Türk sağlayıcıları
        'mynet.com',
        'superonline.com',
        'ttnet.com.tr',
        'turk.net',
        'ttmail.com',
        'n11.com',
        'gittigidiyor.com',
        
        // Diğer uluslararası sağlayıcılar
        'gmx.com', 'gmx.de',
        'web.de',
        'freenet.de',
        't-online.de',
        'libero.it',
        'virgilio.it',
        'orange.fr',
        'laposte.net',
        'free.fr',
        'wanadoo.fr',
        'naver.com',
        'daum.net',
        'nate.com',
        'sina.com',
        'qq.com',
        '163.com',
        '126.com',
        'rediffmail.com',
        'indiatimes.com'
    ];
    
    // Önce bilinen sağlayıcılar listesinde kontrol et
    if (in_array($domain, $validProviders)) {
        return true;
    }
    
    // Domain'in geçerli bir TLD ile bitip bitmediğini kontrol et
    foreach ($validTLDs as $tld) {
        if (substr($domain, -strlen($tld)) === $tld) {
            // Domain en az 3 karakter + TLD uzunluğunda olmalı (örn: a.com minimum)
            $domainWithoutTLD = substr($domain, 0, -strlen($tld));
            if (strlen($domainWithoutTLD) >= 1 && preg_match('/^[a-z0-9\-\.]+$/', $domainWithoutTLD)) {
                return true;
            }
        }
    }
    
    return false;
}
