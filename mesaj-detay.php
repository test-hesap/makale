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

// Mesaj ID'sini al
$message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Mesaj ID boş ise mesajlar sayfasına yönlendir
if ($message_id <= 0) {
    header("Location: mesajlarim.php");
    exit();
}

// Yanıt gönderme işlemi
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply_message = trim($_POST['message'] ?? '');
    $reply_subject = trim($_POST['subject'] ?? '');
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    
    // Form doğrulama
    if (empty($reply_subject)) {
        $error = t('messages_subject_required');
    } elseif (empty($reply_message)) {
        $error = t('messages_message_required');
    } elseif ($receiver_id <= 0) {
        $error = t('messages_not_found');
    } else {
        try {
            // Yanıtı veritabanına kaydet
            $stmt = $db->prepare("
                INSERT INTO user_messages (sender_id, receiver_id, subject, message) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $receiver_id, $reply_subject, $reply_message]);
            
            $success = t('messages_reply_success');
            
            // Başarılı olursa formu temizle
            $reply_subject = '';
            $reply_message = '';
        } catch (PDOException $e) {
            error_log("Yanıt gönderme hatası: " . $e->getMessage());
            $error = t('messages_reply_error');
        }
    }
}

try {
    // Mesaj bilgilerini getir ve kullanıcının bu mesajı görüntüleme yetkisi olup olmadığını kontrol et
    $stmt = $db->prepare("
        SELECT m.*, 
               sender.username as sender_name, sender.avatar as sender_avatar,
               receiver.username as receiver_name, receiver.avatar as receiver_avatar
        FROM user_messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE m.id = :message_id AND (m.sender_id = :user_id OR m.receiver_id = :user_id)
        AND ((m.sender_id = :sender_id AND m.is_deleted_by_sender = 0) OR (m.receiver_id = :receiver_id AND m.is_deleted_by_receiver = 0))
    ");
    $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mesaj bulunamadıysa veya kullanıcının yetkisi yoksa mesajlar sayfasına yönlendir
    if (!$message) {
        header("Location: mesajlarim.php");
        exit();
    }
    
    // Mesaj alıcıya aitse ve okunmamışsa, okundu olarak işaretle
    if ($message['receiver_id'] == $user_id && $message['status'] === 'unread') {
        $update_stmt = $db->prepare("UPDATE user_messages SET status = 'read' WHERE id = :message_id");
        $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        $update_stmt->execute();
        $message['status'] = 'read'; // Durum bilgisini güncelle
    }
    
    // Mesajı gönderen veya alan kişi mi kontrol et
    $is_sender = ($message['sender_id'] == $user_id);
    $other_user_id = $is_sender ? $message['receiver_id'] : $message['sender_id'];
    $other_user_name = $is_sender ? $message['receiver_name'] : $message['sender_name'];
    $other_user_avatar = $is_sender ? $message['receiver_avatar'] : $message['sender_avatar'];
    
} catch (PDOException $e) {
    error_log("Mesaj detayı alma hatası: " . $e->getMessage());
    header("Location: mesajlarim.php");
    exit();
}

// Sayfa başlığı
$page_title = t('messages_view_message') . ": " . htmlspecialchars($message['subject']);
$meta_description = t('messages_view_message');

// Header'ı dahil et
require_once 'templates/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white dark:bg-[#121212] rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                    <?php echo htmlspecialchars($message['subject']); ?>
                </h1>
                
                <div>
                    <a href="mesajlarim.php<?php echo $is_sender ? '?tab=sent' : ''; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        <?php echo t('messages_back_to_messages'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Mesaj Bilgileri -->
            <div class="bg-gray-50 dark:bg-[#1a1a1a] p-4 rounded-lg mb-6">
                <div class="flex justify-between">
                    <div class="flex items-center">
                        <img class="h-10 w-10 rounded-full" 
                             src="<?php echo getAvatarBase64($is_sender ? $message['receiver_avatar'] : $message['sender_avatar']); ?>" 
                             alt="<?php echo htmlspecialchars($is_sender ? $message['receiver_name'] : $message['sender_name']); ?>">
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo $is_sender ? t('messages_recipient') . ': ' : t('messages_sender') . ': '; ?>
                                <a href="uye.php?username=<?php echo htmlspecialchars($other_user_name); ?>" class="text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300">
                                    <?php echo htmlspecialchars($other_user_name); ?>
                                </a>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <?php if (!$is_sender): ?>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $message['status'] === 'unread' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; ?>">
                                <?php echo $message['status'] === 'unread' ? t('messages_unread') : t('messages_read'); ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                <?php echo t('messages_sent_status'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Mesaj İçeriği -->
            <div class="bg-white dark:bg-[#121212] border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-8">
                <div class="prose dark:prose-invert max-w-none">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                </div>
            </div>
            
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
            
            <?php 
            // Sadece alıcıysak yanıt seçeneğini göster
            if (!$is_sender):
                // Kullanıcının premium veya admin olup olmadığını kontrol et
                $isPremiumOrAdmin = isPremium() || isAdmin();
                
                // Kullanıcının onaylanmış olup olmadığını kontrol et
                $user_status_stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
                $user_status_stmt->execute([$_SESSION['user_id']]);
                $user_status = $user_status_stmt->fetchColumn();
                $isUserActive = ($user_status === 'active');
                
                // Yanıt gönderme izni var mı?
                $canSendReply = $isPremiumOrAdmin && $isUserActive;
                
                // Eğer izin yoksa tooltip mesajını hazırla
                $tooltipMessage = '';
                if (!$isPremiumOrAdmin) {
                    $tooltipMessage = 'Mesaj gönderme özelliği sadece premium üyeler ve yöneticiler için geçerlidir.';
                } elseif (!$isUserActive) {
                    $tooltipMessage = 'Mesaj gönderebilmek için üyeliğinizin onaylanmış olması gerekmektedir.';
                }
            ?>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4"><?php echo t('messages_reply'); ?></h2>
                    
                    <?php if ($canSendReply): ?>
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="receiver_id" value="<?php echo $message['sender_id']; ?>">
                        
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <?php echo t('messages_subject'); ?>
                            </label>
                            <input type="text" name="subject" id="subject" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white"
                                   value="<?php echo t('messages_reply_subject') . htmlspecialchars($message['subject']); ?>" required>
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <?php echo t('contact_message'); ?>
                            </label>
                            <textarea name="message" id="message" rows="6" 
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white"
                                      required><?php echo htmlspecialchars($reply_message ?? ''); ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="reply" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                <?php echo t('messages_send_reply'); ?>
                            </button>
                        </div>
                    </form>
                    <?php else: // Eğer yanıt gönderme izni yoksa ?>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <?php echo t('messages_subject'); ?>
                            </label>
                            <input type="text" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:outline-none bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed opacity-70"
                                   value="<?php echo t('messages_reply_subject') . htmlspecialchars($message['subject']); ?>" disabled>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <?php echo t('contact_message'); ?>
                            </label>
                            <textarea rows="6" 
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:outline-none bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed opacity-70"
                                      disabled></textarea>
                        </div>
                        
                        <div class="relative flex justify-end">
                            <span 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 opacity-50 cursor-not-allowed"
                                title="<?php echo htmlspecialchars($tooltipMessage); ?>">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                <?php echo t('messages_send_reply'); ?>
                            </span>
                            <div class="tooltip hidden absolute bottom-full right-0 px-3 py-2 bg-gray-900 text-white text-xs rounded whitespace-nowrap mb-2 z-10" style="min-width:200px;">
                                <?php echo htmlspecialchars($tooltipMessage); ?>
                                <div class="tooltip-arrow absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                        
                        <script>
                            // Tooltip gösterme/gizleme işlemleri
                            document.addEventListener('DOMContentLoaded', function() {
                                const tooltipTrigger = document.querySelector('.cursor-not-allowed');
                                const tooltip = document.querySelector('.tooltip');
                                
                                if (tooltipTrigger && tooltip) {
                                    tooltipTrigger.addEventListener('mouseenter', function() {
                                        tooltip.classList.remove('hidden');
                                    });
                                    
                                    tooltipTrigger.addEventListener('mouseleave', function() {
                                        tooltip.classList.add('hidden');
                                    });
                                }
                            });
                        </script>
                    </div>
                    <?php endif; // Yanıt gönderme izni kontrolü sonu ?>
                </div>
            <?php endif; // Alıcı mı kontrolü sonu ?>
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