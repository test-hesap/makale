<?php
// Çıktı tamponlamasını başlat
ob_start();

require_once 'includes/config.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Kullanıcının premium veya admin olup olmadığını ve durumunu kontrol et
$isPremiumOrAdmin = isPremium() || isAdmin();
$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_status = $stmt->fetchColumn();
    $isUserActive = ($user_status === 'active');
} catch (PDOException $e) {
    error_log("Kullanıcı durumu kontrol hatası: " . $e->getMessage());
    $isUserActive = false;
}

// Alıcı ID'sini al
$receiver_id = isset($_GET['alici']) ? (int)$_GET['alici'] : 0;

// Alıcı ID boş ise ana sayfaya yönlendir
if ($receiver_id <= 0) {
    header("Location: index.php");
    exit();
}

// Kullanıcı kendine mesaj gönderemez
if ($receiver_id == $_SESSION['user_id']) {
    header("Location: index.php");
    exit();
}

// Engelleme durumunu kontrol et - alıcı kullanıcıyı engellemiş mi?
if (isUserBlocked($receiver_id, $_SESSION['user_id'])) {
    // Kullanıcı engellenmiş, mesaj gönderemez
    $_SESSION['error'] = t('messages_blocked_by_user');
    header("Location: index.php");
    exit();
}

// Engelleme durumunu kontrol et - kullanıcı alıcıyı engellemiş mi?
if (isUserBlocked($_SESSION['user_id'], $receiver_id)) {
    // Kullanıcı alıcıyı engellemiş
    $_SESSION['error'] = t('messages_user_blocked');
    header("Location: engellenen-kullanicilar.php");
    exit();
}

// Alıcı bilgilerini getir
try {
    $stmt = $db->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->execute([$receiver_id]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Alıcı bulunamadıysa ana sayfaya yönlendir
    if (!$receiver) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Alıcı bilgisi alma hatası: " . $e->getMessage());
    header("Location: index.php");
    exit();
}

// Mesaj gönderme yetkisi kontrolü
$canSendMessage = $isPremiumOrAdmin && $isUserActive;

// Eğer izin yoksa tooltip mesajını hazırla
$tooltipMessage = '';
if (!$isPremiumOrAdmin) {
    $tooltipMessage = 'Mesaj gönderme özelliği sadece premium üyeler ve yöneticiler için geçerlidir.';
} elseif (!$isUserActive) {
    $tooltipMessage = 'Mesaj gönderebilmek için üyeliğinizin onaylanmış olması gerekmektedir.';
}

// Form gönderildiğinde
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSendMessage) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Form doğrulama
    if (empty($subject)) {
        $error = t('messages_subject_required');
    } elseif (empty($message)) {
        $error = t('messages_message_required');
    } else {
        try {
            // Mesajı veritabanına kaydet
            $stmt = $db->prepare("
                INSERT INTO user_messages (sender_id, receiver_id, subject, message) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $receiver_id, $subject, $message]);
            
            $success = t('messages_sent_success');
            
            // Başarılı olursa formu temizle
            $subject = '';
            $message = '';
        } catch (PDOException $e) {
            error_log("Mesaj gönderme hatası: " . $e->getMessage());
            $error = t('messages_sent_error');
        }
    }
}

// Sayfa başlığı
$page_title = htmlspecialchars($receiver['username']) . ' - ' . t('messages_send_message');
$meta_description = t('messages_send_message');

// Header'ı dahil et
require_once 'templates/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white dark:bg-[#121212] rounded-lg shadow-lg overflow-hidden">
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                <span class="mr-2"><?php echo t('messages_to'); ?></span>
                <div class="flex items-center">
                    <img src="<?php echo getAvatarBase64($receiver['avatar'] ?? 'default-avatar.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($receiver['username']); ?>" 
                         class="w-8 h-8 rounded-full mr-2">
                    <span><?php echo htmlspecialchars($receiver['username']); ?></span>
                </div>
            </h1>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                    <p class="mt-2">
                        <a href="mesajlarim.php" class="text-blue-600 hover:underline"><?php echo t('messages_back_to_messages'); ?></a> <?php echo t('or'); ?> 
                        <a href="uye.php?username=<?php echo htmlspecialchars($receiver['username']); ?>" class="text-blue-600 hover:underline">
                            <?php echo t('messages_back_to_profile'); ?> <?php echo htmlspecialchars($receiver['username']); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <?php if (!$canSendMessage): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded mb-6">
                    <p class="font-bold"><?php echo htmlspecialchars($tooltipMessage); ?></p>
                    <p class="mt-2 text-sm">Aşağıdaki formu görebilirsiniz ancak mesaj gönderemezsiniz.</p>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <?php echo t('messages_subject'); ?>
                        </label>
                        <input type="text" name="subject" id="subject" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white <?php echo !$canSendMessage ? 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed opacity-70' : ''; ?>"
                               value="<?php echo htmlspecialchars($subject ?? ''); ?>" required <?php echo !$canSendMessage ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <?php echo t('contact_message'); ?>
                        </label>
                        <textarea name="message" id="message" rows="8" 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white <?php echo !$canSendMessage ? 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed opacity-70' : ''; ?>"
                                  required <?php echo !$canSendMessage ? 'disabled' : ''; ?>><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="uye.php?username=<?php echo htmlspecialchars($receiver['username']); ?>" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            <?php echo t('messages_back'); ?>
                        </a>
                        
                        <?php if ($canSendMessage): ?>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <?php echo t('messages_send_message'); ?>
                        </button>
                        <?php else: ?>
                        <div class="relative">
                            <span 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 opacity-50 cursor-not-allowed"
                                title="<?php echo htmlspecialchars($tooltipMessage); ?>">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                <?php echo t('messages_send_message'); ?>
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
                        <?php endif; ?>
                        </button>
                    </div>
                </form>
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