<?php
// İstatistikleri göstermek için kenar çubuğu bileşeni

// Toplam üye sayısını getir
try {
    $total_users_stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $total_users = $total_users_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $total_users = 0;
    error_log("Toplam üye sayısı getirilirken hata: " . $e->getMessage());
}

// Toplam makale sayısını getir
try {
    $total_articles_stmt = $db->query("SELECT COUNT(*) as total FROM articles WHERE status = 'published'");
    $total_articles = $total_articles_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $total_articles = 0;
    error_log("Toplam makale sayısı getirilirken hata: " . $e->getMessage());
}

// Son kaydolan üyeyi getir
try {
    $last_user_stmt = $db->query("
        SELECT id, username, created_at
        FROM users 
        WHERE status = 'active'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $last_user = $last_user_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $last_user = null;
    error_log("Son üye getirilirken hata: " . $e->getMessage());
}
?>

<!-- İstatistikler Kutusu -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6">
    <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-white">
        <i class="fas fa-chart-bar text-blue-500 mr-2"></i><?php echo __('sidebar_statistics'); ?>
    </h3>
    
    <div class="stats-list space-y-3">
        <!-- Toplam Üye -->
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-users text-gray-500 mr-2"></i>
                <span class="text-gray-700 dark:text-gray-300"><?php echo __('sidebar_total_members'); ?></span>
            </div>
            <span class="font-semibold text-blue-600 dark:text-blue-400">
                <?php echo number_format($total_users); ?>
            </span>
        </div>
        
        <!-- Toplam Makale -->
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-file-alt text-gray-500 mr-2"></i>
                <span class="text-gray-700 dark:text-gray-300"><?php echo __('sidebar_total_articles'); ?></span>
            </div>
            <span class="font-semibold text-blue-600 dark:text-blue-400">
                <?php echo number_format($total_articles); ?>
            </span>
        </div>
        
        <!-- Son Üye -->
        <?php if ($last_user): ?>
        <div class="border-t border-gray-100 dark:border-gray-700 pt-3 mt-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-gray-700 dark:text-gray-300">
                    <i class="fas fa-user-plus text-gray-500 mr-2"></i>
                    <?php echo __('sidebar_newest_member'); ?>
                </span>
                <span class="text-gray-500 text-xs">
                    <?php 
                    $date = new DateTime($last_user['created_at']);
                    echo __('sidebar_registered') . ' ' . $date->format('d.m.Y'); 
                    ?>
                </span>
            </div>
            <a href="/uyeler/<?php echo $last_user['username']; ?>" 
               class="font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                <?php echo htmlspecialchars($last_user['username']); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
