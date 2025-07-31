<?php
require_once 'includes/config.php';

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Toplam makale sayısını al
$count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE author_id = ?");
$count_stmt->execute([$user_id]);
$total_articles = $count_stmt->fetchColumn();
$total_pages = ceil($total_articles / $per_page);

// Kullanıcının makalelerini sayfalı şekilde al
$articles_stmt = $db->prepare("SELECT * FROM articles WHERE author_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$articles_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$articles_stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$articles_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$articles_stmt->execute();
$articles = $articles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Başlık ve sayfa bilgileri
$page_title = "Tüm Makalelerim";
require_once 'templates/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold text-gray-800">Tüm Makalelerim</h1>
            <a href="/makale_ekle" class="bg-blue-600 text-white rounded-md px-4 py-2 hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>Yeni Makale Ekle
            </a>
        </div>
        
        <div class="p-6">
            <?php if (count($articles) > 0): ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($articles as $article): ?>
                        <?php                        // Makale başlığından SEO dostu URL oluştur
                        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim(str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $article['title']))));
                        ?>                        <div class="py-4">
                            <a href="/makale/<?php echo $slug; ?>" class="block hover:bg-gray-50 rounded transition-colors">
                                <h2 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($article['title']); ?></h2>
                                <p class="mt-1 text-gray-600 line-clamp-2"><?php echo substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 200) . '...'; ?></p>
                                <div class="flex justify-between mt-2">
                                    <span class="text-sm text-gray-500">
                                        <i class="far fa-calendar-alt mr-1"></i><?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
                                    </span>
                                    <?php if (isset($article['view_count'])): ?>
                                    <span class="text-sm text-gray-500">
                                        <i class="far fa-eye mr-1"></i><?php echo $article['view_count']; ?> görüntülenme
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Sayfalama -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <div class="inline-flex rounded-md shadow">
                        <nav class="flex" aria-label="Sayfalama">
                            <!-- Önceki Sayfa -->
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo ($current_page - 1); ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">
                                    Önceki
                                </a>
                            <?php else: ?>
                                <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-l-md cursor-not-allowed">
                                    Önceki
                                </span>
                            <?php endif; ?>
                            
                            <!-- Sayfa Numaraları -->
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="?page=1" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                    1
                                </a>
                                <?php if ($start_page > 2): ?>
                                    <span class="px-2 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300">
                                        ...
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-600">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="px-2 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300">
                                        ...
                                    </span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>
                            
                            <!-- Sonraki Sayfa -->
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo ($current_page + 1); ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">
                                    Sonraki
                                </a>
                            <?php else: ?>
                                <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-r-md cursor-not-allowed">
                                    Sonraki
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-file-alt text-5xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Henüz hiç makale yazmadınız</h3>
                    <p class="text-gray-500 mb-4">İlk makalenizi yazarak içerik üretmeye başlayın.</p>
                    <a href="makale_ekle" class="inline-block bg-blue-600 text-white rounded-md px-4 py-2 font-medium hover:bg-blue-700 transition-colors">
                        İlk Makalenizi Yazın
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
