<?php
require_once '../includes/config.php';
checkAuth(true); // Sadece admin kullanıcılar erişebilir

// Hata ayıklama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Dosya parametresini kontrol et
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Hata: Dosya belirtilmedi!");
}

try {
    // Dosya yolunu çöz
    $encoded_file = $_GET['file'];
    $decoded_file = base64_decode($encoded_file);
    
    if ($decoded_file === false) {
        throw new Exception("Geçersiz base64 kodlaması!");
    }
    
    $file_path = realpath($decoded_file);
    $backup_dir = realpath('../backups');
    
    if (!$backup_dir) {
        throw new Exception("Yedek dizini bulunamadı!");
    }
    
    // Güvenlik kontrolleri
    if (!$file_path) {
        throw new Exception("Dosya yolu çözümlenemedi: " . htmlspecialchars($decoded_file));
    }
    
    if (!file_exists($file_path)) {
        throw new Exception("Dosya mevcut değil: " . htmlspecialchars($file_path));
    }
    
    if (!is_file($file_path)) {
        throw new Exception("Bu bir dosya değil!");
    }
    
    // Sadece backups dizini altındaki dosyalara erişime izin ver
    if (strpos($file_path, $backup_dir) !== 0) {
        throw new Exception("Bu dosyaya erişim izniniz yok! Sadece yedek dizinindeki dosyalara erişilebilir.");
    }
    
    // Dosya boyutu kontrolü
    $file_size = filesize($file_path);
    if ($file_size === false || $file_size === 0) {
        throw new Exception("Dosya boş veya boyutu okunamıyor!");
    }

    // Dosya tipini belirle
    $file_info = pathinfo($file_path);
    $extension = strtolower($file_info['extension']);
    $filename = basename($file_path);

// İçerik türünü belirle
switch ($extension) {
    case 'sql':
        $content_type = 'text/plain';
        break;
    case 'gz':
        $content_type = 'application/gzip';
        break;
    case 'zip':
        $content_type = 'application/zip';
        break;
    default:
        $content_type = 'application/octet-stream';
}

// Dosya boyutunu al
$file_size = filesize($file_path);

// İndirme başlıklarını ayarla
header('Content-Description: File Transfer');
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $file_size);

// Çıktı tamponlamasını temizle
@ob_end_clean();

// Zaman aşımını artır
set_time_limit(300); // 5 dakika

// Dosyayı chunk'lar halinde gönder (büyük dosyalar için daha iyi)
$handle = fopen($file_path, 'rb');
if ($handle === false) {
    throw new Exception("Dosya açılamadı!");
}

// Dosyayı 8kb'lik parçalar halinde oku ve gönder
$chunk_size = 8192; // 8kb
while (!feof($handle)) {
    $chunk = fread($handle, $chunk_size);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    flush();
}

fclose($handle);
exit;

} catch (Exception $e) {
    header("HTTP/1.0 500 Internal Server Error");
    echo "<h1>Hata</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='backup.php'>Yedekleme sayfasına geri dön</a></p>";
    
    // Hatayı logla
    error_log("Yedek indirme hatası: " . $e->getMessage());
}
?>
