<?php
require_once '../includes/config.php';
checkAuth('admin');

$error = '';
$success = '';

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_settings':
                    // Ayarları güncelle
                    $settings = [
                        'headline_enabled' => isset($_POST['headline_enabled']) ? '1' : '0',
                        'headline_count' => (int)$_POST['headline_count'],
                        'headline_style' => $_POST['headline_style'],
                        'headline_auto_rotate' => isset($_POST['headline_auto_rotate']) ? '1' : '0',
                        'headline_rotation_speed' => (int)$_POST['headline_rotation_speed']
                    ];
                    
                    $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
                    foreach ($settings as $key => $value) {
                        $stmt->execute([$key, $value, $value]);
                    }
                    $success = t('admin_headline_settings_updated');
                    break;
                    
                case 'add_headline':
                    $article_id = (int)$_POST['article_id'];
                    $position = (int)$_POST['position'];
                    
                    // Pozisyon boşsa en son sıraya ekle
                    if ($position == 0) {
                        $max_pos = $db->query("SELECT MAX(position) FROM article_headlines")->fetchColumn();
                        $position = ($max_pos ?? 0) + 1;
                    }
                    
                    $stmt = $db->prepare("INSERT INTO article_headlines (article_id, position, status) VALUES (?, ?, 'active')");
                    $stmt->execute([$article_id, $position]);
                    $success = t('admin_article_added_to_headline');
                    break;
                    
                case 'remove_headline':
                    $headline_id = (int)$_POST['headline_id'];
                    $stmt = $db->prepare("DELETE FROM article_headlines WHERE id = ?");
                    $stmt->execute([$headline_id]);
                    $success = t('admin_article_removed_from_headline');
                    break;
                    
                case 'update_positions':
                    if (isset($_POST['positions'])) {
                        foreach ($_POST['positions'] as $id => $position) {
                            $stmt = $db->prepare("UPDATE article_headlines SET position = ? WHERE id = ?");
                            $stmt->execute([(int)$position, (int)$id]);
                        }
                        $success = t('admin_order_updated');
                    }
                    break;
                    
                case 'toggle_status':
                    $headline_id = (int)$_POST['headline_id'];
                    $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
                    $stmt = $db->prepare("UPDATE article_headlines SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $headline_id]);
                    $success = t('admin_status_updated');
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = t('admin_operation_error') . ': ' . $e->getMessage();
    }
}

// Mevcut ayarları getir
$settings = [];
$stmt = $db->query("SELECT * FROM settings WHERE `key` LIKE 'headline_%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}

// Varsayılan değerler
$default_settings = [
    'headline_enabled' => '1',
    'headline_count' => '5',
    'headline_style' => 'slider',
    'headline_auto_rotate' => '1',
    'headline_rotation_speed' => '5000'
];

foreach ($default_settings as $key => $default) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default;
    }
}

// Mevcut manşet makalelerini getir
$headlines = $db->query("
    SELECT ah.*, a.title, a.slug, a.featured_image, c.name as category_name, u.username
    FROM article_headlines ah
    LEFT JOIN articles a ON ah.article_id = a.id
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    ORDER BY ah.position ASC, ah.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Manşete eklenebilecek makaleleri getir (henüz manşette olmayanlar)
$available_articles = $db->query("
    SELECT a.id, a.title, a.slug, a.featured_image, c.name as category_name, u.username
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published' 
    AND a.id NOT IN (SELECT article_id FROM article_headlines)
    ORDER BY a.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-newspaper mr-2"></i><?php echo t('admin_article_headline_management'); ?>
        </h1>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Manşet Ayarları -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4">
            <i class="fas fa-cog mr-2"></i><?php echo t('admin_headline_settings'); ?>
        </h2>
        
        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="headline_enabled" value="1" 
                               <?php echo $settings['headline_enabled'] ? 'checked' : ''; ?>
                               class="mr-2">
                        <span class="text-gray-700 dark:text-gray-300"><?php echo t('admin_enable_headline_system'); ?></span>
                    </label>
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1"><?php echo t('admin_max_headline_count'); ?></label>
                    <input type="number" name="headline_count" min="1" max="20" 
                           value="<?php echo $settings['headline_count']; ?>"
                           class="w-full px-3 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1"><?php echo t('admin_display_style'); ?></label>
                    <select name="headline_style" class="w-full px-3 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="slider" <?php echo $settings['headline_style'] === 'slider' ? 'selected' : ''; ?>><?php echo t('admin_style_slider'); ?></option>
                        <option value="grid" <?php echo $settings['headline_style'] === 'grid' ? 'selected' : ''; ?>><?php echo t('admin_style_grid'); ?></option>
                        <option value="list" <?php echo $settings['headline_style'] === 'list' ? 'selected' : ''; ?>><?php echo t('admin_style_list'); ?></option>
                        <option value="carousel" <?php echo $settings['headline_style'] === 'carousel' ? 'selected' : ''; ?>><?php echo t('admin_style_carousel'); ?></option>
                        <option value="top_articles" <?php echo $settings['headline_style'] === 'top_articles' ? 'selected' : ''; ?>><?php echo t('admin_style_top_articles'); ?></option>
                        <option value="top_articles_v2" <?php echo $settings['headline_style'] === 'top_articles_v2' ? 'selected' : ''; ?>><?php echo t('admin_style_top_articles_v2'); ?></option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <?php echo t('admin_headline_style_description'); ?>
                    </p>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="headline_auto_rotate" value="1" 
                               <?php echo $settings['headline_auto_rotate'] ? 'checked' : ''; ?>
                               class="mr-2">
                        <span class="text-gray-700 dark:text-gray-300"><?php echo t('admin_auto_rotate'); ?></span>
                    </label>
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1"><?php echo t('admin_rotation_speed'); ?></label>
                    <input type="number" name="headline_rotation_speed" min="1000" max="10000" step="500"
                           value="<?php echo $settings['headline_rotation_speed']; ?>"
                           class="w-full px-3 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-save mr-2"></i><?php echo t('admin_save_settings'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Mevcut Manşet Makaleleri -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4">
            <i class="fas fa-list mr-2"></i><?php echo t('admin_current_headline_articles'); ?>
            <span class="text-sm font-normal text-gray-500">(<?php echo count($headlines); ?>/<?php echo $settings['headline_count']; ?>)</span>
        </h2>
        
        <?php if (empty($headlines)): ?>
        <p class="text-gray-500 dark:text-gray-400 text-center py-8">
            <?php echo t('admin_no_headline_articles'); ?>
        </p>
        <?php else: ?>
        <form method="post" id="position-form">
            <input type="hidden" name="action" value="update_positions">
            <div class="space-y-3" id="headlines-list">
                <?php foreach ($headlines as $headline): ?>
                <div class="flex items-center gap-4 p-4 border rounded-lg bg-gray-50 dark:bg-gray-700 dark:border-gray-600" data-id="<?php echo $headline['id']; ?>">
                    <div class="flex-shrink-0">
                        <i class="fas fa-grip-vertical text-gray-400 cursor-move"></i>
                    </div>
                    
                    <div class="w-16">
                        <input type="number" name="positions[<?php echo $headline['id']; ?>]" 
                               value="<?php echo $headline['position']; ?>" min="1"
                               class="w-full px-2 py-1 text-sm border rounded dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                    </div>
                    
                    <div class="flex-shrink-0 w-16 h-12">
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
                             alt="<?php echo t('admin_article_image'); ?>" class="w-full h-full object-cover rounded">
                    </div>
                    
                    <div class="flex-grow">
                        <h3 class="font-medium text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($headline['title']); ?>
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <?php echo htmlspecialchars($headline['category_name']); ?> • 
                            <?php echo htmlspecialchars($headline['username']); ?>
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $headline['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300'; ?>">
                            <?php echo $headline['status'] === 'active' ? t('admin_active') : t('admin_inactive'); ?>
                        </span>
                        
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="headline_id" value="<?php echo $headline['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $headline['status']; ?>">
                            <button type="submit" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                <i class="fas fa-toggle-<?php echo $headline['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                            </button>
                        </form>
                        
                        <form method="post" class="inline" onsubmit="return confirm('<?php echo t('admin_confirm_remove_headline'); ?>')">
                            <input type="hidden" name="action" value="remove_headline">
                            <input type="hidden" name="headline_id" value="<?php echo $headline['id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-save mr-2"></i><?php echo t('admin_save_order'); ?>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Yeni Makale Ekleme -->
    <?php if (count($headlines) < $settings['headline_count']): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4">
            <i class="fas fa-plus mr-2"></i><?php echo t('admin_add_article_to_headline'); ?>
        </h2>
        
        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="add_headline">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1"><?php echo t('admin_select_article'); ?></label>
                    <select name="article_id" required class="w-full px-3 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value=""><?php echo t('admin_select_article_placeholder'); ?></option>
                        <?php foreach ($available_articles as $article): ?>
                        <option value="<?php echo $article['id']; ?>">
                            <?php echo htmlspecialchars($article['title']); ?> 
                            (<?php echo htmlspecialchars($article['category_name']); ?> - <?php echo htmlspecialchars($article['username']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1"><?php echo t('admin_position'); ?></label>
                    <input type="number" name="position" min="1" placeholder="<?php echo t('admin_position_placeholder'); ?>"
                           class="w-full px-3 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-plus mr-2"></i><?php echo t('admin_add_to_headline'); ?>
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-yellow-100 dark:bg-yellow-900/20 border border-yellow-400 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300 px-4 py-3 rounded">
        <i class="fas fa-info-circle mr-2"></i>
        <?php echo t('admin_headline_limit_reached', $settings['headline_count']); ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sürükle bırak özelliği için
    const headlinesList = document.getElementById('headlines-list');
    if (headlinesList) {
        // Sortable.js eklenebilir veya basit drag-drop implementasyonu yapılabilir
        // Bu örnekte manuel pozisyon değişikliği kullanıyoruz
    }
});
</script>

<?php include 'includes/footer.php'; ?>
