<?php
require_once '../includes/config.php';

// Sadece admin kullanıcılarına izin ver
if (!isAdmin()) {
    header('Location: /');
    exit;
}

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

include 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Mesajı işaretleme veya silme işlemleri
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Mesajı okundu olarak işaretle
    if ($action === 'mark-read') {
        try {
            $stmt = $db->prepare("UPDATE contacts SET status = 'read' WHERE id = ?");
            $stmt->execute([$id]);
            $success = t('admin_message_marked_read');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_message_mark_error');
        }
    }
    
    // Mesajı okunmadı olarak işaretle
    if ($action === 'mark-unread') {
        try {
            $stmt = $db->prepare("UPDATE contacts SET status = 'unread' WHERE id = ?");
            $stmt->execute([$id]);
            $success = t('admin_message_marked_unread');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_message_mark_error');
        }
    }
    
    // Mesajı sil
    if ($action === 'delete') {
        try {
            $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([$id]);
            $success = t('admin_message_deleted');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_message_delete_error');
        }
    }
}

// Mesaja cevap verme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $contact_id = (int)$_POST['contact_id'];
    $reply_message = $_POST['reply_message'];
    $to_email = $_POST['to_email'];
    
    // Basit doğrulama
    if (empty($reply_message)) {
        $error = t('admin_reply_text_empty');
    } else {
        try {
            // Cevap veritabanına kaydediliyor
            $stmt = $db->prepare("UPDATE contacts SET reply = ?, reply_date = NOW(), status = 'replied' WHERE id = ?");
            $stmt->execute([$reply_message, $contact_id]);
            
            // E-posta gönderme işlemi
            $site_name = getSetting('site_title', 'Site Adı');
            $email_subject = "[$site_name] " . t('admin_reply_to_your_message');
            
            // E-posta şablonu oluştur
            $email_body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #f5f5f5; padding: 15px; border-bottom: 2px solid #ddd; }
                    .content { padding: 20px 0; }
                    .footer { font-size: 12px; color: #777; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>' . $site_name . ' - ' . t('admin_reply_to_your_message') . '</h2>
                    </div>
                    <div class="content">
                        <p>' . t('admin_email_greeting') . ',</p>
                        <p>' . t('admin_email_reply_intro') . ':</p>
                        <div style="background-color: #f9f9f9; border-left: 4px solid #ddd; padding: 15px; margin: 20px 0;">
                            ' . nl2br(htmlspecialchars($reply_message)) . '
                        </div>
                        <p>' . t('admin_email_reply_closing') . '</p>
                        <p>' . t('admin_email_regards') . ',<br>' . $site_name . ' Ekibi</p>
                    </div>
                    <div class="footer">
                        <p>' . t('admin_email_auto_reply_note') . '</p>
                    </div>
                </div>
            </body>
            </html>';
            
            // E-posta gönder
            $mail_sent = sendEmail($to_email, $email_subject, $email_body);
            
            if ($mail_sent) {
                $success = t('admin_reply_sent_success');
            } else {
                $success = t('admin_reply_sent_failure');
                error_log("E-posta gönderme hatası - Alıcı: $to_email, Konu: $email_subject");
            }
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_reply_send_error');
        }
    }
}

// Mesajları getir
if ($action === 'list') {
    // Sayfalama için gerekli parametreler
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10; 
    $offset = ($page - 1) * $per_page;
    
    // Toplam mesaj sayısını al
    $total_stmt = $db->query("SELECT COUNT(*) FROM contacts");
    $total_messages = $total_stmt->fetchColumn();
    $total_pages = ceil($total_messages / $per_page);
    
    // Mesajları sayfalı olarak getir
    $stmt = $db->prepare("
        SELECT * FROM contacts
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Okunmamış mesaj sayısını al
    $unread_stmt = $db->query("SELECT COUNT(*) FROM contacts WHERE status = 'unread'");
    $unread_count = $unread_stmt->fetchColumn();
}

// Mesaj detayı
if ($action === 'view' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        header('Location: messages.php');
        exit;
    }
    
    // Mesajı otomatik olarak okundu olarak işaretle
    if ($message['status'] === 'unread') {
        $stmt = $db->prepare("UPDATE contacts SET status = 'read' WHERE id = ?");
        $stmt->execute([$id]);
    }
}
?>

<div class="content-wrapper">
    <div class="max-w-full mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                <?php echo $action === 'view' ? t('admin_message_detail') : t('admin_message_management'); ?>
        </h1>
                        <?php if ($action === 'list'): ?>
                            <div class="flex items-center">
                                <?php if ($unread_count > 0): ?>
                                    <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-semibold mr-3">
                                        <?php echo $unread_count; ?> <?php echo t('admin_unread_messages'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            
            <main class="mx-auto px-8 py-8 w-full max-w-full">
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
                  <?php if ($action === 'view' && isset($message)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold dark:text-gray-200"><?php echo clean($message['subject']); ?></h2>                                <span class="px-3 py-1 rounded-full text-sm font-semibold 
                                    <?php echo $message['status'] === 'unread' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : 
                                            ($message['status'] === 'replied' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'); ?>">
                                    <?php echo $message['status'] === 'unread' ? t('admin_unread') : 
                                           ($message['status'] === 'replied' ? t('admin_replied') : t('admin_read')); ?>
                                </span>
                            </div>
                              <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <strong class="dark:text-gray-300"><?php echo t('admin_sender'); ?>:</strong> <?php echo clean($message['name']); ?> (<a href="mailto:<?php echo clean($message['email']); ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?php echo clean($message['email']); ?></a>)
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <strong class="dark:text-gray-300"><?php echo t('admin_date'); ?>:</strong> <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                                </p>
                            </div>
                            
                            <div class="mb-6 whitespace-pre-wrap text-gray-800 dark:text-gray-200"><?php echo clean($message['message']); ?></div>
                              <?php if ($message['reply']): ?>
                                <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <h3 class="text-lg font-semibold mb-2 dark:text-gray-200"><?php echo t('admin_your_reply'); ?></h3>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md whitespace-pre-wrap dark:text-gray-200">
                                        <?php echo clean($message['reply']); ?>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        <strong class="dark:text-gray-300"><?php echo t('admin_reply_date'); ?>:</strong> <?php echo date('d.m.Y H:i', strtotime($message['reply_date'])); ?>
                                    </p>
                                </div>                            <?php else: ?>
                                <form method="post" action="messages.php" class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <input type="hidden" name="contact_id" value="<?php echo $message['id']; ?>">
                                    <input type="hidden" name="to_email" value="<?php echo clean($message['email']); ?>">
                                    
                                    <div class="mb-4">
                                        <label for="reply_message" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"><?php echo t('admin_reply_text'); ?></label>
                                        <textarea id="reply_message" name="reply_message" rows="5" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required></textarea>
                                    </div>
                                      <div class="flex justify-end">
                                        <button type="submit" name="reply" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-reply mr-2"></i> <?php echo t('admin_send_reply'); ?>
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                              <div class="flex justify-between mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <a href="messages.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-arrow-left mr-2"></i> <?php echo t('admin_go_back'); ?>
                                    </a>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <?php if ($message['status'] !== 'unread'): ?>
                                        <a href="?action=mark-unread&id=<?php echo $message['id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-envelope mr-2"></i> <?php echo t('admin_mark_unread'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?action=delete&id=<?php echo $message['id']; ?>" onclick="return confirm('<?php echo t('admin_confirm_delete'); ?>')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-trash-alt mr-2"></i> <?php echo t('admin_delete_message'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>                <?php elseif ($action === 'list'): ?>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="overflow-x-auto">                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-700">
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                            <?php echo t('admin_status'); ?>
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                            <?php echo t('admin_sender'); ?>
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                            <?php echo t('admin_subject'); ?>
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                            <?php echo t('admin_date'); ?>
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                            <?php echo t('admin_actions'); ?>
                                        </th>
                                    </tr>                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($messages)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                <?php echo t('admin_no_messages_found'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>                                        <?php foreach ($messages as $message): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap">                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php echo $message['status'] === 'unread' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : 
                                                               ($message['status'] === 'replied' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'); ?>">
                                                        <?php echo $message['status'] === 'unread' ? t('admin_unread') : 
                                                               ($message['status'] === 'replied' ? t('admin_replied') : t('admin_read')); ?>
                                                    </span></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                        <?php echo clean($message['name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo clean($message['email']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs">
                                                        <?php echo clean($message['subject']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">                                                    <a href="?action=view&id=<?php echo $message['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($message['status'] !== 'unread'): ?>
                                                        <a href="?action=mark-unread&id=<?php echo $message['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                            <i class="fas fa-envelope"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?action=mark-read&id=<?php echo $message['id']; ?>" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300 mr-3">
                                                            <i class="fas fa-envelope-open"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?action=delete&id=<?php echo $message['id']; ?>" onclick="return confirm('<?php echo t('admin_confirm_delete'); ?>')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                          <!-- Sayfalama Kontrolleri -->
                        <?php if ($total_pages > 1): ?>
                        <div class="px-6 py-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-center items-center">
                                <ul class="flex space-x-2">
                                    <?php if($page > 1): ?>
                                    <li>                                        <a href="?page=<?php echo $page-1; ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <i class="fas fa-chevron-left"></i> <?php echo t('admin_previous_page'); ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Sayfa numaralarını göster
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    // İlk sayfa linkini göster
                                    if ($start_page > 1): ?>
                                        <li>                                            <a href="?page=1" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                1
                                            </a>
                                        </li>
                                        <?php if($start_page > 2): ?>
                                            <li class="px-3 py-2 text-gray-500">
                                                ...
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li>
                                            <a href="?page=<?php echo $i; ?>" class="px-3 py-2 border <?php echo $i == $page ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> rounded-md text-sm font-medium">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php 
                                    // Son sayfa linkini göster
                                    if ($end_page < $total_pages): ?>
                                        <?php if($end_page < $total_pages - 1): ?>
                                            <li class="px-3 py-2 text-gray-500">
                                                ...
                                            </li>
                                        <?php endif; ?>
                                        <li>                                            <a href="?page=<?php echo $total_pages; ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <?php echo $total_pages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if($page < $total_pages): ?>
                                    <li>                                        <a href="?page=<?php echo $page+1; ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <?php echo t('admin_next_page'); ?> <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                              <div class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                <?php echo t('admin_total_messages'); ?> <?php echo $total_messages; ?>, <?php echo t('admin_total_pages'); ?> <?php echo $total_pages; ?>                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

<?php include 'includes/footer.php'; ?>
