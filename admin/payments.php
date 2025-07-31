<?php
require_once '../includes/config.php';
checkAuth(true); // true parametresi admin kontrolü için

// İşlem türünü belirle
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Geri ödeme talebi detaylarını getir (AJAX için)
if ($action === 'get_refund_details') {
    $refund_id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("
            SELECT rr.*, pt.amount, pt.package, pt.payment_method, pt.created_at as payment_date, u.username, u.email
            FROM refund_requests rr
            JOIN payment_transactions pt ON rr.transaction_id = pt.id
            JOIN users u ON rr.user_id = u.id
            WHERE rr.id = ?
        ");
        $stmt->execute([$refund_id]);
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($refund) {
            // Tarih formatlaması
            $refund['created_at'] = date('d.m.Y H:i', strtotime($refund['created_at']));
            $refund['payment_date'] = date('d.m.Y H:i', strtotime($refund['payment_date']));
            
            // JSON olarak döndür
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'request' => $refund]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Geri ödeme talebi bulunamadı']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Geri ödeme talebi işleme
if ($action === 'process_refund' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $refund_id = (int)$_POST['refund_id'];
    $status = $_POST['status']; // approved veya rejected
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Geri ödeme talebini güncelle
        $update_refund = $db->prepare("
            UPDATE refund_requests 
            SET status = ?, admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_refund->execute([$status, $admin_notes, $refund_id]);
        
        // Geri ödeme talebi bilgilerini al
        $get_refund = $db->prepare("
            SELECT rr.*, pt.status as payment_status, pt.user_id, pt.package, u.username, u.email
            FROM refund_requests rr
            JOIN payment_transactions pt ON rr.transaction_id = pt.id
            JOIN users u ON pt.user_id = u.id
            WHERE rr.id = ?
        ");
        $get_refund->execute([$refund_id]);
        $refund = $get_refund->fetch(PDO::FETCH_ASSOC);
        
        // Eğer talep onaylandıysa
        if ($status === 'approved') {
            // Ödeme durumunu güncelle
            $update_payment = $db->prepare("
                UPDATE payment_transactions 
                SET status = 'refunded', updated_at = NOW()
                WHERE id = ?
            ");
            $update_payment->execute([$refund['transaction_id']]);
            
            // Kullanıcı bilgilerini güncelle (eğer bu kullanıcının başka aktif ödemesi yoksa)
            $check_other_payments = $db->prepare("
                SELECT COUNT(*) FROM payment_transactions
                WHERE user_id = ? AND id != ? AND status = 'completed'
            ");
            $check_other_payments->execute([$refund['user_id'], $refund['transaction_id']]);
            
            if ($check_other_payments->fetchColumn() == 0) {
                // Başka aktif ödeme yoksa premium üyeliği iptal et
                $update_user = $db->prepare("
                    UPDATE users
                    SET is_premium = 0, premium_until = NULL
                    WHERE id = ?
                ");
                $update_user->execute([$refund['user_id']]);
                
                // Aktif abonelikleri iptal et
                $update_subs = $db->prepare("
                    UPDATE subscriptions
                    SET status = 'cancelled'
                    WHERE user_id = ? AND status = 'active'
                ");
                $update_subs->execute([$refund['user_id']]);
            }
        }
        
        $db->commit();
        
        // Kullanıcıya bildirim gönder
        $notification_message = '';
        if ($status === 'approved') {
            $notification_message = getActiveLang() == 'en' ? 
                "Your refund request has been approved. Your payment will be refunded as soon as possible." :
                "Geri ödeme talebiniz onaylandı. Ödemeniz en kısa sürede iade edilecektir.";
        } else {
            $notification_message = getActiveLang() == 'en' ? 
                "Your refund request has been rejected. Check your profile for details." :
                "Geri ödeme talebiniz reddedildi. Detaylı bilgi için profilinizi kontrol edin.";
        }
        
        // Kullanıcıya e-posta gönder
        if (!empty($refund['email'])) {
            require_once '../includes/functions.php'; // sendEmail fonksiyonunu içe aktar
            
            $site_title = getSetting('site_title', 'Makalem.com');
            $user_name = $refund['username'];
            $package_name = getActiveLang() == 'en' ? 
                (($refund['package'] === 'monthly') ? 'Monthly Premium' : 'Annual Premium') :
                (($refund['package'] === 'monthly') ? 'Aylık Premium' : 'Yıllık Premium');
            
            $email_subject = $status === 'approved' 
                ? (getActiveLang() == 'en' ? "Your Refund Request Has Been Approved - {$site_title}" : "Geri Ödeme Talebiniz Onaylandı - {$site_title}")
                : (getActiveLang() == 'en' ? "Your Refund Request Has Been Rejected - {$site_title}" : "Geri Ödeme Talebiniz Reddedildi - {$site_title}");
                
            // E-posta içeriğini oluştur
            $email_content = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: " . ($status === 'approved' ? '#4CAF50' : '#F44336') . "; color: white; padding: 10px 20px; text-align: center; }
                        .content { padding: 20px; border: 1px solid #ddd; border-top: none; }
                        .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>" . ($status === 'approved' ? 'Geri Ödeme Talebiniz Onaylandı' : 'Geri Ödeme Talebiniz Reddedildi') . "</h2>
                        </div>
                        <div class='content'>
                            <p>Sayın <strong>{$user_name}</strong>,</p>
                            <p>{$package_name} paketi için yapmış olduğunuz geri ödeme talebiniz " . 
                            ($status === 'approved' ? 'onaylanmıştır. Ödemeniz en kısa sürede hesabınıza iade edilecektir.' : 'değerlendirilmiş ve reddedilmiştir.') . "</p>";
            
            // Eğer not varsa ekle
            if (!empty($admin_notes)) {
                $email_content .= "<p><strong>Yönetici Notu:</strong> {$admin_notes}</p>";
            }
            
            $email_content .= "
                            <p>Detaylı bilgi için lütfen hesabınızı kontrol ediniz.</p>
                            <p>Saygılarımızla,<br>{$site_title} Ekibi</p>
                        </div>
                        <div class='footer'>
                            <p>Bu e-posta sistem tarafından otomatik olarak gönderilmiştir. Lütfen yanıtlamayınız.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            // E-postayı gönder
            $sent = sendEmail($refund['email'], $email_subject, $email_content);
            if ($sent) {
                error_log("Geri ödeme durumu e-postası başarıyla gönderildi: {$refund['email']}");
            } else {
                error_log("Geri ödeme durumu e-postası gönderilemedi: {$refund['email']}");
            }
        }
        
        // Admin'e bildirim ekle
        require_once 'includes/notifications.php';
        $package_text = ($refund['package'] === 'monthly') ? 'Aylık' : 'Yıllık';
        $status_text = ($status === 'approved') ? 'onaylandı' : 'reddedildi';
        $admin_notification_message = "{$refund['username']} kullanıcısının {$package_text} Premium abonelik geri ödeme talebi {$status_text}.";
        addAdminNotification('refund_' . $status, $refund['user_id'], $admin_notification_message, "/admin/payments.php?action=refund_requests", $refund_id);
        
        $_SESSION['success_message'] = "Geri ödeme talebi başarıyla işlendi.";
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = "İşlem sırasında bir hata oluştu: " . $e->getMessage();
        error_log("Geri ödeme işleme hatası: " . $e->getMessage());
    }
    
    header("Location: payments.php?action=refund_requests");
    exit();
}

// Geri ödeme talepleri listesi
if ($action === 'refund_requests') {
    // Geri ödeme taleplerini al
    $stmt = $db->prepare("
        SELECT rr.*, pt.amount, pt.package, pt.payment_method, pt.created_at as payment_date, u.username, u.email
        FROM refund_requests rr
        JOIN payment_transactions pt ON rr.transaction_id = pt.id
        JOIN users u ON rr.user_id = u.id
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute();
    $refund_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// İşlem: Ödemeyi göster
if ($action === 'view' && isset($_GET['id'])) {
    $payment_id = (int)$_GET['id'];
    
    // Ödeme detaylarını al
    $stmt = $db->prepare("
        SELECT pt.*, u.username, u.email
        FROM payment_transactions pt
        JOIN users u ON pt.user_id = u.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        $_SESSION['error_message'] = "Ödeme bulunamadı.";
        header("Location: payments.php");
        exit();
    }
    
    // Bu ödeme için geri ödeme talebi var mı kontrol et
    $refund_req = null;
    $check_refund = $db->prepare("
        SELECT * FROM refund_requests 
        WHERE transaction_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $check_refund->execute([$payment_id]);
    if ($check_refund->rowCount() > 0) {
        $refund_req = $check_refund->fetch(PDO::FETCH_ASSOC);
    }
}

// Geri ödeme talebi detaylarını getir (AJAX)
if ($action === 'get_refund_details' && isset($_GET['id'])) {
    $refund_id = (int)$_GET['id'];
    
    // AJAX yanıtı için header
    header('Content-Type: application/json');
    
    try {
        // Geri ödeme talebi detaylarını al
        $stmt = $db->prepare("
            SELECT rr.*, pt.amount, pt.package, pt.payment_method, pt.created_at as payment_date, u.username, u.email
            FROM refund_requests rr
            JOIN payment_transactions pt ON rr.transaction_id = pt.id
            JOIN users u ON rr.user_id = u.id
            WHERE rr.id = ?
        ");
        $stmt->execute([$refund_id]);
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($refund) {
            // Tarih formatlarını düzenle
            $refund['payment_date'] = date('d.m.Y H:i', strtotime($refund['payment_date']));
            $refund['created_at'] = date('d.m.Y H:i', strtotime($refund['created_at']));
            $refund['updated_at'] = date('d.m.Y H:i', strtotime($refund['updated_at']));
            $refund['amount'] = number_format($refund['amount'], 2, ',', '.');
            
            echo json_encode(['success' => true, 'request' => $refund]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Geri ödeme talebi bulunamadı.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
    }
    
    exit();
}

// İşlem: Ödeme durumunu güncelle
if ($action === 'update_status' && isset($_POST['payment_id']) && isset($_POST['status'])) {
    $payment_id = (int)$_POST['payment_id'];
    $status = $_POST['status'];
    
    // Geçerli durumlar
    $valid_statuses = ['pending', 'completed', 'failed', 'refunded'];
    
    if (in_array($status, $valid_statuses)) {
        // Ödeme bilgilerini al
        $stmt = $db->prepare("SELECT * FROM payment_transactions WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            try {
                // Transaction başlat
                $db->beginTransaction();
                
                // Ödeme durumunu güncelle
                $update_payment = $db->prepare("UPDATE payment_transactions SET status = ?, updated_at = NOW() WHERE id = ?");
                $update_payment->execute([$status, $payment_id]);
                
                // Eğer ödeme tamamlandı olarak işaretlendiyse kullanıcıyı premium yap
                if ($status === 'completed' && $payment['status'] !== 'completed') {
                    $user_id = $payment['user_id'];
                    $package = $payment['package'];
                    
                    // Süre hesapla
                    $duration = ($package === 'monthly') ? '+1 month' : '+1 year';
                    $end_date = date('Y-m-d', strtotime($duration));
                    
                    // Kullanıcıyı premium yap
                    $update_user = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
                    $update_user->execute([$end_date, $user_id]);
                    
                    // Plan bilgisini tespit et
                    $plan_name = ($package === 'monthly') ? 'Premium Aylık' : 'Premium Yıllık';
                    $plan_duration = ($package === 'monthly') ? '1 ay' : '1 yıl';
                    
                    // Plan tablosunda kayıt var mı kontrol et
                    $check_plan = $db->prepare("SELECT id FROM plans WHERE name = ?");
                    $check_plan->execute([$plan_name]);
                    $plan = $check_plan->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$plan) {
                        // Plan yoksa ekle
                        $insert_plan = $db->prepare("INSERT INTO plans (name, description, price, duration) VALUES (?, ?, ?, ?)");
                        $insert_plan->execute([$plan_name, $plan_duration . ' süreli premium üyelik', $payment['amount'], $plan_duration]);
                        $plan_id = $db->lastInsertId();
                    } else {
                        $plan_id = $plan['id'];
                    }
                    
                    // Abonelik kaydını ekle
                    $insert_sub = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
                    $insert_sub->execute([$user_id, $plan_id, 'active', date('Y-m-d'), $end_date]);
                }
                
                // Eğer ödeme iptal edildiyse ve önceden tamamlandıysa kullanıcıyı premium üyelikten çıkar
                if (($status === 'failed' || $status === 'refunded') && $payment['status'] === 'completed') {
                    $user_id = $payment['user_id'];
                    
                    // Kullanıcının aktif başka aboneliği var mı kontrol et
                    $check_other_subs = $db->prepare("
                        SELECT COUNT(*) FROM payment_transactions 
                        WHERE user_id = ? AND id != ? AND status = 'completed'
                    ");
                    $check_other_subs->execute([$user_id, $payment_id]);
                    
                    if ($check_other_subs->fetchColumn() == 0) {
                        // Başka aktif abonelik yoksa premium iptal et
                        $update_user = $db->prepare("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?");
                        $update_user->execute([$user_id]);
                        
                        // Abonelikleri pasif yap
                        $update_subs = $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'");
                        $update_subs->execute([$user_id]);
                    }
                }
                
                // Transaction'ı tamamla
                $db->commit();
                
                $_SESSION['success_message'] = "Ödeme durumu başarıyla güncellendi.";
            } catch (PDOException $e) {
                // Hata durumunda transaction'ı geri al
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                
                $_SESSION['error_message'] = "Ödeme durumu güncellenirken bir hata oluştu: " . $e->getMessage();
                error_log("Ödeme durumu güncelleme hatası: " . $e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Ödeme bulunamadı.";
        }
    } else {
        $_SESSION['error_message'] = "Geçersiz ödeme durumu.";
    }
    
    header("Location: payments.php");
    exit();
}

// İşlem: Ödeme sil
if ($action === 'delete' && isset($_GET['id'])) {
    $payment_id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM payment_transactions WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        $_SESSION['success_message'] = "Ödeme kaydı başarıyla silindi.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ödeme kaydı silinirken bir hata oluştu: " . $e->getMessage();
        error_log("Ödeme silme hatası: " . $e->getMessage());
    }
    
    header("Location: payments.php");
    exit();
}

// Sayfa başına gösterilecek ödeme sayısı
$per_page = 20;

// Mevcut sayfa
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $per_page;

// Filtreleme
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Filtre sorgusu oluştur
$filter_query = "";
$params = [];

if ($filter !== 'all') {
    $filter_query .= " AND pt.status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $filter_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR pt.order_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Toplam ödeme sayısını al
$count_query = "
    SELECT COUNT(*) 
    FROM payment_transactions pt
    JOIN users u ON pt.user_id = u.id
    WHERE 1=1 $filter_query
";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_payments = $count_stmt->fetchColumn();

// Toplam sayfa sayısı
$total_pages = ceil($total_payments / $per_page);

// Ödemeleri al
$query = "
    SELECT pt.*, u.username, u.email
    FROM payment_transactions pt
    JOIN users u ON pt.user_id = u.id
    WHERE 1=1 $filter_query
    ORDER BY pt.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = getActiveLang() == 'en' ? "Payments - Admin Panel" : "Ödemeler - Admin Paneli";
include 'includes/header.php';
?>

<div class="container-xl mx-auto px-4 py-6">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; ?></p>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 md:mb-0"><?php echo getActiveLang() == 'en' ? 'Payment Transactions' : 'Ödeme İşlemleri'; ?></h1>
                
                <div class="flex flex-col md:flex-row md:space-x-4 space-y-2 md:space-y-0">
                    <a href="payments.php?action=refund_requests" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md mb-2 md:mb-0">
                        <i class="fas fa-undo mr-1"></i> <?php echo getActiveLang() == 'en' ? 'Refund Requests' : 'Geri Ödeme Talepleri'; ?>
                    </a>
                    <form method="GET" class="flex">
                        <select name="filter" class="rounded-l-md border-r-0 bg-gray-50 border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'All Payments' : 'Tüm Ödemeler'; ?></option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Pending' : 'Bekleyen'; ?></option>
                            <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Completed' : 'Tamamlanan'; ?></option>
                            <option value="failed" <?php echo $filter === 'failed' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Failed' : 'Başarısız'; ?></option>
                            <option value="refunded" <?php echo $filter === 'refunded' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Refunded' : 'İade Edilen'; ?></option>
                        </select>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo getActiveLang() == 'en' ? 'Username, email or order ID' : 'Kullanıcı adı, e-posta veya sipariş no'; ?>" class="rounded-r-md border-l-0 bg-gray-50 border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <button type="submit" class="absolute inset-y-0 right-0 flex items-center px-3">
                                <i class="fas fa-search text-gray-400"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'Transaction ID' : 'İşlem No'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'User' : 'Kullanıcı'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'Package' : 'Paket'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'Amount' : 'Tutar'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'Payment Method' : 'Ödeme Yöntemi'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'Status' : 'Durum'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                Tarih
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                İşlemler
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo htmlspecialchars($payment['order_id']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <div>
                                            <?php echo htmlspecialchars($payment['username']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($payment['email']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php 
                                        echo ($payment['package'] === 'monthly') ? 'Aylık Premium' : 'Yıllık Premium'; 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo number_format($payment['amount'], 2, ',', '.'); ?> ₺
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php 
                                        $method = $payment['payment_method'];
                                        echo ($method === 'paytr') ? 'PayTR' : (($method === 'iyzico') ? 'iyzico' : $method); 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status = $payment['status'];
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        switch($status) {
                                            case 'pending':
                                                $status_class = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                                $status_text = 'Bekliyor';
                                                break;
                                            case 'completed':
                                                $status_class = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                                $status_text = 'Tamamlandı';
                                                break;
                                            case 'failed':
                                                $status_class = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                                $status_text = 'Başarısız';
                                                break;
                                            case 'refunded':
                                                $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                                $status_text = 'İade Edildi';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo date('d.m.Y H:i', strtotime($payment['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="payments.php?action=view&id=<?php echo $payment['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                            <i class="fas fa-eye"></i> <?php echo getActiveLang() == 'en' ? 'View' : 'Görüntüle'; ?>
                                        </a>
                                        <a href="payments.php?action=delete&id=<?php echo $payment['id']; ?>" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" onclick="return confirm('<?php echo getActiveLang() == 'en' ? 'Are you sure you want to delete this payment record?' : 'Bu ödeme kaydını silmek istediğinize emin misiniz?'; ?>')">
                                            <i class="fas fa-trash"></i> <?php echo getActiveLang() == 'en' ? 'Delete' : 'Sil'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                    <?php echo getActiveLang() == 'en' ? 'No payment records found.' : 'Ödeme kaydı bulunamadı.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center mt-6">
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <a href="?page=<?php echo max(1, $page - 1); ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700">
                            <span class="sr-only"><?php echo getActiveLang() == 'en' ? 'Previous' : 'Önceki'; ?></span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i == $page ? 'text-blue-600 bg-blue-50 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700">
                            <span class="sr-only"><?php echo getActiveLang() == 'en' ? 'Next' : 'Sonraki'; ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($action === 'view' && isset($payment)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo getActiveLang() == 'en' ? 'Payment Details' : 'Ödeme Detayları'; ?></h1>
                
                <div class="flex space-x-2">
                    <a href="payments.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white px-4 py-2 rounded-md">
                        <i class="fas fa-arrow-left mr-1"></i> <?php echo getActiveLang() == 'en' ? 'Go Back' : 'Geri Dön'; ?>
                    </a>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"><?php echo getActiveLang() == 'en' ? 'Payment Information' : 'Ödeme Bilgileri'; ?></h2>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                        <div class="grid grid-cols-1 gap-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Transaction ID:' : 'İşlem No:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($payment['order_id']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Payment Method:' : 'Ödeme Yöntemi:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <?php 
                                    $method = $payment['payment_method'];
                                    echo ($method === 'paytr') ? 'PayTR' : (($method === 'iyzico') ? 'iyzico' : $method); 
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Package:' : 'Paket:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <?php echo ($payment['package'] === 'monthly') ? (getActiveLang() == 'en' ? 'Monthly Premium' : 'Aylık Premium') : (getActiveLang() == 'en' ? 'Annual Premium' : 'Yıllık Premium'); ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Amount:' : 'Tutar:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo number_format($payment['amount'], 2, ',', '.'); ?> ₺</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Durum:</span>
                                <?php
                                $status = $payment['status'];
                                $status_class = '';
                                $status_text = '';
                                
                                switch($status) {
                                    case 'pending':
                                        $status_class = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                        $status_text = getActiveLang() == 'en' ? 'Pending' : 'Bekliyor';
                                        break;
                                    case 'completed':
                                        $status_class = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                        $status_text = getActiveLang() == 'en' ? 'Completed' : 'Tamamlandı';
                                        break;
                                    case 'failed':
                                        $status_class = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                        $status_text = getActiveLang() == 'en' ? 'Failed' : 'Başarısız';
                                        break;
                                    case 'refunded':
                                        $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                        $status_text = getActiveLang() == 'en' ? 'Refunded' : 'İade Edildi';
                                        break;
                                }
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Oluşturulma Tarihi:</span>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo date('d.m.Y H:i', strtotime($payment['created_at'])); ?></span>
                            </div>
                            <?php if ($payment['updated_at']): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Güncelleme Tarihi:</span>
                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo date('d.m.Y H:i', strtotime($payment['updated_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($payment['token']): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Token:</span>
                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo substr($payment['token'], 0, 10) . '...'; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"><?php echo getActiveLang() == 'en' ? 'User Information' : 'Kullanıcı Bilgileri'; ?></h2>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                        <div class="grid grid-cols-1 gap-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'User ID:' : 'Kullanıcı ID:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo $payment['user_id']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Username:' : 'Kullanıcı Adı:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($payment['username']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? 'Email:' : 'E-posta:'; ?></span>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($payment['email']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <a href="users.php?action=edit&id=<?php echo $payment['user_id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                    <i class="fas fa-user mr-1"></i> <?php echo getActiveLang() == 'en' ? 'View User Profile' : 'Kullanıcı Profilini Görüntüle'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"><?php echo getActiveLang() == 'en' ? 'Update Payment Status' : 'Ödeme Durumunu Güncelle'; ?></h2>
                    
                    <form method="POST" action="payments.php?action=update_status" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                        
                        <div class="mb-4">
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo getActiveLang() == 'en' ? 'Status' : 'Durum'; ?></label>
                            <select id="status" name="status" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <option value="pending" <?php echo $payment['status'] === 'pending' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Pending' : 'Bekliyor'; ?></option>
                                <option value="completed" <?php echo $payment['status'] === 'completed' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Completed' : 'Tamamlandı'; ?></option>
                                <option value="failed" <?php echo $payment['status'] === 'failed' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Failed' : 'Başarısız'; ?></option>
                                <option value="refunded" <?php echo $payment['status'] === 'refunded' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Refunded' : 'İade Edildi'; ?></option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md">
                                <?php echo getActiveLang() == 'en' ? 'Update Status' : 'Durumu Güncelle'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php elseif ($action === 'refund_requests'): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo getActiveLang() == 'en' ? 'Refund Requests' : 'Geri Ödeme Talepleri'; ?></h1>
            
            <div class="flex space-x-2">
                <a href="payments.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white px-4 py-2 rounded-md">
                    <i class="fas fa-arrow-left mr-1"></i> <?php echo getActiveLang() == 'en' ? 'Back to Payments' : 'Ödemelere Dön'; ?>
                </a>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Request ID' : 'Talep No'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'User' : 'Kullanıcı'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Package' : 'Paket'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Amount' : 'Tutar'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Payment Date' : 'Ödeme Tarihi'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Request Date' : 'Talep Tarihi'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Status' : 'Durum'; ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            <?php echo getActiveLang() == 'en' ? 'Actions' : 'İşlemler'; ?>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                    <?php if (count($refund_requests) > 0): ?>
                        <?php foreach ($refund_requests as $request): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    #<?php echo $request['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    <div>
                                        <?php echo htmlspecialchars($request['username']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($request['email']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    <?php 
                                    if (getActiveLang() == 'en') {
                                        echo ($request['package'] === 'monthly') ? 'Monthly Premium' : 'Yearly Premium'; 
                                    } else {
                                        echo ($request['package'] === 'monthly') ? 'Aylık Premium' : 'Yıllık Premium';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    <?php echo number_format($request['amount'], 2, ',', '.'); ?> ₺
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    <?php echo date('d.m.Y', strtotime($request['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    <?php echo date('d.m.Y', strtotime($request['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status = $request['status'];
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    switch($status) {
                                        case 'pending':
                                            $status_class = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                            $status_text = getActiveLang() == 'en' ? 'Under Review' : 'İnceleniyor';
                                            break;
                                        case 'approved':
                                            $status_class = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                            $status_text = getActiveLang() == 'en' ? 'Approved' : 'Onaylandı';
                                            break;
                                        case 'rejected':
                                            $status_class = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                            $status_text = getActiveLang() == 'en' ? 'Rejected' : 'Reddedildi';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="openRefundModal(<?php echo $request['id']; ?>, <?php echo $request['transaction_id']; ?>, '<?php echo htmlspecialchars($request['username']); ?>', '<?php echo $status; ?>')" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                        <i class="fas fa-eye"></i> <?php echo getActiveLang() == 'en' ? 'Review' : 'İncele'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                <?php echo getActiveLang() == 'en' ? 'No refund requests found.' : 'Henüz geri ödeme talebi bulunmamaktadır.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Geri Ödeme Modal -->
    <div id="refundModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full mx-4 shadow-xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                        <?php echo getActiveLang() == 'en' ? 'Refund Request Review' : 'Geri Ödeme Talebi İnceleme'; ?>
                    </h3>
                    <button onclick="closeRefundModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-300 dark:hover:text-gray-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="refundDetails" class="mb-6">
                    <!-- Ajax ile doldurulacak -->
                    <div class="flex justify-center p-6">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500"></div>
                    </div>
                </div>
                
                <div id="refundForm" class="mb-6 hidden">
                    <form method="POST" action="payments.php?action=process_refund">
                        <input type="hidden" name="refund_id" id="refund_id" value="">
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2">
                                <?php echo getActiveLang() == 'en' ? 'Action' : 'İşlem'; ?>
                            </label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="status" value="approved" class="form-radio text-blue-600">
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">
                                        <?php echo getActiveLang() == 'en' ? 'Approve' : 'Onayla'; ?>
                                    </span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="status" value="rejected" class="form-radio text-red-600">
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">
                                        <?php echo getActiveLang() == 'en' ? 'Reject' : 'Reddet'; ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="admin_notes" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">
                                <?php echo getActiveLang() == 'en' ? 'Admin Notes' : 'Admin Notları'; ?>
                            </label>
                            <textarea name="admin_notes" id="admin_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo getActiveLang() == 'en' ? 'These notes are only visible in the admin panel.' : 'Bu notlar sadece admin panelinde görünür.'; ?>
                            </p>
                        </div>
                        
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="closeRefundModal()" class="bg-gray-300 hover:bg-gray-400 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-semibold py-2 px-4 rounded-md">
                                <?php echo getActiveLang() == 'en' ? 'Cancel' : 'İptal'; ?>
                            </button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md">
                                <?php echo getActiveLang() == 'en' ? 'Save' : 'Kaydet'; ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="refundComplete" class="mb-6 hidden">
                    <div class="text-center py-4">
                        <p class="text-gray-700 dark:text-gray-300 mb-4"><?php echo getActiveLang() == 'en' ? 'This refund request has already been processed.' : 'Bu geri ödeme talebi işlenmiş durumda.'; ?></p>
                        <button type="button" onclick="closeRefundModal()" class="bg-gray-300 hover:bg-gray-400 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-semibold py-2 px-4 rounded-md">
                            <?php echo getActiveLang() == 'en' ? 'Close' : 'Kapat'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Aktif dili almak için yardımcı fonksiyon
        function getActiveLang() {
            // Bu fonksiyon PHP'den dil bilgisini alır, ancak JavaScript tarafında eklememiz gerekiyor
            // PHP'de getActiveLang() fonksiyonu çağrıldığında 'en' veya 'tr' döner
            return '<?php echo getActiveLang(); ?>';
        }
        
        function openRefundModal(refundId, transactionId, username, status) {
            document.getElementById('refund_id').value = refundId;
            document.getElementById('refundDetails').innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-500"></div></div>';
            
            // Form veya complete bölümünü göster/gizle
            if (status === 'pending') {
                document.getElementById('refundForm').classList.remove('hidden');
                document.getElementById('refundComplete').classList.add('hidden');
            } else {
                document.getElementById('refundForm').classList.add('hidden');
                document.getElementById('refundComplete').classList.remove('hidden');
            }
            
            // Modalı göster
            document.getElementById('refundModal').classList.remove('hidden');
            
            // AJAX ile talep detaylarını getir
            fetch('payments.php?action=get_refund_details&id=' + refundId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">${getActiveLang() == 'en' ? 'User Information' : 'Kullanıcı Bilgileri'}</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">${getActiveLang() == 'en' ? 'Username:' : 'Kullanıcı Adı:'}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${data.request.username}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">${getActiveLang() == 'en' ? 'Email:' : 'E-posta:'}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${data.request.email}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">${getActiveLang() == 'en' ? 'Payment Information' : 'Ödeme Bilgileri'}</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">${getActiveLang() == 'en' ? 'Package:' : 'Paket:'}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${data.request.package === 'monthly' ? (getActiveLang() == 'en' ? 'Monthly Premium' : 'Aylık Premium') : (getActiveLang() == 'en' ? 'Yearly Premium' : 'Yıllık Premium')}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">${getActiveLang() == 'en' ? 'Amount:' : 'Tutar:'}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${data.request.amount} ₺</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">${getActiveLang() == 'en' ? 'Payment Date:' : 'Ödeme Tarihi:'}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${data.request.payment_date}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">${getActiveLang() == 'en' ? 'Request Date:' : 'Talep Tarihi:'}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${data.request.created_at}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">${getActiveLang() == 'en' ? 'Refund Reason' : 'Geri Ödeme Sebebi'}</h4>
                                <p class="text-gray-800 dark:text-white">${data.request.reason}</p>
                            </div>
                        `;
                        
                        document.getElementById('refundDetails').innerHTML = html;
                    } else {
                        document.getElementById('refundDetails').innerHTML = '<div class="text-red-500 dark:text-red-400 text-center">' + (getActiveLang() == 'en' ? 'An error occurred while loading request details.' : 'Talep detayları yüklenirken bir hata oluştu.') + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('refundDetails').innerHTML = '<div class="text-red-500 dark:text-red-400 text-center">' + (getActiveLang() == 'en' ? 'An error occurred while loading request details.' : 'Talep detayları yüklenirken bir hata oluştu.') + '</div>';
                });
        }
        
        function closeRefundModal() {
            document.getElementById('refundModal').classList.add('hidden');
            // Form içeriğini sıfırla
            document.getElementById('admin_notes').value = '';
            const radioButtons = document.querySelectorAll('input[name="status"]');
            radioButtons.forEach(radio => {
                radio.checked = false;
            });
        }
    </script>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
