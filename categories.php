<?php
require_once 'includes/config.php';

// Dil seçimini kontrol et
$activeLang = getActiveLang();

// Tüm kategorileri getir
$stmt = $db->query("
    SELECT c.*, COUNT(a.id) as article_count 
    FROM categories c 
    LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
    GROUP BY c.id, c.name, c.slug
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sayfa başlığı
$page_title = t('categories');
$canonical_url = getSetting('site_url') . "/kategoriler";

require_once 'templates/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo t('all_categories'); ?></h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($categories as $category): ?>            <a href="/kategori/<?php echo $category['slug']; ?>" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800"><?php echo clean($category['name']); ?></h2>
                    <span class="bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full">
                        <?php 
                        // Her kategori için makale sayısını doğrudan sorgula
                        $count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                        $count_stmt->execute([$category['id']]);
                        $count = $count_stmt->fetchColumn();
                        echo $count; ?> <?php echo getActiveLang() == 'tr' ? 'makale' : 'article'; ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
