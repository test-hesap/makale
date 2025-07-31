<?php
// Son yorumları göstermek için kenar çubuğu bileşeni

// Son 5 yorumu getir
try {
    $recent_comments_stmt = $db->prepare("
        SELECT c.*, c.id as comment_id, a.title as article_title, a.slug as article_slug, u.username 
        FROM comments c
        LEFT JOIN articles a ON c.article_id = a.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.status = 'approved'
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $recent_comments_stmt->execute();
    $recent_comments = $recent_comments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_comments = [];
    error_log("Son yorumlar getirilirken hata: " . $e->getMessage());
}
?>

<!-- Son Yorumlar Kutusu -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6">
    <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-white">
        <i class="fas fa-comments text-blue-500 mr-2"></i><?php echo __('sidebar_recent_comments'); ?>
    </h3>
    
    <div class="recent-comments-list space-y-3">
        <?php if (count($recent_comments) > 0): ?>
            <?php foreach ($recent_comments as $comment): ?>
                <div class="comment-item border-b border-gray-100 dark:border-gray-700 pb-3 last:border-0 last:pb-0">
                    <div class="flex items-center mb-1">
                        <span class="font-medium text-sm text-blue-600 dark:text-blue-400">
                            <?php echo htmlspecialchars($comment['username'] ?? 'Anonim'); ?>
                        </span>
                        <span class="mx-2 text-gray-400 text-xs">&bull;</span>
                        <span class="text-gray-500 text-xs">
                            <?php 
                            $date = new DateTime($comment['created_at']);
                            echo $date->format('d.m.Y H:i'); 
                            ?>
                        </span>
                    </div>
                    <a href="/makale/<?php echo urlencode($comment['article_slug']); ?>#comment-<?php echo $comment['comment_id']; ?>" 
                       class="block">
                        <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                            <?php echo htmlspecialchars(mb_substr(strip_tags($comment['content']), 0, 100)) . (mb_strlen($comment['content']) > 100 ? '...' : ''); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1 italic">
                            &ldquo;<?php echo htmlspecialchars($comment['article_title']); ?>&rdquo;
                        </p>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-500 text-sm">Henüz yorum yapılmamış.</p>
        <?php endif; ?>
    </div>
</div>
