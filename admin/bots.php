<?php
// Admin paneli - Bot ziyaretçileri yönetimi
require_once '../includes/config.php';
require_once 'includes/header.php';

// Sadece admin kullanıcıların erişimine izin ver
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

// Botları temizle
cleanupBots();

// Tüm botları getir
try {
    $stmt = $db->query("
        SELECT bot_name, user_agent, last_activity, ip_address, visit_count
        FROM online_bots 
        ORDER BY last_activity DESC
    ");
    $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = t('admin_bots_fetch_error') . ": " . $e->getMessage();
    $bots = [];
}

// Toplam bot sayısını getir
$bot_count = getBotCount();

// Bot istatistikleri
try {
    $stmt = $db->query("
        SELECT bot_name, COUNT(*) as count
        FROM online_bots 
        GROUP BY bot_name 
        ORDER BY count DESC
        LIMIT 10
    ");
    $bot_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = t('admin_bot_stats_fetch_error') . ": " . $e->getMessage();
    $bot_stats = [];
}
?>

<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo t('admin_bot_visitors'); ?>
        </h1>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- İstatistik Kartları -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-500 bg-opacity-75">
                    <i class="fas fa-robot text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <?php echo $bot_count; ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_active_bots'); ?></div>
                </div>
            </div>
        </div>
        
        <?php if (count($bot_stats) > 0): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-500 bg-opacity-75">
                    <i class="fas fa-award text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <?php echo htmlspecialchars($bot_stats[0]['bot_name']); ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_most_active_bot'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-500 bg-opacity-75">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        15 <?php echo t('admin_minutes'); ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_session_timeout'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-500 bg-opacity-75">
                    <i class="fas fa-file-alt text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <a href="/robots.txt" target="_blank" class="hover:underline">robots.txt</a>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_bot_control'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Çevrimiçi Botlar Tablosu -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-8">
        <div class="flex justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                <i class="fas fa-robot mr-2"></i>
                <?php echo t('admin_online_bots'); ?> (<?php echo t('admin_last_15_minutes'); ?>)
            </h3>
            <button id="refreshBots" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                <i class="fas fa-sync-alt mr-2"></i>
                <?php echo t('admin_refresh'); ?>
            </button>
        </div>
        <div class="p-6">
            <?php if (count($bots) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_bot_name'); ?>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_last_activity'); ?>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_ip_address'); ?>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_visit_count'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($bots as $bot): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php 
                                            $bot_icon = 'fa-robot';
                                            $bot_color = 'text-gray-500';
                                            
                                            // Bot türüne göre ikon ve renk belirle
                                            if (stripos($bot['bot_name'], 'Google') !== false) {
                                                $bot_icon = 'fa-google';
                                                $bot_color = 'text-blue-500';
                                            } elseif (stripos($bot['bot_name'], 'Bing') !== false) {
                                                $bot_icon = 'fa-microsoft';
                                                $bot_color = 'text-blue-400';
                                            } elseif (stripos($bot['bot_name'], 'Yahoo') !== false) {
                                                $bot_icon = 'fa-yahoo';
                                                $bot_color = 'text-purple-500';
                                            } elseif (stripos($bot['bot_name'], 'Facebook') !== false) {
                                                $bot_icon = 'fa-facebook';
                                                $bot_color = 'text-blue-600';
                                            } elseif (stripos($bot['bot_name'], 'Twitter') !== false) {
                                                $bot_icon = 'fa-twitter';
                                                $bot_color = 'text-blue-400';
                                            } elseif (stripos($bot['bot_name'], 'Yandex') !== false) {
                                                $bot_icon = 'fa-yandex';
                                                $bot_color = 'text-red-500';
                                            }
                                            ?>
                                            <span class="mr-2">
                                                <i class="fab <?php echo $bot_icon; ?> <?php echo $bot_color; ?>"></i>
                                            </span>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-200" title="<?php echo htmlspecialchars($bot['user_agent']); ?>">
                                                <?php echo htmlspecialchars($bot['bot_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('d.m.Y H:i:s', strtotime($bot['last_activity'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($bot['ip_address']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                            <?php echo $bot['visit_count']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 dark:bg-gray-900/30 border-l-0 p-4 text-blue-700 dark:text-gray-300">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p><?php echo t('admin_no_online_bots'); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bot İstatistikleri -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-8">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                <i class="fas fa-chart-bar mr-2"></i>
                <?php echo t('admin_bot_statistics'); ?>
            </h3>
        </div>
        <div class="p-6">
            <?php if (count($bot_stats) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_bot_name'); ?>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_count'); ?>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <?php echo t('admin_percentage'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php 
                            $total = array_sum(array_column($bot_stats, 'count'));
                            foreach ($bot_stats as $stat): 
                                $percentage = ($total > 0) ? round(($stat['count'] / $total) * 100) : 0;
                            ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                            <?php echo htmlspecialchars($stat['bot_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo $stat['count']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 inline-block">
                                            %<?php echo $percentage; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 dark:bg-gray-900/30 border-l-0 p-4 text-blue-700 dark:text-gray-300">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p><?php echo t('admin_no_bot_stats'); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Yenile butonu
    document.getElementById('refreshBots').addEventListener('click', function() {
        location.reload();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 