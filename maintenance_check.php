<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

require_once 'includes/config.php';

// Bakım modu durumunu kontrol et
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
            $log_message = date('Y-m-d H:i:s') . " - Bakım modu otomatik olarak kapatıldı (API). Bitiş zamanı: " . $maintenance_end_time . "\n";
            @file_put_contents(__DIR__ . '/logs/maintenance.log', $log_message, FILE_APPEND | LOCK_EX);
        }
    }
    
    echo json_encode([
        'success' => true,
        'maintenance_mode' => $maintenance_mode,
        'maintenance_end_time' => $maintenance_end_time,
        'maintenance_countdown_enabled' => $maintenance_countdown_enabled,
        'current_time' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'maintenance_mode' => '0',
        'timestamp' => time()
    ]);
}
?>
