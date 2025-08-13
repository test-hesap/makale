<?php
/**
 * AI Bot API Key Manager
 * Admin panelinden API anahtarlarini yonetmek icin
 */

require_once '../includes/config.php';
checkAuth(true);

// API keys tablosunu olustur
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_bot_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // Tablo zaten varsa hata vermesin
}

// Ayarlari getir
function getAiSetting($key, $default = '') {
    global $db;
    
    $stmt = $db->prepare("SELECT setting_value FROM ai_bot_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['setting_value'] : $default;
}

// Ayarlari kaydet
function setAiSetting($key, $value) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO ai_bot_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
    ");
    
    return $stmt->execute([$key, $value, $value]);
}

$success = '';
$error = '';

// Form islemlerini handle et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_api_keys'])) {
            // AI Text API Keys
            setAiSetting('gemini_api_key', $_POST['gemini_api_key'] ?? '');
            setAiSetting('grok_api_key', $_POST['grok_api_key'] ?? '');
            setAiSetting('huggingface_api_key', $_POST['huggingface_api_key'] ?? '');
            setAiSetting('default_provider', $_POST['default_provider'] ?? 'gemini');
            setAiSetting('bot_enabled', isset($_POST['bot_enabled']) ? '1' : '0');
            
            // AI Image API Keys
            setAiSetting('google_search_api_key', $_POST['google_search_api_key'] ?? '');
            setAiSetting('google_search_engine_id', $_POST['google_search_engine_id'] ?? '');
            setAiSetting('unsplash_access_key', $_POST['unsplash_access_key'] ?? '');
            
            $success = __('admin_api_keys_saved_success');
        }
    } catch (Exception $e) {
        $error = __('error') . ": " . $e->getMessage();
    }
}

// Mevcut ayarlari al
$currentSettings = [
    'gemini_api_key' => getAiSetting('gemini_api_key'),
    'grok_api_key' => getAiSetting('grok_api_key'),
    'huggingface_api_key' => getAiSetting('huggingface_api_key'),
    'default_provider' => getAiSetting('default_provider', 'gemini'),
    'bot_enabled' => getAiSetting('bot_enabled', '1'),
    'google_search_api_key' => getAiSetting('google_search_api_key'),
    'google_search_engine_id' => getAiSetting('google_search_engine_id'),
    'unsplash_access_key' => getAiSetting('unsplash_access_key')
];

include 'includes/header.php';
?>

<div class="max-w-full mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-key mr-3"></i><?php echo __('admin_ai_bot_api_settings'); ?>
        </h1>
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

    <form method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6" autocomplete="off">
        <div class="space-y-6">
            <!-- Genel Ayarlar -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200 mb-4"><?php echo __('admin_general_settings'); ?></h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="bot_enabled" <?php echo $currentSettings['bot_enabled'] ? 'checked' : ''; ?> class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo __('admin_bot_active'); ?></span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <?php echo __('admin_default_provider'); ?>
                        </label>
                        <select name="default_provider" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200">
                            <option value="gemini" <?php echo $currentSettings['default_provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                            <option value="grok" <?php echo $currentSettings['default_provider'] === 'grok' ? 'selected' : ''; ?>>xAI Grok</option>
                            <option value="huggingface" <?php echo $currentSettings['default_provider'] === 'huggingface' ? 'selected' : ''; ?>>Hugging Face</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Metin AI API Anahtarlari -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200 mb-4">
                    <i class="fas fa-robot mr-2"></i><?php echo __('admin_text_ai_api_keys'); ?>
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fab fa-google mr-2"></i><?php echo __('admin_google_gemini_api_key'); ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo htmlspecialchars($currentSettings['gemini_api_key']); ?>" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="<?php echo __('admin_enter_gemini_api_key'); ?>" autocomplete="off">
                            <button type="button" onclick="togglePasswordVisibility('gemini_api_key')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <a href="https://makersuite.google.com/" target="_blank" class="text-blue-600 hover:underline"><?php echo __('admin_get_api_key_here'); ?></a>
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fas fa-robot mr-2"></i><?php echo __('admin_xai_grok_api_key'); ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="grok_api_key" name="grok_api_key" value="<?php echo htmlspecialchars($currentSettings['grok_api_key']); ?>" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="<?php echo __('admin_enter_grok_api_key'); ?>" autocomplete="off">
                            <button type="button" onclick="togglePasswordVisibility('grok_api_key')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <a href="https://console.x.ai/" target="_blank" class="text-blue-600 hover:underline"><?php echo __('admin_get_api_key_here'); ?></a>
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fas fa-brain mr-2"></i><?php echo __('admin_hugging_face_api_key'); ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="huggingface_api_key" name="huggingface_api_key" value="<?php echo htmlspecialchars($currentSettings['huggingface_api_key']); ?>" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="<?php echo __('admin_enter_hugging_face_api_key'); ?>" autocomplete="off">
                            <button type="button" onclick="togglePasswordVisibility('huggingface_api_key')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <a href="https://huggingface.co/settings/tokens" target="_blank" class="text-blue-600 hover:underline"><?php echo __('admin_get_api_key_here'); ?></a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Görsel AI API Anahtarlari -->
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200 mb-4">
                    <i class="fas fa-image mr-2"></i><?php echo __('admin_image_ai_api_keys'); ?>
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fab fa-google mr-2"></i><?php echo __('admin_google_search_api_key'); ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="google_search_api_key" name="google_search_api_key" value="<?php echo htmlspecialchars($currentSettings['google_search_api_key']); ?>" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="<?php echo __('admin_enter_google_search_api_key'); ?>" autocomplete="off">
                            <button type="button" onclick="togglePasswordVisibility('google_search_api_key')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <a href="https://developers.google.com/custom-search/v1/introduction" target="_blank" class="text-blue-600 hover:underline"><?php echo __('admin_get_api_key_here'); ?></a> (<?php echo __('admin_daily_free_searches'); ?>)
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fab fa-google mr-2"></i><?php echo __('admin_google_search_engine_id'); ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="google_search_engine_id" name="google_search_engine_id" value="<?php echo htmlspecialchars($currentSettings['google_search_engine_id']); ?>" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="<?php echo __('admin_enter_google_search_engine_id'); ?>" autocomplete="off">
                            <button type="button" onclick="togglePasswordVisibility('google_search_engine_id')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <a href="https://cse.google.com/" target="_blank" class="text-blue-600 hover:underline"><?php echo __('admin_create_custom_search_engine'); ?></a>
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            <i class="fas fa-camera mr-2"></i><?php echo __('admin_unsplash_access_key'); ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="unsplash_access_key" name="unsplash_access_key" value="<?php echo htmlspecialchars($currentSettings['unsplash_access_key']); ?>" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="<?php echo __('admin_enter_unsplash_access_key'); ?>" autocomplete="off">
                            <button type="button" onclick="togglePasswordVisibility('unsplash_access_key')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <a href="https://unsplash.com/developers" target="_blank" class="text-blue-600 hover:underline"><?php echo __('admin_get_api_key_here'); ?></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end space-x-4">
            <a href="ai_article_bot.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                <?php echo __('admin_cancel'); ?>
            </a>
            <button type="submit" name="save_api_keys" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i><?php echo __('admin_save'); ?>
            </button>
        </div>
    </form>

    <!-- Guvenlik Uyarisi -->
    <div class="mt-6 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                    <?php echo __('admin_security_warning'); ?>
                </h3>
                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                    <ul class="list-disc list-inside">
                        <li><?php echo __('admin_never_share_api_keys'); ?></li>
                        <li><?php echo __('admin_regularly_renew_keys'); ?></li>
                        <li><?php echo __('admin_remove_unnecessary_permissions'); ?></li>
                        <li><?php echo __('admin_settings_stored_in_database'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Şifre görünürlüğünü değiştirme JavaScript kodu -->
<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}
</script>

<?php include 'includes/footer.php'; ?>
