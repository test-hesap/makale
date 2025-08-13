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
        <!-- Toplu işlemler için kontrol paneli -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tümünü Seç</span>
                    </label>
                    <span id="selected-count" class="text-sm text-gray-500 dark:text-gray-400">0 seçili</span>
                </div>
                <div class="flex space-x-2">
                    <button id="bulk-delete" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-trash mr-1"></i>
                        Seçilenleri Sil
                    </button>
                    <button id="bulk-mark-read" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-check mr-1"></i>
                        Seçilenleri Okundu İşaretle
                    </button>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($notifications as $notification): ?>
                    <?php 
                        $isRead = $notification['is_read'] ? 'bg-white dark:bg-gray-800' : 'bg-blue-50 dark:bg-gray-700';
                        $timeAgo = time_elapsed_string($notification['created_at']);
                    ?>
                    <div class="p-4 <?php echo $isRead; ?> hover:bg-gray-50 dark:hover:bg-gray-700 transition" data-notification-id="<?php echo $notification['id']; ?>">
                        <div class="flex items-start">
                            <!-- Seçim checkbox'ı -->
                            <div class="mr-3 flex-shrink-0">
                                <input type="checkbox" class="notification-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                                       value="<?php echo $notification['id']; ?>">
                            </div>
                            
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
                                    
                                    <button onclick="deleteNotification(<?php echo $notification['id']; ?>, this)" 
                                            class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 hover:underline">
                                        Sil
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-4 flex justify-between">
            <div class="flex space-x-2">
                <button id="delete-all" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    <i class="fas fa-trash mr-1"></i>
                    Tüm Bildirimleri Sil
                </button>
            </div>
            <button id="mark-all-read" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                Tümünü Okundu İşaretle
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
// Tek bildirim silme
function deleteNotification(id, button) {
    if (!confirm('Bu bildirimi silmek istediğinizden emin misiniz?')) {
        return;
    }
    
    fetch('ajax_notifications.php?action=delete&id=' + id, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Bildirimi DOM'dan kaldır
            const notificationElement = button.closest('[data-notification-id]');
            notificationElement.remove();
            
            // Bildirim sayısını güncelle
            updateNotificationCount(data.unread_count);
            
            // Seçili sayısını güncelle
            updateSelectedCount();
        } else {
            alert('Bildirim silinirken hata oluştu: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Bildirim silinirken hata:', error);
        alert('Bildirim silinirken hata oluştu');
    });
}

// Çoklu bildirim silme
function deleteSelectedNotifications() {
    const selectedIds = getSelectedNotificationIds();
    
    if (selectedIds.length === 0) {
        alert('Lütfen silinecek bildirimleri seçin');
        return;
    }
    
    if (!confirm(`${selectedIds.length} bildirimi silmek istediğinizden emin misiniz?`)) {
        return;
    }
    
    fetch('ajax_notifications.php?action=bulk_delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: selectedIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Seçili bildirimleri DOM'dan kaldır
            selectedIds.forEach(id => {
                const element = document.querySelector(`[data-notification-id="${id}"]`);
                if (element) element.remove();
            });
            
            // Bildirim sayısını güncelle
            updateNotificationCount(data.unread_count);
            
            // Checkbox'ları sıfırla
            resetCheckboxes();
            
            alert(`${data.deleted_count} bildirim başarıyla silindi`);
        } else {
            alert('Bildirimler silinirken hata oluştu: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Bildirimler silinirken hata:', error);
        alert('Bildirimler silinirken hata oluştu');
    });
}

// Tüm bildirimleri silme
function deleteAllNotifications() {
    if (!confirm('TÜM bildirimleri silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
        return;
    }
    
    fetch('ajax_notifications.php?action=delete_all', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sayfayı yenile
            window.location.reload();
        } else {
            alert('Tüm bildirimler silinirken hata oluştu: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Tüm bildirimler silinirken hata:', error);
        alert('Tüm bildirimler silinirken hata oluştu');
    });
}

// Seçili bildirimleri okundu işaretleme
function markSelectedAsRead() {
    const selectedIds = getSelectedNotificationIds();
    
    if (selectedIds.length === 0) {
        alert('Lütfen okundu işaretlenecek bildirimleri seçin');
        return;
    }
    
    fetch('ajax_notifications.php?action=bulk_mark_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: selectedIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Seçili bildirimlerin arkaplan rengini değiştir
            selectedIds.forEach(id => {
                const element = document.querySelector(`[data-notification-id="${id}"]`);
                if (element) {
                    element.classList.remove('bg-blue-50', 'dark:bg-gray-700');
                    element.classList.add('bg-white', 'dark:bg-gray-800');
                    
                    // Okundu işaretle butonunu kaldır
                    const readButton = element.querySelector('button[onclick^="markAsRead"]');
                    if (readButton) readButton.remove();
                }
            });
            
            // Bildirim sayısını güncelle
            updateNotificationCount(data.unread_count);
            
            // Checkbox'ları sıfırla
            resetCheckboxes();
            
            alert(`${data.marked_count} bildirim okundu olarak işaretlendi`);
        } else {
            alert('Bildirimler okundu işaretlenirken hata oluştu: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Bildirimler okundu işaretlenirken hata:', error);
        alert('Bildirimler okundu işaretlenirken hata oluştu');
    });
}

// Yardımcı fonksiyonlar
function getSelectedNotificationIds() {
    const checkboxes = document.querySelectorAll('.notification-checkbox:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.value));
}

function updateSelectedCount() {
    const selectedCount = document.querySelectorAll('.notification-checkbox:checked').length;
    document.getElementById('selected-count').textContent = `${selectedCount} seçili`;
    
    // Toplu işlem butonlarını etkinleştir/devre dışı bırak
    const bulkButtons = document.querySelectorAll('#bulk-delete, #bulk-mark-read');
    bulkButtons.forEach(button => {
        button.disabled = selectedCount === 0;
    });
}

function resetCheckboxes() {
    document.querySelectorAll('.notification-checkbox, #select-all').forEach(cb => {
        cb.checked = false;
    });
    updateSelectedCount();
}

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

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Tümünü seç checkbox
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.notification-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Bireysel checkbox'lar
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('notification-checkbox')) {
            updateSelectedCount();
            
            // Tümünü seç checkbox'ının durumunu güncelle
            const allCheckboxes = document.querySelectorAll('.notification-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.notification-checkbox:checked');
            const selectAllCheckbox = document.getElementById('select-all');
            
            if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCheckboxes.length === allCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }
    });
    
    // Toplu silme butonu
    document.getElementById('bulk-delete').addEventListener('click', deleteSelectedNotifications);
    
    // Toplu okundu işaretle butonu
    document.getElementById('bulk-mark-read').addEventListener('click', markSelectedAsRead);
    
    // Tüm bildirimleri sil butonu
    document.getElementById('delete-all').addEventListener('click', deleteAllNotifications);
    
    // Tümünü okundu işaretle butonu
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
});
</script>

<?php require_once 'includes/footer.php'; ?>
