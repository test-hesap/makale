<?php
require_once '../includes/config.php';
checkAuth(true);

$error = '';
$success = '';

// Tabloların varlığını kontrol et
try {
    $tables = $db->query("SHOW TABLES LIKE 'banned_users'")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        $error = getActiveLang() == 'en' ? '"banned_users" table not found. Please make the necessary installations.' : '"banned_users" tablosu bulunamadı. Lütfen gerekli kurulumları yapın.';
    }
    
    // Users tablosunda status alanı kontrolü
    $columns = $db->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($columns && strpos($columns['Type'], 'banned') === false) {
        $error = getActiveLang() == 'en' ? '"status" field in "users" table does not contain "banned" value. Please make the necessary installations.' : '"users" tablosundaki "status" alanında "banned" değeri bulunamadı. Lütfen gerekli kurulumları yapın.';
    }
} catch (PDOException $e) {
    $error = getActiveLang() == 'en' ? 'Database error: ' . $e->getMessage() : 'Veritabanı hatası: ' . $e->getMessage();
}

// Ban kaldırma işlemi
if (isset($_GET['action']) && $_GET['action'] === 'unban' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    if (unbanUser($user_id)) {
        $success = getActiveLang() == 'en' ? 'User unbanned successfully.' : 'Kullanıcının banı başarıyla kaldırıldı.';
    } else {
        $error = getActiveLang() == 'en' ? 'An error occurred while unbanning the user.' : 'Kullanıcının banı kaldırılırken bir hata oluştu.';
    }
}

// Tüm banlı kullanıcıları getir
$banned_users = getBannedUsers();

include 'includes/header.php';
?>

<div class="max-w-full px-4 sm:px-6 mx-auto">
    <h2 class="my-4 sm:my-6 text-xl sm:text-2xl font-semibold text-gray-700 dark:text-gray-200">
        <?php echo getActiveLang() == 'en' ? 'Banned Users' : 'Banlı Üyeler'; ?>
    </h2>
    
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Banlı Kullanıcı Listesi -->
    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <?php if (empty($banned_users)): ?>
                <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                    <?php echo getActiveLang() == 'en' ? 'No banned users found.' : 'Banlı kullanıcı bulunmamaktadır.'; ?>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'User' : 'Kullanıcı'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'Ban Reason' : 'Ban Sebebi'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'Banned By' : 'Banlayan'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'Ban Date' : 'Ban Tarihi'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'Ban Expires' : 'Ban Bitiş'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'IP Address' : 'IP Adresi'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'IP Ban' : 'IP Banı'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo getActiveLang() == 'en' ? 'Actions' : 'İşlemler'; ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($banned_users as $ban): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full" src="<?php echo !empty($ban['avatar']) ? '/uploads/avatars/' . $ban['avatar'] : '/assets/img/default-avatar.jpg'; ?>" alt="<?php echo htmlspecialchars($ban['username']); ?>">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <?php echo htmlspecialchars($ban['username']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($ban['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo htmlspecialchars($ban['reason']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo htmlspecialchars($ban['banned_by_username']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo date('d.m.Y H:i', strtotime($ban['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php 
                                        if (empty($ban['expires_at'])) {
                                            echo '<span class="text-red-600 dark:text-red-400">' . (getActiveLang() == 'en' ? t('admin_ban_permanent') : 'Süresiz') . '</span>';
                                        } else {
                                            echo date('d.m.Y H:i', strtotime($ban['expires_at']));
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo !empty($ban['ip_address']) ? htmlspecialchars($ban['ip_address']) : '<span class="text-gray-400">-</span>'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo isset($ban['is_ip_banned']) && $ban['is_ip_banned'] ? '<span class="text-red-600 dark:text-red-400">' . (getActiveLang() == 'en' ? t('admin_yes') : 'Evet') . '</span>' : (getActiveLang() == 'en' ? t('admin_no') : 'Hayır'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="?action=unban&id=<?php echo $ban['user_id']; ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3" onclick="return confirm('<?php echo getActiveLang() == 'en' ? t('admin_ban_confirm_unban') : 'Bu kullanıcının banını kaldırmak istediğinize emin misiniz?'; ?>');">
                                        <i class="fas fa-unlock"></i> <?php echo getActiveLang() == 'en' ? t('admin_unban') : 'Banı Kaldır'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 mb-8 text-center">
        <a href="ban_users.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <i class="fas fa-ban mr-2"></i> <?php echo getActiveLang() == 'en' ? t('admin_go_to_ban_users_page') : 'Üye Banla Sayfasına Git'; ?>
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
