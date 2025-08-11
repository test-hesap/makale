<?php
require_once 'includes/config.php';
include_once 'includes/auto_unban.php'; // Ban süresi dolmuş kullanıcıları otomatik aktif et

// article_featured tablosunun varlığını kontrol et
$checkTable = $db->query("SHOW TABLES LIKE 'article_featured'");
if ($checkTable && $checkTable->rowCount() == 0) {
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
    
    // Gerekli ayarları ekle
    $settings = [
        'show_recent_articles' => '1',
        'recent_articles_count' => '4',
        'recent_articles_title' => __('recent_articles'),
        
        'show_popular_articles' => '1',
        'popular_articles_count' => '4',
        'popular_articles_title' => __('popular_articles'),
        
        'show_featured_articles' => '1',
        'featured_articles_count' => '4',
        'featured_articles_title' => __('featured_articles'),
    ];

    foreach ($settings as $key => $value) {
        // Ayar zaten var mı kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
        $stmt->execute([$key]);
        $exists = (bool)$stmt->fetchColumn();
        
        if (!$exists) {
            $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
    }
}

// Manşet ayarlarını al
$headline_enabled = getSetting('headline_enabled');
$headline_count = (int)getSetting('headline_count') ?: 5;
$headline_style = getSetting('headline_style') ?: 'slider';
$headline_auto_rotate = getSetting('headline_auto_rotate');
$headline_rotation_speed = (int)getSetting('headline_rotation_speed') ?: 5000;

// Manşet makalelerini getir
$headlines = [];
if ($headline_enabled) {
    $headlines = $db->query("
        SELECT a.*, a.slug, c.name as category_name, u.username,
               (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
               IFNULL(a.view_count, 0) as view_count,
               ah.position
        FROM article_headlines ah
        LEFT JOIN articles a ON ah.article_id = a.id
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id
        WHERE ah.status = 'active' AND a.status = 'published'
        ORDER BY ah.position ASC, ah.created_at DESC
        LIMIT $headline_count
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Sayfalama ayarlarını al
$posts_per_page = (int)getSetting('posts_per_page');
if ($posts_per_page <= 0) $posts_per_page = 6;

$pagination_type = getSetting('pagination_type');
if (!$pagination_type) $pagination_type = 'numbered'; // Varsayılan değer

// Sayfa parametresini al
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Sayfalama için offset hesapla
$offset = ($current_page - 1) * $posts_per_page;

// Son eklenen makaleleri getir
$stmt = $db->prepare("
    SELECT a.*, a.slug, c.name as category_name, u.username, a.featured_image,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
           IFNULL(a.view_count, 0) as view_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindParam(1, $posts_per_page, PDO::PARAM_INT);
$stmt->bindParam(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam makale sayısını al
$total_articles = $db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
$total_pages = ceil($total_articles / $posts_per_page);

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
    SELECT a.*, a.slug, c.name as category_name, u.username, a.featured_image,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
           IFNULL(a.view_count, 0) as view_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.view_count DESC, a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Site başlığını al
$site_title = getSetting('site_title');
$site_description = getSetting('site_description');
?>
<?php include 'templates/header.php'; ?>

<div class="container mx-auto px-4 py-0 mt-1">
    <!-- Boşluk eklendi -->
</div>

<?php if ($headline_enabled && !empty($headlines)): ?>
<!-- Manşet Bölümü -->
<div class="container mx-auto px-4 py-0 mt-0">
    <?php if ($headline_style === 'slider'): ?>
    <!-- Slider Manşet -->
    <div class="relative bg-white rounded-lg shadow-lg overflow-hidden mb-4" id="headline-slider">
        <div class="relative h-96 overflow-hidden">
            <?php foreach ($headlines as $index => $headline): ?>
            <div class="headline-slide absolute inset-0 transition-opacity duration-500 <?php echo $index === 0 ? 'opacity-100' : 'opacity-0'; ?>" data-slide="<?php echo $index; ?>">
                <div class="relative h-full">
                    <?php
                    // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                    $imgSrc = !empty($headline['featured_image']) ? 
                        ((strpos($headline['featured_image'], 'http://') === 0 || strpos($headline['featured_image'], 'https://') === 0) 
                            ? $headline['featured_image'] 
                            : (strpos($headline['featured_image'], '/') === 0 
                                ? $headline['featured_image'] 
                                : "/uploads/ai_images/" . $headline['featured_image'])) 
                        : '/assets/img/default-article.jpg';
                    ?>
                    <img src="<?php echo $imgSrc; ?>" 
                         class="w-full h-full object-cover" alt="<?php echo safeTitle($headline['title']); ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                        <div class="max-w-4xl">
                            <span class="inline-block bg-blue-600 text-white px-3 py-1 rounded-full text-sm mb-2">
                                <?php echo htmlspecialchars($headline['category_name']); ?>
                            </span>
                            <h2 class="text-3xl md:text-4xl font-bold mb-3 leading-tight">
                                <a href="/makale/<?php echo urlencode($headline['slug']); ?>" class="hover:text-blue-300 transition-colors">
                                    <?php echo safeTitle($headline['title']); ?>
                                </a>
                            </h2>
                            <p class="text-lg text-gray-200 mb-4 line-clamp-2">
                                <?php echo mb_substr(strip_tags(html_entity_decode($headline['content'], ENT_QUOTES, 'UTF-8')), 0, 200, 'UTF-8') . '...'; ?>
                            </p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-sm text-gray-300">
                                    <i class="fas fa-user mr-2"></i>
                                    <span class="mr-4"><?php echo htmlspecialchars($headline['username']); ?></span>
                                    <i class="fas fa-eye mr-2"></i>
                                    <span class="mr-4"><?php echo number_format($headline['view_count']); ?></span>
                                    <i class="fas fa-comment mr-2"></i>
                                    <span><?php echo $headline['comment_count']; ?></span>
                                </div>
                                <a href="/makale/<?php echo urlencode($headline['slug']); ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-full transition-colors">
                                    <?php echo __('read_more'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Slider Navigation -->
        <?php if (count($headlines) > 1): ?>
        <div class="absolute top-1/2 left-4 transform -translate-y-1/2">
            <button onclick="previousSlide()" class="bg-black/50 hover:bg-black/70 text-white p-2 rounded-full">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        <div class="absolute top-1/2 right-4 transform -translate-y-1/2">
            <button onclick="nextSlide()" class="bg-black/50 hover:bg-black/70 text-white p-2 rounded-full">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <!-- Slide Indicators -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
            <?php for ($i = 0; $i < count($headlines); $i++): ?>
            <button onclick="goToSlide(<?php echo $i; ?>)" class="w-3 h-3 rounded-full transition-colors <?php echo $i === 0 ? 'bg-white' : 'bg-white/50'; ?>" data-indicator="<?php echo $i; ?>"></button>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php elseif ($headline_style === 'grid'): ?>
    <!-- Grid Manşet -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <?php foreach ($headlines as $headline): ?>
        <article class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
            <div class="relative h-48">
                <?php
                // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                $imgSrc = !empty($headline['featured_image']) ? 
                    ((strpos($headline['featured_image'], 'http://') === 0 || strpos($headline['featured_image'], 'https://') === 0) 
                        ? $headline['featured_image'] 
                        : (strpos($headline['featured_image'], '/') === 0 
                            ? $headline['featured_image'] 
                            : "/uploads/ai_images/" . $headline['featured_image'])) 
                    : '/assets/img/default-article.jpg';
                ?>
                <img src="<?php echo $imgSrc; ?>" 
                     class="w-full h-full object-cover" alt="<?php echo safeTitle($headline['title']); ?>">
                <div class="absolute top-2 left-2">
                    <span class="bg-red-600 text-white px-2 py-1 rounded text-xs font-medium"><?php echo __('featured'); ?></span>
                </div>
                <div class="absolute bottom-2 left-2">
                    <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs">
                        <?php echo htmlspecialchars($headline['category_name']); ?>
                    </span>
                </div>
            </div>
            <div class="p-4">
                <h2 class="text-lg font-bold mb-2 hover:text-blue-600">
                    <a href="/makale/<?php echo urlencode($headline['slug']); ?>">
                        <?php echo safeTitle($headline['title']); ?>
                    </a>
                </h2>
                <p class="text-gray-600 text-sm mb-3">
                    <?php echo mb_substr(strip_tags(html_entity_decode($headline['content'], ENT_QUOTES, 'UTF-8')), 0, 120, 'UTF-8') . '...'; ?>
                </p>
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span><?php echo htmlspecialchars($headline['username']); ?></span>
                    <div class="flex items-center space-x-3">
                        <span><i class="fas fa-eye mr-1"></i><?php echo number_format($headline['view_count']); ?></span>
                        <span><i class="fas fa-comment mr-1"></i><?php echo $headline['comment_count']; ?></span>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    
    <?php elseif ($headline_style === 'list'): ?>
    <!-- Liste Manşet -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b-2 border-red-600 pb-2">
            <i class="fas fa-fire text-red-600 mr-2"></i><?php echo __('featured_news'); ?>
        </h2>
        <div class="space-y-4">
            <?php foreach ($headlines as $index => $headline): ?>
            <article class="flex gap-4 p-4 hover:bg-gray-50 rounded-lg transition-colors">
                <div class="flex-shrink-0">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-red-600 text-white rounded-full text-sm font-bold">
                        <?php echo $index + 1; ?>
                    </span>
                </div>
                <div class="flex-shrink-0 w-20 h-16">
                    <?php
                    // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                    $imgSrc = !empty($headline['featured_image']) ? 
                        ((strpos($headline['featured_image'], 'http://') === 0 || strpos($headline['featured_image'], 'https://') === 0) 
                            ? $headline['featured_image'] 
                            : (strpos($headline['featured_image'], '/') === 0 
                                ? $headline['featured_image'] 
                                : "/uploads/ai_images/" . $headline['featured_image'])) 
                        : '/assets/img/default-article.jpg';
                    ?>
                    <img src="<?php echo $imgSrc; ?>" 
                         class="w-full h-full object-cover rounded" alt="<?php echo safeTitle($headline['title']); ?>">
                </div>
                <div class="flex-grow">
                    <h3 class="font-bold text-gray-900 hover:text-blue-600 mb-1">
                        <a href="/makale/<?php echo urlencode($headline['slug']); ?>">
                            <?php echo safeTitle($headline['title']); ?>
                        </a>
                    </h3>
                    <p class="text-sm text-gray-600 mb-2">
                        <?php echo mb_substr(strip_tags(html_entity_decode($headline['content'], ENT_QUOTES, 'UTF-8')), 0, 100, 'UTF-8') . '...'; ?>
                    </p>
                    <div class="flex items-center text-xs text-gray-500">
                        <span class="mr-3"><?php echo htmlspecialchars($headline['category_name']); ?></span>
                        <span class="mr-3"><?php echo htmlspecialchars($headline['username']); ?></span>
                        <span><i class="fas fa-eye mr-1"></i><?php echo number_format($headline['view_count']); ?></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php elseif ($headline_style === 'carousel'): ?>
    <!-- Carousel Manşet -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8" id="headline-carousel">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">
            <i class="fas fa-star text-yellow-500 mr-2"></i>Öne Çıkan Haberler
        </h2>
        <div class="relative overflow-hidden">
            <div class="flex transition-transform duration-500 ease-in-out" id="carousel-container">
                <?php foreach ($headlines as $headline): ?>
                <div class="w-full md:w-1/2 lg:w-1/3 flex-shrink-0 px-2">
                    <article class="bg-gray-50 rounded-lg overflow-hidden">
                        <div class="relative h-40">
                            <?php
                            // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                            $imgSrc = !empty($headline['featured_image']) ? 
                                ((strpos($headline['featured_image'], 'http://') === 0 || strpos($headline['featured_image'], 'https://') === 0) 
                                    ? $headline['featured_image'] 
                                    : (strpos($headline['featured_image'], '/') === 0 
                                        ? $headline['featured_image'] 
                                        : "/uploads/ai_images/" . $headline['featured_image'])) 
                                : '/assets/img/default-article.jpg';
                            ?>
                            <img src="<?php echo $imgSrc; ?>" 
                                 class="w-full h-full object-cover" alt="<?php echo safeTitle($headline['title']); ?>">
                            <div class="absolute top-2 right-2">
                                <span class="bg-yellow-500 text-white px-2 py-1 rounded text-xs font-medium">
                                    <i class="fas fa-star mr-1"></i>ÖNE ÇIKAN
                                </span>
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-gray-900 hover:text-blue-600 mb-2">
                                <a href="/makale/<?php echo urlencode($headline['slug']); ?>">
                                    <?php echo safeTitle($headline['title']); ?>
                                </a>
                            </h3>
                            <div class="flex items-center text-xs text-gray-500">
                                <span class="mr-2"><?php echo htmlspecialchars($headline['category_name']); ?></span>
                                <span><i class="fas fa-eye mr-1"></i><?php echo number_format($headline['view_count']); ?></span>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
<?php if ($headline_style === 'slider' && count($headlines) > 1): ?>
// Slider functionality
let currentSlide = 0;
const totalSlides = <?php echo count($headlines); ?>;
<?php if ($headline_auto_rotate): ?>
const autoRotateSpeed = <?php echo $headline_rotation_speed; ?>;
let autoRotateInterval;
<?php endif; ?>

function showSlide(n) {
    const slides = document.querySelectorAll('.headline-slide');
    const indicators = document.querySelectorAll('[data-indicator]');
    
    slides.forEach(slide => slide.classList.remove('opacity-100'));
    slides.forEach(slide => slide.classList.add('opacity-0'));
    indicators.forEach(indicator => indicator.classList.remove('bg-white'));
    indicators.forEach(indicator => indicator.classList.add('bg-white/50'));
    
    if (slides[n]) {
        slides[n].classList.remove('opacity-0');
        slides[n].classList.add('opacity-100');
    }
    
    if (indicators[n]) {
        indicators[n].classList.remove('bg-white/50');
        indicators[n].classList.add('bg-white');
    }
    
    currentSlide = n;
}

function nextSlide() {
    currentSlide = (currentSlide + 1) % totalSlides;
    showSlide(currentSlide);
}

function previousSlide() {
    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
    showSlide(currentSlide);
}

function goToSlide(n) {
    showSlide(n);
}

<?php if ($headline_auto_rotate): ?>
function startAutoRotate() {
    autoRotateInterval = setInterval(nextSlide, autoRotateSpeed);
}

function stopAutoRotate() {
    clearInterval(autoRotateInterval);
}

// Auto rotate başlat
startAutoRotate();

// Hover ile durdur
document.getElementById('headline-slider').addEventListener('mouseenter', stopAutoRotate);
document.getElementById('headline-slider').addEventListener('mouseleave', startAutoRotate);
<?php endif; ?>
<?php endif; ?>

<?php if ($headline_style === 'carousel' && count($headlines) > 3): ?>
// Carousel functionality
let carouselPosition = 0;
const carouselContainer = document.getElementById('carousel-container');
const itemWidth = 100 / 3; // Her seferde 3 item göster

function moveCarousel() {
    carouselPosition = (carouselPosition + itemWidth) % (<?php echo count($headlines); ?> * itemWidth / 3);
    carouselContainer.style.transform = `translateX(-${carouselPosition}%)`;
}

// Otomatik carousel
<?php if ($headline_auto_rotate): ?>
setInterval(moveCarousel, <?php echo $headline_rotation_speed; ?>);
<?php endif; ?>
<?php endif; ?>

<?php if ($headline_style === 'top_articles_v2' && count($headlines) > 1): ?>
// Top Articles V2 functionality
let currentV2Slide = 0;
const totalV2Slides = <?php echo count($headlines); ?>;
<?php if ($headline_auto_rotate): ?>
const v2AutoRotateSpeed = <?php echo $headline_rotation_speed; ?>;
let v2AutoRotateInterval;
<?php endif; ?>

function showV2Slide(n) {
    const slides = document.querySelectorAll('.headline-v2-slide');
    const dots = document.querySelectorAll('.headline-v2-dot');
    
    // Debug: Hangi slide gösteriliyor
    console.log('Showing V2 slide:', n);
    
    slides.forEach((slide, index) => {
        if (index === n) {
            slide.classList.remove('opacity-0');
            slide.classList.add('opacity-100');
            // Debug: Aktif slide bilgileri
            const articleSlug = slide.getAttribute('data-article-slug');
            const articleId = slide.getAttribute('data-article-id');
            console.log('Active slide - ID:', articleId, 'Slug:', articleSlug);
        } else {
            slide.classList.remove('opacity-100');
            slide.classList.add('opacity-0');
        }
    });
    
    dots.forEach((dot, index) => {
        if (index === n) {
            dot.classList.remove('bg-white/50');
            dot.classList.add('bg-white');
        } else {
            dot.classList.remove('bg-white');
            dot.classList.add('bg-white/50');
        }
    });
    
    currentV2Slide = n;
}

function nextV2Slide() {
    currentV2Slide = (currentV2Slide + 1) % totalV2Slides;
    showV2Slide(currentV2Slide);
}

// Dot click handlers
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.headline-v2-dot').forEach((dot, index) => {
        dot.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const targetSlug = dot.getAttribute('data-article-slug');
            console.log('Dot clicked - Index:', index, 'Target slug:', targetSlug);
            
            showV2Slide(index);
            <?php if ($headline_auto_rotate): ?>
            // Restart auto rotation when manually clicked
            clearInterval(v2AutoRotateInterval);
            startV2AutoRotate();
            <?php endif; ?>
        });
    });
});

<?php if ($headline_auto_rotate): ?>
function startV2AutoRotate() {
    v2AutoRotateInterval = setInterval(nextV2Slide, v2AutoRotateSpeed);
}

function stopV2AutoRotate() {
    clearInterval(v2AutoRotateInterval);
}

// Auto rotate başlat
document.addEventListener('DOMContentLoaded', function() {
    startV2AutoRotate();
});

// Hover ile durdur
const v2Container = document.getElementById('headline-v2-container');
if (v2Container) {
    v2Container.addEventListener('mouseenter', stopV2AutoRotate);
    v2Container.addEventListener('mouseleave', startV2AutoRotate);
}
<?php endif; ?>
<?php endif; ?>
</script>
<?php endif; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Ana İçerik - Makale Kartları -->
            <div class="w-full lg:w-2/3">
                <!-- Makale Üstü Manşet - Büyük ve Çekici Görünüm -->
                <?php if ($headline_enabled && !empty($headlines) && $headline_style === 'top_articles'): ?>
                <div class="bg-white rounded-xl shadow-xl overflow-hidden mb-8 border border-gray-200">
                    <!-- Manşet Header -->
                    <div class="bg-gradient-to-r from-red-600 via-red-700 to-red-800 text-white p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="bg-white/20 p-2 rounded-full">
                                    <i class="fas fa-fire text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold">MANŞET HABERLER</h2>
                                    <p class="text-red-100 text-sm">Son dakika gelişmeleri</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="bg-yellow-400 text-red-800 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider animate-pulse">
                                    🔴 CANLI
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manşet Content -->
                    <div class="p-6">
                        <?php 
                        $first_headline = $headlines[0];
                        $other_headlines = array_slice($headlines, 1, 2);
                        ?>
                        
                        <!-- Ana Manşet -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <div class="lg:col-span-1">
                                <div class="relative group cursor-pointer">
                                    <div class="aspect-video rounded-lg overflow-hidden">
                                        <?php
                                        // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                                        $imgSrc = !empty($first_headline['featured_image']) ? 
                                            ((strpos($first_headline['featured_image'], 'http://') === 0 || strpos($first_headline['featured_image'], 'https://') === 0) 
                                                ? $first_headline['featured_image'] 
                                                : (strpos($first_headline['featured_image'], '/') === 0 
                                                    ? $first_headline['featured_image'] 
                                                    : "/uploads/ai_images/" . $first_headline['featured_image'])) 
                                            : '/assets/img/default-article.jpg';
                                        ?>
                                        <img src="<?php echo $imgSrc; ?>" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                             alt="<?php echo htmlspecialchars($first_headline['title']); ?>">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                                        <div class="absolute top-3 left-3">
                                            <span class="bg-red-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                                                #1 MANŞET
                                            </span>
                                        </div>
                                        <div class="absolute bottom-3 left-3 right-3">
                                            <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-medium">
                                                <?php echo htmlspecialchars($first_headline['category_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="lg:col-span-1">
                                <div class="h-full flex flex-col justify-center">
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3 line-clamp-2 hover:text-red-600 transition-colors">
                                        <a href="/makale/<?php echo urlencode($first_headline['slug']); ?>">
                                            <?php echo htmlspecialchars($first_headline['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-gray-600 text-base mb-4 line-clamp-3">
                                        <?php echo mb_substr(strip_tags(html_entity_decode($first_headline['content'], ENT_QUOTES, 'UTF-8')), 0, 200, 'UTF-8') . '...'; ?>
                                    </p>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-sm text-gray-500 space-x-4">
                                            <span class="flex items-center">
                                                <i class="fas fa-user mr-2"></i>
                                                <?php echo htmlspecialchars($first_headline['username']); ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-eye mr-2"></i>
                                                <?php echo number_format($first_headline['view_count']); ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-comment mr-2"></i>
                                                <?php echo $first_headline['comment_count']; ?>
                                            </span>
                                        </div>
                                        <a href="/makale/<?php echo urlencode($first_headline['slug']); ?>" 
                                           class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-full font-medium transition-colors flex items-center">
                                            <i class="fas fa-arrow-right mr-2"></i>
                                            <?php echo __('read_more'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Diğer Manşet Haberleri -->
                        <?php if (!empty($other_headlines)): ?>
                        <div class="border-t pt-6">
                            <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-list mr-2 text-red-600"></i>
                                <?php echo __('other_headline_news'); ?>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($other_headlines as $index => $headline): ?>
                                <div class="flex items-start space-x-4 p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors group">
                                    <div class="flex-shrink-0">
                                        <div class="w-20 h-16 rounded-lg overflow-hidden">
                                            <?php
                                            // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                                            $imgSrc = !empty($headline['featured_image']) ? 
                                                ((strpos($headline['featured_image'], 'http://') === 0 || strpos($headline['featured_image'], 'https://') === 0) 
                                                    ? $headline['featured_image'] 
                                                    : (strpos($headline['featured_image'], '/') === 0 
                                                        ? $headline['featured_image'] 
                                                        : "/uploads/ai_images/" . $headline['featured_image'])) 
                                                : '/assets/img/default-article.jpg';
                                            ?>
                                            <img src="<?php echo $imgSrc; ?>" 
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                                                 alt="<?php echo safeTitle($headline['title']); ?>">
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-red-600 text-white rounded-full text-xs font-bold">
                                                <?php echo $index + 2; ?>
                                            </span>
                                            <span class="text-xs text-red-600 font-medium uppercase tracking-wider">
                                                <?php echo htmlspecialchars($headline['category_name']); ?>
                                            </span>
                                        </div>
                                        <h5 class="font-semibold text-gray-900 group-hover:text-red-600 transition-colors duration-200 line-clamp-2 mb-2">
                                            <a href="/makale/<?php echo urlencode($headline['slug']); ?>">
                                                <?php echo safeTitle($headline['title']); ?>
                                            </a>
                                        </h5>
                                        <div class="flex items-center text-xs text-gray-500 space-x-3">
                                            <span class="flex items-center">
                                                <i class="fas fa-user mr-1"></i>
                                                <?php echo htmlspecialchars($headline['username']); ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-eye mr-1"></i>
                                                <?php echo number_format($headline['view_count']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <a href="/makale/<?php echo urlencode($headline['slug']); ?>" 
                                           class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs rounded-md hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition-colors">
                                            <i class="fas fa-external-link-alt mr-1"></i>
                                            Oku
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Manşet Footer -->
                    <div class="bg-gray-50 px-6 py-3 border-t">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo __('last_update'); ?>: <?php echo date('d.m.Y H:i'); ?>
                            </span>
                            <a href="/categories.php" class="text-red-600 hover:text-red-700 font-medium">
                                <?php echo __('all_categories'); ?> <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Makale Üstü V2 Manşet - Büyük Banner Stil (3 Makale Kartı Yüksekliği) -->
                <?php if ($headline_enabled && !empty($headlines) && $headline_style === 'top_articles_v2'): ?>
                <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-black rounded-2xl shadow-2xl overflow-hidden mb-8 border border-gray-700" style="height: 400px;" id="headline-v2-container">
                    <!-- Manşet Container -->
                    <div class="relative h-full">
                        <?php foreach ($headlines as $index => $headline): ?>
                        <!-- Slide <?php echo $index + 1; ?> - Article ID: <?php echo $headline['id']; ?> -->
                        <div class="headline-v2-slide absolute inset-0 transition-opacity duration-1000 <?php echo $index === 0 ? 'opacity-100' : 'opacity-0'; ?>" 
                             data-slide="<?php echo $index; ?>" 
                             data-article-id="<?php echo $headline['id']; ?>"
                             data-article-slug="<?php echo htmlspecialchars($headline['slug']); ?>">
                            <div class="relative h-full group">
                                <!-- Arkaplan Resmi -->
                                <div class="absolute inset-0">
                                    <?php
                                    // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                                    $imgSrc = !empty($headline['featured_image']) ? 
                                        ((strpos($headline['featured_image'], 'http://') === 0 || strpos($headline['featured_image'], 'https://') === 0) 
                                            ? $headline['featured_image'] 
                                            : (strpos($headline['featured_image'], '/') === 0 
                                                ? $headline['featured_image'] 
                                                : "/uploads/ai_images/" . $headline['featured_image'])) 
                                        : '/assets/img/default-article.jpg';
                                    ?>
                                    <img src="<?php echo $imgSrc; ?>" 
                                         class="w-full h-full object-cover" 
                                         alt="<?php echo safeTitle($headline['title']); ?>">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                                </div>
                                
                                <!-- Kategori Etiketi - Sol Üst -->
                                <div class="absolute top-4 left-4">
                                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm font-medium">
                                        <?php echo htmlspecialchars($headline['category_name']); ?>
                                    </span>
                                </div>
                                
                                <!-- Clickable Overlay - Tüm slide'ı tıklanabilir yap -->
                                <a href="/makale/<?php echo urlencode($headline['slug']); ?>" 
                                   class="absolute inset-0 z-10 block"
                                   title="<?php echo safeTitle($headline['title']); ?>">
                                </a>
                                
                                <!-- İçerik -->
                                <div class="relative h-full flex flex-col justify-end p-8 z-20 pointer-events-none">
                                    <!-- Başlık -->
                                    <h2 class="text-3xl lg:text-4xl font-bold text-white mb-4 line-clamp-3 group-hover:text-red-300 transition-colors duration-300">
                                        <span class="pointer-events-auto">
                                            <a href="/makale/<?php echo urlencode($headline['slug']); ?>" class="relative z-30">
                                                <?php echo safeTitle($headline['title']); ?>
                                            </a>
                                        </span>
                                    </h2>
                                    
                                    <!-- Özet -->
                                    <p class="text-gray-200 text-lg mb-6 line-clamp-2 lg:line-clamp-3">
                                        <?php echo mb_substr(strip_tags(html_entity_decode($headline['content'], ENT_QUOTES, 'UTF-8')), 0, 150, 'UTF-8') . '...'; ?>
                                    </p>
                                    
                                    <!-- Alt Bilgiler -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-gray-300 space-x-4">
                                            <span class="flex items-center">
                                                <i class="fas fa-user mr-2"></i>
                                                <?php echo htmlspecialchars($headline['username']); ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-eye mr-2"></i>
                                                <?php echo number_format($headline['view_count']); ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-comment mr-2"></i>
                                                <?php echo $headline['comment_count']; ?>
                                            </span>
                                        </div>
                                        <a href="/makale/<?php echo urlencode($headline['slug']); ?>" 
                                           class="bg-blue-100 text-blue-700 px-4 py-2 rounded font-medium transition-all duration-300 hover:bg-blue-200 shadow-lg pointer-events-auto relative z-30">
                                            <?php echo __('read_more'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Slide Indicators (Dots) -->
                        <?php if (count($headlines) > 1): ?>
                        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2 z-40">
                            <?php foreach ($headlines as $index => $headline): ?>
                            <button class="headline-v2-dot w-3 h-3 rounded-full transition-all duration-300 <?php echo $index === 0 ? 'bg-white' : 'bg-white/50'; ?>" 
                                    data-slide="<?php echo $index; ?>"
                                    data-article-slug="<?php echo htmlspecialchars($headline['slug']); ?>"
                                    title="<?php echo safeTitle($headline['title']); ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Öne Çıkan Makaleler - Ana makale kartlarının üstünde göster
                $show_featured_articles = getSetting('show_featured_articles');
                $featured_articles_count = (int)getSetting('featured_articles_count') ?: 4;
                $featured_articles_title = getSetting('featured_articles_title') ?: __('featured_articles');
                
                if ($show_featured_articles) {
                    try {
                        // Önce tablo var mı kontrol et
                        $tableCheck = $db->query("SHOW TABLES LIKE 'article_featured'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            $featured_articles = $db->query("
                                SELECT a.*, a.slug, c.name as category_name, u.username,
                                   (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
                                   IFNULL(a.view_count, 0) as view_count
                                FROM article_featured af
                                JOIN articles a ON af.article_id = a.id
                                LEFT JOIN categories c ON a.category_id = c.id
                                LEFT JOIN users u ON a.author_id = u.id
                                WHERE a.status = 'published'
                                ORDER BY af.position ASC
                                LIMIT $featured_articles_count
                            ")->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $featured_articles = [];
                        }
                    } catch (PDOException $e) {
                        error_log("Öne çıkan makaleler sorgulanırken hata: " . $e->getMessage());
                        $featured_articles = [];
                    }
                    
                    if (!empty($featured_articles)) {
                ?>
                <!-- Öne Çıkan Makaleler -->
                <div class="mb-8">
                    <div class="flex items-center mb-6">
                        <div class="text-yellow-500 mr-3">
                            <i class="fas fa-star text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo __('featured_articles'); ?></h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php foreach ($featured_articles as $article): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col h-full border border-gray-200 dark:border-gray-700">
                            <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="block overflow-hidden h-32">
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
                                     class="w-full h-full object-cover transform hover:scale-105 transition-transform duration-500" 
                                     alt="<?php echo safeTitle($article['title']); ?>">
                            </a>
                            <div class="p-3 flex flex-col flex-grow">
                                <div class="flex justify-between items-center mb-2">
                                    <a href="/kategori/<?php echo urlencode($article['category_name']); ?>" class="text-blue-600 dark:text-blue-400 text-xs font-medium">
                                        <?php echo htmlspecialchars($article['category_name']); ?>
                                    </a>
                                    <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">
                                        <i class="fas fa-star text-xs mr-1"></i> <?php echo __('featured'); ?>
                                    </span>
                                </div>
                                <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="block">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1 line-clamp-2 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <?php echo safeTitle($article['title']); ?>
                                    </h3>
                                </a>
                                <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400 mt-auto">
                                    <span class="flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($article['username']); ?>
                                    </span>
                                    <div>
                                        <span class="mr-2"><i class="fas fa-eye mr-1"></i><?php echo number_format($article['view_count']); ?></span>
                                        <span><i class="fas fa-comment mr-1"></i><?php echo $article['comment_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                    }
                }
                ?>

                <!-- Ana Makaleler -->
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><?php echo __('all_articles'); ?></h1>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    foreach ($articles as $index => $article): 
                        // Her 3 makalede bir reklam göster
                        if ($index > 0 && showArticleAd($index)) {
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
                                 class="w-full h-full object-cover" alt="Makale Görseli">                            <div class="absolute bottom-0 left-0 bg-blue-100 text-blue-700 py-1 px-3 rounded-tr">
                                <?php echo htmlspecialchars($article['category_name']); ?>
                            </div>
                            <?php if (isset($article['is_premium']) && $article['is_premium'] == 1): ?>
                            <div class="absolute top-0 right-0 bg-purple-500 text-white py-1 px-3 rounded-bl flex items-center">
                                <i class="fas fa-crown mr-1"></i> <?php echo __('premium'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                          <!-- Makale Bilgileri -->
                        <div class="p-4 flex flex-col h-full">
                            <div class="flex-grow">
                                <h3 class="text-lg font-semibold mb-2">
                                    <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="hover:text-blue-500">
                                        <?php echo safeTitle($article['title']); ?>
                                    </a>
                                </h3>
                                <p class="text-gray-600 text-sm mb-3"><?php echo formatTurkishDate($article['created_at']); ?></p>
                                <p class="text-gray-700 mb-4">
                                    <?php echo mb_substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 100, 'UTF-8') . '...'; ?>
                                </p>
                            </div>

                            <div class="mt-auto flex justify-end">
                                <a href="/makale/<?php echo urlencode($article['slug']); ?>" 
                                   class="inline-block bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200 transition duration-300">
                                    <?php echo __('read_more'); ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="px-4 pb-4 mt-2 border-t">                            <div class="flex items-center justify-between text-sm text-gray-500 pt-3">                                <div class="flex items-center">
                                    <i class="fas fa-user mr-2"></i>
                                    <a href="/uyeler/<?php echo urlencode($article['username']); ?>" class="hover:text-blue-600">
                                        <?php echo htmlspecialchars($article['username']); ?>
                                    </a>
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
                    <?php endforeach; ?>                </div>
                
                <?php
                // Son Eklenen Makaleler
                $show_recent_articles = getSetting('show_recent_articles');
                $recent_articles_count = (int)getSetting('recent_articles_count') ?: 4;
                $recent_articles_title = getSetting('recent_articles_title') ?: __('recent_articles');
                
                if ($show_recent_articles) {
                    $recent_articles = $db->query("
                        SELECT a.*, a.slug, c.name as category_name, u.username,
                               (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
                               IFNULL(a.view_count, 0) as view_count
                        FROM articles a
                        LEFT JOIN categories c ON a.category_id = c.id
                        LEFT JOIN users u ON a.author_id = u.id
                        WHERE a.status = 'published'
                        ORDER BY a.created_at DESC
                        LIMIT $recent_articles_count
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($recent_articles)) {
                ?>
                <!-- Son Eklenen Makaleler -->
                <div class="mb-8">
                    <div class="flex items-center mb-6">
                        <div class="text-blue-500 mr-3">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo __('recent_articles'); ?></h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($recent_articles as $article): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col h-full border border-gray-200 dark:border-gray-700">
                            <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="block overflow-hidden h-48">
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
                                     class="w-full h-full object-cover transform hover:scale-105 transition-transform duration-500" 
                                     alt="<?php echo safeTitle($article['title']); ?>">
                            </a>
                            <div class="p-5 flex flex-col flex-grow">
                                <a href="/kategori/<?php echo urlencode($article['category_name']); ?>" class="text-blue-600 dark:text-blue-400 text-sm font-medium mb-2">
                                    <?php echo htmlspecialchars($article['category_name']); ?>
                                </a>
                                <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="block">
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 line-clamp-2 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <?php echo safeTitle($article['title']); ?>
                                    </h3>
                                </a>
                                <p class="text-gray-600 dark:text-gray-300 text-sm line-clamp-3 mb-4 flex-grow">
                                    <?php echo mb_substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 150, 'UTF-8') . '...'; ?>
                                </p>
                                <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-300 text-sm font-medium mb-3">
                                    <?php echo __('read_more'); ?>
                                </a>
                                <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 mt-auto">
                                    <span><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($article['username']); ?></span>
                                    <div>
                                        <span class="mr-3"><i class="fas fa-eye mr-1"></i> <?php echo number_format($article['view_count']); ?></span>
                                        <span><i class="fas fa-comment mr-1"></i> <?php echo $article['comment_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                    }
                }
                
                // Popüler Makaleler
                $show_popular_articles = getSetting('show_popular_articles');
                $popular_articles_count = (int)getSetting('popular_articles_count') ?: 4;
                $popular_articles_title = getSetting('popular_articles_title') ?: __('popular_articles');
                
                if ($show_popular_articles && !empty($popular_articles)) {
                ?>
                <!-- Popüler Makaleler -->
                <div class="mb-12 mt-8">
                    <div class="flex items-center mb-4">
                        <div class="text-red-500 mr-2">
                            <i class="fas fa-fire text-xl"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo __('popular_articles'); ?></h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php foreach ($popular_articles as $index => $article): ?>
                        <?php if ($index < $popular_articles_count): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col h-full border border-gray-100 dark:border-gray-700">
                            <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="block overflow-hidden h-32">
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
                                     class="w-full h-full object-cover transform hover:scale-105 transition-transform duration-500" 
                                     alt="<?php echo safeTitle($article['title']); ?>">
                            </a>
                            <div class="p-3 flex flex-col flex-grow">
                                <div class="flex justify-between items-center mb-2">
                                    <a href="/kategori/<?php echo urlencode($article['category_name']); ?>" class="text-blue-600 dark:text-blue-400 text-xs font-medium">
                                        <?php echo htmlspecialchars($article['category_name']); ?>
                                    </a>
                                            <span class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded">
                                        <i class="fas fa-fire text-xs mr-1"></i> <?php echo __('popular'); ?>
                                    </span>
                                </div>
                                <a href="/makale/<?php echo urlencode($article['slug']); ?>" class="block">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1 line-clamp-2 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <?php echo safeTitle($article['title']); ?>
                                    </h3>
                                </a>
                                <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400 mt-auto">
                                    <span class="flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($article['username']); ?>
                                    </span>
                                    <div>
                                        <span class="mr-2"><i class="fas fa-eye mr-1"></i><?php echo number_format($article['view_count']); ?></span>
                                        <span><i class="fas fa-comment mr-1"></i><?php echo $article['comment_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php } ?>
                
                <!-- Sayfalama -->
                <div id="pagination-container" class="mt-8">
                    <?php if ($pagination_type == 'numbered'): ?>
                    <!-- Numaralandırılmış Sayfalama -->
                    <div class="flex justify-center mt-8">
                        <div class="inline-flex rounded-md shadow-sm">
                            <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-l-md hover:bg-gray-100 text-gray-800">
                                <i class="fas fa-chevron-left"></i> <?php echo t('previous'); ?>
                            </a>
                            <?php else: ?>
                            <span class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-l-md text-gray-400 cursor-not-allowed">
                                <i class="fas fa-chevron-left"></i> <?php echo t('previous'); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php
                            // Sayfa numaralarını göster
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1) {
                                echo '<a href="?page=1" class="px-4 py-2 bg-white border-t border-b border-gray-300 hover:bg-gray-100 text-gray-800">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-4 py-2 bg-white border-t border-b border-gray-300 text-gray-800">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $current_page) {
                                    echo '<span class="px-4 py-2 bg-blue-500 border-t border-b border-blue-500 text-white">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '" class="px-4 py-2 bg-white border-t border-b border-gray-300 hover:bg-gray-100 text-gray-800">' . $i . '</a>';
                                }
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-4 py-2 bg-white border-t border-b border-gray-300 text-gray-800">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '" class="px-4 py-2 bg-white border-t border-b border-gray-300 hover:bg-gray-100 text-gray-800">' . $total_pages . '</a>';
                            }
                            ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-r-md hover:bg-gray-100 text-gray-800">
                                <?php echo t('next'); ?> <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php else: ?>
                            <span class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-r-md text-gray-400 cursor-not-allowed">
                                <?php echo t('next'); ?> <i class="fas fa-chevron-right"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($pagination_type == 'load_more'): ?>
                    <!-- Load More Button -->
                    <?php if ($current_page < $total_pages): ?>
                    <div class="text-center mt-8">
                        <button id="load-more-btn" class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded shadow transition duration-300" 
                                data-page="<?php echo $current_page; ?>" 
                                data-total="<?php echo $total_pages; ?>">
                            <?php echo t('load_more'); ?> <i class="fas fa-angle-down ml-2"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($pagination_type == 'infinite_scroll' || $pagination_type == 'load_more'): ?>
                    <div id="articles-container" data-page="<?php echo $current_page; ?>" data-total-pages="<?php echo $total_pages; ?>">
                        <!-- AJAX ile yüklenen içerik buraya gelecek -->
                    </div>
                    
                    <!-- Yükleniyor göstergesi -->
                    <div id="loading-indicator" class="text-center py-4 hidden">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-gray-300 border-t-blue-500"></div>
                        <p class="mt-2 text-gray-600"><?php echo t('loading_articles'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>            <!-- Sağ Sidebar -->
            <div class="w-full lg:w-1/3 space-y-6">
                <?php echo showAd('sidebar'); // Sidebar reklamı ?>                <!-- Arama -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                 <h3 class="text-lg font-semibold mb-4 sidebar-heading"><?php echo __('search'); ?></h3>
                    <form action="/search.php" method="get" class="flex">                        <input type="text" name="q" placeholder="<?php echo __('search_article_placeholder'); ?>" 
                               class="flex-1 px-4 py-2 border rounded-l focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 dark:focus:ring-blue-500 dark:placeholder-gray-400">                        <button type="submit" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-r hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-300">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>                </div>                <!-- Kategoriler -->
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
                        ?>
                        <div>
                            <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>" 
                               class="flex items-center justify-between py-2 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                <span class="font-medium"><?php echo htmlspecialchars($mainCategory['name']); ?></span>
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
                <?php include 'includes/online_users_sidebar.php'; ?><!-- Popüler Makaleler -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4 sidebar-heading"><?php echo __('popular_articles'); ?></h3>
                    <div class="space-y-4">
                        <?php foreach ($popular_articles as $article): ?><a href="/makale/<?php echo urlencode($article['slug']); ?>" 
                           class="flex items-start space-x-4 group">
                            <?php if (!empty($article['featured_image'])): ?>
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
                                 class="w-20 h-20 object-cover rounded">
                            <?php else: ?>
                            <div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center">
                                <i class="fas fa-image text-gray-400 text-xl"></i>
                            </div>
                            <?php endif; ?>
                            <div class="flex-1">                                <h4 class="font-medium text-gray-900 group-hover:text-blue-600 line-clamp-2 sidebar-article-title">
                                    <?php echo safeTitle($article['title']); ?>
                                </h4>
                                <div class="flex items-center text-sm text-gray-500 mt-1">
                                    <span><?php echo date('d.m.Y', strtotime($article['created_at'])); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo $article['comment_count']; ?> <?php echo __('comments'); ?></span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>                    </div>
                </div>
                
                <!-- Son Yorumlar -->
                <?php include 'includes/recent_comments_sidebar.php'; ?>
                
                <!-- İstatistikler -->
                <?php include 'includes/stats_sidebar.php'; ?>

                <?php echo showAd('sidebar_bottom'); // Sidebar bottom ad ?>
            </div>        </div>
    </div>

<?php require_once 'templates/footer.php'; ?>

    <?php if ($pagination_type == 'infinite_scroll' || $pagination_type == 'load_more'): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const articlesGrid = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-6');
        const articlesContainer = document.getElementById('articles-container');
        const loadingIndicator = document.getElementById('loading-indicator');
        const loadMoreBtn = document.getElementById('load-more-btn');
        
        let currentPage = parseInt(articlesContainer ? articlesContainer.dataset.page : 1);
        let totalPages = parseInt(articlesContainer ? articlesContainer.dataset.totalPages : 1);
        let isLoading = false;
        
        // Fonksiyon: Makaleleri AJAX ile yükle
        function loadArticles(page) {
            if (isLoading || page > totalPages) return;
            
            isLoading = true;
            if (loadingIndicator) {
                loadingIndicator.classList.remove('hidden');
            }
            
            fetch(`/get_articles.php?page=${page}&limit=<?php echo $posts_per_page; ?>`)
                .then(response => response.json())
                .then(data => {
                    isLoading = false;
                    
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('hidden');
                    }
                    
                    if (data.articles) {
                        // İlk yüklemede mevcut makaleleri temizle
                        if (page === 1) {
                            articlesGrid.innerHTML = '';
                        }
                        
                        // Yeni makaleleri ekle (HTML olarak geliyor)
                        let tempDiv = document.createElement('div');
                        tempDiv.innerHTML = data.articles;
                        
                        // HTML elemanlarını doğrudan ekle
                        while (tempDiv.firstChild) {
                            articlesGrid.appendChild(tempDiv.firstChild);
                        }
                        
                        currentPage = data.currentPage;
                        
                        // Update for load more button
                        if (loadMoreBtn) {
                            loadMoreBtn.dataset.page = currentPage;
                            if (currentPage >= data.totalPages) {
                                loadMoreBtn.classList.add('hidden');
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('<?php echo __("articles_loading_error"); ?>:', error);
                    isLoading = false;
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('hidden');
                    }
                });
        }
        
        // Event listener for Load More button
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                const nextPage = parseInt(this.dataset.page) + 1;
                loadArticles(nextPage);
            });
        }
        
        // Sonsuz kaydırma için olay dinleyici
        if ('<?php echo $pagination_type; ?>' === 'infinite_scroll') {
            window.addEventListener('scroll', function() {
                if (isLoading) return;
                
                const lastArticle = articlesGrid.lastElementChild;
                if (!lastArticle) return;
                
                const lastArticleOffset = lastArticle.offsetTop + lastArticle.clientHeight;
                const pageOffset = window.pageYOffset + window.innerHeight;
                
                // Sayfanın altına yaklaştığında yeni makaleleri yükle
                if (pageOffset > lastArticleOffset - 500 && currentPage < totalPages) {
                    loadArticles(currentPage + 1);
                }
            });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
