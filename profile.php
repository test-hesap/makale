<?php
// Çıktı tamponlamasını başlat
ob_start();

require_once 'includes/config.php';

// Canonical URL ekleyelim
$canonical_url = getSetting('site_url') . "/profil";

// Hata raporlama etkin - sorunları görmek için
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Hata günlüğünü açık tut
ini_set('log_errors', 1);

// Varsayılan avatar dosyasını config.php'den al
$default_avatar = DEFAULT_AVATAR_PATH;
$default_avatar_dir = dirname($default_avatar);

if (!file_exists($default_avatar)) {
    if (!is_dir($default_avatar_dir)) {
        mkdir($default_avatar_dir, 0777, true);
    }
    
    // Base64 ile kodlanmış basit bir avatar resmi
    $avatar_data = base64_decode('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');
    file_put_contents($default_avatar, $avatar_data);
}

// Session kontrolü
// Session zaten config.php'de başlatıldı
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Session'da başarı mesajı varsa al ve temizle
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Veritabanı bağlantısı kontrolü
if (!isset($db)) {
    die("Veritabanı bağlantısı kurulamadı");
}

// Veritabanı bağlantısı ve kullanıcı bilgilerini al
$user = [
    'id' => $_SESSION['user_id'] ?? 0,
    'username' => $_SESSION['username'] ?? '',
    'email' => '',  // E-posta adresini veritabanından alacağız
    'avatar' => $_SESSION['avatar'] ?? DEFAULT_AVATAR_PATH,
    'role' => $_SESSION['role'] ?? ''
];

// Veritabanı işlemleri için try-catch kullanılacak

try {
    if (!isset($db)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    // Kullanıcı bilgilerini veritabanından al
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
      // Kullanıcı bulunamadıysa SESSION'daki bilgileri kullan
    if (!$user) {
        // SESSION'dan kullanıcı bilgilerini al
        $user = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'Misafir',
            'email' => '', // SESSION'da email olmayabilir
            'avatar' => $_SESSION['avatar'] ?? DEFAULT_AVATAR_PATH
        ];
        
        // Email'i veritabanından ayrı sorguyla al (bazen ana sorgu email'i getirmeyebiliyor)
        try {
            $email_stmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $email_stmt->execute([$_SESSION['user_id']]);
            $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);
            if ($email_result && !empty($email_result['email'])) {
                $user['email'] = $email_result['email'];
                error_log("Profil e-posta düzeltme: Kullanıcı e-postası ayrı sorgu ile alındı: " . $email_result['email']);
            }
        } catch (Exception $e) {
            error_log("Profil e-posta düzeltme hata: " . $e->getMessage());
        }
    }
    
    // Kullanıcı bilgileri hala eksikse hata fırlat
    if (empty($user['username'])) {
        throw new Exception("Kullanıcı bilgileri eksik. Session ve DB'de bilgi bulunamadı.");
    }
      // Veritabanından sosyal medya ve biyografi bilgilerini yeniden al
    try {
        $stmt = $db->prepare("SELECT bio, location, website, twitter, facebook, instagram, linkedin, youtube, tiktok, github FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $social_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Eğer veritabanında bilgiler varsa, onları kullan
        if ($social_info) {
            $user['bio'] = $social_info['bio'] ?? '';
            $user['location'] = $social_info['location'] ?? '';
            $user['website'] = $social_info['website'] ?? '';
            $user['twitter'] = $social_info['twitter'] ?? '';
            $user['facebook'] = $social_info['facebook'] ?? '';
            $user['instagram'] = $social_info['instagram'] ?? '';
            $user['linkedin'] = $social_info['linkedin'] ?? '';
            $user['youtube'] = $social_info['youtube'] ?? '';
            $user['tiktok'] = $social_info['tiktok'] ?? '';
            $user['github'] = $social_info['github'] ?? '';
            
            // Session'ı da güncelle
            $_SESSION['user_bio'] = $social_info['bio'] ?? '';
            $_SESSION['user_location'] = $social_info['location'] ?? '';
            $_SESSION['user_website'] = $social_info['website'] ?? '';
            $_SESSION['user_twitter'] = $social_info['twitter'] ?? '';
            $_SESSION['user_facebook'] = $social_info['facebook'] ?? '';
            $_SESSION['user_instagram'] = $social_info['instagram'] ?? '';
            $_SESSION['user_linkedin'] = $social_info['linkedin'] ?? '';
            $_SESSION['user_youtube'] = $social_info['youtube'] ?? '';
            $_SESSION['user_tiktok'] = $social_info['tiktok'] ?? '';
            $_SESSION['user_github'] = $social_info['github'] ?? '';
            
            error_log("Profile.php: Sosyal medya bilgileri veritabanından yüklendi ve session güncellendi.");
        }
        // Eğer veritabanında bilgi yoksa ama session'da varsa, onları kullan
        else {
            if (isset($_SESSION['user_bio'])) $user['bio'] = $_SESSION['user_bio'];
            if (isset($_SESSION['user_location'])) $user['location'] = $_SESSION['user_location'];
            if (isset($_SESSION['user_website'])) $user['website'] = $_SESSION['user_website'];
            if (isset($_SESSION['user_twitter'])) $user['twitter'] = $_SESSION['user_twitter'];
            if (isset($_SESSION['user_facebook'])) $user['facebook'] = $_SESSION['user_facebook'];
            if (isset($_SESSION['user_instagram'])) $user['instagram'] = $_SESSION['user_instagram'];
            if (isset($_SESSION['user_linkedin'])) $user['linkedin'] = $_SESSION['user_linkedin'];
            if (isset($_SESSION['user_youtube'])) $user['youtube'] = $_SESSION['user_youtube'];
            if (isset($_SESSION['user_tiktok'])) $user['tiktok'] = $_SESSION['user_tiktok'];
            if (isset($_SESSION['user_github'])) $user['github'] = $_SESSION['user_github'];
            
            error_log("Profile.php: Sosyal medya bilgileri veritabanında bulunamadı, session'dan alındı.");
        }
    } catch (PDOException $e) {
        error_log("Sosyal medya bilgileri alınırken hata: " . $e->getMessage());
        // Hata durumunda session'dan bilgileri al
        if (isset($_SESSION['user_bio'])) $user['bio'] = $_SESSION['user_bio'];
        if (isset($_SESSION['user_location'])) $user['location'] = $_SESSION['user_location'];
        if (isset($_SESSION['user_website'])) $user['website'] = $_SESSION['user_website'];
        if (isset($_SESSION['user_twitter'])) $user['twitter'] = $_SESSION['user_twitter'];
        if (isset($_SESSION['user_facebook'])) $user['facebook'] = $_SESSION['user_facebook'];
        if (isset($_SESSION['user_instagram'])) $user['instagram'] = $_SESSION['user_instagram'];
        if (isset($_SESSION['user_linkedin'])) $user['linkedin'] = $_SESSION['user_linkedin'];
        if (isset($_SESSION['user_youtube'])) $user['youtube'] = $_SESSION['user_youtube'];
        if (isset($_SESSION['user_tiktok'])) $user['tiktok'] = $_SESSION['user_tiktok'];
        if (isset($_SESSION['user_github'])) $user['github'] = $_SESSION['user_github'];
    }
      error_log("Profile.php: Kullanıcı verileri yüklendi, SESSION'dan veriler entegre edildi: " . 
             (isset($_SESSION['user_bio']) ? 'Bio var, ' : 'Bio yok, ') . 
             (isset($_SESSION['user_location']) ? 'Location var' : 'Location yok'));
    } catch (PDOException $e) {
    error_log("Veritabanı hatası: " . $e->getMessage());
    $error_message = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
    
    // SESSION'dan yedek bilgileri al
    $user = [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'Misafir',
        'email' => '',
        'avatar' => $_SESSION['avatar'] ?? 'default-avatar.jpg'
    ];
    
} catch (Exception $e) {
    error_log("Genel hata: " . $e->getMessage());
    $error_message = $e->getMessage();
    
    // SESSION'dan yedek bilgileri al
    $user = [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'Misafir',
        'email' => '',
        'avatar' => $_SESSION['avatar'] ?? 'default-avatar.jpg'
    ];
}

// E-posta güncelleme işlemi
if (isset($_POST['update_profile'])) {
    // Debug bilgisi
    error_log("UPDATE_PROFILE çalışıyor. POST verileri: " . print_r($_POST, true));
    
    $new_email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $is_valid = true;
    
    // Öncelikle mevcut e-postayı alalım
    $current_email = '';
    try {
        $current_email_stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $current_email_stmt->execute([$user_id]);
        $current_email_result = $current_email_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_email_result && isset($current_email_result['email'])) {
            $current_email = $current_email_result['email'];
            error_log("E-posta güncelleme: Mevcut e-posta alındı: " . $current_email);
        } else {
            error_log("E-posta güncelleme: Kullanıcı bulunamadı veya e-posta adresi yok. user_id: " . $user_id);
        }
    } catch (Exception $e) {
        error_log("E-posta güncelleme - mevcut e-posta sorgusunda hata: " . $e->getMessage());
    }
    
    // E-posta kontrolü
    if (empty($new_email)) {
        $error_message = "E-posta adresi boş bırakılamaz.";
        $is_valid = false;
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Geçerli bir e-posta adresi giriniz.";
        $is_valid = false;
    } else if (!empty($current_email) && $current_email === $new_email) {
        // Eğer e-posta değişmediyse, güncelleme yapmadan başarılı mesajı gösterelim
        $success_message = "E-posta adresiniz zaten güncel.";
        $user['email'] = $new_email; // User dizisini güncelleyelim
        $_SESSION['user_email'] = $new_email; // Session'a da kaydedelim
        $is_valid = false; // Güncelleme yapmayalım
        error_log("E-posta güncelleme: Değişiklik yok, mevcut e-posta: " . $current_email);
    } else {
        // E-posta adresinin başka biri tarafından kullanılıp kullanılmadığını kontrol et
        $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$new_email, $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $error_message = "Bu e-posta adresi zaten kullanılıyor.";
            $is_valid = false;
            error_log("E-posta güncelleme: E-posta başkası tarafından kullanılıyor: " . $new_email);
        }
    }

    // E-posta güncelle
    if ($is_valid) {
        try {
            $update_stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
            if ($update_stmt->execute([$new_email, $user_id])) {
                $success_message = "E-posta adresiniz başarıyla güncellendi.";
                $user['email'] = $new_email;
                $_SESSION['user_email'] = $new_email; // Session'a da kaydedelim
                error_log("E-posta güncelleme: Başarılı - Yeni e-posta: " . $new_email);
                
                // Sayfayı yenileyerek kullanıcıyı taze verilerle görelim
                header("Location: profile.php?updated=1");
                exit();
            } else {
                $error_message = "E-posta güncellenirken bir hata oluştu.";
                error_log("E-posta güncelleme: Başarısız - Hata kodu: " . implode(", ", $db->errorInfo()));
            }
        } catch (Exception $e) {
            $error_message = "E-posta güncellenirken bir hata oluştu.";
            error_log("E-posta güncelleme exception: " . $e->getMessage());
        }
    }
}

// Şifre değiştirme işlemi
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $user_id]);
            $success_message = "Şifreniz başarıyla güncellendi.";
        } else {
            $error_message = "Yeni şifreler eşleşmiyor.";
        }
    } else {
        $error_message = "Mevcut şifre yanlış.";
    }
}

// Profil resmi güncelleme
if (isset($_POST['update_avatar']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Uploads klasörünü kontrol et ve oluştur
    $avatar_dir = 'uploads/avatars';
    if (!is_dir($avatar_dir)) {
        $old_umask = umask(0);
        $mkdir_result = @mkdir($avatar_dir, 0777, true);
        umask($old_umask);
        if (!$mkdir_result) {
            error_log("Avatar dizini oluşturulamadı: " . $avatar_dir);
            error_log("PHP işlem kullanıcısı: " . exec('whoami'));
            error_log("Dizin izinleri (üst klasör): " . substr(sprintf('%o', fileperms(dirname($avatar_dir))), -4));
            $error_message = "Profil resmi yükleme klasörü oluşturulamadı. Lütfen site yöneticisine bildirin.";
        } else {
            // LiteSpeed ve Apache için klasör izinlerini ayarla
            @chmod($avatar_dir, 0777);
            error_log("Avatar dizini oluşturuldu: " . $avatar_dir);
        }
    }
    
    // Dizin yazılabilir mi kontrol et
    if (!is_writable($avatar_dir)) {
        error_log("Avatar dizini yazılabilir değil: " . $avatar_dir);
        error_log("Dizin izinleri: " . substr(sprintf('%o', fileperms($avatar_dir)), -4));
        $error_message = "Profil resmi yükleme klasörüne yazma izni yok. Lütfen site yöneticisine bildirin.";
        // Dizini yazılabilir yapmaya çalış
        @chmod($avatar_dir, 0777);
    }
    
    // Hata mesajı varsa işleme devam etme
    if (!empty($error_message)) {
        // Hata mesajı yukarıda tanımlandı, işleme devam etmeyin
    }
    else if ($file['size'] > $max_size) {
        $error_message = "Dosya boyutu çok büyük. Maksimum 5MB yükleyebilirsiniz.";
    } elseif (!in_array($file['type'], $allowed_types)) {
        $error_message = "Sadece JPG, PNG ve GIF formatları desteklenmektedir.";
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $extension;
        $upload_path = 'uploads/avatars/' . $new_filename;
        
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Dosyanın başarıyla yüklendiğini ve okunabilir olduğunu kontrol et
            if (file_exists($upload_path) && is_readable($upload_path)) {
                // İzinleri ayarla
                @chmod($upload_path, 0666);
                
                error_log("Avatar yüklendi: " . $upload_path);
                error_log("Dosya boyutu: " . filesize($upload_path) . " bytes");
                error_log("Dosya izinleri: " . substr(sprintf('%o', fileperms($upload_path)), -4));
                
                // Eski profil resmini sil (varsayılan avatar dışındakiler)
                if (!empty($user['avatar']) && $user['avatar'] !== 'default-avatar.jpg') {
                    $old_avatar = 'uploads/avatars/' . $user['avatar'];
                    if (file_exists($old_avatar)) {
                        @unlink($old_avatar);
                        error_log("Eski avatar silindi: " . $old_avatar);
                    }
                }
                
                // Veritabanını güncelle - sadece dosya adını sakla
                $update_stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                if ($update_stmt->execute([$new_filename, $user_id])) {
                    $success_message = "Profil resminiz başarıyla güncellendi.";
                    $user['avatar'] = $new_filename;
                    // Session'daki avatar bilgisini güncelle
                    $_SESSION['avatar'] = $new_filename;
                    
                    error_log("Avatar veritabanı ve session güncellendi: " . $new_filename);
                }
                
                // Hata ayıklama için
                error_log("Avatar güncellendi: kullanıcı_id=" . $user_id . ", yeni_avatar=" . $new_filename);
                
                // Bu bilgiyi tüm oturumlarda doğru göstermek için
                $update_session = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $update_session->execute([$new_filename, $user_id]);
            } else {
                $error_message = "Veritabanı güncellenirken bir hata oluştu.";
                if (file_exists($upload_path)) {
                    unlink($upload_path);
                }
            }
        } else {
            $error_message = "Dosya yüklenirken bir hata oluştu.";
        }
    }
}

// Profil bilgilerini güncelleme
if (isset($_POST['update_profile_details'])) {
    $bio = trim($_POST['bio']);
    $location = trim($_POST['location']);
    $website = trim($_POST['website']);
    $twitter = trim($_POST['twitter']);
    $facebook = trim($_POST['facebook']);
    $instagram = trim($_POST['instagram']);
    $linkedin = trim($_POST['linkedin']);
    $youtube = trim($_POST['youtube']);
    $tiktok = trim($_POST['tiktok']);
    $github = trim($_POST['github']);
    
    $is_valid = true;
    
    // URL kontrolü (opsiyonel)
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $error_message = "Geçerli bir web sitesi URL'si giriniz.";
        $is_valid = false;
    }
      // Bilgileri güncelle
    if ($is_valid) {
        try {
            // Önce veritabanında bu alanların olup olmadığını kontrol edelim
            $check_columns = $db->query("SHOW COLUMNS FROM users LIKE 'bio'");
            $bio_exists = ($check_columns->rowCount() > 0);
            
            if ($bio_exists) {
                // Tüm sosyal medya alanları mevcut
                $update_stmt = $db->prepare("UPDATE users SET 
                    bio = ?, 
                    location = ?, 
                    website = ?, 
                    twitter = ?, 
                    facebook = ?, 
                    instagram = ?,
                    linkedin = ?,
                    youtube = ?,
                    tiktok = ?,
                    github = ?
                    WHERE id = ?");
                
                if ($update_stmt->execute([
                    $bio, 
                    $location, 
                    $website, 
                    $twitter, 
                    $facebook, 
                    $instagram, 
                    $linkedin, 
                    $youtube,
                    $tiktok,
                    $github,
                    $user_id                ])) {
                    $success_message = "Profil bilgileriniz başarıyla güncellendi.";
                      // Oturumu güncelle ve yeni değerleri oturum değişkenlerine ekle
                    try {
                        $refresh_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $refresh_stmt->execute([$user_id]);
                        $refreshed_user = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($refreshed_user) {
                            // Veritabanından güncellenen bilgileri al
                            $user = $refreshed_user;
                            
                            // Sosyal medya alanlarını oturum değişkenlerine ekle 
                            $_SESSION['user_bio'] = $user['bio'];
                            $_SESSION['user_location'] = $user['location'];
                            $_SESSION['user_website'] = $user['website'];
                            $_SESSION['user_twitter'] = $user['twitter'];
                            $_SESSION['user_facebook'] = $user['facebook'];
                            $_SESSION['user_instagram'] = $user['instagram'];
                            $_SESSION['user_linkedin'] = $user['linkedin'];
                            $_SESSION['user_youtube'] = $user['youtube'];
                            $_SESSION['user_tiktok'] = $user['tiktok'];
                            $_SESSION['user_github'] = $user['github'];
                            
                            error_log("Kullanıcı profil bilgileri güncellendi ve SESSION'a kaydedildi: " . 
                                     print_r([
                                         'bio' => $_SESSION['user_bio'],
                                         'location' => $_SESSION['user_location'],
                                         'website' => $_SESSION['user_website']
                                     ], true));
                        } else {
                            // Veritabanından güncel bilgileri alamadıysak manuel olarak değiştirelim
                            $user['bio'] = $bio;
                            $user['location'] = $location;
                            $user['website'] = $website;
                            $user['twitter'] = $twitter;
                            $user['facebook'] = $facebook;
                            $user['instagram'] = $instagram;
                            $user['linkedin'] = $linkedin;
                            $user['youtube'] = $youtube;
                            $user['tiktok'] = $tiktok;
                            $user['github'] = $github;
                            
                            // Ayrıca oturum değişkenlerine de ekle
                            $_SESSION['user_bio'] = $bio;
                            $_SESSION['user_location'] = $location;
                            $_SESSION['user_website'] = $website;
                            $_SESSION['user_twitter'] = $twitter;
                            $_SESSION['user_facebook'] = $facebook;
                            $_SESSION['user_instagram'] = $instagram;
                            $_SESSION['user_linkedin'] = $linkedin;
                            $_SESSION['user_youtube'] = $youtube;
                            $_SESSION['user_tiktok'] = $tiktok;
                            $_SESSION['user_github'] = $github;
                            
                            error_log("Veritabanından güncel bilgiler alınamadı, manuel güncelleme yapıldı ve SESSION güncellendi.");
                        }
                    } catch (PDOException $e) {
                        // Hata olursa manuel olarak değiştirelim
                        $user['bio'] = $bio;
                        $user['location'] = $location;
                        $user['website'] = $website;
                        $user['twitter'] = $twitter;
                        $user['facebook'] = $facebook;
                        $user['instagram'] = $instagram;
                        $user['linkedin'] = $linkedin;
                        $user['youtube'] = $youtube;
                        $user['tiktok'] = $tiktok;
                        $user['github'] = $github;
                        
                        // Oturum değişkenlerine de ekle
                        $_SESSION['user_bio'] = $bio;
                        $_SESSION['user_location'] = $location;
                        $_SESSION['user_website'] = $website;
                        $_SESSION['user_twitter'] = $twitter;
                        $_SESSION['user_facebook'] = $facebook;
                        $_SESSION['user_instagram'] = $instagram;
                        $_SESSION['user_linkedin'] = $linkedin;
                        $_SESSION['user_youtube'] = $youtube;
                        $_SESSION['user_tiktok'] = $tiktok;
                        $_SESSION['user_github'] = $github;
                        
                        error_log("Profil bilgileri yenilenirken hata: " . $e->getMessage() . " SESSION ile güncellendi.");
                    }
                    
                    // Form üzerinden POST yapmak yerine sayfayı yenile
                    header("Location: profile.php?updated=1");
                    exit;
                } else {
                    $error_message = "Profil bilgileri güncellenirken bir hata oluştu.";
                    error_log("Profil bilgileri güncellenemedi: " . print_r($db->errorInfo(), true));
                }
            } else {
                // Veritabanında gerekli alanlar yok, kullanıcıyı bilgilendir
                $error_message = "Veritabanı yapılandırması tamamlanmamış. Lütfen site yöneticisi ile iletişime geçin ve 'add_social_media_fields.sql' dosyasının çalıştırılması gerektiğini bildirin.";
                error_log("Sosyal medya alanları veritabanında mevcut değil. add_social_media_fields.sql dosyası çalıştırılmalı.");
            }
        } catch (PDOException $e) {
            $error_message = "Veritabanı hatası oluştu: " . $e->getMessage();
            error_log("Sosyal medya profil güncellemesinde hata: " . $e->getMessage());
        }
    }
}

// GET parametresi kontrolü - güncelleme bildirimi
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success_message = "Profil bilgileriniz başarıyla güncellendi.";
}

require_once 'templates/header.php';
?>

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
    
    /* Firefox için ek güvenlik önlemleri */
    input[type="password"] {
        color-scheme: light dark; /* Firefox için renk şemasını düzeltme */
    }
    
    /* Firefox özel */
    @-moz-document url-prefix() {
        input[type="password"] {
            background-color: white !important;
            color: #333 !important;
        }
        
        .dark input[type="password"] {
            background-color: #292929 !important;
            color: white !important;
        }
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Sol Kısım - Profil Bilgileri -->
        <div class="md:col-span-1"><div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">                <div class="text-center">                    <?php                    // LiteSpeed sorununu çözmek için her zaman base64 kodlu avatar yaklaşımı kullan                    $avatar = $_SESSION['avatar'] ?? $user['avatar'] ?? 'default-avatar.jpg';
                    
                    // Kullanıcı adı güvenli şekilde alınıyor
                    $username = isset($user['username']) ? $user['username'] : ($_SESSION['username'] ?? 'Misafir');
                    
                    // Hata ayıklama için avatar bilgisini loglayalım
                    error_log("Avatar bilgisi: kullanıcı=" . $username . ", avatar=" . $avatar);
                    
                    // getAvatarBase64 fonksiyonunu kullan (functions.php'den)
                    $profile_image_url = getAvatarBase64($avatar);
                    
                    // Hata ayıklama için URL'yi loglayalım
                    error_log("Oluşturulan avatar URL tipi: " . (strpos($profile_image_url, 'data:') === 0 ? 'Base64' : 'Standard URL'));
                    
                    ?>                    <img src="<?php echo $profile_image_url; ?>"
                         alt="<?php echo __('profile_picture'); ?>"
                         class="w-32 h-32 rounded-full mx-auto mb-4 object-cover border-4 border-gray-100 dark:border-gray-700">                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 flex items-center justify-center gap-2">
                        <?php 
                        // Önce SESSION'dan kontrol et, sonra user dizisinden
                        $username = $_SESSION['username'] ?? $user['username'] ?? __('profile_username_not_found');
                        echo '<a href="uyeler/'.htmlspecialchars($username).'" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">'.htmlspecialchars($username).'</a>';
                        // Kullanıcı durumu ikonunu ekle
                        echo getUserStatusHtml(isUserOnline($user_id));
                        ?>
                    </h2><p class="text-gray-600 dark:text-gray-400 mt-1">
                        <?php                        if (isset($user['email']) && !empty($user['email'])) {
                            echo htmlspecialchars($user['email']);
                        } else {
                            // Veritabanından kullanıcının e-posta adresini direkt olarak çek
                            try {
                                $email_query = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                                $email_query->execute([$user_id]);
                                $email_result = $email_query->fetch(PDO::FETCH_ASSOC);
                                
                                if ($email_result && !empty($email_result['email'])) {
                                    echo htmlspecialchars($email_result['email']);
                                } else {
                                    // E-posta bulunamazsa kullanıcı adıyla uygun bir e-posta oluştur
                                    $username = $_SESSION['username'] ?? $user['username'] ?? 'kullanici';
                                    $defaultEmail = $username . '@mail.com';
                                    echo htmlspecialchars($defaultEmail);
                                }
                            } catch (Exception $e) {
                                // Hata durumunda varsayılan e-posta göster
                                $username = $_SESSION['username'] ?? $user['username'] ?? 'kullanici';
                                $defaultEmail = $username . '@mail.com';
                                echo htmlspecialchars($defaultEmail);
                            }
                        }
                        ?>
                    </p>
                      <!-- Premium Üyelik Bilgisi -->
                    <?php                    $is_premium = $_SESSION['is_premium'] ?? $user['is_premium'] ?? 0;
                    $premium_until = $_SESSION['premium_until'] ?? $user['premium_until'] ?? null;
                    
                    // Role bilgisini daha güvenli bir şekilde al
                    $user_role = '';
                    if (isset($_SESSION['role']) && !empty($_SESSION['role'])) {
                        $user_role = $_SESSION['role'];
                    } elseif (isset($user['role']) && !empty($user['role'])) {
                        $user_role = $user['role'];
                    } else {
                        // Son çare olarak veritabanından direkt sor
                        try {
                            $role_stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                            $role_stmt->execute([$user_id]);
                            $role_result = $role_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($role_result && !empty($role_result['role'])) {
                                $user_role = $role_result['role'];
                                // Session'a da ekleyelim
                                $_SESSION['role'] = $user_role;
                            }
                        } catch (Exception $e) {
                            // Hata durumunda varsayılan role'ü kullan (boş kalacak)
                        }
                    }
                    
                    // Admin kullanıcısı için premium bölümünü gösterme
                    if ($user_role === 'admin'): ?>
                        <div class="mt-4 py-2 px-4 bg-blue-100 text-blue-800 rounded-md flex items-center">
                            <i class="fas fa-shield-alt text-blue-500 mr-2"></i>
                            <div>
                                <p class="font-bold"><?php echo __('profile_admin_account'); ?></p>
                                <p class="text-sm"><?php echo __('profile_admin_access'); ?></p>
                            </div>
                        </div>
                    <?php elseif ($is_premium && $premium_until && strtotime($premium_until) >= time()): ?>
                        <div class="mt-4 py-2 px-4 bg-gray-600 text-white rounded-md flex items-center">
                            <i class="fas fa-crown text-yellow-500 mr-2"></i>
                            <div>
                                <p class="font-bold"><?php echo __('profile_premium_member'); ?></p>
                                <p class="text-sm"><?php echo __('profile_premium_expiry'); ?>: <?php echo date('d.m.Y', strtotime($premium_until)); ?></p>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <a href="refund.php" class="block w-full text-center bg-red-600 text-white rounded-md px-4 py-2 hover:bg-red-700 transition-colors text-sm">
                                <i class="fas fa-undo mr-2"></i> Geri Ödeme Talebi Oluştur
                            </a>
                        </div>                    <?php else: ?>
                        <div class="mt-4">
                            <a href="premium.php" class="block w-full text-center bg-amber-600 text-white rounded-md px-4 py-2 hover:bg-amber-700 transition-colors">
                                <i class="fas fa-crown mr-2"></i><?php echo __('profile_premium_button'); ?>
                            </a>
                            <p class="text-xs text-gray-500 mt-2 text-center"><?php echo __('profile_premium_info'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Profil Resmi Güncelleme Formu -->
                    <form method="POST" enctype="multipart/form-data" class="mt-6">
                        <div class="mt-4">                            <div class="flex items-center justify-center">
                                <label for="profile_picture" class="cursor-pointer bg-gray-600 text-white rounded-md px-4 py-2 hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-camera mr-2"></i><?php echo __('profile_select_image'); ?>
                                    <input type="file" class="hidden" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                                </label>
                            </div>
                            <button type="submit" name="update_avatar" class="w-full mt-4 bg-gray-600 text-white rounded-md px-4 py-2 hover:bg-gray-700 transition-colors">
                                <i class="fas fa-upload mr-2"></i><?php echo __('profile_upload_avatar'); ?>
                            </button>
                        </div>
                     <!-- Makale Ekle Butonu -->                    <div class="mt-6">
                   <a href="/makale_ekle" class="block w-full text-center bg-gray-600 text-white rounded-md px-4 py-3 hover:bg-gray-700 transition-colors font-medium">
                    <i class="fas fa-plus-circle mr-2"></i> <?php echo __('add_article'); ?>
                </a>
                </div>
                    </form>
                </div>
            </div>
        </div>        <!-- Sağ Kısım - Formlar ve Makaleler -->
        <div class="md:col-span-2">
            <?php if ($success_message): ?>
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>            <!-- Profil Bilgileri Güncelleme Formu -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200"><?php echo __('profile_edit'); ?></h3>
                </div><div class="p-6">                    <?php
                    // Kullanıcı bilgilerini direkt SESSION'dan al
                    if (!isset($user['username']) && isset($_SESSION['username'])) {
                        $user['username'] = $_SESSION['username'];
                    }                    
                    // Veritabanından güncel kullanıcı bilgilerini al
                    try {
                        // Hata ayıklama için
                        error_log("Profil güncellemesi: Veritabanından e-posta alınıyor. user_id: " . $user_id);
                        
                        $user_stmt = $db->prepare("SELECT username, email, role FROM users WHERE id = ? LIMIT 1");
                        $user_stmt->execute([$user_id]);
                        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Veritabanındaki kullanıcı bilgilerini kullan
                        if (!empty($user_data)) {
                            $user['email'] = $user_data['email'] ?? '';
                            $user['username'] = $user_data['username'] ?? $user['username'] ?? '';
                            $user['role'] = $user_data['role'] ?? '';
                            
                            // Email bulundu, session'a da kaydedelim
                            if (!empty($user_data['email'])) {
                                $_SESSION['user_email'] = $user_data['email'];
                                error_log("Profil güncellemesi: Veritabanından e-posta alındı ve session'a kaydedildi: " . $user_data['email']);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Profil güncellemesi: Kullanıcı bilgileri alınırken hata: " . $e->getMessage());
                    }
                      
                    // Form gönderimi sonrası kullanıcı verilerini yeniden yükle
                    if (isset($_POST['update_profile']) || isset($_POST['update_avatar'])) {
                        try {
                            $refresh_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                            $refresh_stmt->execute([$user_id]);
                            $refresh_data = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($refresh_data) {
                                // Yeni bilgileri kullanıcı dizisine entegre et
                                foreach($refresh_data as $key => $value) {
                                    $user[$key] = $value;
                                }
                                
                                // E-posta başarıyla alındıysa, session'a kaydedelim
                                if (!empty($refresh_data['email'])) {
                                    $_SESSION['user_email'] = $refresh_data['email'];
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Profil güncellemesi: Form sonrası yenileme hatası: " . $e->getMessage());
                        }
                    }
                    ?>
                    <form method="POST" class="space-y-6">
                        <div class="space-y-4">                            <!-- Kullanıcı Adı -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_username'); ?></label>
                                <div class="p-3 bg-gray-50 dark:bg-[#292929] border border-gray-300 dark:border-gray-600 rounded-md text-gray-800 dark:text-gray-200">
                                    <?php 
                                    // Önce SESSION'dan kontrol et, sonra user dizisinden
                                    $username = $_SESSION['username'] ?? $user['username'] ?? __('profile_username_not_found');
                                    echo '<a href="uyeler/'.htmlspecialchars($username).'" class="text-blue-600 dark:text-blue-400 hover:underline">'.htmlspecialchars($username).'</a>';
                                    ?>
                                </div>
                            </div>
                              <!-- E-posta Adresi -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_email'); ?></label>
                                <?php                                
                                // E-posta adresini güvenli bir şekilde al
                                $email = '';
                                
                                // Önce $_POST varsa oradaki değeri alalım (form gönderiminde hata olduysa)
                                if (isset($_POST['email']) && !empty($_POST['email'])) {
                                    $email = $_POST['email'];
                                    error_log("E-posta form: POST'tan e-posta alındı: " . $email);
                                }
                                // Sonra user dizisinden kontrol et
                                elseif (isset($user['email']) && !empty($user['email'])) {
                                    $email = $user['email'];
                                    error_log("E-posta form: User dizisinden e-posta alındı: " . $email);
                                } 
                                // Session'dan kontrol et 
                                elseif (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
                                    $email = $_SESSION['user_email'];
                                    error_log("E-posta form: Session'dan e-posta alındı: " . $email);
                                }
                                // Son çare olarak direkt veritabanından alalım
                                else {
                                    try {
                                        $email_stmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                                        $email_stmt->execute([$user_id]);
                                        $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($email_result && !empty($email_result['email'])) {
                                            $email = $email_result['email'];
                                            // Session'a da kaydedelim
                                            $_SESSION['user_email'] = $email;
                                            error_log("E-posta form: Veritabanından e-posta alındı: " . $email);
                                        }
                                    } catch (Exception $e) {
                                        error_log("E-posta form sorgusunda hata: " . $e->getMessage());
                                    }
                                }
                                
                                // Eğer e-posta hala boşsa, kullanıcı adıyla varsayılan bir e-posta oluşturalım
                                if (empty($email)) {
                                    $username = $_SESSION['username'] ?? $user['username'] ?? 'kullanici';
                                    $email = $username . '@mail.com';
                                    error_log("E-posta form: Varsayılan e-posta oluşturuldu: " . $email);
                                }
                                
                                // E-posta bulunduysa, kullanıcı dizisine ekleyelim
                                $user['email'] = $email;
                                ?>                                <input type="email" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo htmlspecialchars($email); ?>" 
                                    required 
                                    placeholder="<?php echo __('profile_email_placeholder'); ?>"
                                    class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3">
                            </div>

                            <!-- Güncelleme Butonu -->
                            <button type="submit" 
                                name="update_profile" 
                                class="w-full bg-gray-600 text-white rounded-md py-3 px-4 hover:bg-gray-700 transition-colors flex items-center justify-center">
                                <i class="fas fa-envelope mr-2"></i>
                                <?php echo __('profile_update_email'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>            <!-- Şifre Değiştirme Formu -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200"><?php echo __('profile_password_change'); ?></h3>
                </div>
                <div class="p-6">
                    <form method="POST" autocomplete="off">
                        <div class="space-y-4">
                            <div>                                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo __('profile_current_password'); ?></label>                                <input type="password" 
                                    id="current_password" 
                                    name="current_password" 
                                    required 
                                    autocomplete="new-password"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3">
                            </div>
                            <div>                                <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo __('profile_new_password'); ?></label>                                <input type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    required 
                                    autocomplete="new-password"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3">
                            </div>
                            <div>                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo __('profile_confirm_password'); ?></label>                                <input type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    required 
                                    autocomplete="new-password"
                                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3">
                            </div>
                            <button type="submit" 
                                name="change_password" 
                                class="w-full bg-gray-600 text-white rounded-md px-4 py-2 hover:bg-gray-700 transition-colors">
                                <?php echo __('profile_update_password'); ?>
                            </button>
                        </div>
                    </form>
                    
                    <script>
                    // Firefox için ek güvenlik: Şifre alanlarına her odaklandığında içeriği temizler
                    document.addEventListener('DOMContentLoaded', function() {
                        const passwordFields = document.querySelectorAll('input[type="password"]');
                        
                        passwordFields.forEach(field => {
                            // Sayfa yüklendiğinde value özelliğini temizle
                            field.setAttribute('value', '');
                            
                            // Odaklandığında autocomplete'i devre dışı bırak
                            field.addEventListener('focus', function() {
                                this.setAttribute('autocomplete', 'new-password');
                                
                                // Alanı kısa bir süre sonra type değiştirerek sıfırla ve geri getir
                                // Firefox'un otomatik doldurmasını devre dışı bırakmak için
                                const currentType = this.type;
                                setTimeout(() => {
                                    this.type = 'text';
                                    setTimeout(() => {
                                        this.type = currentType;
                                    }, 1);
                                }, 1);
                            });
                        });
                    });
                    </script>
                </div>            </div>
              <!-- Sosyal Medya ve Biyografi Bilgileri Formu -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200"><?php echo __('profile_social_media'); ?></h3>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <div class="space-y-4">                            <!-- Biyografi -->
                            <div>                                <label for="bio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_bio'); ?></label>
                                <textarea id="bio" name="bio" rows="3" class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_bio_placeholder'); ?>"><?php echo htmlspecialchars($user['bio'] ?? ($_SESSION['user_bio'] ?? '')); ?></textarea>
                            </div>
                            <!-- Konum -->
                            <div>                                <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_location'); ?></label>                                <input type="text" 
                                    id="location" 
                                    name="location" 
                                    value="<?php echo htmlspecialchars($user['location'] ?? ($_SESSION['user_location'] ?? '')); ?>" 
                                    class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" 
                                    placeholder="<?php echo __('profile_location_placeholder'); ?>">
                            </div>
                            <!-- Web Sitesi -->
                            <div>                                <label for="website" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_website'); ?></label>                                <input type="url" 
                                    id="website" 
                                    name="website" 
                                    value="<?php echo htmlspecialchars($user['website'] ?? ($_SESSION['user_website'] ?? '')); ?>" 
                                    class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" 
                                    placeholder="https://example.com">
                            </div>
                            <!-- Sosyal Medya Linkleri -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_social_media_links'); ?></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>                                        <label for="twitter" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_twitter'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-twitter text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="twitter" id="twitter" value="<?php echo htmlspecialchars($user['twitter'] ?? ($_SESSION['user_twitter'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_username_placeholder'); ?>">
                                        </div>
                                    </div>
                                    <div>                                        <label for="facebook" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_facebook'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-facebook-f text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="facebook" id="facebook" value="<?php echo htmlspecialchars($user['facebook'] ?? ($_SESSION['user_facebook'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_username_placeholder'); ?>">
                                        </div>
                                    </div>
                                    <div>                                        <label for="instagram" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_instagram'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-instagram text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="instagram" id="instagram" value="<?php echo htmlspecialchars($user['instagram'] ?? ($_SESSION['user_instagram'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_username_placeholder'); ?>">
                                        </div>
                                    </div>
                                    <div>                                        <label for="linkedin" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_linkedin'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-linkedin-in text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="linkedin" id="linkedin" value="<?php echo htmlspecialchars($user['linkedin'] ?? ($_SESSION['user_linkedin'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_username_placeholder'); ?>">
                                        </div>
                                    </div>
                                    <div>                                        <label for="youtube" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_youtube'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-youtube text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="youtube" id="youtube" value="<?php echo htmlspecialchars($user['youtube'] ?? ($_SESSION['user_youtube'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_channel_id'); ?>">
                                        </div>
                                    </div>
                                    <div>                                        <label for="tiktok" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_tiktok'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-tiktok text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="tiktok" id="tiktok" value="<?php echo htmlspecialchars($user['tiktok'] ?? ($_SESSION['user_tiktok'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_username_placeholder'); ?>">
                                        </div>
                                    </div>
                                    <div>                                        <label for="github" class="block text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo __('profile_github'); ?></label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fab fa-github text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <input type="text" name="github" id="github" value="<?php echo htmlspecialchars($user['github'] ?? ($_SESSION['user_github'] ?? '')); ?>" class="block w-full pl-10 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-[#292929] dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-3" placeholder="<?php echo __('profile_username_placeholder'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>                            <!-- Güncelleme Butonu -->
                            <button type="submit" 
                                name="update_profile_details" 
                                class="w-full bg-gray-600 text-white rounded-md py-3 px-4 hover:bg-gray-700 transition-colors flex items-center justify-center">
                                <i class="fas fa-save mr-2"></i>
                                <?php echo __('profile_save_changes'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
  
        </div>
    </div>
</div>

<?php
// Debug kaldırıldı

// Debug kaldırıldı - sorun çözüldü

require_once 'templates/footer.php';

// Çıktı tamponlamasını sonlandır
ob_end_flush();
