<?php
/**
 * Bakım Modu Manuel Test Dosyası
 * Bu dosya cron job yerine manuel test için kullanılabilir.
 * Cron job kullanmaya gerek yoktur - sistem otomatik olarak kontrol eder.
 * 
 * Manuel test için:
 * php /path/to/your/project/cron/maintenance_check.php
 * 
 * NOT: Bu dosya artık cron job olarak çalıştırılmasına gerek yoktur!
 * Bakım modu otomatik olarak şu durumlarda kontrol edilir:
 * 1. Her sayfa yüklemesinde (config.php)
 * 2. Bakım sayfasında JavaScript ile (her 30 saniye)
 * 3. API çağrıları ile (maintenance_check.php)
 */

define('NO_SESSION_START', true);
define('NO_MAINTENANCE_CHECK', true);

require_once __DIR__ . '/../includes/config.php';

echo "=== Bakım Modu Manuel Test Aracı ===\n";
echo "Tarih: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Bakım modu ayarlarını kontrol et
    $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('maintenance_mode', 'maintenance_end_time', 'maintenance_countdown_enabled')");
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    
    $maintenance_mode = $settings['maintenance_mode'] ?? '0';
    $maintenance_end_time = $settings['maintenance_end_time'] ?? '';
    $maintenance_countdown_enabled = $settings['maintenance_countdown_enabled'] ?? '0';
    
    echo "Mevcut Ayarlar:\n";
    echo "- Bakım Modu: " . ($maintenance_mode === '1' ? 'Aktif' : 'Pasif') . "\n";
    echo "- Geri Sayım: " . ($maintenance_countdown_enabled === '1' ? 'Aktif' : 'Pasif') . "\n";
    echo "- Bitiş Zamanı: " . ($maintenance_end_time ?: 'Belirtilmemiş') . "\n\n";
    
    // Eğer bakım modu aktifse ve geri sayım etkinse
    if ($maintenance_mode === '1' && $maintenance_countdown_enabled === '1' && !empty($maintenance_end_time)) {
        $end_time = new DateTime($maintenance_end_time);
        $now = new DateTime();
        
        echo "Zaman Kontrolü:\n";
        echo "- Şimdiki Zaman: " . $now->format('Y-m-d H:i:s') . "\n";
        echo "- Bitiş Zamanı: " . $end_time->format('Y-m-d H:i:s') . "\n";
        
        if ($now >= $end_time) {
            echo "- Durum: Süre dolmuş, bakım modu kapatılıyor...\n";
            
            // Bakım modunu kapat
            $update_stmt = $db->prepare("UPDATE settings SET value = '0' WHERE `key` = 'maintenance_mode'");
            $update_stmt->execute();
            
            // Log kaydı oluştur
            $log_message = date('Y-m-d H:i:s') . " - Bakım modu manuel test ile kapatıldı. Bitiş zamanı: " . $maintenance_end_time . "\n";
            @file_put_contents(__DIR__ . '/../logs/maintenance.log', $log_message, FILE_APPEND | LOCK_EX);
            
            echo "✅ Bakım modu başarıyla kapatıldı!\n";
        } else {
            $remaining = $end_time->diff($now);
            echo "- Durum: Bakım modu aktif\n";
            echo "- Kalan Süre: " . $remaining->format('%d gün, %h saat, %i dakika') . "\n";
        }
    } else {
        if ($maintenance_mode === '0') {
            echo "✅ Bakım modu zaten pasif.\n";
        } else {
            echo "ℹ️ Bakım modu aktif ama geri sayım kapalı (manuel kapatma gerekli).\n";
        }
    }
    
    echo "\n=== SONUÇ ===\n";
    echo "Cron job kullanmaya gerek yoktur!\n";
    echo "Sistem otomatik olarak kontrol eder:\n";
    echo "1. Her sayfa yüklemesinde\n";
    echo "2. Bakım sayfasında JavaScript ile\n";
    echo "3. API çağrıları ile\n";
    
} catch (Exception $e) {
    $error_message = date('Y-m-d H:i:s') . " - Bakım modu test hatası: " . $e->getMessage() . "\n";
    @file_put_contents(__DIR__ . '/../logs/maintenance.log', $error_message, FILE_APPEND | LOCK_EX);
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>
