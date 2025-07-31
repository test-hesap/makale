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

// Sayfalama için değişkenler
$current_page = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$per_page = 10; // Sayfa başına gösterilecek mesaj sayısı
$offset = ($current_page - 1) * $per_page;

// Aktif sekme (gelen, giden)
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'sent' ? 'sent' : 'inbox';

// Mesaj silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $message_id = (int)$_GET['id'];
    
    try {
        // Mesajı silen kullanıcıya göre ilgili alanı güncelle
        if ($active_tab === 'inbox') {
            $stmt = $db->prepare("UPDATE user_messages SET is_deleted_by_receiver = 1 WHERE id = ? AND receiver_id = ?");
        } else {
            $stmt = $db->prepare("UPDATE user_messages SET is_deleted_by_sender = 1 WHERE id = ? AND sender_id = ?");
        }
        
        $stmt->execute([$message_id, $user_id]);
        
        // Başarılı silme işlemi sonrası yönlendirme
        header("Location: mesajlarim.php" . ($active_tab === 'sent' ? '?tab=sent' : ''));
        exit();
    } catch (PDOException $e) {
        error_log("Mesaj silme hatası: " . $e->getMessage());
    }
}

// Mesaj okundu olarak işaretleme
if (isset($_GET['action']) && $_GET['action'] === 'read' && isset($_GET['id'])) {
    $message_id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("UPDATE user_messages SET status = 'read' WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$message_id, $user_id]);
        
        // Başarılı işlem sonrası yönlendirme
        header("Location: mesajlarim.php" . ($active_tab === 'sent' ? '?tab=sent' : ''));
        exit();
    } catch (PDOException $e) {
        error_log("Mesaj okundu işaretleme hatası: " . $e->getMessage());
    }
}

try {
    // Toplam mesaj sayısını al
    if ($active_tab === 'inbox') {
        $count_stmt = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id = ? AND is_deleted_by_receiver = 0");
    } else {
        $count_stmt = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE sender_id = ? AND is_deleted_by_sender = 0");
    }
    
    $count_stmt->execute([$user_id]);
    $total_messages = $count_stmt->fetchColumn();
    
    // Toplam sayfa sayısını hesapla
    $total_pages = ceil($total_messages / $per_page);
    
    // Geçerli sayfa numarasını kontrol et
    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    
    // Mesajları getir
    if ($active_tab === 'inbox') {
        $query = "
            SELECT m.*, u.username as sender_name, u.avatar as sender_avatar
            FROM user_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = :user_id AND m.is_deleted_by_receiver = 0
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($query);
    } else {
        $query = "
            SELECT m.*, u.username as receiver_name, u.avatar as receiver_avatar
            FROM user_messages m
            JOIN users u ON m.receiver_id = u.id
            WHERE m.sender_id = :user_id AND m.is_deleted_by_sender = 0
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($query);
    }
    
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Okunmamış mesaj sayısını al
    $unread_stmt = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id = ? AND status = 'unread' AND is_deleted_by_receiver = 0");
    $unread_stmt->execute([$user_id]);
    $unread_count = $unread_stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Mesaj listeleme hatası: " . $e->getMessage());
    $messages = [];
    $total_pages = 0;
    $unread_count = 0;
}

// Sayfa başlığı
$page_title = t('messages_title');
$meta_description = t('my_messages');

// Header'ı dahil et
require_once 'templates/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white dark:bg-[#121212] rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo t('messages_title'); ?></h1>
                <div class="flex space-x-2">
                    <a href="engellenen-kullanicilar.php" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                        <?php echo t('messages_blocked_users'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Sekmeler -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="flex -mb-px">
                    <a href="mesajlarim.php" class="<?php echo $active_tab === 'inbox' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                        <?php echo t('messages_inbox'); ?>
                        <?php if ($unread_count > 0): ?>
                            <span class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="mesajlarim.php?tab=sent" class="<?php echo $active_tab === 'sent' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        <?php echo t('messages_sent'); ?>
                    </a>
                </nav>
            </div>
            
            <?php if (count($messages) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <?php echo $active_tab === 'inbox' ? t('messages_sender') : t('messages_recipient'); ?>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <?php echo t('messages_subject'); ?>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <?php echo t('messages_date'); ?>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <?php echo t('messages_status'); ?>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <?php echo t('messages_actions'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                            <?php foreach ($messages as $message): ?>
                                <tr class="<?php echo ($active_tab === 'inbox' && $message['status'] === 'unread') ? 'bg-blue-50 dark:bg-blue-900/20' : ''; ?> hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" 
                                                     src="<?php echo getAvatarBase64($active_tab === 'inbox' ? ($message['sender_avatar'] ?? 'default-avatar.jpg') : ($message['receiver_avatar'] ?? 'default-avatar.jpg')); ?>" 
                                                     alt="<?php echo htmlspecialchars($active_tab === 'inbox' ? $message['sender_name'] : $message['receiver_name']); ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <a href="uye.php?username=<?php echo htmlspecialchars($active_tab === 'inbox' ? $message['sender_name'] : $message['receiver_name']); ?>" class="hover:text-blue-600 dark:hover:text-blue-400">
                                                        <?php echo htmlspecialchars($active_tab === 'inbox' ? $message['sender_name'] : $message['receiver_name']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white font-medium">
                                            <a href="mesaj-detay.php?id=<?php echo $message['id']; ?>" class="hover:text-blue-600 dark:hover:text-blue-400">
                                                <?php echo htmlspecialchars($message['subject']); ?>
                                            </a>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                            <?php echo htmlspecialchars(substr(strip_tags($message['message']), 0, 50) . (strlen($message['message']) > 50 ? '...' : '')); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($active_tab === 'inbox'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $message['status'] === 'unread' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; ?>">
                                                <?php echo $message['status'] === 'unread' ? t('messages_unread') : t('messages_read'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                <?php echo t('messages_sent_status'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="mesaj-detay.php?id=<?php echo $message['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($active_tab === 'inbox' && $message['status'] === 'unread'): ?>
                                            <a href="mesajlarim.php?action=read&id=<?php echo $message['id']; ?>" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-3">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="mesajlarim.php?action=delete&id=<?php echo $message['id']; ?><?php echo $active_tab === 'sent' ? '&tab=sent' : ''; ?>" 
                                           onclick="return confirm('<?php echo t('messages_delete_confirm'); ?>')" 
                                           class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Sayfalama -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-center">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="mesajlarim.php?sayfa=<?php echo $current_page - 1; ?><?php echo $active_tab === 'sent' ? '&tab=sent' : ''; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="sr-only">Previous</span>
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-900 text-sm font-medium text-blue-600 dark:text-blue-200">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="mesajlarim.php?sayfa=<?php echo $i; ?><?php echo $active_tab === 'sent' ? '&tab=sent' : ''; ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="mesajlarim.php?sayfa=<?php echo $current_page + 1; ?><?php echo $active_tab === 'sent' ? '&tab=sent' : ''; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="sr-only">Next</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-10 text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo t('messages_no_messages_inbox'); ?></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        <?php echo $active_tab === 'inbox' ? t('messages_no_messages_inbox') : t('messages_no_messages_sent'); ?>
                    </p>
                </div>
            <?php endif; ?>
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