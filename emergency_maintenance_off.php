<?php
/**
 * Acil Durum Bakım Modu Kapatma
 * Bu dosya sadece acil durumlar için kullanılmalıdır.
 * URL: /emergency_maintenance_off.php?confirm=1
 */

define('NO_MAINTENANCE_CHECK', true);
require_once 'includes/config.php';

// Güvenlik kontrolü
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($client_ip, $allowed_ips) && !isset($_GET['confirm'])) {
    http_response_code(403);
    die('Access denied');
}

$message = '';
$current_status = 'Unknown';

try {
    // Mevcut durumu kontrol et
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'maintenance_mode'");
    $stmt->execute();
    $current_status = $stmt->fetchColumn() ?: '0';
    
    if (isset($_GET['action']) && $_GET['action'] === 'disable' && isset($_GET['confirm'])) {
        // Bakım modunu kapat
        $stmt = $db->prepare("UPDATE settings SET value = '0' WHERE `key` = 'maintenance_mode'");
        $stmt->execute();
        
        // Log kaydı
        $log_message = date('Y-m-d H:i:s') . " - Bakım modu acil kapatma ile kapatıldı. IP: " . $client_ip . "\n";
        @file_put_contents(__DIR__ . '/logs/maintenance.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $message = 'Bakım modu başarıyla kapatıldı!';
        $current_status = '0';
    }
} catch (Exception $e) {
    $message = 'Hata: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acil Durum Bakım Kontrolü</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .active { background: #ffebee; color: #c62828; border: 1px solid #ef5350; }
        .inactive { background: #e8f5e8; color: #2e7d32; border: 1px solid #4caf50; }
        .warning { background: #fff3e0; color: #f57c00; border: 1px solid #ff9800; }
        button { background: #d32f2f; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #c62828; }
        .success { background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #e3f2fd; color: #1565c0; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚨 Acil Durum Bakım Kontrolü</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="status <?php echo $current_status === '1' ? 'active' : 'inactive'; ?>">
            <strong>Mevcut Durum:</strong> 
            <?php echo $current_status === '1' ? 'Bakım Modu AKTİF' : 'Site AKTİF'; ?>
        </div>
        
        <?php if ($current_status === '1'): ?>
            <div class="warning">
                ⚠️ <strong>Uyarı:</strong> Bakım modu aktif. Site ziyaretçiler için kapalı.
            </div>
            
            <h3>Bakım Modunu Kapatmak İçin:</h3>
            <ol>
                <li>Aşağıdaki butona tıklayın</li>
                <li>Veya admin panelden kapatın: <a href="/admin/maintenance.php">/admin/maintenance.php</a></li>
            </ol>
            
            <a href="?action=disable&confirm=1">
                <button type="button">🔧 Bakım Modunu KAPAT</button>
            </a>
        <?php else: ?>
            <div class="info">
                ✅ Site normal çalışıyor. Herhangi bir işlem yapmanıza gerek yok.
            </div>
            
            <p><a href="/">← Ana Sayfaya Dön</a></p>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        
        <h3>Diğer İşlemler:</h3>
        <ul>
            <li><a href="/admin/maintenance.php">Admin Bakım Paneli</a></li>
            <li><a href="/maintenance_check.php">API Durum Kontrolü</a></li>
            <li><a href="javascript:location.reload();">Bu Sayfayı Yenile</a></li>
        </ul>
        
        <div class="info">
            <strong>Not:</strong> Bu sayfa sadece acil durumlar için tasarlanmıştır. 
            Normal kullanım için admin panelini kullanın.
        </div>
    </div>
</body>
</html>
