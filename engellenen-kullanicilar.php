<?php
// Çıktı tamponlamasını başlat
ob_start();

require_once 'includes/config.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Kullanıcı ID'sini al
$user_id = $_SESSION['user_id'];

// İşlem yapılacak
$action = isset($_GET['action']) ? $_GET['action'] : '';
$target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Engelleme/engel kaldırma işlemleri
if ($action && $target_user_id > 0) {
    if ($action === 'block' && $target_user_id != $user_id) {
        // Engelleme sebebi
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
        
        // Kullanıcıyı engelle
        if (blockUser($user_id, $target_user_id, $reason)) {
            $message = "Kullanıcı başarıyla engellendi.";
        } else {
            $error = "Kullanıcı engellenirken bir hata oluştu.";
        }
    } elseif ($action === 'unblock') {
        // Engeli kaldır
        if (unblockUser($user_id, $target_user_id)) {
            $message = "Kullanıcının engeli başarıyla kaldırıldı.";
        } else {
            $error = "Kullanıcının engeli kaldırılırken bir hata oluştu.";
        }
    }
}

// Engellenen kullanıcıları listele
$blocked_users = getBlockedUsers($user_id);

// Sayfa başlığı
$page_title = "Engellenen Kullanıcılar";
$meta_description = "Engellediğiniz kullanıcıları yönetin";

// Header'ı dahil et
require_once 'templates/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white dark:bg-[#121212] rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Engellenen Kullanıcılar</h1>
            
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($blocked_users)): ?>
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-md mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 dark:text-blue-200">
                                Henüz hiçbir kullanıcıyı engellemediğiniz görünüyor.
                            </p>
                        </div>
                    </div>
                </div>
                
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Bir kullanıcıyı engellemek için, o kullanıcının profil sayfasını ziyaret edin ve "Engelle" butonuna tıklayın.
                    Engellediğiniz kullanıcılar size mesaj gönderemez.
                </p>
            <?php else: ?>
                <div class="mb-4">
                    <p class="text-gray-600 dark:text-gray-400">
                        Engellediğiniz kullanıcılar size mesaj gönderemez. Engeli kaldırmak için "Engeli Kaldır" butonuna tıklayın.
                    </p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Kullanıcı
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Engelleme Sebebi
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Engelleme Tarihi
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    İşlem
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                            <?php foreach ($blocked_users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" 
                                                     src="<?php echo getAvatarBase64($user['avatar'] ?? 'default-avatar.jpg'); ?>" 
                                                     alt="<?php echo htmlspecialchars($user['username']); ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <a href="uye.php?username=<?php echo htmlspecialchars($user['username']); ?>" class="hover:underline">
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-200">
                                            <?php echo $user['reason'] ? htmlspecialchars($user['reason']) : '<em class="text-gray-500 dark:text-gray-400">Sebep belirtilmedi</em>'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?action=unblock&id=<?php echo $user['blocked_id']; ?>" 
                                           class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                           onclick="return confirm('Bu kullanıcının engelini kaldırmak istediğinizden emin misiniz?');">
                                            Engeli Kaldır
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <a href="mesajlarim.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Mesajlarıma Dön
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'templates/footer.php';

// Eğer başlatılmış bir çıktı tamponlama varsa, sonlandır
if (ob_get_level() > 0) {
    ob_end_flush();
}
?> 