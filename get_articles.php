<?php
require_once 'includes/config.php';

// Sayfa ve limit parametrelerini al
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (int)getSetting('posts_per_page');

if ($limit <= 0) $limit = 6; // Varsayılan değer
if ($page <= 0) $page = 1;

$offset = ($page - 1) * $limit;

// Makaleleri getir
$stmt = $db->prepare("
    SELECT a.*, a.slug, c.name as category_name, u.username,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindParam(1, $limit, PDO::PARAM_INT);
$stmt->bindParam(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam makale sayısını al
$total = $db->query("
    SELECT COUNT(*) as count FROM articles 
    WHERE status = 'published'
")->fetch(PDO::FETCH_ASSOC)['count'];

$hasMore = ($offset + $limit) < $total;

// JSON yanıtı hazırla
$response = [
    'articles' => [],
    'hasMore' => $hasMore,
    'currentPage' => $page,
    'totalPages' => ceil($total / $limit)
];

// Makaleleri HTML olarak formatla
ob_start();
foreach ($articles as $index => $article): 
    // Her 3 makalede bir reklam göster
    if ($index > 0 && function_exists('showArticleAd') && showArticleAd($index)) {
        continue;
    }
?>
<div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col h-full">
    <!-- Öne Çıkan Görsel -->
    <div class="relative h-48">
        <?php
        // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
        $imgSrc = !empty($article['featured_image']) ? 
            ((strpos($article['featured_image'], 'http://') === 0 || strpos($article['featured_image'], 'https://') === 0) 
                ? $article['featured_image'] 
                : (strpos($article['featured_image'], '/') === 0 
                    ? $article['featured_image'] 
                    : "/uploads/ai_images/" . $article['featured_image'])) 
            : '/assets/img/default-article.jpg';
        ?>
        <img src="<?php echo $imgSrc; ?>" 
             class="w-full h-full object-cover" alt="Makale Görseli">
        <div class="absolute bottom-0 left-0 bg-blue-500 text-white py-1 px-3 rounded-tr">
            <?php echo htmlspecialchars($article['category_name']); ?>
        </div>
        <?php if (isset($article['is_premium']) && $article['is_premium'] == 1): ?>
        <div class="absolute top-0 right-0 bg-purple-500 text-white py-1 px-3 rounded-bl flex items-center">
            <i class="fas fa-crown mr-1"></i> Premium
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Makale Bilgileri -->
    <div class="p-4 flex-grow">        <h3 class="text-lg font-semibold mb-2">
            <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="hover:text-blue-500">
                <?php echo safeTitle($article['title']); ?>
            </a>
        </h3>
        <p class="text-gray-600 text-sm mb-3"><?php echo formatTurkishDate($article['created_at']); ?></p>
        <p class="text-gray-700 mb-4">
            <?php echo mb_substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 100, 'UTF-8') . '...'; ?>
        </p>        <div class="mt-4">
            <a href="/makale/<?php echo urlencode($article['slug']); ?>" 
               class="inline-block bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200 transition duration-300">
                Devamını Oku
            </a>
        </div>
    </div>
    
    <div class="px-4 pb-4 mt-2 border-t">
        <div class="flex items-center justify-between text-sm text-gray-500 pt-3">
            <div class="flex items-center">
                <i class="fas fa-user mr-2"></i>
                <?php echo htmlspecialchars($article['username']); ?>
            </div>
            <div class="flex items-center">
                <i class="fas fa-comment mr-2"></i>
                <?php echo $article['comment_count']; ?> yorum
            </div>
        </div>
    </div>
</div>
<?php endforeach;

$html = ob_get_clean();
$response['articles'] = $html;

header('Content-Type: application/json');
echo json_encode($response);
?>
