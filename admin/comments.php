<?php
require_once '../includes/config.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Yorum silme
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $success = t('admin_comment_deleted_success');
    } catch(PDOException $e) {
        $error = t('admin_comment_deleted_error');
    }
}

// Yorum onaylama/reddetme
if ($action === 'status' && isset($_GET['id']) && isset($_GET['status'])) {
    $status = $_GET['status'] === 'approve' ? 'approved' : 'pending';
    try {
        $stmt = $db->prepare("UPDATE comments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $_GET['id']]);
        $success = t('admin_comment_status_updated');
    } catch(PDOException $e) {
        $error = t('admin_comment_status_update_error');
    }
}

// Yorumları getir
$stmt = $db->query("
    SELECT c.*, a.title as article_title, u.username
    FROM comments c
    JOIN articles a ON c.article_id = a.id
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
");
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'includes/header.php'; ?>

<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo t('admin_comments'); ?>
        </h1>
    </div>

            <main class="mx-auto px-4 py-8">
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>                </div>
                <?php endif; ?>                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden overflow-x-auto overflow-y-hidden relative">
                    <div class="scrollbar-indicator absolute bottom-0 left-0 right-0 h-1">
                        <div class="bg-gray-400 dark:bg-gray-600 h-full opacity-0 transition-opacity duration-300 rounded"></div>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-fixed md:table-auto">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_article'); ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_comment'); ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_user'); ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_date'); ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_status'); ?></th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($comments as $comment): ?>                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo mb_substr($comment['article_title'], 0, 30) . '...'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">
                                        <div class="comment-preview comment-preview-<?php echo $comment['id']; ?>">
                                            <?php echo mb_substr(html_entity_decode($comment['content']), 0, 50) . (mb_strlen($comment['content']) > 50 ? '...' : ''); ?>
                                            
                                            <?php if(mb_strlen($comment['content']) > 50): ?>
                                                <button type="button" 
                                                        class="text-blue-600 hover:text-blue-800 text-xs mt-1 show-full-comment"
                                                        onclick="toggleComment('<?php echo $comment['id']; ?>')">
                                                    <?php echo t('admin_view_full_comment'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if(mb_strlen($comment['content']) > 50): ?>
                                        <div class="comment-full comment-full-<?php echo $comment['id']; ?> hidden">
                                            <div class="whitespace-normal bg-gray-50 dark:bg-gray-700 p-3 rounded my-1">
                                                <?php echo nl2br(html_entity_decode($comment['content'])); ?>
                                            </div>
                                            <button type="button" 
                                                    class="text-gray-600 hover:text-gray-800 text-xs mt-1"
                                                    onclick="toggleComment('<?php echo $comment['id']; ?>')">
                                                <?php echo t('admin_hide_full_comment'); ?>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-200">
                                        <?php echo $comment['username']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                    </div>
                                </td>                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $comment['status'] === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200'; ?>">
                                        <?php echo $comment['status'] === 'approved' ? t('admin_approved') : t('admin_pending'); ?>
                                    </span>
                                </td>                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <?php if ($comment['status'] === 'pending'): ?>
                                    <a href="?action=status&id=<?php echo $comment['id']; ?>&status=approve" 
                                       class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-3"><?php echo t('admin_approve'); ?></a>
                                    <?php else: ?>
                                    <a href="?action=status&id=<?php echo $comment['id']; ?>&status=pending" 
                                       class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 mr-3"><?php echo t('admin_pending_status'); ?></a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $comment['id']; ?>" 
                                       onclick="return confirm('<?php echo t('admin_confirm_delete_comment'); ?>')"
                                       class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"><?php echo t('admin_delete'); ?></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>                </div>
</div>
<script>
function toggleComment(id) {
    const preview = document.querySelector('.comment-preview-' + id);
    const full = document.querySelector('.comment-full-' + id);
    
    if (full.classList.contains('hidden')) {
        // Tam yorumu göster
        preview.classList.add('hidden');
        full.classList.remove('hidden');
    } else {
        // Önizlemeyi göster
        preview.classList.remove('hidden');
        full.classList.add('hidden');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
