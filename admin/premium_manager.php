<?php
require_once '../includes/config.php';

// Sadece admin kullanıcıları için erişime izin ver
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'includes/header.php';

$message = '';
$error = '';

// Premium düzeltme işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Premium üyelik alanını düzelt
    if (isset($_POST['fix_premium_column'])) {
        try {
            // is_premium sütununu doğru tipte güncelle
            $db->exec("ALTER TABLE users MODIFY COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0");
            $message = getActiveLang() == 'en' ? "is_premium column successfully fixed." : "is_premium sütunu başarıyla düzeltildi.";
        } catch (PDOException $e) {
            $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
        }
    }
      // Premium tabloyu sıfırla
    if (isset($_POST['reset_premium'])) {
        try {
            // Önce mevcut premium kullanıcı sayısını kontrol et
            $countStmt = $db->query("SELECT COUNT(*) AS premium_count FROM users WHERE is_premium = 1");
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $premiumCount = $countResult['premium_count'] ?? 0;
            
            // Premium üyelikleri sıfırla
            $db->exec("UPDATE users SET is_premium = 0, premium_until = NULL");
            
            // Sıfırlama sonrası tekrar kontrol et
            $afterCountStmt = $db->query("SELECT COUNT(*) AS premium_count FROM users WHERE is_premium = 1");
            $afterCountResult = $afterCountStmt->fetch(PDO::FETCH_ASSOC);
            $afterPremiumCount = $afterCountResult['premium_count'] ?? 0;
            
            $message = getActiveLang() == 'en' ? 
                "All users' premium subscriptions have been reset. Previous premium user count: $premiumCount, Current premium user count: $afterPremiumCount" : 
                "Tüm kullanıcıların premium üyelikleri sıfırlandı. Önceki premium kullanıcı sayısı: $premiumCount, Şu anki premium kullanıcı sayısı: $afterPremiumCount";
            
            // Log kaydı
            error_log("Admin tarafından tüm premium üyelikler sıfırlandı - Önceki: $premiumCount, Sonraki: $afterPremiumCount");
        } catch (PDOException $e) {
            $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
        }
    }
    
    // Premium tablo bilgisini göster
    if (isset($_POST['show_premium_info'])) {
        try {
            $result = $db->query("DESCRIBE users is_premium");
            $column_info = $result->fetch(PDO::FETCH_ASSOC);
            
            if ($column_info) {
                $message = getActiveLang() == 'en' ? 
                    "is_premium column structure: <br>" . 
                    "Type: " . $column_info['Type'] . "<br>" . 
                    "Null: " . $column_info['Null'] . "<br>" .
                    "Default: " . ($column_info['Default'] ?? 'NULL') . "<br>" :
                    "is_premium sütun yapısı: <br>" . 
                    "Tip: " . $column_info['Type'] . "<br>" . 
                    "Null: " . $column_info['Null'] . "<br>" .
                    "Varsayılan: " . ($column_info['Default'] ?? 'NULL') . "<br>";
            } else {
                $error = getActiveLang() == 'en' ? "is_premium column information not found." : "is_premium sütun bilgisi bulunamadı.";
            }
        } catch (PDOException $e) {
            $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
        }
    }
    
    // Premium kullanıcıları göster
    if (isset($_POST['show_premium_users'])) {
        try {
            $result = $db->query("SELECT id, username, is_premium, premium_until FROM users WHERE is_premium = 1");
            $premium_users = $result->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($premium_users) > 0) {
                $message = getActiveLang() == 'en' ? "<strong>Premium Users:</strong><br>" : "<strong>Premium Kullanıcılar:</strong><br>";
                foreach ($premium_users as $user) {
                    $message .= getActiveLang() == 'en' ? 
                        "ID: " . $user['id'] . ", Username: " . $user['username'] . 
                        ", Premium: " . $user['is_premium'] . 
                        ", Expires: " . ($user['premium_until'] ?? 'NULL') . "<br>" :
                        "ID: " . $user['id'] . ", Kullanıcı adı: " . $user['username'] . 
                        ", Premium: " . $user['is_premium'] . 
                        ", Bitiş: " . ($user['premium_until'] ?? 'NULL') . "<br>";
                }
            } else {
                $message = getActiveLang() == 'en' ? "No premium users found." : "Premium kullanıcı bulunamadı.";
            }
        } catch (PDOException $e) {
            $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
        }
    }
    
    // Seçili kullanıcıyı premium yap
    if (isset($_POST['make_premium']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $expiry_date = date('Y-m-d', strtotime('+1 month'));
        
        try {
            $stmt = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
            if ($stmt->execute([$expiry_date, $user_id])) {
                $message = getActiveLang() == 'en' ? 
                    "User ID: $user_id successfully made premium. Expiry date: $expiry_date" :
                    "Kullanıcı ID: $user_id başarıyla premium yapıldı. Bitiş tarihi: $expiry_date";
            } else {
                $error = getActiveLang() == 'en' ? "User could not be made premium." : "Kullanıcı premium yapılamadı.";
            }
        } catch (PDOException $e) {
            $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Tüm kullanıcıları getir
$users = $db->query("SELECT id, username, is_premium, premium_until FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Tabloyu kontrol et
try {
    $table_info = $db->query("SHOW TABLES LIKE 'users'")->fetchAll(PDO::FETCH_COLUMN);
    $table_exists = count($table_info) > 0;
    
    $column_info = null;
    if ($table_exists) {
        $result = $db->query("SHOW COLUMNS FROM users LIKE 'is_premium'");
        $column_info = $result->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = getActiveLang() == 'en' ? "Error during database table check: " . $e->getMessage() : "Veritabanı tablosu kontrolü sırasında hata: " . $e->getMessage();
}

// Premium üyelik düzenle
// Admin'in premium üyelik düzenlemesine izin ver
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    
    try {
        $stmt = $db->prepare("SELECT id, username, email, is_premium, premium_until FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_to_edit) {
            $error = getActiveLang() == 'en' ? "User not found." : "Kullanıcı bulunamadı.";
        } else {
            // Kullanıcı premium durumunu kontrol et ve düzelt
            $today = date('Y-m-d');
            $is_premium = (int)$user_to_edit['is_premium'];
            $premium_until = $user_to_edit['premium_until'];
            
            // Premium süresi dolmuş ama is_premium 1 olarak işaretlenmişse düzelt
            if ($is_premium && $premium_until && $premium_until < $today) {
                $fixStmt = $db->prepare("UPDATE users SET is_premium = 0 WHERE id = ?");
                $fixStmt->execute([$user_id]);
                $user_to_edit['is_premium'] = 0;
                $message = getActiveLang() == 'en' ? 
                    "Premium membership has expired and has been automatically deactivated." :
                    "Premium üyelik süresi dolmuş ve otomatik olarak deaktive edilmiştir.";
            }
            
            // is_premium=1 ama premium_until null ise düzelt
            if ($is_premium && empty($premium_until)) {
                $fixStmt = $db->prepare("UPDATE users SET is_premium = 0 WHERE id = ?");
                $fixStmt->execute([$user_id]);
                $user_to_edit['is_premium'] = 0;
                $message = getActiveLang() == 'en' ? 
                    "Invalid premium membership status detected and fixed." :
                    "Geçersiz premium üyelik durumu tespit edildi ve düzeltildi.";
            }
        }
    } catch (PDOException $e) {
        $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
    }
      // Premium üyelik güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_premium'])) {
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $premium_until = $_POST['premium_until'] ?? null;
        
        // Premium tarihi boş olmamalı, eğer premium aktifse
        if ($is_premium && empty($premium_until)) {
            $error = getActiveLang() == 'en' ? 
                "When premium membership is marked as active, an expiry date must be specified." : 
                "Premium üyelik aktif olarak işaretlendiğinde bitiş tarihi belirtilmelidir.";
        } else {
            try {
                // Premium üyelik güncelleme öncesi durumu kontrol et
                $checkStmt = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
                $checkStmt->execute([$user_id]);
                $oldStatus = $checkStmt->fetch(PDO::FETCH_ASSOC);
                $statusChanged = ($oldStatus['is_premium'] != $is_premium || $oldStatus['premium_until'] != $premium_until);
                
                // Eğer premium status değişiyorsa veya tarih değişiyorsa
                if ($statusChanged) {
                    error_log("Premium durum değişimi - Kullanıcı ID: $user_id, Eski durum: {$oldStatus['is_premium']}, Yeni durum: $is_premium");
                    error_log("Premium tarih değişimi - Eski tarih: " . ($oldStatus['premium_until'] ?? 'null') . ", Yeni tarih: " . ($premium_until ?? 'null'));
                }
                
                // Premium değilse premium_until kısmını null yap
                if (!$is_premium) {
                    $premium_until = null;
                }
                
                // Premium durumunu güncelle
                $stmt = $db->prepare("UPDATE users SET is_premium = ?, premium_until = ? WHERE id = ?");
                if ($stmt->execute([$is_premium, $premium_until, $user_id])) {
                    $message = getActiveLang() == 'en' ? 
                        "User ID: $user_id premium status successfully updated." :
                        "Kullanıcı ID: $user_id premium durumu başarıyla güncellendi.";
                    
                    // Güncel kullanıcı bilgisini yeniden yükle
                    $stmt = $db->prepare("SELECT id, username, email, is_premium, premium_until FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Premium durum değişikliği bildirimi
                    if ($statusChanged) {
                        // Kullanıcı oturumlarını temizle
                        clearUserSessions($user_id);
                        
                        $message .= getActiveLang() == 'en' ? 
                            " <strong>User session cleared.</strong> If the user is currently in an active session, they will need to log in again for the updated premium status." :
                            " <strong>Kullanıcı oturumu temizlendi.</strong> Eğer kullanıcı şu anda aktif oturum içindeyse, güncellenmiş premium durumu için tekrar giriş yapması gerekecektir.";
                        
                        // Log kaydı
                        error_log("Admin tarafından kullanıcı premium durumu güncellendi: is_premium={$is_premium}, premium_until={$premium_until}");
                    }
                } else {
                    $error = getActiveLang() == 'en' ? "User could not be updated." : "Kullanıcı güncellenemedi.";
                }
            } catch (PDOException $e) {
                $error = getActiveLang() == 'en' ? "Database error: " . $e->getMessage() : "Veritabanı hatası: " . $e->getMessage();
            }
        }
    }
}

// Kullanıcı oturumlarını temizlemek için gelişmiş fonksiyon - tüm PHP dosyalarında kullanılabilir
function clearUserSessions($user_id) {
    global $db;
    
    try {
        // 1. Debug bilgisi
        error_log("clearUserSessions: " . (getActiveLang() == 'en' ? "Session cleanup started for User ID: $user_id" : "Kullanıcı ID: $user_id için oturum temizleme başlatıldı"));
        
        // 2. Session tablosu varsa, kullanıcının tüm oturumlarını sil
        $db->query("CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            ip_address VARCHAR(50),
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY (user_id),
            KEY (session_id)
        )");
        
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $deleted_sessions = $stmt->rowCount();
        error_log("clearUserSessions: " . (getActiveLang() == 'en' ? "$deleted_sessions session records deleted" : "$deleted_sessions adet oturum kaydı silindi"));
        
        // 3. Kullanıcının session_token'ını sıfırla (varsa)
        $checkColumn = $db->query("SHOW COLUMNS FROM `users` LIKE 'session_token'");
        if ($checkColumn->rowCount() > 0) {
            $stmt = $db->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            error_log("clearUserSessions: " . (getActiveLang() == 'en' ? "User's session_token value reset" : "Kullanıcının session_token değeri sıfırlandı"));
        }
        
        // 4. Kullanıcı bilgilerini al ve premium durumunu doğrula
        $stmt = $db->prepare("SELECT username, is_premium, premium_until FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $username = $user_data['username'] ?? 'bilinmeyen';
            $is_premium = $user_data['is_premium'] ?? 0;
            $premium_until = $user_data['premium_until'] ?? null;
            
            error_log("clearUserSessions: Kullanıcı: $username, Premium durumu: " . 
                     ($is_premium ? 'Premium (Bitiş: ' . ($premium_until ?? 'Belirtilmemiş') . ')' : 'Normal') . 
                     " için oturum temizleme tamamlandı");
                
            // 5. Tutarsız premium durumu düzelt (is_premium=1 ama premium_until=null veya geçmiş tarihse)
            if ($is_premium && (!$premium_until || $premium_until < date('Y-m-d'))) {
                $update = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                $update->execute([$user_id]);
                error_log("clearUserSessions: Kullanıcı $username (ID: $user_id) için tutarsız premium durumu düzeltildi");
            }
        } else {
            error_log("clearUserSessions: UYARI - ID:$user_id için kullanıcı bulunamadı!");
        }
          // 6. Oturum verileriyle ilgili güvenli temizlik
        error_log("clearUserSessions: Güvenli oturum temizleme işlemi başlatılıyor");
        
        // Bu kısım dosya erişim izni gerektirdiğinden ve her sistemde çalışmayabileceğinden,
        // bunu daha güvenli bir yöntemle değiştiriyoruz
        try {
            // A. Oturum bilgilerini veritabanından temizle - kullanıcı oturum tablosunu güncelle
            $delete_query = "DELETE FROM user_sessions WHERE user_id = ?";
            $stmt = $db->prepare($delete_query);
            if ($stmt->execute([$user_id])) {
                error_log("clearUserSessions: Kullanıcı oturumları veritabanından silindi");
            }
            
            // B. Kullanıcının session_token'ını sıfırla (varsa)
            $token_check = $db->query("SHOW COLUMNS FROM `users` LIKE 'session_token'");
            if ($token_check && $token_check->rowCount() > 0) {
                $stmt = $db->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                error_log("clearUserSessions: Kullanıcının session_token'ı sıfırlandı");
            }
            
            // C. Aktif oturumların kaydını silen özel komut çalıştır
            // Bu kısım oturum dosyalarına erişemediğimiz için alternatif bir çözümdür
            if (function_exists('session_regenerate_id')) {
                // Mevcut oturum ID'sini yenile - eski oturumla olan bağlantıyı kopar
                if (session_id()) {
                    @session_regenerate_id(true);
                    error_log("clearUserSessions: Mevcut oturum ID'si yenilendi");
                }
            }
        } catch (Exception $e) {
            error_log("clearUserSessions: Oturum temizleme hatası: " . $e->getMessage());
        }
        
        // 7. Mevcut oturum bu kullanıcıya ait ise, oturum değişkenlerini de temizle
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
            $_SESSION['is_premium'] = 0;
            $_SESSION['premium_until'] = null;
            error_log("clearUserSessions: Mevcut oturumdaki premium bilgileri sıfırlandı");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("clearUserSessions HATA: " . $e->getMessage());
        return false;
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-full mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-gray-900 dark:text-gray-100"><?php echo getActiveLang() == 'en' ? 'Premium Membership Management' : 'Premium Üyelik Yönetimi'; ?></h1>

    <?php if ($message): ?>
    <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'Database Checks' : 'Veritabanı Kontrolleri'; ?></h2>
            
            <div class="space-y-6">
                <!-- Tablo ve Sütun Bilgisi -->
                <div>
                    <h3 class="font-medium text-lg text-gray-800 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'Database Structure' : 'Veritabanı Yapısı'; ?></h3>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-2">
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-600 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'users table' : 'users tablosu'; ?></td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <?php echo $table_exists ? 
                                        '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded">' . (getActiveLang() == 'en' ? 'Exists' : 'Mevcut') . '</span>' : 
                                        '<span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 rounded">' . (getActiveLang() == 'en' ? 'Not Found' : 'Bulunamadı') . '</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-600 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'is_premium column' : 'is_premium sütunu'; ?></td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <?php echo $column_info ? 
                                        '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded">' . (getActiveLang() == 'en' ? 'Exists' : 'Mevcut') . '</span>' : 
                                        '<span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 rounded">' . (getActiveLang() == 'en' ? 'Not Found' : 'Bulunamadı') . '</span>'; ?>
                                </td>
                            </tr>                            <?php if ($column_info): ?>
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-600 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'is_premium type' : 'is_premium tipi'; ?></td>
                                <td class="px-4 py-2 whitespace-nowrap dark:text-gray-300">
                                    <?php echo $column_info['Type']; ?>
                                    <?php 
                                    if (strpos(strtolower($column_info['Type']), 'tinyint') === false) {
                                        echo ' <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 rounded">' . (getActiveLang() == 'en' ? 'WARNING: Should be TINYINT' : 'UYARI: TINYINT olmalıdır') . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                  <!-- Düzeltme İşlemleri -->
                <div class="border-t dark:border-gray-700 pt-4">
                    <h3 class="font-medium text-lg mb-2 text-gray-800 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'Database Fixes' : 'Veritabanı Düzeltmeleri'; ?></h3>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST">
                            <button type="submit" name="fix_premium_column" class="px-4 py-2 bg-blue-500 dark:bg-blue-600 text-white rounded hover:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                                <?php echo getActiveLang() == 'en' ? 'Fix Premium Column' : 'Premium Sütununu Düzelt'; ?>
                            </button>
                        </form>
                        
                        <form method="POST">
                            <button type="submit" name="show_premium_info" class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded hover:bg-gray-600 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800">
                                <?php echo getActiveLang() == 'en' ? 'Show Column Details' : 'Sütun Detaylarını Göster'; ?>
                            </button>
                        </form>
                          <form method="POST">
                            <button type="submit" name="reset_premium" onclick="return confirm('<?php echo getActiveLang() == 'en' ? 'Are you sure you want to reset all premium memberships?' : 'Tüm premium üyelikleri sıfırlamak istediğinize emin misiniz?'; ?>')" class="px-4 py-2 bg-red-500 dark:bg-red-600 text-white rounded hover:bg-red-600 dark:hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800">
                                <?php echo getActiveLang() == 'en' ? 'Reset All Premium Memberships' : 'Tüm Premium Üyelikleri Sıfırla'; ?>
                            </button>
                        </form>
                        
                        <form method="POST">
                            <button type="submit" name="show_premium_users" class="px-4 py-2 bg-green-500 dark:bg-green-600 text-white rounded hover:bg-green-600 dark:hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800">
                                <?php echo getActiveLang() == 'en' ? 'List Premium Users' : 'Premium Kullanıcıları Listele'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'Make User Premium' : 'Kullanıcı Premium Yapma'; ?></h2>
            
            <form method="POST" class="mb-4">
                <div class="mb-4">
                    <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'Select User:' : 'Kullanıcı Seçin:'; ?></label>
                    <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            ID: <?php echo $user['id']; ?> - 
                            <?php echo $user['username']; ?> 
                            <?php echo $user['is_premium'] ? (getActiveLang() == 'en' ? '(Premium)' : '(Premium)') : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="make_premium" class="w-full px-4 py-2 bg-yellow-500 dark:bg-yellow-600 text-white rounded hover:bg-yellow-600 dark:hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 dark:focus:ring-offset-gray-800">
                    <?php echo getActiveLang() == 'en' ? 'Make Selected User Premium (1 Month)' : 'Seçilen Kullanıcıyı Premium Yap (1 Ay)'; ?>
                </button>
            </form>
        </div>
    </div>
      <!-- Kullanıcı Listesi -->
    <div class="mt-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'All Users' : 'Tüm Kullanıcılar'; ?></h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                            ID
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                            <?php echo getActiveLang() == 'en' ? 'Username' : 'Kullanıcı Adı'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                            <?php echo getActiveLang() == 'en' ? 'Premium Status' : 'Premium Durumu'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                            <?php echo getActiveLang() == 'en' ? 'Premium Expiry' : 'Premium Bitiş'; ?>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-200">
                            <?php echo $user['id']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-200">
                            <?php echo $user['username']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo (int)$user['is_premium'] ? 
                                '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-full">' . (getActiveLang() == 'en' ? 'Premium' : 'Premium') . '</span>' : 
                                '<span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 rounded-full">' . (getActiveLang() == 'en' ? 'Normal' : 'Normal') . '</span>'; 
                            ?>
                        </td>                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            if ($user['premium_until']) {
                                $today = date('Y-m-d');
                                $is_valid = $user['premium_until'] >= $today;
                                $days_left = 0;
                                
                                if ($is_valid) {
                                    // Kalan gün sayısını hesapla
                                    $premium_date = new DateTime($user['premium_until']);
                                    $today_date = new DateTime($today);
                                    $interval = $today_date->diff($premium_date);
                                    $days_left = $interval->days;
                                }
                                
                                echo '<span class="text-gray-900 dark:text-gray-200">' . date('d.m.Y', strtotime($user['premium_until'])) . '</span> (' . 
                                    ($is_valid ? 
                                        '<span class="text-green-600 dark:text-green-400">' . (getActiveLang() == 'en' ? 'Valid - ' . $days_left . ' days left' : 'Geçerli - ' . $days_left . ' gün kaldı') . '</span>' : 
                                        '<span class="text-red-600 dark:text-red-400">' . (getActiveLang() == 'en' ? 'Expired' : 'Süresi Dolmuş') . '</span>')
                                    . ')';
                            } else {
                                echo '<span class="text-gray-500 dark:text-gray-400">-</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
      <!-- Kullanıcı Premium Durumu Düzenleme -->    <?php if (isset($user_to_edit)): ?>
    <div class="mt-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md border border-blue-200 dark:border-blue-800">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'Edit Premium Membership: ' : 'Premium Üyelik Düzenle: '; ?><?php echo $user_to_edit['username']; ?></h2>
        
        <div class="mb-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg">
            <h3 class="font-medium text-gray-900 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'User Information' : 'Kullanıcı Bilgileri'; ?></h3>
            <div class="grid grid-cols-2 gap-4 mt-3">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'User ID:' : 'Kullanıcı ID:'; ?></p>
                    <p class="font-medium text-gray-900 dark:text-gray-200"><?php echo $user_to_edit['id']; ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Username:' : 'Kullanıcı Adı:'; ?></p>
                    <p class="font-medium text-gray-900 dark:text-gray-200"><?php echo $user_to_edit['username']; ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Email:' : 'E-posta:'; ?></p>
                    <p class="font-medium text-gray-900 dark:text-gray-200"><?php echo $user_to_edit['email'] ?? (getActiveLang() == 'en' ? 'Not specified' : 'Belirtilmemiş'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Current Premium Status:' : 'Mevcut Premium Durumu:'; ?></p>
                    <p class="font-medium">
                        <?php if ($user_to_edit['is_premium']): ?>
                            <span class="text-green-600 dark:text-green-400"><?php echo getActiveLang() == 'en' ? 'Premium Member' : 'Premium Üye'; ?></span>
                            <?php if ($user_to_edit['premium_until']): ?>
                                (<?php echo getActiveLang() == 'en' ? 'Expires: ' : 'Bitiş: '; ?><span class="text-gray-900 dark:text-gray-200"><?php echo date('d.m.Y', strtotime($user_to_edit['premium_until'])); ?></span>)
                                <?php 
                                $today = date('Y-m-d');
                                $days_left = (strtotime($user_to_edit['premium_until']) - strtotime($today)) / (60*60*24);
                                if ($days_left > 0):
                                ?>
                                <span class="text-sm text-blue-600 dark:text-blue-400">(<?php echo getActiveLang() == 'en' ? ceil($days_left) . ' days left' : ceil($days_left) . ' gün kaldı'; ?>)</span>
                                <?php else: ?>
                                <span class="text-sm text-red-600 dark:text-red-400">(<?php echo getActiveLang() == 'en' ? 'Expired' : 'Süresi dolmuş'; ?>)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Regular Member' : 'Normal Üye'; ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
          <form method="POST" class="mt-6">
            <div class="mb-4">
                <div class="flex items-center mb-2">
                    <input type="checkbox" id="is_premium" name="is_premium" class="h-5 w-5 text-blue-600 dark:text-blue-500 border-gray-300 dark:border-gray-700 rounded dark:bg-gray-700" 
                           <?php echo $user_to_edit['is_premium'] ? 'checked' : ''; ?>>
                    <label for="is_premium" class="ml-2 block font-medium text-gray-900 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'Activate Premium Membership' : 'Premium Üyeliği Aktif Et'; ?></label>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 ml-7"><?php echo getActiveLang() == 'en' ? 'If you check this box, the user will be set as a premium member.' : 'Bu kutucuğu işaretlerseniz kullanıcı premium üye olarak ayarlanır.'; ?></p>
            </div>
            
            <div class="mb-4">
                <label for="premium_until" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo getActiveLang() == 'en' ? 'Premium Expiry Date:' : 'Premium Bitiş Tarihi:'; ?></label>
                <input type="date" id="premium_until" name="premium_until" 
                       value="<?php echo $user_to_edit['premium_until'] ? date('Y-m-d', strtotime($user_to_edit['premium_until'])) : date('Y-m-d', strtotime('+1 month')); ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Last valid date of premium membership' : 'Premium üyelik son geçerlilik tarihi'; ?></p>
            </div>
              <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 dark:border-yellow-600 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400 dark:text-yellow-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700 dark:text-yellow-300">
                            <?php echo getActiveLang() == 'en' ? 'Important: When the premium status is changed, the user will need to log in again.' : 'Önemli: Premium durumunda değişiklik yapıldığında, kullanıcının tekrar giriş yapması gerekecektir.'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3">
                <button type="submit" name="update_premium" class="px-4 py-2 bg-blue-500 dark:bg-blue-600 text-white rounded hover:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                    <?php echo getActiveLang() == 'en' ? 'Save' : 'Kaydet'; ?>
                </button>
                <a href="../admin/subscriptions.php" class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded hover:bg-gray-600 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800">
                    <?php echo getActiveLang() == 'en' ? 'Cancel' : 'İptal'; ?>
                </a>
            </div>
        </form>
    </div>    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<?php require_once 'includes/footer.php'; ?>
