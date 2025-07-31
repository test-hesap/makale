<?php
require_once 'includes/config.php';

$slug = clean($_GET['slug'] ?? '');
$parent_slug = clean($_GET['parent_slug'] ?? '');

if (empty($slug)) {
    header('Location: /');
    exit;
}

// Eğer ana kategori parametresi varsa, alt kategori olduğunu anlıyoruz
if (!empty($parent_slug)) {
    // Önce ana kategoriyi kontrol et
    $parentStmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
    $parentStmt->execute([$parent_slug]);
    $parentCategory = $parentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parentCategory) {
        header('Location: /');
        exit;
    }
    
    // Şimdi alt kategoriyi getir
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND parent_id = ?");
    $stmt->execute([$slug, $parentCategory['id']]);
} else {
    // Ana kategoriyi getir
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND parent_id IS NULL");
    $stmt->execute([$slug]);
}

$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header('Location: /');
    exit;
}

// Bu kategorideki makaleleri getir
$stmt = $db->prepare("
    SELECT a.*, c.name as category_name, c.slug as category_slug, u.username,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
           IFNULL(a.view_count, 0) as view_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.category_id = ? AND a.status = 'published'
    ORDER BY a.created_at DESC
");
$stmt->execute([$category['id']]);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kategorileri getir - ana ve alt kategorileri hiyerarşik olarak
$categories = $db->query("
    SELECT c.*, parent.name as parent_name, COUNT(a.id) as article_count
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.id
    LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
    GROUP BY c.id, c.name, c.slug, c.parent_id, parent.name
    ORDER BY COALESCE(c.parent_id, c.id), c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Kategorileri ana ve alt kategori olarak düzenle
$categoriesHierarchy = [];
$subcategories = [];

foreach ($categories as $cat) {
    if (empty($cat['parent_id'])) {
        // Ana kategori
        $categoriesHierarchy[$cat['id']] = $cat;
        $categoriesHierarchy[$cat['id']]['subcategories'] = [];
    } else {
        // Alt kategori
        $subcategories[$cat['id']] = $cat;
    }
}

// Alt kategorileri ana kategorilere ekle
foreach ($subcategories as $subcategory) {
    if (isset($categoriesHierarchy[$subcategory['parent_id']])) {
        $categoriesHierarchy[$subcategory['parent_id']]['subcategories'][] = $subcategory;
    } else {
        // Eğer üst kategori yoksa, doğrudan ana kategoriler listesine ekle
        $categoriesHierarchy[$subcategory['id']] = $subcategory;
    }
}

// Popüler makaleleri getir (sidebar için)
$popular_articles = $db->query("
    SELECT a.*, a.slug, c.name as category_name, u.username,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
           IFNULL(a.view_count, 0) as view_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.views DESC, a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Site başlığını al
$site_title = getSetting('site_title');
$site_description = getSetting('site_description');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - <?php echo $site_title; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($category['name']); ?> kategorisindeki makaleler">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php
    // Favicon ekle
    $favicon = getSetting('favicon');
    if (!empty($favicon)) {
        echo '<link rel="icon" href="/' . $favicon . '">';
    }
    ?>
</head>
<body class="bg-gray-100">
    <?php include 'templates/header.php'; ?>

    <div class="container mx-auto px-4 py-0 mt-1">
        <!-- Boşluk eklendi -->
    </div>

    <?php echo showAd('header'); // Header altı reklam ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Ana İçerik - Makale Kartları -->
            <div class="w-full lg:w-2/3">                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </h1>
                    <p class="text-gray-600 mt-2">
                        <?php echo htmlspecialchars($category['description'] ?? ''); ?>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                    <?php foreach ($articles as $article): ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col h-full">
                        <?php if (!empty($article['featured_image'])): ?>
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
                             alt="<?php echo htmlspecialchars($article['title']); ?>" 
                             class="w-full h-48 object-cover">
                        <?php else: ?>
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <i class="fas fa-image text-gray-400 text-4xl"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="p-4 flex flex-col h-full">
                            <div class="flex-grow">
                                <div class="flex items-center text-sm text-gray-500 mb-2">
                                    <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded">
                                        <?php echo htmlspecialchars($article['category_name']); ?>
                                    </span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo date('d.m.Y', strtotime($article['created_at'])); ?></span>
                                </div>
                                
                                <h2 class="text-xl font-semibold mb-2">
                                    <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="text-gray-900 hover:text-blue-600">
                                        <?php echo htmlspecialchars($article['title']); ?>
                                    </a>
                                </h2>
                                
                                <p class="text-gray-600 mb-4 line-clamp-3">
                                    <?php echo htmlspecialchars(substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 150)) . '...'; ?>
                                </p>
                            </div>

                            <div class="mt-auto flex justify-end">
                                <a href="/makale/<?php echo urlencode($article['slug']); ?>" 
                                   class="inline-block bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200 transition duration-300">
                                    <?php echo __('read_more'); ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="px-4 pb-4 mt-2 border-t">                            <div class="flex items-center justify-between text-sm text-gray-500 pt-3">
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-2"></i>
                                    <?php echo htmlspecialchars($article['username']); ?>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <span class="flex items-center">
                                        <i class="fas fa-eye mr-2"></i>
                                        <?php echo number_format($article['view_count']); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-comment mr-2"></i>
                                        <?php echo $article['comment_count']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($articles)): ?>
                <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                    <p class="text-gray-600"><?php echo __('no_articles_in_category'); ?></p>
                </div>
                <?php endif; ?>
            </div>
              <!-- Sağ Sidebar -->
            <div class="w-full lg:w-1/3 space-y-6">
                <?php echo showAd('sidebar_top'); // Sidebar üst reklam ?>
                  
                <!-- Arama -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4 sidebar-heading"><?php echo __('search'); ?></h3>
                    <form action="/search.php" method="get" class="flex">
                        <input type="text" name="q" placeholder="<?php echo __('search_article_placeholder'); ?>" 
                               class="flex-1 px-4 py-2 border rounded-l focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 dark:focus:ring-blue-500 dark:placeholder-gray-400">
                        <button type="submit" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-r hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-300">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>                  <!-- Kategoriler -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="collapsible-sidebar-header" data-sidebar-id="categories">
                        <h3 class="text-lg font-semibold sidebar-heading"><?php echo __('categories'); ?></h3>
                        <i class="fas fa-chevron-down rotate-icon"></i>
                    </div>
                    <div class="collapsible-sidebar-content space-y-2 mt-4">
                        <?php foreach ($categoriesHierarchy as $mainCategory): 
                            // Ana kategori makale sayısı
                            $count = 0;
                            $count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                            $count_stmt->execute([$mainCategory['id']]);
                            $count = $count_stmt->fetchColumn();
                            
                            // Alt kategorilerdeki makale sayılarını da ekle
                            $subcategoryCount = 0;
                            if (!empty($mainCategory['subcategories'])) {
                                foreach ($mainCategory['subcategories'] as $sub) {
                                    $sub_count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                                    $sub_count_stmt->execute([$sub['id']]);
                                    $subcategoryCount += $sub_count_stmt->fetchColumn();
                                }
                            }
                            $totalCount = $count + $subcategoryCount;
                            
                            // Şu an aktif olan kategori mi kontrol et
                            $isActiveMain = $mainCategory['id'] === $category['id'];
                        ?>
                        <div>
                            <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>" 
                               class="flex items-center justify-between py-2 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo $isActiveMain ? 'bg-blue-100 dark:bg-gray-600' : ''; ?>">
                                <span class="font-medium <?php echo $isActiveMain ? 'text-blue-700 dark:text-gray-100' : ''; ?>"><?php echo htmlspecialchars($mainCategory['name']); ?></span>
                                <span class="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs font-semibold px-2.5 py-0.5 rounded inline-block min-w-[1.5rem] text-center">
                                    <?php echo $totalCount; ?>
                                </span>
                            </a>
                            
                            <?php if (!empty($mainCategory['subcategories'])): ?>
                            <div class="ml-4 mt-1">
                                <?php foreach ($mainCategory['subcategories'] as $subCategory): 
                                    $subCount = 0;
                                    $sub_count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                                    $sub_count_stmt->execute([$subCategory['id']]);
                                    $subCount = $sub_count_stmt->fetchColumn();
                                    
                                    // Şu an aktif olan alt kategori mi kontrol et
                                    $isActiveSub = $subCategory['id'] === $category['id'];
                                ?>
                                <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>/<?php echo urlencode($subCategory['slug']); ?>" 
                                   class="flex items-center justify-between py-1 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo $isActiveSub ? 'bg-blue-100 dark:bg-gray-600' : ''; ?>">
                                    <span class="text-sm <?php echo $isActiveSub ? 'text-blue-700 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400'; ?>">└─ <?php echo htmlspecialchars($subCategory['name']); ?></span>
                                    <span class="bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 text-xs px-2 py-0.5 rounded inline-block min-w-[1.5rem] text-center">
                                        <?php echo $subCount; ?>
                                    </span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Çevrimiçi Üyeler -->
                <?php include 'includes/online_users_sidebar.php'; ?>
                  <!-- Popüler Makaleler -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4 sidebar-heading"><?php echo __('popular_articles'); ?></h3>
                    <div class="space-y-4">
                        <?php foreach ($popular_articles as $article): ?>
                        <a href="/makale/<?php echo urlencode($article['slug']); ?>" 
                           class="flex items-start space-x-4 group">
                            <?php if (!empty($article['featured_image'])): ?>
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
                                 alt="<?php echo htmlspecialchars($article['title']); ?>" 
                                 class="w-20 h-20 object-cover rounded">
                            <?php else: ?>
                            <div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center">
                                <i class="fas fa-image text-gray-400 text-xl"></i>
                            </div>
                            <?php endif; ?>
                            <div class="flex-1">                                <h4 class="font-medium text-gray-900 group-hover:text-blue-600 line-clamp-2 sidebar-article-title">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </h4>
                                <div class="flex items-center text-sm text-gray-500 mt-1">
                                    <span><?php echo date('d.m.Y', strtotime($article['created_at'])); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo $article['comment_count']; ?> <?php echo __('comment_count'); ?></span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>                    </div>
                </div>
                
                <!-- Son Yorumlar -->
                <?php include 'includes/recent_comments_sidebar.php'; ?>
                
                <!-- İstatistikler -->
                <?php include 'includes/stats_sidebar.php'; ?>
                
                <?php echo showAd('sidebar_bottom'); // Sidebar alt reklam ?>
            </div>
        </div>
    </div>    <?php require_once 'templates/footer.php'; ?>
