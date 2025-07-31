<?php
require_once '../includes/config.php';
checkAuth(true); // true parametresi admin kontrolü için

// Dashboard istatistiklerini al
$stats = [
    'users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'articles' => $db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
    'comments' => $db->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'")->fetchColumn(),
    'subscriptions' => $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn(),
    'total_payments' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE status = 'completed'")->fetchColumn()
];

// Son aktiviteleri al
$recent_activities = $db->query("
    SELECT a.title, a.created_at, u.username, 'article' as type
    FROM articles a
    JOIN users u ON a.author_id = u.id
    WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT c.content as title, c.created_at, u.username, 'comment' as type
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// HTML karakter kodlamasını çöz
foreach ($recent_activities as &$activity) {
    $activity['title'] = html_entity_decode($activity['title'], ENT_QUOTES, 'UTF-8');
}

// Son eklenen makaleleri al
$recent_articles = $db->query("
    SELECT a.id, a.title, a.created_at, u.username 
    FROM articles a
    JOIN users u ON a.author_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Bekleyen yorumları al
$pending_comments = $db->query("
    SELECT c.id, c.content, c.created_at, u.username, a.title as article_title
    FROM comments c
    JOIN users u ON c.user_id = u.id
    JOIN articles a ON c.article_id = a.id
    WHERE c.status = 'pending'
    ORDER BY c.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Yeni kullanıcıları al
$new_users = $db->query("
    SELECT id, username, email, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Sistem durumu verilerini al
$system_stats = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $db->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor',
    'disk_space' => disk_free_space('/') !== false ? round(disk_free_space('/') / (1024 * 1024 * 1024), 2) . ' GB' : 'Bilinmiyor',
    'total_disk_space' => disk_total_space('/') !== false ? round(disk_total_space('/') / (1024 * 1024 * 1024), 2) . ' GB' : 'Bilinmiyor',
    'memory_usage' => function_exists('memory_get_usage') ? round(memory_get_usage() / (1024 * 1024), 2) . ' MB' : 'Bilinmiyor',
    'server_load' => function_exists('sys_getloadavg') ? implode(', ', sys_getloadavg()) : 'Bilinmiyor'
    ];
// Mail servisi (SMTP) durumu
$smtp_enabled = 'Pasif'; // Default value, will be translated when displayed
$smtp_setting = $db->query("SELECT value FROM settings WHERE `key` = 'smtp_enabled'")->fetchColumn();
if ($smtp_setting === '1') {
    $smtp_enabled = 'Aktif'; // Default value, will be translated when displayed
}

// Yedekleme dosyalarını kontrol et
$backup_dir_db = '../backups/database';
$backup_dir_files = '../backups/files';
$latest_db_backup = '';
$latest_files_backup = '';
if (is_dir($backup_dir_db)) {
    $db_files = glob($backup_dir_db . '/*.sql');
    if ($db_files && count($db_files) > 0) {
        usort($db_files, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latest_db_backup = basename($db_files[0]);
    }
}
if (is_dir($backup_dir_files)) {
    $files_backups = glob($backup_dir_files . '/*.zip');
    if ($files_backups && count($files_backups) > 0) {
        usort($files_backups, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latest_files_backup = basename($files_backups[0]);
    }
}

// Veritabanı bağlantı durumu
$db_status = 'Pasif'; // Default value, will be translated when displayed
try {
    $db->query('SELECT 1');
    $db_status = 'Aktif'; // Default value, will be translated when displayed
} catch (Exception $e) {
    $db_status = 'Pasif'; // Default value, will be translated when displayed
}

include 'includes/header.php';
?>
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo t('admin_dashboard'); ?>
        </h1>
    </div>
            </header>

            <main class="mx-auto px-4 py-6">
                <!-- Hızlı İşlemler -->
                <div class="mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo t('admin_quick_actions'); ?></h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 lg:grid-cols-6 xl:grid-cols-6 gap-4">
                            <!-- Yeni Makale -->
                            <a href="articles.php?action=create" class="flex flex-col items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg p-4 transition-colors duration-200">
                                <i class="fas fa-plus text-2xl mb-2"></i>
                                <span class="text-center text-sm"><?php echo t('admin_new_article'); ?></span>
                            </a>

                            <!-- Kategori Ekle -->
                            <a href="categories.php?action=create" class="flex flex-col items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-lg p-4 transition-colors duration-200">
                                <i class="fas fa-tag text-2xl mb-2"></i>
                                <span class="text-center text-sm"><?php echo t('admin_add_category'); ?></span>
                            </a>

                            <!-- Kullanıcılar -->
                            <a href="users.php" class="flex flex-col items-center justify-center bg-amber-600 hover:bg-amber-700 text-white rounded-lg p-4 transition-colors duration-200">
                                <i class="fas fa-users text-2xl mb-2"></i>
                                <span class="text-center text-sm"><?php echo t('admin_users'); ?></span>
                            </a>

                            <!-- Yorumlar -->
                            <a href="comments.php" class="flex flex-col items-center justify-center bg-red-600 hover:bg-red-700 text-white rounded-lg p-4 transition-colors duration-200">
                                <i class="fas fa-comments text-2xl mb-2"></i>
                                <span class="text-center text-sm"><?php echo t('admin_comments'); ?></span>
                            </a>

                            <!-- Makale AI -->
                            <a href="ai_article_bot.php" class="flex flex-col items-center justify-center bg-purple-600 hover:bg-purple-700 text-white rounded-lg p-4 transition-colors duration-200">
                                <i class="fas fa-robot text-2xl mb-2"></i>
                                <span class="text-center text-sm"><?php echo t('admin_article_ai'); ?></span>
                            </a>

                            <!-- Ayarlar -->
                            <a href="settings.php" class="flex flex-col items-center justify-center bg-gray-600 hover:bg-gray-700 text-white rounded-lg p-4 transition-colors duration-200">
                                <i class="fas fa-cogs text-2xl mb-2"></i>
                                <span class="text-center text-sm"><?php echo t('admin_settings'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- İstatistik kartları -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-500 bg-opacity-75">
                                <i class="fas fa-users text-white text-2xl"></i>
                            </div>
                            <div class="mx-4">
                                <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                                    <?php echo $stats['users']; ?>
                                </h4>
                                <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_users'); ?></div>
                            </div>
                        </div>
                    </div>                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-500 bg-opacity-75">
                                <i class="fas fa-newspaper text-white text-2xl"></i>
                            </div>
                            <div class="mx-4">
                                <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                                    <?php echo $stats['articles']; ?>
                                </h4>
                                <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_articles'); ?></div>
                            </div>
                        </div>
                    </div>                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-500 bg-opacity-75">
                                <i class="fas fa-comments text-white text-2xl"></i>
                            </div>
                            <div class="mx-4">
                                <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                                    <?php echo $stats['comments']; ?>
                                </h4>
                                <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_pending_comments'); ?></div>
                            </div>
                        </div>
                    </div>                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-500 bg-opacity-75">
                                <i class="fas fa-star text-white text-2xl"></i>
                            </div>
                            <div class="mx-4">
                                <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                                    <?php echo $stats['subscriptions']; ?>
                                </h4>
                                <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_subscriptions'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-600 bg-opacity-75">
                                <i class="fas fa-money-bill text-white text-2xl"></i>
                            </div>
                            <div class="mx-4">
                                <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                                    <?php echo number_format($stats['total_payments'], 2, ',', '.'); ?> ₺
                                </h4>
                                <div class="text-gray-500 dark:text-gray-400">Toplam Gelir</div>
                            </div>
                        </div>
                    </div>
                </div>                

                <!-- Ana içerik bölümü - iki sütunlu layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Sol sütun -->
                    <div class="space-y-6">
                        <!-- Son Eklenen Makaleler -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200"><?php echo t('admin_recent_articles'); ?></h3>
                                    <a href="articles.php" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                        <?php echo t('admin_view_all'); ?> <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_title'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_author'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_date'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <?php if (empty($recent_articles)): ?>
                                                <tr>
                                                    <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_no_articles'); ?></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_articles as $article): ?>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                                <a href="articles.php?edit=<?php echo $article['id']; ?>" class="hover:text-blue-500 dark:hover:text-blue-400">
                                                                    <?php echo htmlspecialchars(mb_substr($article['title'], 0, 30)) . (mb_strlen($article['title']) > 30 ? '...' : ''); ?>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo $article['username']; ?>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo date('d.m.Y H:i', strtotime($article['created_at'])); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bekleyen Yorumlar -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200"><?php echo t('admin_pending_comments'); ?></h3>
                                    <a href="comments.php?filter=pending" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                        <?php echo t('admin_view_all'); ?> <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_comment'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_article'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_user'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_actions'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <?php if (empty($pending_comments)): ?>
                                                <tr>
                                                    <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_no_pending_comments'); ?></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($pending_comments as $comment): ?>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm text-gray-900 dark:text-gray-200">
                                                                <?php echo htmlspecialchars(mb_substr($comment['content'], 0, 30)) . (mb_strlen($comment['content']) > 30 ? '...' : ''); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo htmlspecialchars(mb_substr($comment['article_title'], 0, 20)) . (mb_strlen($comment['article_title']) > 20 ? '...' : ''); ?>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo $comment['username']; ?>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                            <a href="comments.php?approve=<?php echo $comment['id']; ?>" class="text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 mr-2">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="comments.php?reject=<?php echo $comment['id']; ?>" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sağ sütun -->
                    <div class="space-y-6">
                        <!-- Son Aktiviteler -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200"><?php echo t('admin_recent_activities'); ?></h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_type'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_content'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_user'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <?php if (empty($recent_activities)): ?>
                                                <tr>
                                                    <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_no_activities'); ?></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_activities as $activity): ?>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3 whitespace-nowrap">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $activity['type'] === 'article' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200'; ?>">
                                                                <?php echo $activity['type'] === 'article' ? t('admin_article') : t('admin_comment'); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm text-gray-900 dark:text-gray-200">
                                                                <?php echo htmlspecialchars(mb_substr($activity['title'], 0, 30)) . (mb_strlen($activity['title']) > 30 ? '...' : ''); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo $activity['username']; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Yeni Kullanıcılar -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200"><?php echo t('admin_new_users'); ?></h3>
                                    <a href="users.php" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                        <?php echo t('admin_view_all'); ?> <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_username'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_email'); ?></th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_registration_date'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <?php if (empty($new_users)): ?>
                                                <tr>
                                                    <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_no_users'); ?></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($new_users as $user): ?>
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                                <a href="users.php?edit=<?php echo $user['id']; ?>" class="hover:text-blue-500 dark:hover:text-blue-400">
                                                                    <?php echo $user['username']; ?>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo $user['email']; ?>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ödemeler ve Abonelikler Bölümü -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Ödemeler Bölümü -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                                    <i class="fas fa-money-bill-wave mr-2 text-green-600 dark:text-green-400"></i> <?php echo t('admin_payment_operations'); ?>
                                </h3>
                                <a href="payments.php" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                    <?php echo t('admin_view_all_payments'); ?> <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        
                        <?php
                        // Son ödeme işlemlerini al
                        $recent_payments = $db->query("
                            SELECT pt.id, pt.user_id, pt.amount, pt.status, pt.payment_method, pt.created_at, u.username 
                            FROM payment_transactions pt
                            JOIN users u ON pt.user_id = u.id
                            ORDER BY pt.created_at DESC
                            LIMIT 5
                        ")->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Özet ödeme istatistikleri
                        $payment_stats = [
                            'total' => $db->query("SELECT COUNT(*) FROM payment_transactions")->fetchColumn(),
                            'completed' => $db->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'completed'")->fetchColumn(),
                            'pending' => $db->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'pending'")->fetchColumn(),
                            'failed' => $db->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'failed'")->fetchColumn(),
                            'refunded' => $db->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'refunded'")->fetchColumn(),
                            'today_total' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn()
                        ];
                        ?>
                        
                        <!-- Ödeme İstatistikleri -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4 mb-6">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1"><?php echo t('admin_total_transactions'); ?></div>
                                <div class="text-xl font-bold text-gray-800 dark:text-gray-100"><?php echo number_format($payment_stats['total']); ?></div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
                                <div class="text-xs font-medium text-green-700 dark:text-green-300 mb-1"><?php echo t('admin_completed_transactions'); ?></div>
                                <div class="text-xl font-bold text-green-800 dark:text-green-100"><?php echo number_format($payment_stats['completed']); ?></div>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
                                <div class="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1"><?php echo t('admin_pending_transactions'); ?></div>
                                <div class="text-xl font-bold text-blue-800 dark:text-blue-100"><?php echo number_format($payment_stats['pending']); ?></div>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900 p-4 rounded-lg">
                                <div class="text-xs font-medium text-red-700 dark:text-red-300 mb-1"><?php echo t('admin_failed_transactions'); ?></div>
                                <div class="text-xl font-bold text-red-800 dark:text-red-100"><?php echo number_format($payment_stats['failed']); ?></div>
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900 p-4 rounded-lg">
                                <div class="text-xs font-medium text-yellow-700 dark:text-yellow-300 mb-1"><?php echo t('admin_refunded_transactions'); ?></div>
                                <div class="text-xl font-bold text-yellow-800 dark:text-yellow-100"><?php echo number_format($payment_stats['refunded']); ?></div>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900 p-4 rounded-lg">
                                <div class="text-xs font-medium text-purple-700 dark:text-purple-300 mb-1"><?php echo t('admin_today_income'); ?></div>
                                <div class="text-xl font-bold text-purple-800 dark:text-purple-100"><?php echo number_format($payment_stats['today_total'], 2); ?> ₺</div>
                            </div>
                        </div>
                        
                        <!-- Son Ödemeler Tablosu -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_payment_user'); ?></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_payment_amount'); ?></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_payment_status'); ?></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_payment_method'); ?></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_payment_date'); ?></th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($recent_payments)): ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_payment_no_transactions'); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_payments as $payment): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                        <a href="users.php?search=<?php echo urlencode($payment['username']); ?>" class="hover:text-blue-500 dark:hover:text-blue-400">
                                                            <?php echo $payment['username']; ?>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo number_format($payment['amount'], 2, ',', '.'); ?> ₺</div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                        switch($payment['status']) {
                                                            case 'completed':
                                                                echo 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200';
                                                                break;
                                                            case 'pending':
                                                                echo 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200';
                                                                break;
                                                            case 'failed':
                                                                echo 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200';
                                                                break;
                                                            case 'refunded':
                                                                echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200';
                                                                break;
                                                            default:
                                                                echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                                        }
                                                        ?>">
                                                        <?php 
                                                        switch($payment['status']) {
                                                            case 'completed':
                                                                echo t('admin_payment_status_completed');
                                                                break;
                                                            case 'pending':
                                                                echo t('admin_payment_status_pending');
                                                                break;
                                                            case 'failed':
                                                                echo t('admin_payment_status_failed');
                                                                break;
                                                            case 'refunded':
                                                                echo t('admin_payment_status_refunded');
                                                                break;
                                                            default:
                                                                echo $payment['status'];
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php 
                                                    switch($payment['payment_method']) {
                                                        case 'credit_card':
                                                            echo '<i class="far fa-credit-card mr-1"></i> ' . t('admin_payment_method_credit_card');
                                                            break;
                                                        case 'bank_transfer':
                                                            echo '<i class="fas fa-university mr-1"></i> ' . t('admin_payment_method_bank_transfer');
                                                            break;
                                                        case 'paypal':
                                                            echo '<i class="fab fa-paypal mr-1"></i> ' . t('admin_payment_method_paypal');
                                                            break;
                                                        default:
                                                            echo $payment['payment_method'];
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('d.m.Y H:i', strtotime($payment['created_at'])); ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                                    <a href="payments.php?id=<?php echo $payment['id']; ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" title="Detay">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-chart-line mr-1"></i> <?php echo t('admin_payment_last_five'); ?>
                            </div>
                            <div>
                                <a href="payments.php?action=reports" class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-500 dark:hover:text-blue-400 mr-4">
                                    <i class="fas fa-chart-bar mr-1"></i> <?php echo t('admin_payment_reports'); ?>
                                </a>
                                <a href="payments.php?action=refunds" class="text-sm text-yellow-600 hover:text-yellow-700 dark:text-yellow-500 dark:hover:text-yellow-400">
                                    <i class="fas fa-undo-alt mr-1"></i> <?php echo t('admin_payment_refund_operations'); ?>
                                </a>
                            </div>
                        </div>
                        </div>
                    </div>
                    
                    <!-- Abonelikler Bölümü -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                                    <i class="fas fa-star mr-2 text-purple-600 dark:text-purple-400"></i> <?php echo t('admin_subscription_title'); ?>
                                </h3>
                                <a href="subscriptions.php" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                    <?php echo t('admin_view_all'); ?> <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                            
                            <?php
                            // Abonelik istatistiklerini al
                            $subscription_stats = [
                                'active' => $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn(),
                                'expired' => $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'expired'")->fetchColumn(),
                                'cancelled' => $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'cancelled'")->fetchColumn(),
                                'pending' => $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'pending'")->fetchColumn(),
                                'total' => $db->query("SELECT COUNT(*) FROM subscriptions")->fetchColumn()
                            ];
                            
                            // Son abonelikler
                            $recent_subscriptions = $db->query("
                                SELECT s.id, s.plan_id, s.status, u.username 
                                FROM subscriptions s
                                JOIN users u ON s.user_id = u.id
                                ORDER BY s.id DESC
                                LIMIT 5
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <!-- Abonelik İstatistikleri -->
                            <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-green-50 dark:bg-green-900 p-3 rounded-lg">
                                    <h4 class="text-green-800 dark:text-green-100 text-sm font-medium"><?php echo t('admin_subscription_status_active'); ?></h4>
                                    <p class="text-green-900 dark:text-green-200 text-2xl font-bold"><?php echo $subscription_stats['active']; ?></p>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900 p-3 rounded-lg">
                                    <h4 class="text-red-800 dark:text-red-100 text-sm font-medium"><?php echo t('admin_subscription_status_expired'); ?></h4>
                                    <p class="text-red-900 dark:text-red-200 text-2xl font-bold"><?php echo $subscription_stats['expired']; ?></p>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-lg">
                                    <h4 class="text-yellow-800 dark:text-yellow-100 text-sm font-medium"><?php echo t('admin_subscription_status_cancelled'); ?></h4>
                                    <p class="text-yellow-900 dark:text-yellow-200 text-2xl font-bold"><?php echo $subscription_stats['cancelled']; ?></p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900 p-3 rounded-lg">
                                    <h4 class="text-blue-800 dark:text-blue-100 text-sm font-medium"><?php echo t('admin_subscription_status_pending'); ?></h4>
                                    <p class="text-blue-900 dark:text-blue-200 text-2xl font-bold"><?php echo $subscription_stats['pending']; ?></p>
                                </div>
                            </div>
                            
                            <!-- Son Abonelikler -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_subscription_user'); ?></th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_subscription_status'); ?></th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_subscription_plan'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php if (empty($recent_subscriptions)): ?>
                                            <tr>
                                                <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_subscription_no_subscriptions'); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_subscriptions as $subscription): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                            <a href="users.php?search=<?php echo urlencode($subscription['username']); ?>" class="hover:text-blue-500 dark:hover:text-blue-400">
                                                                <?php echo $subscription['username']; ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php 
                                                            switch($subscription['status']) {
                                                                case 'active':
                                                                    echo 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200';
                                                                    break;
                                                                case 'expired':
                                                                    echo 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200';
                                                                    break;
                                                                case 'cancelled':
                                                                    echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200';
                                                                    break;
                                                                case 'pending':
                                                                    echo 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200';
                                                                    break;
                                                                default:
                                                                    echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                                            }
                                                            ?>">
                                                            <?php 
                                                            switch($subscription['status']) {
                                                                case 'active':
                                                                    echo t('admin_subscription_status_active');
                                                                    break;
                                                                case 'expired':
                                                                    echo t('admin_subscription_status_expired');
                                                                    break;
                                                                case 'cancelled':
                                                                    echo t('admin_subscription_status_cancelled');
                                                                    break;
                                                                case 'pending':
                                                                    echo t('admin_subscription_status_pending');
                                                                    break;
                                                                default:
                                                                    echo $subscription['status'];
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        Plan #<?php echo $subscription['plan_id']; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sistem Durumu Bölümü -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200"><?php echo t('admin_system_status'); ?></h3>
                            </div>

                            <!-- Sistem Durumu Kartları -->
                            <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg shadow p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-full <?php echo $db_status === t('admin_active') ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200'; ?>">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo t('admin_database'); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo $db_status === 'Aktif' ? t('admin_active') : t('admin_passive'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg shadow p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-full <?php echo function_exists('opcache_get_status') && opcache_get_status(false) ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200'; ?>">
                                            <i class="fas fa-bolt"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo t('admin_cache_system'); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo function_exists('opcache_get_status') && opcache_get_status(false) ? t('admin_working') : t('admin_passive'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg shadow p-4">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-full <?php echo is_dir('../backups') && is_writable('../backups') ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200'; ?>">
                                            <i class="fas fa-save"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo t('admin_backup'); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php
                                                if ($latest_db_backup || $latest_files_backup) {
                                                    echo t('admin_database') . ': ' . ($latest_db_backup ? '<a href="../backups/database/' . htmlspecialchars($latest_db_backup) . '" download class="text-blue-600 hover:underline">' . $latest_db_backup . '</a>' : t('admin_no_backup'));
                                                    echo '<br>' . t('admin_files') . ': ' . ($latest_files_backup ? '<a href="../backups/files/' . htmlspecialchars($latest_files_backup) . '" download class="text-blue-600 hover:underline">' . $latest_files_backup . '</a>' : t('admin_no_backup'));
                                                } else {
                                                    echo t('admin_no_backup');
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg shadow p-4">
                                    <div class="flex items-center">
                                        <!-- Mail Servisi Durumu -->
                                        <div class="p-2 rounded-full <?php echo $smtp_enabled === t('admin_active') ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200'; ?>">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-200"><?php echo t('admin_mail_service'); ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo t('admin_status'); ?>: <span class="font-bold"><?php echo $smtp_enabled === 'Aktif' ? t('admin_active') : t('admin_passive'); ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <a href="settings.php" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-500 dark:hover:text-blue-400">
                                    <?php echo t('admin_system_settings'); ?> <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>

                            <div class="space-y-3 mt-5">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_php_version'); ?>:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo $system_stats['php_version']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_mysql_version'); ?>:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo $system_stats['mysql_version']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_server_software'); ?>:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo $system_stats['server_software']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_disk_space'); ?>:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                        <?php echo $system_stats['disk_space']; ?> / <?php echo $system_stats['total_disk_space']; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_memory_usage'); ?>:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo $system_stats['memory_usage']; ?></span>
                                </div>
                                <?php if ($system_stats['server_load'] !== 'Bilinmiyor'): ?>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_server_load'); ?>:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo $system_stats['server_load']; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

<?php include 'includes/footer.php'; ?>
