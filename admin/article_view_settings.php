<?php
require_once '../includes/config.php';
checkAuth(true); // Admin kontrolü

// Ayarları kaydet
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    // Form verilerini al
    $show_recent_articles = isset($_POST['show_recent_articles']) ? 1 : 0;
    $recent_articles_count = (int)$_POST['recent_articles_count'];
    $recent_articles_title = trim($_POST['recent_articles_title']);
    
    $show_popular_articles = isset($_POST['show_popular_articles']) ? 1 : 0;
    $popular_articles_count = (int)$_POST['popular_articles_count'];
    $popular_articles_title = trim($_POST['popular_articles_title']);
    
    $show_featured_articles = isset($_POST['show_featured_articles']) ? 1 : 0;
    $featured_articles_count = (int)$_POST['featured_articles_count'];
    $featured_articles_title = trim($_POST['featured_articles_title']);
    
    // Ayarları veritabanına kaydet
    $settings = [
        'show_recent_articles' => $show_recent_articles,
        'recent_articles_count' => $recent_articles_count,
        'recent_articles_title' => $recent_articles_title,
        
        'show_popular_articles' => $show_popular_articles,
        'popular_articles_count' => $popular_articles_count,
        'popular_articles_title' => $popular_articles_title,
        
        'show_featured_articles' => $show_featured_articles,
        'featured_articles_count' => $featured_articles_count,
        'featured_articles_title' => $featured_articles_title,
    ];
    
    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    
    // Öne çıkan makaleleri kaydet
    if ($show_featured_articles && isset($_POST['featured_articles'])) {
        // Önce tablonun varlığını kontrol et
        $tableCheck = $db->query("SHOW TABLES LIKE 'article_featured'");
        if ($tableCheck && $tableCheck->rowCount() == 0) {
            // Tablo yoksa oluştur
            $db->query("
            CREATE TABLE IF NOT EXISTS article_featured (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                position INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");
        }
        
        // Önce mevcut tüm öne çıkan makaleleri temizle
        $db->query("DELETE FROM article_featured");
        
        $featured_articles = $_POST['featured_articles'];
        if (!empty($featured_articles)) {
            $position = 1;
            foreach ($featured_articles as $article_id) {
                $stmt = $db->prepare("INSERT INTO article_featured (article_id, position, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$article_id, $position]);
                $position++;
            }
        }
    }
    
    // Başarılı mesajı
    $_SESSION['success_message'] = t('admin_article_view_settings_saved');
    header("Location: article_view_settings.php");
    exit;
}

// Mevcut ayarları al
$show_recent_articles = getSetting('show_recent_articles');
$recent_articles_count = (int)getSetting('recent_articles_count') ?: 4;
$recent_articles_title = getSetting('recent_articles_title') ?: t('admin_recent_articles_default_title');

$show_popular_articles = getSetting('show_popular_articles');
$popular_articles_count = (int)getSetting('popular_articles_count') ?: 4;
$popular_articles_title = getSetting('popular_articles_title') ?: t('admin_popular_articles_default_title');

$show_featured_articles = getSetting('show_featured_articles');
$featured_articles_count = (int)getSetting('featured_articles_count') ?: 4;
$featured_articles_title = getSetting('featured_articles_title') ?: t('admin_featured_articles_default_title');

// Tüm makaleleri al - son eklenenler için
$recent_articles = $db->query("
    SELECT a.id, a.title, a.slug, a.created_at, a.view_count, c.name as category_name, u.username as author_name
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Popüler makaleleri al - görüntülenme sayısına göre
$popular_articles = $db->query("
    SELECT a.id, a.title, a.slug, a.created_at, a.view_count, c.name as category_name, u.username as author_name
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.view_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Tüm makaleleri al (varsayılan liste için)
$articles = $recent_articles;

// Öne çıkan makaleler tablosunun varlığını kontrol et
$tableExists = false;
$checkTable = $db->query("SHOW TABLES LIKE 'article_featured'");
if ($checkTable && $checkTable->rowCount() > 0) {
    $tableExists = true;
} else {
    // Tablo mevcut değilse oluştur
    $db->query("
    CREATE TABLE IF NOT EXISTS article_featured (
        id INT AUTO_INCREMENT PRIMARY KEY,
        article_id INT NOT NULL,
        position INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    
    // Tablo oluşturulduktan sonra varlığını tekrar kontrol et
    $checkTableAgain = $db->query("SHOW TABLES LIKE 'article_featured'");
    if ($checkTableAgain && $checkTableAgain->rowCount() > 0) {
        $tableExists = true;
    }
}

// Şu anda öne çıkan makaleleri al
$featured_article_ids = [];
$featured_articles = [];

if ($tableExists) {
    $featuredQuery = $db->query("
        SELECT af.article_id, a.title, a.slug, c.name as category_name
        FROM article_featured af
        LEFT JOIN articles a ON af.article_id = a.id
        LEFT JOIN categories c ON a.category_id = c.id
        ORDER BY af.position ASC
    ");
    
    if ($featuredQuery) {
        $featured_articles = $featuredQuery->fetchAll(PDO::FETCH_ASSOC);
        foreach ($featured_articles as $article) {
            $featured_article_ids[] = $article['article_id'];
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-full mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white"><?php echo t('admin_article_view_settings'); ?></h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success_message']; ?></p>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <form action="" method="post">
            <!-- Son Eklenen Makaleler Ayarları -->
            <div class="mb-8 border-b dark:border-gray-700 pb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-800 dark:text-gray-200"><?php echo t('admin_recent_articles_settings'); ?></h3>
                
                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="show_recent_articles" name="show_recent_articles" value="1" class="mr-2 h-5 w-5" <?php echo $show_recent_articles ? 'checked' : ''; ?>>
                    <label for="show_recent_articles" class="text-gray-700 dark:text-gray-300"><?php echo t('admin_show_recent_articles'); ?></label>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="recent_articles_title" class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_title'); ?></label>
                        <input type="text" id="recent_articles_title" name="recent_articles_title" value="<?php echo htmlspecialchars($recent_articles_title); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                    </div>
                    
                    <div>
                        <label for="recent_articles_count" class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_number_of_articles_to_display'); ?></label>
                        <input type="number" id="recent_articles_count" name="recent_articles_count" value="<?php echo $recent_articles_count; ?>" min="1" max="12" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                    </div>
                </div>
            </div>
            
            <!-- Popüler Makaleler Ayarları -->
            <div class="mb-8 border-b dark:border-gray-700 pb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-800 dark:text-gray-200"><?php echo t('admin_popular_articles_settings'); ?></h3>
                
                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="show_popular_articles" name="show_popular_articles" value="1" class="mr-2 h-5 w-5" <?php echo $show_popular_articles ? 'checked' : ''; ?>>
                    <label for="show_popular_articles" class="text-gray-700 dark:text-gray-300"><?php echo t('admin_show_popular_articles'); ?></label>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="popular_articles_title" class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_title'); ?></label>
                        <input type="text" id="popular_articles_title" name="popular_articles_title" value="<?php echo htmlspecialchars($popular_articles_title); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                    </div>
                    
                    <div>
                        <label for="popular_articles_count" class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_number_of_articles_to_display'); ?></label>
                        <input type="number" id="popular_articles_count" name="popular_articles_count" value="<?php echo $popular_articles_count; ?>" min="1" max="12" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                    </div>
                </div>
            </div>
            
            <!-- Öne Çıkan Makaleler Ayarları -->
            <div class="mb-8 pb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-800 dark:text-gray-200"><?php echo t('admin_featured_articles_settings'); ?></h3>
                
                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="show_featured_articles" name="show_featured_articles" value="1" class="mr-2 h-5 w-5" <?php echo $show_featured_articles ? 'checked' : ''; ?>>
                    <label for="show_featured_articles" class="text-gray-700 dark:text-gray-300"><?php echo t('admin_show_featured_articles'); ?></label>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="featured_articles_title" class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_title'); ?></label>
                        <input type="text" id="featured_articles_title" name="featured_articles_title" value="<?php echo htmlspecialchars($featured_articles_title); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                    </div>
                    
                    <div>
                        <label for="featured_articles_count" class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_number_of_articles_to_display'); ?></label>
                        <input type="number" id="featured_articles_count" name="featured_articles_count" value="<?php echo $featured_articles_count; ?>" min="1" max="12" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_select_featured_articles'); ?></label>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo t('admin_select'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo t('admin_title'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo t('admin_category'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo t('admin_author'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo t('admin_views'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo t('admin_date'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                <?php 
                                // Öne çıkan makaleler için tüm makaleleri göster
                                $display_articles = $articles;
                                
                                foreach ($display_articles as $article): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="featured_articles[]" value="<?php echo $article['id']; ?>" <?php echo in_array($article['id'], $featured_article_ids) ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($article['title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($article['category_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($article['author_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo number_format($article['view_count']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo date('d.m.Y', strtotime($article['created_at'])); ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="save_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
                    <?php echo t('admin_save_settings'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
