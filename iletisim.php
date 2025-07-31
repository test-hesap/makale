<?php
require_once 'includes/config.php';
require_once 'includes/turnstile.php'; // Turnstile fonksiyonlarını dahil et

// Sayfa için canonical URL
$canonical_url = getSetting('site_url') . "/iletisim";

$error = '';
$success = '';

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');
    
    // Basit doğrulama
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = __('contact_fields_error');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('contact_email_error');
    } else {
        // Turnstile kontrolü
        $turnstileEnabled = isTurnstileEnabled('contact');
        if ($turnstileEnabled) {
            $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
            if (!verifyTurnstile($turnstileToken)) {
                $error = __('contact_captcha_error');
            }
        }
        
        // Eğer Turnstile hatası yoksa devam et
        if (empty($error)) {
            try {
                // Mesajı veritabanına kaydet
                $stmt = $db->prepare("INSERT INTO contacts (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
                $stmt->execute([$name, $email, $subject, $message]);
                $success = __('contact_success');
            } catch(PDOException $e) {
                $error = __('contact_error') . $e->getMessage();
            }
        }
    }
}

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

foreach ($categories as $category) {
    if (empty($category['parent_id'])) {
        // Ana kategori
        $categoriesHierarchy[$category['id']] = $category;
        $categoriesHierarchy[$category['id']]['subcategories'] = [];
    } else {
        // Alt kategori
        $subcategories[$category['id']] = $category;
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

// Popüler makaleleri getir
$popular_articles = $db->query("
    SELECT a.*, a.slug, c.name as category_name, u.username,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
           IFNULL(a.view_count, 0) as view_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.view_count DESC, a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'templates/header.php'; ?>

<div class="container mx-auto px-4 py-0 mt-1">
    <!-- Boşluk eklendi -->
</div>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Ana İçerik -->
        <div class="w-full lg:w-2/3">            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="px-6 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><?php echo __('contact_title'); ?></h1>
                    
                    <?php if($error): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo $error; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p><?php echo $success; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4"><?php echo __('contact_intro'); ?></h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-6"><?php echo __('contact_description'); ?></p>
                            
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center">                                <div class="bg-blue-100 dark:bg-gray-800 rounded-full p-2 mr-3">
                                        <i class="fas fa-envelope text-blue-600 dark:text-gray-300"></i>
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300"><?php echo getSetting('contact_email') ?: 'bilgi@herbilgi.net'; ?></span>
                                </div>
                                
                                <!-- Telefon ve adres bilgileri kaldırıldı -->
                            </div>
                            
                            <!-- Sosyal Medya Linkleri -->
                            <div class="mt-6">
                                <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-300"><?php echo __('contact_follow'); ?></h3>
                                <div class="flex space-x-4">
                                    <?php if ($social_facebook = getSetting('social_facebook')): ?>                    <a href="<?php echo $social_facebook; ?>" target="_blank" class="bg-gray-100 dark:bg-gray-800 hover:bg-blue-500 hover:text-white text-gray-600 dark:text-gray-400 p-3 rounded-full transition duration-300">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($social_twitter = getSetting('social_twitter')): ?>                                    <a href="<?php echo $social_twitter; ?>" target="_blank" class="bg-gray-100 dark:bg-gray-800 hover:bg-blue-400 hover:text-white text-gray-600 dark:text-gray-400 p-3 rounded-full transition duration-300">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($social_instagram = getSetting('social_instagram')): ?>                                    <a href="<?php echo $social_instagram; ?>" target="_blank" class="bg-gray-100 dark:bg-gray-800 hover:bg-pink-600 hover:text-white text-gray-600 dark:text-gray-400 p-3 rounded-full transition duration-300">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($social_linkedin = getSetting('social_linkedin')): ?>                                    <a href="<?php echo $social_linkedin; ?>" target="_blank" class="bg-gray-100 dark:bg-gray-800 hover:bg-blue-700 hover:text-white text-gray-600 dark:text-gray-400 p-3 rounded-full transition duration-300">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>                        <div>
                            <form action="<?php echo __('link_contact'); ?>" method="POST" class="bg-gray-50 dark:bg-[#292929] p-6 rounded-lg shadow-sm">
                                <div class="mb-4">
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('contact_name'); ?></label>
                                    <input type="text" name="name" id="name" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-gray-700 dark:bg-[#1e1e1e] dark:text-gray-200" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('contact_email'); ?></label>
                                    <input type="email" name="email" id="email" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-gray-700 dark:bg-[#1e1e1e] dark:text-gray-200" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('contact_subject'); ?></label>
                                    <input type="text" name="subject" id="subject" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-gray-700 dark:bg-[#1e1e1e] dark:text-gray-200" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo __('contact_message'); ?></label>
                                    <textarea id="message" name="message" rows="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-gray-700 dark:bg-[#1e1e1e] dark:text-gray-200" required></textarea>
                                </div>
                                
                                <?php if (isTurnstileEnabled('contact')): ?>
                                <div class="mb-4">
                                    <?php echo turnstileWidget(); ?>
                                </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-[#1e1e1e] dark:hover:bg-gray-800 text-white py-2 px-4 rounded-md transition duration-300 flex items-center justify-center">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    <?php echo __('contact_send'); ?>
                                </button>
                            </form>
                        </div></div>
                </div>
            </div>
        </div>
          <!-- Sağ Sidebar -->
        <div class="w-full lg:w-1/3 space-y-6">
            <?php echo showAd('sidebar'); // Sidebar reklamı ?>
            
            <!-- Arama -->
            <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold mb-4 sidebar-heading dark:text-gray-200"><?php echo __('sidebar_search'); ?></h3>
                <form action="/search.php" method="get" class="flex">
                    <input type="text" name="q" placeholder="<?php echo __('sidebar_search_placeholder'); ?>" 
                           class="flex-1 px-4 py-2 border rounded-l focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 dark:focus:ring-blue-500 dark:placeholder-gray-400">
                    <button type="submit" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-r hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-300">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- Kategoriler -->
            <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg p-6">
                <div class="collapsible-sidebar-header" data-sidebar-id="categories">
                    <h3 class="text-lg font-semibold dark:text-gray-200"><?php echo __('sidebar_categories'); ?></h3>
                    <i class="fas fa-chevron-down rotate-icon dark:text-gray-200"></i>
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
                    ?>
                    <div>
                        <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>" 
                           class="flex items-center justify-between py-2 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                            <span class="font-medium dark:text-gray-200"><?php echo htmlspecialchars($mainCategory['name']); ?></span>
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
                            ?>
                            <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>/<?php echo urlencode($subCategory['slug']); ?>" 
                               class="flex items-center justify-between py-1 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                <span class="text-sm text-gray-600 dark:text-gray-400">└─ <?php echo htmlspecialchars($subCategory['name']); ?></span>
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
            <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold mb-4 dark:text-gray-200"><?php echo __('sidebar_popular_articles'); ?></h3>
                <div class="space-y-4">
                    <?php foreach ($popular_articles as $article): ?>
                    <a href="/makale/<?php echo urlencode($article['slug']); ?>" 
                       class="flex items-start space-x-4 group">
                        <?php if (!empty($article['featured_image'])): ?>
                        <?php
                            $imgSrc = (strpos($article['featured_image'], 'http://') === 0 || strpos($article['featured_image'], 'https://') === 0)
                                ? $article['featured_image']
                                : (strpos($article['featured_image'], '/') === 0
                                    ? $article['featured_image']
                                    : "/uploads/ai_images/" . $article['featured_image']);
                        ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                             alt="<?php echo htmlspecialchars($article['title']); ?>" 
                             class="w-20 h-20 object-cover rounded">
                        <?php else: ?>
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                            <i class="fas fa-image text-gray-400 dark:text-gray-500 text-xl"></i>
                        </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-gray-200 group-hover:text-blue-600 line-clamp-2">
                                <?php echo htmlspecialchars($article['title']); ?>
                            </h4>
                            <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <span><?php echo date('d.m.Y', strtotime($article['created_at'])); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo $article['comment_count']; ?> <?php echo __('comment_count'); ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>                </div>
            </div>
            
            <!-- Son Yorumlar -->
            <?php include 'includes/recent_comments_sidebar.php'; ?>
            
            <!-- İstatistikler -->
            <?php include 'includes/stats_sidebar.php'; ?>

            <?php echo showAd('sidebar_bottom'); // Sidebar altı reklam ?></div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
