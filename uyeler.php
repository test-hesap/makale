<?php
// Çıktı tamponlamasını başlat
ob_start();

require_once 'includes/config.php';

// Sayfa başlığı ve meta bilgileri
$page_title = t('members') . " - " . getSetting('site_title');
$meta_description = getActiveLang() == 'tr' ? "Sitemize kayıtlı üyeler listesi" : "List of registered members";
$meta_keywords = getActiveLang() == 'tr' ? "üyeler, kullanıcılar, profiller" : "members, users, profiles";
$canonical_url = getSetting('site_url') . "/uyeler";

// Dil seçimini kontrol et
$activeLang = getActiveLang();

// Sayfalama için değişkenler
$current_page = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$per_page = 20; // Sayfa başına gösterilecek üye sayısı
$offset = ($current_page - 1) * $per_page;

try {
    // Önce veritabanındaki users tablosu yapısını kontrol et
    $check_table = $db->query("SHOW COLUMNS FROM users");
    $columns = $check_table->fetchAll(PDO::FETCH_COLUMN);
    
    // Status sütunu var mı kontrol et
    $has_status_column = in_array('status', $columns);
    
    // Önce approved sütunu var mı kontrol et
    $has_approved_column = in_array('approved', $columns);
    
    // Toplam üye sayısını al - onaylanmış üyeleri ve admin'leri say
    if ($has_status_column && $has_approved_column) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (status = 'active' AND (approved = TRUE OR role = 'admin'))");
    } elseif ($has_status_column) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
    } elseif ($has_approved_column) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (approved = TRUE OR role = 'admin')");
    } else {
        // Hiçbir filtre sütunu yoksa tüm kullanıcıları say
        $stmt = $db->prepare("SELECT COUNT(*) FROM users");
    }
    
    $stmt->execute();
    $total_users = $stmt->fetchColumn();
    
    // Toplam sayfa sayısını hesapla
    $total_pages = ceil($total_users / $per_page);
    
    // Geçerli sayfa numarasını kontrol et
    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }    // Mevcut sütunları kontrol et ve uygun SQL sorgusunu oluştur
    $select_columns = [];
    
    // Temel kolonlar
    $basic_columns = ['id', 'username'];
    foreach ($basic_columns as $col) {
        if (in_array($col, $columns)) {
            $select_columns[] = $col;
        }
    }
    
    // İsteğe bağlı kolonlar
    $optional_columns = ['full_name', 'avatar', 'bio', 'role', 'register_date', 'last_activity', 'is_online', 'is_premium'];
    foreach ($optional_columns as $col) {
        if (in_array($col, $columns)) {
            $select_columns[] = $col;
        }
    }
    
    // En az id ve username var mı kontrol et
    if (count($select_columns) < 2) {
        throw new Exception('Gerekli veritabanı kolonları bulunamadı. En azından id ve username olmalı.');
    }
    
    $select_sql = implode(', ', $select_columns);
    
    // Sıralama için kullanılacak kolonu belirle
    $order_column = 'id'; // Varsayılan olarak ID'ye göre sırala
    $possible_order_columns = ['id', 'username', 'register_date'];
    
    foreach ($possible_order_columns as $col) {
        if (in_array($col, $columns)) {
            $order_column = $col;
            if ($col == 'register_date') {
                break; // register_date bulunduysa, onu kullan ve döngüden çık
            }
        }
    }
    
    // Üyeleri getir
    if ($has_status_column && $has_approved_column) {
        $stmt = $db->prepare("
            SELECT $select_sql
            FROM users 
            WHERE status = 'active' AND (approved = TRUE OR role = 'admin')
            ORDER BY $order_column DESC
            LIMIT :offset, :per_page
        ");
    } elseif ($has_status_column) {
        $stmt = $db->prepare("
            SELECT $select_sql
            FROM users 
            WHERE status = 'active'
            ORDER BY $order_column DESC
            LIMIT :offset, :per_page
        ");
    } elseif ($has_approved_column) {
        $stmt = $db->prepare("
            SELECT $select_sql
            FROM users 
            WHERE approved = TRUE OR role = 'admin'
            ORDER BY $order_column DESC
            LIMIT :offset, :per_page
        ");
    } else {
        // Hiçbir filtre sütunu yoksa tüm kullanıcıları göster
        $stmt = $db->prepare("
            SELECT $select_sql
            FROM users 
            ORDER BY $order_column DESC
            LIMIT :offset, :per_page
        ");
    }
    
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Geliştirme aşamasında gerçek hata mesajını göster
    error_log("Üyeler sayfasında veritabanı hatası: " . $e->getMessage());
    $error_message = "Hata: " . $e->getMessage(); // Gerçek hata mesajını göster
}

// Header'ı dahil et
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-0 mt-1">
    <!-- Boşluk eklendi -->
</div>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8 text-gray-800 dark:text-white"><?php echo t('members'); ?></h1>
      <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <p class="font-bold"><?php echo getActiveLang() == 'tr' ? 'Hata Oluştu' : 'Error Occurred'; ?></p>
            <p><?php echo $error_message; ?></p>
            <p class="mt-2 text-sm"><?php echo getActiveLang() == 'tr' ? 'Eğer bu sorun devam ederse lütfen site yöneticisiyle iletişime geçin.' : 'If this problem persists, please contact the site administrator.'; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($users)): ?>
        <div class="bg-gray-100 p-6 rounded-lg shadow-sm text-center dark:bg-gray-700 dark:text-gray-300">
            <p><?php echo getActiveLang() == 'tr' ? 'Henüz kayıtlı üye bulunmamaktadır.' : 'No registered members yet.'; ?></p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">            <?php foreach ($users as $user): ?>                <div class="bg-white rounded-lg shadow-md overflow-hidden dark:bg-gray-700">
                    <div class="p-4 text-center">
                        <a href="<?php echo '/uyeler/' . urlencode($user['username']); ?>" class="block">                            <img 
                                src="<?php echo getAvatarBase64($user['avatar'] ?? 'default-avatar.jpg'); ?>" 
                                alt="<?php echo htmlspecialchars($user['username']); ?>"
                                class="w-16 h-16 rounded-full mx-auto mb-3 object-cover"
                            >                            <h3 class="text-base font-medium text-gray-900 dark:text-white flex items-center justify-center gap-2">
                                <?php echo htmlspecialchars(isset($user['full_name']) && !empty($user['full_name']) ? $user['full_name'] : $user['username']); ?>
                                <?php echo getUserStatusHtml(isset($user['is_online']) ? (bool)$user['is_online'] : isUserOnline($user['id'], $user['last_activity'] ?? null)); ?>
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                @<?php echo htmlspecialchars($user['username']); ?>
                            </p>
                            <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                                <span class="px-1.5 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-gray-900 dark:text-gray-300 mt-1 inline-block">
                                    <svg class="-ml-0.5 mr-1 h-2 w-2 text-red-400 dark:text-gray-600 inline-block" fill="currentColor" viewBox="0 0 8 8">
                                        <circle cx="4" cy="4" r="3" />
                                    </svg>
                                    <?php echo getActiveLang() == 'tr' ? 'Yönetici' : 'Admin'; ?>
                                </span>
                            <?php elseif (isset($user['is_premium']) && $user['is_premium']): ?>
                                <span class="px-1.5 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-gray-900 dark:text-gray-300 mt-1 inline-block">
                                    <svg class="-ml-0.5 mr-1 h-2 w-2 text-red-400 dark:text-gray-600 inline-block" fill="currentColor" viewBox="0 0 8 8">
                                        <circle cx="4" cy="4" r="3" />
                                    </svg>
                                    <?php echo getActiveLang() == 'tr' ? 'Premium Üye' : 'Premium Member'; ?>
                                </span>
                            <?php else: ?>
                                <span class="px-1.5 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-gray-900 dark:text-gray-300 mt-1 inline-block">
                                    <svg class="-ml-0.5 mr-1 h-2 w-2 text-blue-400 dark:text-gray-600 inline-block" fill="currentColor" viewBox="0 0 8 8">
                                        <circle cx="4" cy="4" r="3" />
                                    </svg>
                                    <?php echo getActiveLang() == 'tr' ? 'Üye' : 'Member'; ?>
                                </span>
                            <?php endif; ?>                            <?php if (isset($user['register_date'])): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo getActiveLang() == 'tr' ? 'Üyelik' : 'Member since'; ?>: <?php echo date('d.m.Y', strtotime($user['register_date'])); ?>
                            </p>
                            <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo getActiveLang() == 'tr' ? 'Üyelik' : 'Member since'; ?>: <?php echo date('d.m.Y'); ?>
                            </p>
                            <?php endif; ?>
                        </a>
                        <?php if (isset($user['bio']) && !empty($user['bio'])): ?>
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-2 line-clamp-2">
                                <?php echo htmlspecialchars(substr($user['bio'], 0, 80)); ?>
                                <?php echo strlen($user['bio']) > 80 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-8">
                <nav class="inline-flex">
                    <?php if ($current_page > 1): ?>
                        <a href="?sayfa=<?php echo $current_page - 1; ?>" class="px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 rounded-l hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            &laquo; <?php echo getActiveLang() == 'tr' ? 'Önceki' : 'Previous'; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?sayfa=1" class="px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = $i === $current_page 
                            ? 'px-4 py-2 text-white bg-blue-600 border border-blue-600 dark:bg-blue-800 dark:border-blue-800' 
                            : 'px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600';
                        echo '<a href="?sayfa=' . $i . '" class="' . $active_class . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">...</span>';
                        }
                        echo '<a href="?sayfa=' . $total_pages . '" class="px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?sayfa=<?php echo $current_page + 1; ?>" class="px-4 py-2 text-gray-600 bg-gray-100 border border-gray-300 rounded-r hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            <?php echo getActiveLang() == 'tr' ? 'Sonraki' : 'Next'; ?> &raquo;
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'templates/footer.php'; 
// Çıktı tamponlamasını sonlandır
if (ob_get_level() > 0) {
    ob_end_flush();
}
