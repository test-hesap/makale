<?php
require_once '../includes/config.php';
checkAuth(true);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // HTML içeriği korumak için clean fonksiyonunu kullanmıyoruz
    $cookie_text = $_POST['cookie_text'];
    $cookie_button = clean($_POST['cookie_button']);
    $cookie_position = clean($_POST['cookie_position']);
    $cookie_enabled = isset($_POST['cookie_enabled']) ? 1 : 0;
    $cookie_bg_color = clean($_POST['cookie_bg_color']);
    $cookie_text_color = clean($_POST['cookie_text_color']);
    $cookie_button_color = clean($_POST['cookie_button_color']);
    $cookie_button_text_color = clean($_POST['cookie_button_text_color']);
    
    // Veritabanında ayarları güncelle
    try {
        $stmt = $db->prepare("UPDATE settings SET 
            cookie_text = ?, 
            cookie_button = ?, 
            cookie_position = ?, 
            cookie_enabled = ?,
            cookie_bg_color = ?,
            cookie_text_color = ?,
            cookie_button_color = ?,
            cookie_button_text_color = ?
            WHERE id = 1");
        $stmt->execute([
            $cookie_text, 
            $cookie_button, 
            $cookie_position, 
            $cookie_enabled,
            $cookie_bg_color,
            $cookie_text_color,
            $cookie_button_color,
            $cookie_button_text_color
        ]);
        $success = t('admin_cookie_settings_updated');
    } catch(PDOException $e) {
        $error = t('admin_cookie_settings_update_error') . ': ' . $e->getMessage();
    }
}

// Mevcut ayarları al
try {
    $stmt = $db->query("SELECT cookie_text, cookie_button, cookie_position, cookie_enabled, 
                       cookie_bg_color, cookie_text_color, cookie_button_color, cookie_button_text_color FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Eğer ayarlar yoksa varsayılan değerler
    if (!$settings) {
        $settings = [
            'cookie_text' => t('admin_default_cookie_text'),
            'cookie_button' => t('admin_default_cookie_button'),
            'cookie_position' => 'bottom',
            'cookie_enabled' => 1,
            'cookie_bg_color' => '#f3f4f6',
            'cookie_text_color' => '#1f2937',
            'cookie_button_color' => '#3b82f6',
            'cookie_button_text_color' => '#ffffff'
        ];
        
        // Veritabanında ayarları oluştur
        $stmt = $db->prepare("UPDATE settings SET 
            cookie_text = ?, 
            cookie_button = ?, 
            cookie_position = ?, 
            cookie_enabled = ?,
            cookie_bg_color = ?,
            cookie_text_color = ?,
            cookie_button_color = ?,
            cookie_button_text_color = ?
            WHERE id = 1");
        $stmt->execute([
            $settings['cookie_text'], 
            $settings['cookie_button'], 
            $settings['cookie_position'], 
            $settings['cookie_enabled'],
            $settings['cookie_bg_color'],
            $settings['cookie_text_color'],
            $settings['cookie_button_color'],
            $settings['cookie_button_text_color']
        ]);
    }
} catch(PDOException $e) {
    $error = t('admin_cookie_settings_fetch_error') . ': ' . $e->getMessage();
    $settings = [
        'cookie_text' => t('admin_default_cookie_text_short'),
        'cookie_button' => t('admin_default_cookie_button'),
        'cookie_position' => 'bottom',
        'cookie_enabled' => 1,
        'cookie_bg_color' => '#f3f4f6',
        'cookie_text_color' => '#1f2937',
        'cookie_button_color' => '#3b82f6',
        'cookie_button_text_color' => '#ffffff'
    ];
}

// Settings tablosunda sütunları kontrol et ve gerekirse ekle
try {
    $columns = ['cookie_text', 'cookie_button', 'cookie_position', 'cookie_enabled',
               'cookie_bg_color', 'cookie_text_color', 'cookie_button_color', 'cookie_button_text_color'];
    
    foreach ($columns as $column) {
        $result = $db->query("SHOW COLUMNS FROM settings LIKE '$column'");
        if ($result->rowCount() === 0) {
            $default = ($column == 'cookie_enabled') ? '1' : "''";
            $db->query("ALTER TABLE settings ADD COLUMN $column VARCHAR(255) DEFAULT $default");
        }
    }
} catch(PDOException $e) {
    $error = t('admin_database_check_error') . ': ' . $e->getMessage();
}

include 'includes/header.php';
?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <?php echo $success; ?>
</div>
<?php endif; ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-200">
            <?php echo t('admin_cookie_notification_settings'); ?>
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
            <?php echo t('admin_cookie_settings_description'); ?>
        </p>
    </div>
    <div class="p-6">
        <form method="post" action="">
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="cookie_enabled" class="form-checkbox h-5 w-5 text-blue-600" 
                           <?php echo ($settings['cookie_enabled'] == 1) ? 'checked' : ''; ?>>
                    <span class="ml-2 text-gray-700 dark:text-gray-300"><?php echo t('admin_enable_cookie_notification'); ?></span>
                </label>
            </div>
            
            <div class="mb-4">
                <label for="cookie_position" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_notification_position'); ?>:</label>
                <select id="cookie_position" name="cookie_position" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="bottom" <?php echo ($settings['cookie_position'] == 'bottom') ? 'selected' : ''; ?>><?php echo t('admin_position_bottom'); ?></option>
                    <option value="top" <?php echo ($settings['cookie_position'] == 'top') ? 'selected' : ''; ?>><?php echo t('admin_position_top'); ?></option>
                    <option value="bottom-left" <?php echo ($settings['cookie_position'] == 'bottom-left') ? 'selected' : ''; ?>><?php echo t('admin_position_bottom_left'); ?></option>
                    <option value="bottom-right" <?php echo ($settings['cookie_position'] == 'bottom-right') ? 'selected' : ''; ?>><?php echo t('admin_position_bottom_right'); ?></option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="cookie_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_notification_text'); ?>:</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1"><?php echo t('admin_html_tags_allowed'); ?></p>
                <textarea id="cookie_text" name="cookie_text" rows="3" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md"><?php echo $settings['cookie_text']; ?></textarea>
            </div>
            
            <div class="mb-4">
                <label for="cookie_button" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_button_text'); ?>:</label>
                <input type="text" id="cookie_button" name="cookie_button" value="<?php echo htmlspecialchars($settings['cookie_button']); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="cookie_bg_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_background_color'); ?>:</label>
                    <div class="flex">
                        <input type="color" id="cookie_bg_color" name="cookie_bg_color" value="<?php echo htmlspecialchars($settings['cookie_bg_color']); ?>" class="h-10 w-10 border-gray-300 rounded">
                        <input type="text" value="<?php echo htmlspecialchars($settings['cookie_bg_color']); ?>" class="ml-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md" data-color-input="cookie_bg_color">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="cookie_text_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_text_color'); ?>:</label>
                    <div class="flex">
                        <input type="color" id="cookie_text_color" name="cookie_text_color" value="<?php echo htmlspecialchars($settings['cookie_text_color']); ?>" class="h-10 w-10 border-gray-300 rounded">
                        <input type="text" value="<?php echo htmlspecialchars($settings['cookie_text_color']); ?>" class="ml-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md" data-color-input="cookie_text_color">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="cookie_button_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_button_background_color'); ?>:</label>
                    <div class="flex">
                        <input type="color" id="cookie_button_color" name="cookie_button_color" value="<?php echo htmlspecialchars($settings['cookie_button_color']); ?>" class="h-10 w-10 border-gray-300 rounded">
                        <input type="text" value="<?php echo htmlspecialchars($settings['cookie_button_color']); ?>" class="ml-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md" data-color-input="cookie_button_color">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="cookie_button_text_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_button_text_color'); ?>:</label>
                    <div class="flex">
                        <input type="color" id="cookie_button_text_color" name="cookie_button_text_color" value="<?php echo htmlspecialchars($settings['cookie_button_text_color']); ?>" class="h-10 w-10 border-gray-300 rounded">
                        <input type="text" value="<?php echo htmlspecialchars($settings['cookie_button_text_color']); ?>" class="ml-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md" data-color-input="cookie_button_text_color">
                    </div>
                </div>
            </div>
            
            <div class="mt-8 mb-4">
                <h4 class="text-md font-medium text-gray-900 dark:text-gray-200 mb-4"><?php echo t('admin_cookie_notification_preview'); ?></h4>
                <div id="cookie-preview" class="border rounded p-4 relative overflow-hidden">
                    <div id="preview-container" class="p-4 flex justify-between items-center" style="background-color: <?php echo htmlspecialchars($settings['cookie_bg_color']); ?>; color: <?php echo htmlspecialchars($settings['cookie_text_color']); ?>">
                        <div id="preview-text" class="flex-1 mr-4"><?php echo $settings['cookie_text']; ?></div>
                        <button type="button" id="preview-button" class="px-4 py-2 rounded" style="background-color: <?php echo htmlspecialchars($settings['cookie_button_color']); ?>; color: <?php echo htmlspecialchars($settings['cookie_button_text_color']); ?>"><?php echo htmlspecialchars($settings['cookie_button']); ?></button>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end mt-6">
                <button type="submit" name="save_settings" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php echo t('admin_save_settings'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Renk giriş alanları için değişiklik dinleyicileri
    document.querySelectorAll('input[type="color"]').forEach(function(colorInput) {
        const id = colorInput.id;
        const textInput = document.querySelector(`[data-color-input="${id}"]`);
        
        // Renk seçiciden metin alanına
        colorInput.addEventListener('input', function() {
            textInput.value = this.value;
            updatePreview();
        });
        
        // Metin alanından renk seçiciye
        textInput.addEventListener('input', function() {
            colorInput.value = this.value;
            updatePreview();
        });
    });
    
    // Metin değişikliklerini dinle
    document.getElementById('cookie_text').addEventListener('input', updatePreview);
    document.getElementById('cookie_button').addEventListener('input', updatePreview);
    document.getElementById('cookie_position').addEventListener('change', updatePreview);
    
    function updatePreview() {
        const text = document.getElementById('cookie_text').value;
        const buttonText = document.getElementById('cookie_button').value;
        const bgColor = document.getElementById('cookie_bg_color').value;
        const textColor = document.getElementById('cookie_text_color').value;
        const buttonColor = document.getElementById('cookie_button_color').value;
        const buttonTextColor = document.getElementById('cookie_button_text_color').value;
        const position = document.getElementById('cookie_position').value;
        
        // Önizleme alanını güncelle
        const previewContainer = document.getElementById('preview-container');
        const previewText = document.getElementById('preview-text');
        const previewButton = document.getElementById('preview-button');
        
        previewContainer.style.backgroundColor = bgColor;
        previewContainer.style.color = textColor;
        previewText.innerHTML = text; // HTML içeriğini düzgün göstermek için innerHTML kullanın
        previewButton.innerText = buttonText;
        previewButton.style.backgroundColor = buttonColor;
        previewButton.style.color = buttonTextColor;
        
        // Pozisyon simülasyonu
        const previewDiv = document.getElementById('cookie-preview');
        previewDiv.style.textAlign = position.includes('left') ? 'left' : position.includes('right') ? 'right' : 'center';
    }
    
    // İlk yükleme için önizlemeyi güncelle
    updatePreview();
});
</script>

<?php include 'includes/footer.php'; ?>
