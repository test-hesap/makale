<?php
require_once '../includes/config.php';
require_once 'includes/header.php';
require_once 'includes/notifications.php';
checkAuth(true);

// Zaman formatını insan dostu hale getiren fonksiyon
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'yıl',
        'm' => 'ay',
        'w' => 'hafta',
        'd' => 'gün',
        'h' => 'saat',
        'i' => 'dakika',
        's' => 'saniye',
    );
    
    $plural = array(
        'y' => 'yıl',
        'm' => 'ay',
        'w' => 'hafta',
        'd' => 'gün',
        'h' => 'saat',
        'i' => 'dakika',
        's' => 'saniye',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . ($diff->$k > 1 ? $plural[$k] : $v);
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' önce' : 'şimdi';
}

// Tüm bildirimleri al
try {
    // Bildirim sorgusunu çalıştır
    $stmt = $db->query("SELECT n.*, u.username
                        FROM admin_notifications n
                        LEFT JOIN users u ON n.user_id = u.id
                        ORDER BY n.created_at DESC");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
    $errorMsg = "Bildirimler alınırken hata: " . $e->getMessage();
    error_log($errorMsg);
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Tüm Bildirimler</h1>
    
    <?php if (empty($notifications)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <p class="text-gray-600 dark:text-gray-400">Henüz bildirim bulunmuyor.</p>
        </div>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($notifications as $notification): ?>
                    <?php 
                        $isRead = $notification['is_read'] ? 'bg-white dark:bg-gray-800' : 'bg-blue-50 dark:bg-gray-700';
                        $timeAgo = time_elapsed_string($notification['created_at']);
                    ?>
                    <div class="p-4 <?php echo $isRead; ?> hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <div class="flex items-start">
                            <?php if (!empty($notification['user_id']) && !empty($notification['username'])): ?>
                                <div class="mr-3 flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                </div>
                            <?php elseif ($notification['type'] == 'new_user'): ?>
                                <div class="mr-3 flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                                        <i class="fas fa-user-plus text-green-600 dark:text-green-400"></i>
                                    </div>
                                </div>
                            <?php elseif ($notification['type'] == 'system'): ?>
                                <div class="mr-3 flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <i class="fas fa-cog text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mr-3 flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <i class="fas fa-bell text-gray-600 dark:text-gray-400"></i>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <?php if (!empty($notification['username'])): ?>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($notification['username']); ?>
                                            </p>
                                        <?php elseif ($notification['type'] == 'new_user'): ?>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                Yeni Üye Kaydı
                                            </p>
                                        <?php elseif ($notification['type'] == 'system'): ?>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                Sistem Bildirimi
                                            </p>
                                        <?php else: ?>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                Bildirim
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </p>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo $timeAgo; ?>
                                    </span>
                                </div>
                                <div class="mt-2 flex space-x-2">
                                    <?php if (!empty($notification['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                       class="text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400 hover:underline">
                                        Görüntüle
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                    <button onclick="markAsRead(<?php echo $notification['id']; ?>, this)" 
                                            class="text-xs text-green-500 hover:text-green-700 dark:text-green-400 hover:underline">
                                        Okundu İşaretle
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <button id="mark-all-read" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                Tümünü Okundu İşaretle
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
function markAsRead(id, button) {
    fetch('ajax_notifications.php?action=mark_read&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Bildirim arkaplan rengini değiştir
                button.closest('.bg-blue-50, .dark\\:bg-gray-700').classList.remove('bg-blue-50', 'dark:bg-gray-700');
                button.closest('div.p-4').classList.add('bg-white', 'dark:bg-gray-800');
                
                // Butonu kaldır
                button.remove();
                
                // Bildirim sayısını güncelle
                updateNotificationCount(data.unread_count);
            }
        })
        .catch(error => console.error('Bildirim okundu işaretlenirken hata:', error));
}

function updateNotificationCount(count) {
    const countEl = document.getElementById('notification-count');
    if (countEl) {
        if (count > 0) {
            countEl.textContent = count;
            countEl.classList.remove('hidden');
        } else {
            countEl.textContent = '';
            countEl.classList.add('hidden');
        }
    }
}

document.getElementById('mark-all-read').addEventListener('click', function() {
    fetch('ajax_notifications.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Tüm bildirimlerin arkaplan rengini değiştir
                document.querySelectorAll('.bg-blue-50, .dark\\:bg-gray-700').forEach(el => {
                    el.classList.remove('bg-blue-50', 'dark:bg-gray-700');
                    el.classList.add('bg-white', 'dark:bg-gray-800');
                });
                
                // Tüm okundu işaretle butonlarını kaldır
                document.querySelectorAll('button[onclick^="markAsRead"]').forEach(el => el.remove());
                
                // Bildirim sayısını güncelle
                updateNotificationCount(0);
                
                // Sayfayı yenile
                window.location.reload();
            }
        })
        .catch(error => console.error('Tüm bildirimler okundu işaretlenirken hata:', error));
});
</script>

<?php require_once 'includes/footer.php'; ?>
