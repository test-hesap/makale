<?php
require_once '../includes/config.php';
checkAuth('admin');

// Dil dosyasını dahil et
$current_lang = $_SESSION['lang'] ?? 'tr';
require_once "../includes/lang/{$current_lang}.php";

$error = '';
$success = '';

// Bakım modu ayarlarını getir
function getMaintenanceSettings() {
    global $db;
    $settings = [];
    $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('maintenance_mode', 'maintenance_title', 'maintenance_message', 'maintenance_title_en', 'maintenance_message_en', 'maintenance_end_time', 'maintenance_countdown_enabled', 'maintenance_contact_email')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

// Varsayılan değerler
$maintenance_settings = getMaintenanceSettings();
$maintenance_mode = $maintenance_settings['maintenance_mode'] ?? '0';
$maintenance_title = $maintenance_settings['maintenance_title'] ?? 'Site Bakımda';
$maintenance_message = $maintenance_settings['maintenance_message'] ?? 'Sitemiz şu anda bakım modunda. Kısa süre sonra tekrar hizmetinizde olacağız.';
$maintenance_title_en = $maintenance_settings['maintenance_title_en'] ?? 'Site Under Maintenance';
$maintenance_message_en = $maintenance_settings['maintenance_message_en'] ?? 'Our site is currently under maintenance. We will be back shortly.';
$maintenance_end_time = $maintenance_settings['maintenance_end_time'] ?? '';
$maintenance_countdown_enabled = $maintenance_settings['maintenance_countdown_enabled'] ?? '1';
$maintenance_contact_email = $maintenance_settings['maintenance_contact_email'] ?? 'info@' . $_SERVER['HTTP_HOST'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        
        // Bakım modu durumu
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $stmt->execute(['maintenance_mode', $maintenance_mode, $maintenance_mode]);
        
        // Bakım modu başlığı
        if (isset($_POST['maintenance_title'])) {
            $title = trim($_POST['maintenance_title']);
            $stmt->execute(['maintenance_title', $title, $title]);
        }
        
        // Bakım modu mesajı
        if (isset($_POST['maintenance_message'])) {
            $message = trim($_POST['maintenance_message']);
            $stmt->execute(['maintenance_message', $message, $message]);
        }
        
        // İngilizce bakım modu başlığı
        if (isset($_POST['maintenance_title_en'])) {
            $title_en = trim($_POST['maintenance_title_en']);
            $stmt->execute(['maintenance_title_en', $title_en, $title_en]);
        }
        
        // İngilizce bakım modu mesajı
        if (isset($_POST['maintenance_message_en'])) {
            $message_en = trim($_POST['maintenance_message_en']);
            $stmt->execute(['maintenance_message_en', $message_en, $message_en]);
        }
        
        // Bakım modu bitiş zamanı
        if (isset($_POST['maintenance_end_time'])) {
            $end_time = $_POST['maintenance_end_time'];
            $stmt->execute(['maintenance_end_time', $end_time, $end_time]);
        }
        
        // Geri sayım aktif/pasif
        $countdown_enabled = isset($_POST['maintenance_countdown_enabled']) ? '1' : '0';
        $stmt->execute(['maintenance_countdown_enabled', $countdown_enabled, $countdown_enabled]);
        
        // İletişim email adresi
        if (isset($_POST['maintenance_contact_email'])) {
            $contact_email = trim($_POST['maintenance_contact_email']);
            $stmt->execute(['maintenance_contact_email', $contact_email, $contact_email]);
        }
        
        $success = $lang['maintenance_settings_saved'];
        
        // Güncel ayarları tekrar yükle
        $maintenance_settings = getMaintenanceSettings();
        $maintenance_mode = $maintenance_settings['maintenance_mode'] ?? '0';
        $maintenance_title = $maintenance_settings['maintenance_title'] ?? 'Site Bakımda';
        $maintenance_message = $maintenance_settings['maintenance_message'] ?? 'Sitemiz şu anda bakım modunda. Kısa süre sonra tekrar hizmetinizde olacağız.';
        $maintenance_title_en = $maintenance_settings['maintenance_title_en'] ?? 'Site Under Maintenance';
        $maintenance_message_en = $maintenance_settings['maintenance_message_en'] ?? 'Our site is currently under maintenance. We will be back shortly.';
        $maintenance_end_time = $maintenance_settings['maintenance_end_time'] ?? '';
        $maintenance_countdown_enabled = $maintenance_settings['maintenance_countdown_enabled'] ?? '1';
        
    } catch (Exception $e) {
        $error = 'Ayarlar güncellenirken bir hata oluştu: ' . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    <i class="fas fa-tools mr-3 text-orange-500"></i>
                    <?php echo $lang['maintenance_mode']; ?> Yönetimi
                </h1>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Durum:</span>
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $maintenance_mode === '1' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; ?>">
                        <?php echo $maintenance_mode === '1' ? $lang['maintenance_status_active'] : $lang['maintenance_status_inactive']; ?>
                    </span>
                </div>
            </div>

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

            <form method="POST" class="space-y-6">
                <!-- Bakım Modu Kontrolü -->
                <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2"><?php echo $lang['maintenance_mode']; ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <?php echo $lang['maintenance_mode_help']; ?>
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="maintenance_mode" value="1" class="sr-only peer" <?php echo $maintenance_mode === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Bakım Sayfası İçeriği -->
                <div class="space-y-4">
                    <div>
                        <label for="maintenance_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $lang['maintenance_page_title']; ?> (Türkçe)
                        </label>
                        <input type="text" id="maintenance_title" name="maintenance_title" 
                               value="<?php echo htmlspecialchars($maintenance_title); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               placeholder="Site Bakımda">
                    </div>

                    <div>
                        <label for="maintenance_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $lang['maintenance_page_message']; ?> (Türkçe)
                        </label>
                        <textarea id="maintenance_message" name="maintenance_message" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                  placeholder="Sitemiz şu anda bakım modunda. Kısa süre sonra tekrar hizmetinizde olacağız."><?php echo htmlspecialchars($maintenance_message); ?></textarea>
                    </div>

                    <div>
                        <label for="maintenance_title_en" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $lang['maintenance_page_title']; ?> (English)
                        </label>
                        <input type="text" id="maintenance_title_en" name="maintenance_title_en" 
                               value="<?php echo htmlspecialchars($maintenance_title_en); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               placeholder="Site Under Maintenance">
                    </div>

                    <div>
                        <label for="maintenance_message_en" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $lang['maintenance_page_message']; ?> (English)
                        </label>
                        <textarea id="maintenance_message_en" name="maintenance_message_en" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                  placeholder="Our site is currently under maintenance. We will be back shortly."><?php echo htmlspecialchars($maintenance_message_en); ?></textarea>
                    </div>
                </div>

                <!-- Geri Sayım Ayarları -->
                <div class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2"><?php echo $lang['maintenance_countdown']; ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <?php echo $lang['maintenance_countdown_help']; ?>
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="maintenance_countdown_enabled" value="1" class="sr-only peer" <?php echo $maintenance_countdown_enabled === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div>
                        <label for="maintenance_end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $lang['maintenance_end_time']; ?>
                        </label>
                        <input type="datetime-local" id="maintenance_end_time" name="maintenance_end_time" 
                               value="<?php echo $maintenance_end_time; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <?php echo $lang['maintenance_auto_end_help']; ?>
                        </p>
                    </div>
                </div>

                <!-- Hızlı Zaman Ayarları -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"><?php echo $lang['maintenance_quick_time']; ?></h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <button type="button" onclick="setMaintenanceTime(1)" class="px-3 py-2 text-sm bg-blue-100 text-blue-800 rounded hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                            <?php echo $lang['maintenance_1_hour']; ?>
                        </button>
                        <button type="button" onclick="setMaintenanceTime(2)" class="px-3 py-2 text-sm bg-blue-100 text-blue-800 rounded hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                            <?php echo $lang['maintenance_2_hours']; ?>
                        </button>
                        <button type="button" onclick="setMaintenanceTime(6)" class="px-3 py-2 text-sm bg-blue-100 text-blue-800 rounded hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                            <?php echo $lang['maintenance_6_hours']; ?>
                        </button>
                        <button type="button" onclick="setMaintenanceTime(24)" class="px-3 py-2 text-sm bg-blue-100 text-blue-800 rounded hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                            <?php echo $lang['maintenance_1_day']; ?>
                        </button>
                    </div>
                </div>

                <!-- İletişim Ayarları -->
                <div class="bg-green-50 dark:bg-green-900/20 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4"><?php echo $lang['maintenance_contact_settings']; ?></h3>
                    <div>
                        <label for="maintenance_contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $lang['maintenance_contact_email']; ?>
                        </label>
                        <input type="email" id="maintenance_contact_email" name="maintenance_contact_email" 
                               value="<?php echo htmlspecialchars($maintenance_contact_email); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               placeholder="destek@siteniz.com">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <?php echo $lang['maintenance_contact_help']; ?>
                        </p>
                    </div>
                </div>

                <!-- Kaydet Butonu -->
                <div class="flex justify-between items-center pt-6">
                    <a href="index.php" class="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <?php echo $lang['maintenance_back_to_dashboard']; ?>
                    </a>
                    <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        <?php echo $lang['maintenance_save_settings']; ?>
                    </button>
                </div>
            </form>

            <!-- Önizleme Butonu -->
            <?php if ($maintenance_mode === '1'): ?>
                <div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200"><?php echo $lang['maintenance_preview']; ?></h4>
                            <p class="text-sm text-yellow-600 dark:text-yellow-300">
                                <?php echo $lang['maintenance_preview_help']; ?>
                            </p>
                        </div>
                        <a href="../maintenance.php" target="_blank" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            <?php echo $lang['maintenance_preview']; ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function setMaintenanceTime(hours) {
    const now = new Date();
    now.setHours(now.getHours() + hours);
    
    // Format the date for datetime-local input
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hour = String(now.getHours()).padStart(2, '0');
    const minute = String(now.getMinutes()).padStart(2, '0');
    
    const formattedDate = `${year}-${month}-${day}T${hour}:${minute}`;
    document.getElementById('maintenance_end_time').value = formattedDate;
}
</script>

<?php require_once 'includes/footer.php'; ?>
