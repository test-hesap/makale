<?php
require_once '../includes/config.php';
checkAuth(true);

// Editor API ayarları fonksiyonları
function getEditorSetting($key, $default = '') {
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

function setEditorSetting($key, $value) {
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

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_tinymce_api'])) {
            $tinymce_api_key = $_POST['tinymce_api_key'] ?? '';
            
            setEditorSetting('tinymce_api_key', $tinymce_api_key);
            
            $success = t('admin_tinymce_api_saved');
        }
    } catch (Exception $e) {
        $error = t('admin_tinymce_api_error') . ': ' . $e->getMessage();
    }
}

// Mevcut ayarları al
$currentSettings = [
    'tinymce_api_key' => getEditorSetting('tinymce_api_key')
];

include 'includes/header.php';
?>

<div class="max-w-full mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-edit mr-3"></i><?php echo t('admin_editor_api_management'); ?>
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

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <!-- TinyMCE API Ayarları -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">
                <i class="fas fa-keyboard mr-2"></i>TinyMCE Editor API
            </h2>
            
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                <p class="text-blue-800 dark:text-blue-200 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?php echo t('admin_tinymce_api_description'); ?>
                </p>
            </div>

            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <?php echo t('admin_tinymce_api_key'); ?>
                    </label>
                    
                    <?php if (!empty($currentSettings['tinymce_api_key'])): ?>
                        <div class="mb-2 text-sm text-green-600 dark:text-green-400">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?php echo t('admin_tinymce_api_current'); ?>: 
                            <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-xs">
                                <?php echo substr($currentSettings['tinymce_api_key'], 0, 20) . '...'; ?>
                            </code>
                        </div>
                    <?php else: ?>
                        <div class="mb-2 text-sm text-orange-600 dark:text-orange-400">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?php echo t('admin_tinymce_api_not_set'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <input type="password" 
                           name="tinymce_api_key" 
                           value="<?php echo htmlspecialchars($currentSettings['tinymce_api_key']); ?>"
                           placeholder="<?php echo t('admin_tinymce_api_placeholder'); ?>"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        <p><?php echo t('admin_tinymce_api_help'); ?> 
                           <a href="https://www.tiny.cloud/auth/signup/" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                               <?php echo t('admin_tinymce_api_link'); ?>
                           </a>
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <p><i class="fas fa-shield-alt mr-1"></i><?php echo t('admin_tinymce_api_security'); ?></p>
                        <p><i class="fas fa-info-circle mr-1"></i><?php echo t('admin_tinymce_api_note'); ?></p>
                    </div>
                    
                    <button type="submit" 
                            name="save_tinymce_api"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-save mr-2"></i><?php echo t('admin_tinymce_api_save'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- API Kullanım Bilgileri -->
        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-800 dark:text-white mb-4">
                <i class="fas fa-lightbulb mr-2"></i>API Kullanım Bilgileri
            </h3>
            
            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-start">
                    <i class="fas fa-check text-green-500 mr-2 mt-0.5"></i>
                    <span>Admin panelinde makale ekleme/düzenleme sayfalarında kullanılır</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-500 mr-2 mt-0.5"></i>
                    <span>Üye makale ekleme sayfasında kullanılır</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check text-green-500 mr-2 mt-0.5"></i>
                    <span>API anahtarı değiştirildiğinde tüm editörler otomatik güncellenir</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-blue-500 mr-2 mt-0.5"></i>
                    <span>API anahtarı veritabanında güvenli şekilde saklanır</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
