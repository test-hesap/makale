<?php
require_once 'includes/config.php';
checkAuth(); // Kullanıcı girişi kontrolü

// Tabloların varlığını kontrol et
$check_refund_table = $db->query("SHOW TABLES LIKE 'refund_requests'");
if ($check_refund_table->rowCount() == 0) {
    // Refund_requests tablosu yok, oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS `refund_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `transaction_id` int(11) NOT NULL,
            `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
            `admin_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `transaction_id` (`transaction_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// Payment_transactions tablosunun varlığını kontrol et
$check_payment_table = $db->query("SHOW TABLES LIKE 'payment_transactions'");
if ($check_payment_table->rowCount() == 0) {
    // Tablo yok, oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS `payment_transactions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `order_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `amount` decimal(10,2) NOT NULL,
            `currency` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'TRY',
            `package` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL,
            `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` enum('pending','completed','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
            `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// Kullanıcı ID'sini al
$user_id = $_SESSION['user_id'];

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id']) && isset($_POST['reason'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    $reason = trim($_POST['reason']);
    
    // Validasyon
    $errors = [];
    
    if (empty($reason)) {
        $errors[] = "Lütfen geri ödeme sebebini belirtiniz.";
    }
    
    try {
        // İşlemin kullanıcıya ait olup olmadığını kontrol et
        $check_transaction = $db->prepare("
            SELECT * FROM payment_transactions 
            WHERE id = ? AND user_id = ? AND status = 'completed'
        ");
        $check_transaction->execute([$transaction_id, $user_id]);
        
        if ($check_transaction->rowCount() === 0) {
            $errors[] = "Geçersiz ödeme bilgisi.";
        } else {
            // Daha önce bu işlem için talep var mı?
            $check_request = $db->prepare("
                SELECT * FROM refund_requests 
                WHERE transaction_id = ? AND user_id = ?
            ");
            $check_request->execute([$transaction_id, $user_id]);
            
            if ($check_request->rowCount() > 0) {
                $errors[] = "Bu ödeme için zaten bir geri ödeme talebinde bulunulmuş.";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Veritabanı sorgu hatası oluştu.";
        error_log("Geri ödeme kontrol hatası: " . $e->getMessage());
    }
    
    // Hata yoksa kaydet
    if (empty($errors)) {
        try {
            $insert = $db->prepare("
                INSERT INTO refund_requests (user_id, transaction_id, reason)
                VALUES (?, ?, ?)
            ");
            
            $insert->execute([$user_id, $transaction_id, $reason]);
            
            // Admin'e bildirim gönder
            require_once 'admin/includes/notifications.php';
            
            // Kullanıcı ve ödeme bilgilerini al
            $payment_query = $db->prepare("
                SELECT p.*, u.username, u.email
                FROM payment_transactions p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $payment_query->execute([$transaction_id]);
            $payment = $payment_query->fetch(PDO::FETCH_ASSOC);
            
            $package_text = ($payment['package'] === 'monthly') ? 'Aylık' : 'Yıllık';
            $amount_text = number_format($payment['amount'], 2, ',', '.') . ' ₺';
            $notification_message = "{$payment['username']} kullanıcısı {$package_text} Premium abonelik ({$amount_text}) için geri ödeme talebinde bulundu.";
            
            addAdminNotification('refund_request', $user_id, $notification_message, "/admin/payments.php?action=refund_requests", $db->lastInsertId());
            
            $_SESSION['success_message'] = "Geri ödeme talebiniz başarıyla alındı. Talebiniz incelendikten sonra size bilgilendirme yapılacaktır.";
            // Başarıyla tamamlandığını belirten bir değişken atayalım
            $refund_created = true;
            
            // Refund talepleri listesini yenileyelim - yeni talep de görünsün
            // $refund_requests_query değişkenini kullanmak yerine doğrudan sorgu çalıştırıyoruz
            $new_refund_query = $db->prepare("
                SELECT rr.*, pt.amount, pt.package, pt.payment_method
                FROM refund_requests rr
                JOIN payment_transactions pt ON rr.transaction_id = pt.id
                WHERE rr.user_id = ?
                ORDER BY rr.created_at DESC
            ");
            $new_refund_query->execute([$user_id]);
            $refund_requests = $new_refund_query->fetchAll(PDO::FETCH_ASSOC);
            
            // Yönlendirme yapmıyoruz, aynı sayfada kalıyoruz
        } catch (PDOException $e) {
            $errors[] = "Sistem hatası: " . $e->getMessage();
            error_log("Geri ödeme talebi hatası: " . $e->getMessage());
        }
    }
}

// Kullanıcının tamamlanmış ödemelerini al
$payments = [];
try {
    $payments_query = $db->prepare("
        SELECT * FROM payment_transactions 
        WHERE user_id = ? AND status = 'completed'
        ORDER BY created_at DESC
    ");
    $payments_query->execute([$user_id]);
    $payments = $payments_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ödeme sorgusu hatası: " . $e->getMessage());
}

// Kullanıcının mevcut geri ödeme taleplerini al
$refund_requests = [];
try {
    $refund_requests_query = $db->prepare("
        SELECT rr.*, pt.amount, pt.package, pt.payment_method
        FROM refund_requests rr
        JOIN payment_transactions pt ON rr.transaction_id = pt.id
        WHERE rr.user_id = ?
        ORDER BY rr.created_at DESC
    ");
    $refund_requests_query->execute([$user_id]);
    $refund_requests = $refund_requests_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Geri ödeme talepleri sorgusu hatası: " . $e->getMessage());
}

// Sayfa başlığı
$page_title = "Geri Ödeme Talebi";
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Geri Ödeme Talebi</h1>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success_message']; ?></p>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php 
        // Form gösterilmesi için kontrol - başarılı kayıt yapılmamışsa veya session'da success_message yoksa göster
        $show_form = !isset($refund_created) && !isset($_SESSION['success_message']);
        
        if ($show_form): 
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Geri Ödeme Talebi Oluştur</h2>
            
            <?php if (count($payments) > 0): ?>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="transaction_id" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Ödeme Seçin</label>
                        <select id="transaction_id" name="transaction_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Ödeme seçin</option>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                // Bu ödeme için daha önce talep var mı kontrol et
                                $check_request = $db->prepare("
                                    SELECT * FROM refund_requests 
                                    WHERE transaction_id = ? AND user_id = ?
                                ");
                                $check_request->execute([$payment['id'], $user_id]);
                                $has_request = $check_request->rowCount() > 0;
                                
                                // Eğer daha önce talep yapılmışsa bu ödemeyi listelemiyoruz
                                if ($has_request) continue;
                                
                                $package_text = ($payment['package'] === 'monthly') ? 'Aylık Premium' : 'Yıllık Premium';
                                $payment_date = date('d.m.Y H:i', strtotime($payment['created_at']));
                                $amount = number_format($payment['amount'], 2, ',', '.');
                                ?>
                                <option value="<?php echo $payment['id']; ?>">
                                    <?php echo $package_text; ?> - <?php echo $amount; ?> ₺ - <?php echo $payment_date; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="reason" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Geri Ödeme Sebebi</label>
                        <textarea id="reason" name="reason" required rows="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Lütfen geri ödeme talep etme sebebinizi açıklayınız.</p>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md">
                            Geri Ödeme Talebi Oluştur
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                    <p>Şu anda geri ödeme talebinde bulunabileceğiniz tamamlanmış bir ödemeniz bulunmamaktadır.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; // form gösterilmesi koşulu sonu ?>
        
        <?php 
        // Eğer talep listemiz varsa veya yeni bir talep oluşturulmuşsa göster
        if (count($refund_requests) > 0 || isset($refund_created)): 
        ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Önceki Geri Ödeme Talepleriniz</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                    Paket
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                    Tutar
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                    Talep Tarihi
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                    Durum
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            <?php foreach ($refund_requests as $request): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo ($request['package'] === 'monthly') ? 'Aylık Premium' : 'Yıllık Premium'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo number_format($request['amount'], 2, ',', '.'); ?> ₺
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status = $request['status'];
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        switch($status) {
                                            case 'pending':
                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                $status_text = 'İnceleniyor';
                                                break;
                                            case 'approved':
                                                $status_class = 'bg-green-100 text-green-800';
                                                $status_text = 'Onaylandı';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-red-100 text-red-800';
                                                $status_text = 'Reddedildi';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; // count($refund_requests) > 0 || isset($refund_created) koşulunun sonu ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
