<?php
require_once '../includes/config.php';
require_once '../includes/AIArticleBot.php';
checkAuth(true);

// AI ayarları fonksiyonları
function getAiSetting($key, $default = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT setting_value FROM ai_bot_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function getApiKeyStatus($provider) {
    // Önce veritabanından kontrol et
    $dbKey = getAiSetting($provider . '_api_key');
    if (!empty($dbKey)) {
        return true;
    }
    
    // Fallback: config.php sabitlerinden kontrol et
    switch ($provider) {
        case 'gemini':
            return !empty(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        case 'grok':
            return !empty(defined('GROK_API_KEY') ? GROK_API_KEY : '');
        case 'huggingface':
            return !empty(defined('HUGGINGFACE_API_KEY') ? HUGGINGFACE_API_KEY : '');
        default:
            return false;
    }
}

$success = '';
$error = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_article'])) {
        try {
            $bot = new AIArticleBot();
            $provider = $_POST['provider'] ?? getAiSetting('default_provider', AI_BOT_DEFAULT_PROVIDER);
            $language = $_POST['article_language'] ?? 'tr';
            
            $articleId = $bot->generateAndPublishArticle($provider, $language);
            
            if ($articleId) {
                $langText = $language === 'en' ? 'İngilizce' : 'Türkçe';
                $success = "Makale başarıyla oluşturuldu! (ID: {$articleId}, Dil: {$langText})";
            } else {
                $error = t('admin_article_generation_failed');
            }
        } catch (Exception $e) {
            $error = t('admin_error') . ": " . $e->getMessage();
        }
    } elseif (isset($_POST['update_settings'])) {
        // API anahtarlarını güncelle (config.php'de tanımlı sabitler güncellenemez, 
        // gerçek uygulamada bunlar veritabanında saklanmalı)
        $success = t('admin_settings_updated_config_note');
    }
}

// Bot istatistiklerini al
try {
    $bot = new AIArticleBot();
    $stats = $bot->getStats();
    $recentArticles = $bot->getRecentArticles(5);
} catch (Exception $e) {
    $error = t('admin_bot_data_access_error') . ": " . $e->getMessage();
    $stats = null;
    $recentArticles = [];
}

// Log dosyasını oku (son 20 satır)
$logContent = '';
if (file_exists(AI_BOT_LOG_FILE)) {
    $logLines = file(AI_BOT_LOG_FILE);
    $logContent = implode('', array_slice($logLines, -20));
}

include 'includes/header.php';
?>

<div class="max-w-full mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-robot mr-3"></i><?php echo t('admin_article_ai_bot'); ?>
        </h1>
        <div class="flex items-center space-x-3">
            <a href="migrate_api_keys.php" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                <i class="fas fa-database mr-2"></i>Migration
            </a>
            <a href="ai_bot_settings.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-key mr-2"></i>API Ayarları
            </a>
            <?php
            // Bot durumunu AIArticleBot sınıfından al
            $bot = new AIArticleBot();
            $isBotEnabled = $bot->isBotEnabled(); // Bu metodu public yapmamız gerekecek
            ?>
            <span class="px-3 py-1 rounded-full text-sm <?php echo $isBotEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $isBotEnabled ? t('admin_active') : t('admin_inactive'); ?>
            </span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- İstatistikler -->
    <?php if ($stats): ?>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-500 bg-opacity-75">
                    <i class="fas fa-newspaper text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <?php echo $stats['total_articles']; ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_total_ai_articles'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-500 bg-opacity-75">
                    <i class="fas fa-calendar-day text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <?php echo $stats['today_articles']; ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_today'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-500 bg-opacity-75">
                    <i class="fas fa-calendar-week text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <?php echo $stats['week_articles']; ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_this_week'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-500 bg-opacity-75">
                    <i class="fas fa-calendar-alt text-white text-2xl"></i>
                </div>
                <div class="mx-4">
                    <h4 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
                        <?php echo $stats['month_articles']; ?>
                    </h4>
                    <div class="text-gray-500 dark:text-gray-400"><?php echo t('admin_this_month'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Makale Üretme Paneli -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                    <i class="fas fa-plus-circle mr-2"></i><?php echo t('admin_generate_new_article'); ?>
                </h2>
            </div>
            <div class="p-6">
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <?php echo t('admin_ai_provider_select'); ?>
                        </label>
                        <select name="provider" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200">
                            <?php 
                            $currentDefaultProvider = getAiSetting('default_provider', AI_BOT_DEFAULT_PROVIDER);
                            ?>
                            <option value="gemini" <?php echo $currentDefaultProvider === 'gemini' ? 'selected' : ''; ?>>
                                <?php echo t('admin_google_gemini'); ?>
                            </option>
                            <option value="grok" <?php echo $currentDefaultProvider === 'grok' ? 'selected' : ''; ?>>
                                <?php echo t('admin_xai_grok'); ?>
                            </option>
                            <option value="huggingface" <?php echo $currentDefaultProvider === 'huggingface' ? 'selected' : ''; ?>>
                                <?php echo t('admin_hugging_face'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fas fa-language mr-2"></i>Makale Dili
                        </label>
                        <select name="article_language" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200">
                            <option value="tr" selected>
                                🇹🇷 Türkçe
                            </option>
                            <option value="en">
                                🇺🇸 English
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Seçilen dilde tamamen özgün makale üretilecektir
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_bot_operation_details'); ?></h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                            <li>• <?php echo t('admin_category_auto_select'); ?></li>
                            <li>• <?php echo t('admin_topic_determine_by_category'); ?></li>
                            <li>• <strong><?php echo t('admin_auto_cover_image'); ?></strong> <?php echo t('admin_will_be_added'); ?></li>
                            <li>• <strong><?php echo t('admin_two_images_in_article'); ?></strong> <?php echo t('admin_will_be_placed'); ?></li>
                            <li>• <?php echo t('admin_article_word_count'); ?> 450-550 kelime</li>
                            <li>• <?php echo t('admin_article_title_max_length'); ?> 100 karakter</li>
                            <li>• <?php echo t('admin_h2_headings_and_paragraph_structure'); ?></li>
                            <li>• <?php echo t('admin_auto_tagging'); ?></li>
                        </ul>
                        
                        <?php
                        // Aktif kategorileri göster
                        try {
                            $catStmt = $db->query("SELECT COUNT(*) as count FROM categories WHERE status = 'active'");
                            $catCount = $catStmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            if ($catCount > 0) {
                                echo "<div class='mt-3 p-2 bg-green-100 dark:bg-green-800 rounded text-green-700 dark:text-green-200'>";
                                echo "<i class='fas fa-check-circle mr-1'></i> " . str_replace('{count}', $catCount, t('admin_active_categories_found'));
                                echo "</div>";
                                
                                // Kategori listesi
                                $catListStmt = $db->query("SELECT name FROM categories WHERE status = 'active' ORDER BY name LIMIT 5");
                                $categories = $catListStmt->fetchAll(PDO::FETCH_ASSOC);
                                if (!empty($categories)) {
                                    echo "<div class='mt-2 text-xs text-gray-500 dark:text-gray-400'>";
                                    echo t('admin_categories') . ": " . implode(", ", array_column($categories, 'name'));
                                    if ($catCount > 5) {
                                        echo " (" . str_replace('{count}', $catCount - 5, t('admin_plus_more')) . ")";
                                    }
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='mt-3 p-2 bg-red-100 dark:bg-red-800 rounded text-red-700 dark:text-red-200'>";
                                echo "<i class='fas fa-exclamation-triangle mr-1'></i> " . t('admin_no_active_categories');
                                echo "<br><small><a href='/admin/categories.php' class='underline'>" . t('admin_add_category') . "</a></small>";
                                echo "</div>";
                            }
                        } catch (Exception $e) {
                            echo "<div class='mt-3 p-2 bg-yellow-100 dark:bg-yellow-800 rounded text-yellow-700 dark:text-yellow-200'>";
                            echo "<i class='fas fa-exclamation-circle mr-1'></i> " . t('admin_category_status_check_failed');
                            echo "</div>";
                        }
                        ?>
                    </div>
                    
                    <button type="submit" name="generate_article" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-magic mr-2"></i><?php echo t('admin_generate_article'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Bot Ayarları -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                    <i class="fas fa-cogs mr-2"></i><?php echo t('admin_bot_settings'); ?>
                </h2>
            </div>
            <div class="p-6">
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo t('admin_bot_status'); ?>
                            </label>
                            <div class="flex items-center space-x-2">
                                <span class="<?php echo $isBotEnabled ? 'text-green-600' : 'text-red-600'; ?>">
                                    <i class="fas fa-circle text-xs"></i>
                                    <?php echo $isBotEnabled ? t('admin_active') : t('admin_inactive'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo t('admin_default_provider'); ?>
                            </label>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                <?php 
                                $providers = [
                                    'gemini' => t('admin_google_gemini'),
                                    'grok' => t('admin_xai_grok'),
                                    'huggingface' => t('admin_hugging_face')
                                ];
                                $currentProvider = getAiSetting('default_provider', AI_BOT_DEFAULT_PROVIDER);
                                echo $providers[$currentProvider] ?? $currentProvider;
                                ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                <?php echo t('admin_api_keys'); ?>
                            </label>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    <span class="text-sm"><?php echo t('admin_gemini_api_key'); ?></span>
                                    <span class="text-xs <?php echo getApiKeyStatus('gemini') ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo getApiKeyStatus('gemini') ? t('admin_defined') : t('admin_not_defined'); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    <span class="text-sm"><?php echo t('admin_grok_api_key'); ?></span>
                                    <span class="text-xs <?php echo getApiKeyStatus('grok') ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo getApiKeyStatus('grok') ? t('admin_defined') : t('admin_not_defined'); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    <span class="text-sm"><?php echo t('admin_hugging_face_api_key'); ?></span>
                                    <span class="text-xs <?php echo getApiKeyStatus('huggingface') ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo getApiKeyStatus('huggingface') ? t('admin_defined') : t('admin_not_defined'); ?>
                                    </span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2"><?php echo t('admin_api_keys_config_note'); ?></p>
                            <div class="mt-4">
                                <a href="ai_bot_settings.php" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    <i class="fas fa-cog mr-2"></i><?php echo t('admin_manage_bot_settings'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Son Makaleler -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                    <i class="fas fa-history mr-2"></i><?php echo t('admin_recent_ai_articles'); ?>
                </h2>
            </div>
            <div class="p-6">
                <?php if (!empty($recentArticles)): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentArticles as $article): ?>
                            <div class="border-l-4 border-blue-500 pl-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-200">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <?php echo t('admin_category'); ?>: <?php echo htmlspecialchars($article['category_name'] ?? t('admin_unknown')); ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo date('d.m.Y H:i', strtotime($article['created_at'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 dark:text-gray-300"><?php echo t('admin_no_ai_articles_generated'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log Görüntüleyici -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                    <i class="fas fa-file-alt mr-2"></i><?php echo t('admin_bot_logs'); ?> (Son 20 Satır)
                </h2>
            </div>
            <div class="p-6">
                <?php if (!empty($logContent)): ?>
                    <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-x-auto max-h-64 overflow-y-auto"><?php echo htmlspecialchars($logContent); ?></pre>
                <?php else: ?>
                    <p class="text-gray-600 dark:text-gray-300"><?php echo t('admin_log_file_not_found_or_empty'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cron Job Bilgileri -->
    <div class="mt-8 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-4">
            <i class="fas fa-clock mr-2"></i><?php echo t('admin_cron_job_setup'); ?>
        </h3>
        <div class="space-y-3">
            <p class="text-yellow-700 dark:text-yellow-300">
                <?php echo t('admin_daily_auto_article_generation_note'); ?>
            </p>
            <code class="block bg-gray-800 text-green-400 p-3 rounded text-sm">
                */5 * * * * /usr/local/lsws/lsphp81/bin/php <?php echo realpath(__DIR__ . '/../ai_bot_cron.php'); ?>
            </code>
            <p class="text-sm text-yellow-600 dark:text-yellow-400">
                <?php echo t('admin_cron_job_period_note'); ?>
            </p>
            <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-2">
                <i class="fas fa-exclamation-triangle mr-1"></i> <strong><?php echo t('admin_important_note'); ?>:</strong> 
                <?php echo t('admin_php_version_note'); ?>
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
