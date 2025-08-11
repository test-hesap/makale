<?php
require_once 'includes/config.php';
require_once 'includes/session_init.php';

// Etiket adını URL'den al
$tag = clean($_GET['tag'] ?? '');

if (empty($tag)) {
    header('Location: /');
    exit;
}

// Sayfalama için parametreler
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // Her sayfada 12 makale
$offset = ($page - 1) * $per_page;

// Etiketle ilgili makaleleri say
$count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE FIND_IN_SET(:tag, tags) > 0 AND status = 'published'");
$count_stmt->bindValue(':tag', $tag);
$count_stmt->execute();
$total_articles = $count_stmt->fetchColumn();
$total_pages = ceil($total_articles / $per_page);

// Etiketle ilgili makaleleri al
$stmt = $db->prepare("
    SELECT a.*, c.name as category_name, u.username
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE FIND_IN_SET(:tag, a.tags) > 0 AND a.status = 'published'
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':tag', $tag);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr" class="dark-mode-transition">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tag); ?> - <?php echo getSetting('site_title'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($tag); ?> etiketli makaleler - <?php echo getSetting('site_description'); ?>">
    
    <!-- Tema ayarlarını sayfa yüklenmeden önce kontrol et ve uygula -->
    <script>
        // Mevcut tema tercihini kontrol et ve uygula
        if (localStorage.getItem('theme') === 'dark' || 
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        dark: {
                            bg: '#121212',
                            card: '#1e1e1e',
                            surface: '#2a2a2a',
                            text: '#e0e0e0',
                            border: '#3a3a3a',
                            accent: '#64b5f6'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Dark mode geçiş efekti */
        .dark-mode-transition {
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .dark-mode-transition * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark-bg dark-mode-transition">
    <?php include 'templates/header.php'; ?>

 <div class="container mx-auto px-4 py-0 mt-1">
    <!-- Boşluk eklendi -->
</div>
   
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Ana İçerik - Makale Listesi -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-tag text-blue-600 dark:text-blue-400 text-xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                            "<?php echo htmlspecialchars($tag); ?>" <?php echo __('tag_results'); ?>
                        </h1>
                    </div>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <?php echo sprintf(__('tag_article_count'), $total_articles); ?>
                    </div>
                </div>
                
                <?php if (count($articles) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($articles as $article): ?>
                            <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg overflow-hidden h-full flex flex-col">
                                <?php if (!empty($article['featured_image'])): ?>
                                    <a href="/makale/<?php echo $article['slug']; ?>" class="block h-48 overflow-hidden">
                                        <?php
                                        // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                                        $imgSrc = (strpos($article['featured_image'], 'http://') === 0 || strpos($article['featured_image'], 'https://') === 0)
                                            ? $article['featured_image'] 
                                            : (strpos($article['featured_image'], '/') === 0 
                                                ? $article['featured_image'] 
                                                : "/uploads/ai_images/" . $article['featured_image']);
                                        ?>
                                        <img src="<?php echo $imgSrc; ?>" 
                                            alt="<?php echo safeTitle($article['title']); ?>"
                                            class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                                    </a>
                                <?php endif; ?>
                                
                                <div class="p-5 flex-grow flex flex-col">
                                    <h2 class="text-xl font-bold mb-3">
                                        <a href="/makale/<?php echo $article['slug']; ?>" class="text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                            <?php echo $article['title']; ?>
                                        </a>
                                    </h2>
                                    
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 flex-grow">
                                        <?php 
                                        // Makale içeriğini kısalt
                                        $content = strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8'));
                                        echo mb_substr($content, 0, 120) . '...'; 
                                        ?>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                            <span>
                                                <i class="fas fa-user mr-1"></i>
                                                <?php echo $article['username']; ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Sayfalama -->
                    <?php if ($total_pages > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <div class="flex">
                            <?php if ($page > 1): ?>
                                <a href="/etiket/<?php echo urlencode($tag); ?>?page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-l hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1 bg-blue-600 text-white font-semibold">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="/etiket/<?php echo urlencode($tag); ?>?page=<?php echo $i; ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="/etiket/<?php echo urlencode($tag); ?>?page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-r hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-8 text-center">
                        <i class="fas fa-search text-gray-400 text-5xl mb-4"></i>
                        <p class="text-xl text-gray-600 dark:text-gray-400"><?php echo __('no_results_found'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sağ Sidebar -->
            <div class="w-full lg:w-1/3 space-y-6">
                <?php echo showAd('sidebar_top'); // Sidebar üst reklam ?>
                
                <!-- Arama -->
                <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-100">
                        <i class="fas fa-search mr-2"></i> <?php echo __('search'); ?>
                    </h2>
                    <form action="/search.php" method="get" class="relative">
                        <input type="text" name="q" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 pr-10" 
                               placeholder="<?php echo __('search_placeholder'); ?>" required>
                        <button type="submit" class="absolute right-0 top-0 mt-3 mr-3 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <?php 
                // Popüler etiketleri getir
                $tag_stmt = $db->query("
                    SELECT tags, COUNT(*) as tag_count
                    FROM articles 
                    WHERE status = 'published' AND tags IS NOT NULL AND tags != ''
                    GROUP BY tags 
                    ORDER BY tag_count DESC 
                    LIMIT 20
                ");
                $popular_tags = $tag_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Etiketleri işle ve birleştir
                $tag_array = [];
                foreach ($popular_tags as $tag_item) {
                    $tags = explode(',', $tag_item['tags']);
                    foreach ($tags as $t) {
                        $t = trim($t);
                        if (!empty($t)) {
                            if (isset($tag_array[$t])) {
                                $tag_array[$t] += $tag_item['tag_count'];
                            } else {
                                $tag_array[$t] = $tag_item['tag_count'];
                            }
                        }
                    }
                }
                
                // En çok kullanılan etiketleri al
                arsort($tag_array);
                $tag_array = array_slice($tag_array, 0, 20, true);
                
                // Etiket bulutu göster
                if (count($tag_array) > 0):
                ?>
                <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-100">
                        <i class="fas fa-tags mr-2"></i> <?php echo __('popular_tags'); ?>
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tag_array as $t => $count): ?>
                            <a href="/etiket/<?php echo urlencode($t); ?>" class="inline-block bg-gray-100 hover:bg-blue-100 dark:bg-gray-700 dark:hover:bg-blue-900 text-gray-700 dark:text-gray-300 hover:text-blue-700 dark:hover:text-blue-300 rounded-full px-3 py-1 text-sm transition-colors duration-200">
                                <?php echo htmlspecialchars($t); ?> 
                                <span class="text-xs text-gray-500 dark:text-gray-400">(<?php echo $count; ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php echo showAd('sidebar_bottom'); // Sidebar alt reklam ?>
            </div>
        </div>
    </div>

    <script>
    // Tema Değiştirme Fonksiyonu
    function toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            // Karanlık temadan aydınlık temaya geçiş
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            
            // İkonları güncelle (varsa)
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.remove('hidden');
            }
        } else {
            // Aydınlık temadan karanlık temaya geçiş
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            
            // İkonları güncelle (varsa)
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.remove('hidden');
            }
        }
    }

    // Tema butonlarına event listener ekle
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggleMobileBtn = document.getElementById('theme-toggle-mobile');
        if (themeToggleMobileBtn) {
            themeToggleMobileBtn.addEventListener('click', toggleTheme);
        }
        
        const themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }
        
        // Mevcut tema ayarına göre ikonları başlangıçta doğru şekilde ayarla
        updateThemeIcons();
    });
    
    // İkonları mevcut temaya göre güncelle
    function updateThemeIcons() {
        if (localStorage.getItem('theme') === 'dark' ||
            (!('theme' in localStorage) &&
            window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            // Koyu mod ikonları
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.remove('hidden');
            }
        } else {
            // Açık mod ikonları
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.remove('hidden');
            }
        }
    }
    </script>

    <?php require_once 'templates/footer.php'; ?>
</body>
</html>
