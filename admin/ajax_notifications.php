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
        
    case 'delete':
        // Tek bildirim silme
        $notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($notification_id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM admin_notifications WHERE id = ?");
                $success = $stmt->execute([$notification_id]);
                
                if ($success) {
                    // Güncellenen okunmamış bildirim sayısını al
                    $count = getUnreadNotificationsCount();
                    echo json_encode([
                        'success' => true,
                        'unread_count' => $count
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Bildirim silinemedi']);
                }
            } catch (Exception $e) {
                error_log("Bildirim silme hatası: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Bildirim silinirken bir hata oluştu',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Geçersiz bildirim ID']);
        }
        break;
        
    case 'bulk_delete':
        // Çoklu bildirim silme
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = isset($input['ids']) ? array_map('intval', $input['ids']) : [];
        
        if (!empty($ids)) {
            try {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $db->prepare("DELETE FROM admin_notifications WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                
                if ($success) {
                    $deleted_count = $stmt->rowCount();
                    $count = getUnreadNotificationsCount();
                    
                    echo json_encode([
                        'success' => true,
                        'deleted_count' => $deleted_count,
                        'unread_count' => $count
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Bildirimler silinemedi']);
                }
            } catch (Exception $e) {
                error_log("Toplu bildirim silme hatası: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Bildirimler silinirken bir hata oluştu',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Silinecek bildirim bulunamadı']);
        }
        break;
        
    case 'delete_all':
        // Tüm bildirimleri silme
        try {
            $stmt = $db->prepare("DELETE FROM admin_notifications");
            $success = $stmt->execute();
            
            if ($success) {
                $deleted_count = $stmt->rowCount();
                echo json_encode([
                    'success' => true,
                    'deleted_count' => $deleted_count,
                    'unread_count' => 0
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tüm bildirimler silinemedi']);
            }
        } catch (Exception $e) {
            error_log("Tüm bildirimleri silme hatası: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Tüm bildirimler silinirken bir hata oluştu',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'bulk_mark_read':
        // Çoklu bildirim okundu işaretleme
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = isset($input['ids']) ? array_map('intval', $input['ids']) : [];
        
        if (!empty($ids)) {
            try {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $db->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id IN ($placeholders)");
                $success = $stmt->execute($ids);
                
                if ($success) {
                    $marked_count = $stmt->rowCount();
                    $count = getUnreadNotificationsCount();
                    
                    echo json_encode([
                        'success' => true,
                        'marked_count' => $marked_count,
                        'unread_count' => $count
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Bildirimler okundu işaretlenemedi']);
                }
            } catch (Exception $e) {
                error_log("Toplu bildirim okundu işaretleme hatası: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Bildirimler okundu işaretlenirken bir hata oluştu',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Okundu işaretlenecek bildirim bulunamadı']);
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
