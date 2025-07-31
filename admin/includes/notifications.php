<?php
// Admin bildirim sistemi işlevleri

/**
 * Yeni bir bildirim ekler
 * 
 * @param string $type Bildirim tipi: 'new_user', 'new_article', 'new_comment'
 * @param int $user_id İlgili kullanıcı ID
 * @param string $message Bildirim mesajı
 * @param string|null $link Tıklanınca gidilecek link (opsiyonel)
 * @param int|null $related_id İlgili içerik ID (makale ID, yorum ID vb.)
 * @return bool İşlem başarılı/başarısız
 */
function addAdminNotification($type, $user_id, $message, $link = null, $related_id = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("INSERT INTO admin_notifications (type, user_id, message, link, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$type, $user_id, $message, $link, $related_id]);
    } catch (PDOException $e) {
        error_log("Bildirim eklenirken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Okunmamış bildirim sayısını döndürür
 * 
 * @param string|null $type Belirli bir bildirim türü için filtreleme
 * @return int Okunmamış bildirim sayısı
 */
function getUnreadNotificationsCount($type = null) {
    global $db;
    
    try {
        // Önce bildirim tablosunun varlığını kontrol et
        $tableCheck = $db->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($tableCheck->rowCount() == 0) {
            error_log("Bildirim tablosu bulunamadı - sayı kontrolünde");
            return 0;
        }
        
        $sql = "SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0";
        $params = [];
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        error_log("Okunmamış bildirim sayı sorgusu: " . $sql);
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        
        error_log("Okunmamış bildirim sayısı: " . $count);
        
        // Eğer bildirim sayısı varsa, o bildirimleri getir ve kontrol et
        if ($count > 0) {
            $checkSql = "SELECT id FROM admin_notifications WHERE is_read = 0 LIMIT 1";
            $checkStmt = $db->query($checkSql);
            $hasRows = ($checkStmt->rowCount() > 0);
            
            if (!$hasRows) {
                error_log("UYARI: Bildirim sayısı > 0 ama bildirim yok! Count: " . $count);
                // Bu durumda sayıyı sıfırla
                $db->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
                return 0;
            }
        }
        
        return $count;
    } catch (PDOException $e) {
        error_log("Bildirim sayısı alınırken hata: " . $e->getMessage());
        error_log("Hata kodu: " . $e->getCode());
        error_log("Hata satırı: " . $e->getLine());
        return 0;
    }
}

/**
 * Bildirimleri listeler
 * 
 * @param int $limit Maksimum bildirim sayısı
 * @param string|null $type Belirli bir bildirim türü için filtreleme
 * @return array Bildirim listesi
 */
function getAdminNotifications($limit = 10, $type = null) {
    global $db;
    
    try {
        // Önce bildirim tablosunun varlığını kontrol et
        $tableCheck = $db->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($tableCheck->rowCount() == 0) {
            error_log("Bildirim tablosu bulunamadı!");
            return [];
        }
        
        // SQL sorgusunu oluştur
        $sql = "SELECT n.*, u.username, u.avatar 
                FROM admin_notifications n 
                LEFT JOIN users u ON n.user_id = u.id 
                WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND n.type = ?";
            $params[] = $type;
        }
        
        // Önce okunmamışları, sonra okunmuşları göster
        $sql .= " ORDER BY n.is_read ASC, n.created_at DESC";
        
        // Limit ekle (eğer 0 değilse)
        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        error_log("Bildirim SQL: " . $sql);
        error_log("Bildirim Parametreleri: " . implode(", ", $params));
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug için hata günlüğüne bildirim sayısını kaydet
        error_log("Getirilen bildirim sayısı: " . count($notifications));
        
        // Sonuç formatını kontrol et ve log'a yaz
        if (count($notifications) > 0) {
            error_log("Örnek bildirim verisi: " . json_encode($notifications[0]));
        }
        
        return $notifications;
    } catch (PDOException $e) {
        error_log("Bildirimler listelenirken hata: " . $e->getMessage());
        error_log("Hata kodu: " . $e->getCode());
        error_log("Hata satırı: " . $e->getLine());
        error_log("Hata dosyası: " . $e->getFile());
        error_log("Hata izleme: " . $e->getTraceAsString());
        return [];
    }
}

/**
 * Bildirimi okundu olarak işaretler
 * 
 * @param int $notification_id Bildirim ID
 * @return bool İşlem başarılı/başarısız
 */
function markNotificationAsRead($notification_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notification_id]);
    } catch (PDOException $e) {
        error_log("Bildirim okundu işaretlenirken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Tüm bildirimleri okundu olarak işaretler
 * 
 * @param string|null $type Belirli bir bildirim türü için filtreleme
 * @return bool İşlem başarılı/başarısız
 */
function markAllNotificationsAsRead($type = null) {
    global $db;
    
    try {
        $sql = "UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0";
        $params = [];
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        error_log("Tüm bildirimleri okundu işaretleme sorgusu: " . $sql);
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        $affectedRows = $stmt->rowCount();
        error_log("Okundu olarak işaretlenen bildirim sayısı: " . $affectedRows);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Tüm bildirimler okundu işaretlenirken hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Bildirim sisteminin tutarlılığını kontrol eder ve gerekirse düzeltir
 * 
 * @return array Tutarlılık durumu ve yapılan işlemler
 */
function checkNotificationsConsistency() {
    global $db;
    $result = ['consistent' => true, 'actions' => [], 'errors' => []];
    
    try {
        // Tablo kontrolü
        $tableCheck = $db->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($tableCheck->rowCount() == 0) {
            $result['consistent'] = false;
            $result['actions'][] = 'table_missing';
            $result['errors'][] = 'Bildirim tablosu bulunamadı';
            return $result;
        }
        
        // Okunmamış bildirim sayısı
        $countStmt = $db->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
        $unreadCount = (int)$countStmt->fetchColumn();
        
        // Bildirim kontrolü
        $notificationStmt = $db->query("SELECT id FROM admin_notifications WHERE is_read = 0 LIMIT 1");
        $hasUnread = ($notificationStmt->rowCount() > 0);
        
        // Tutarsızlık kontrolü
        if ($unreadCount > 0 && !$hasUnread) {
            $result['consistent'] = false;
            $result['actions'][] = 'reset_unread';
            
            // Tüm bildirimleri okundu olarak işaretle
            $db->exec("UPDATE admin_notifications SET is_read = 1");
            $result['actions'][] = 'fixed_unread';
        }
        
        // Bildirimlerin sayısını kontrol et
        $totalStmt = $db->query("SELECT COUNT(*) FROM admin_notifications");
        $totalCount = (int)$totalStmt->fetchColumn();
        
        $result['stats'] = [
            'total' => $totalCount,
            'unread' => $unreadCount
        ];
        
        return $result;
    } catch (PDOException $e) {
        $result['consistent'] = false;
        $result['errors'][] = $e->getMessage();
        return $result;
    }
}
