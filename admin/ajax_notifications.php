<?php
require_once '../includes/config.php';
require_once 'includes/notifications.php';

try {
    // Admin yetki kontrolü
    checkAuth(true); // true parametresi admin kontrolü için
    
    // Veritabanı bağlantısı kontrol et
    if (!isset($db) || !$db) {
        throw new Exception("Veritabanı bağlantısı bulunamadı");
    }
    
    header('Content-Type: application/json');
    
    // İstek tipini kontrol et
    $action = $_GET['action'] ?? '';
    
    // Bildirim tablosu varlığını kontrol et
    $tableExists = false;
    try {
        $result = $db->query("SHOW TABLES LIKE 'admin_notifications'");
        $tableExists = ($result->rowCount() > 0);
        error_log("Bildirim tablosu kontrol: " . ($tableExists ? "Tablo mevcut" : "Tablo bulunamadı"));
    } catch (PDOException $e) {
        error_log("Tablo kontrolü hatası: " . $e->getMessage());
    }
    
    if (!$tableExists) {
        throw new Exception("Bildirim tablosu bulunamadı. Lütfen önce install_notifications.php çalıştırın.");
    }
    
    switch ($action) {
        case 'get_notifications':
            // Bildirimleri getir
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $type = isset($_GET['type']) ? $_GET['type'] : null;
            
            try {
                $notifications = getAdminNotifications($limit, $type);
                $count = getUnreadNotificationsCount();
                
                // Debug için bildirim sayısını kaydet
                error_log("AJAX: Okunmamış bildirim sayısı: " . $count);
                error_log("AJAX: Toplam getirilen bildirim sayısı: " . count($notifications));
                
                // Tutarsızlık kontrolü
                $inconsistent = ($count > 0 && empty($notifications));
                
                if ($inconsistent) {
                    error_log("UYARI: Veri tutarsızlığı tespit edildi! Okunmamış sayısı: $count, Notification sayısı: 0");
                    
                    // Tüm bildirimleri tekrar al (limit olmadan)
                    $allNotifications = getAdminNotifications(0, $type);
                    error_log("Limit olmadan getirilen bildirim sayısı: " . count($allNotifications));
                    
                    // Eğer limit olmadan bildirim geliyorsa, limit ile çağır
                    if (count($allNotifications) > 0) {
                        $notifications = array_slice($allNotifications, 0, $limit);
                    } else {
                        // Veritabanı tutarsızlığı - sayım sıfırla 
                        error_log("Veritabanı tutarsızlığı tespit edildi, sayım sıfırlanıyor");
                        $db->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
                        $count = 0;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'notifications' => $notifications,
                    'unread_count' => $count,
                    'debug_info' => [
                        'inconsistent' => $inconsistent,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'php_version' => phpversion()
                    ]
                ]);
            } catch (Exception $e) {
                error_log("Bildirim getirme hatası: " . $e->getMessage());
                
                // Tüm bildirimleri okundu olarak işaretlemeyi dene
                try {
                    $db->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
                    error_log("Hata sonrası tüm bildirimler okundu olarak işaretlendi");
                } catch (Exception $innerEx) {
                    error_log("Hata sonrası bildirim düzeltme hatası: " . $innerEx->getMessage());
                }
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Bildirimler alınırken bir hata oluştu',
                    'error' => $e->getMessage(),
                    'fix_applied' => true
                ]);
            }
            break;
        
    case 'mark_read':
        // Bildirimi okundu olarak işaretle
        $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        
        if ($notification_id > 0) {
            try {
                $success = markNotificationAsRead($notification_id);
                echo json_encode(['success' => $success]);
            } catch (Exception $e) {
                error_log("Bildirim okundu işaretleme hatası: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Bildirim okundu olarak işaretlenirken bir hata oluştu',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Geçersiz bildirim ID']);
        }
        break;
        
    case 'mark_all_read':
        // Tüm bildirimleri okundu olarak işaretle
        $type = isset($_POST['type']) ? $_POST['type'] : null;
        try {
            $success = markAllNotificationsAsRead($type);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            error_log("Tüm bildirimler okundu işaretleme hatası: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Tüm bildirimler okundu olarak işaretlenirken bir hata oluştu',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
}
} catch (Exception $e) {
    error_log("Bildirim AJAX Hatası: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'İşlem sırasında bir hata oluştu',
        'error' => $e->getMessage()
    ]);
}
?>
