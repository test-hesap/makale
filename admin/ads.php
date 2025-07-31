<?php
require_once '../includes/config.php';
checkAuth(true); // Sadece adminler erişebilir

include 'includes/header.php';

$success = '';
$error = '';

// Ayarları kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Reklam durumunu güncelle
        $ad_status = $_POST['ad_status'] ?? 'inactive';
        updateSetting('ad_status', $ad_status);
        
        // Makale arası reklam aralığını güncelle
        $ad_interval = (int)$_POST['ad_article_interval'] ?? 3;
        updateSetting('ad_article_interval', $ad_interval);
          // Reklam kodlarını güncelle
        $ad_positions = [
            'ad_header',
            'ad_header_below', // Header altı reklam eklendi
            'ad_sidebar_top',
            'ad_sidebar_bottom',
            'ad_article_top',
            'ad_article_bottom',
            'ad_between_articles',
            'ad_article_middle',
            'ad_footer_top',
            'ad_footer', // Footer alt reklam eklendi
            'ad_mobile_sticky' // Mobil sticky reklam eklendi
        ];

        foreach ($ad_positions as $position) {
            $value = $_POST[$position] ?? '';
            updateSetting($position, $value);
        }

        $success = t('admin_ads_settings_updated');
    } catch (Exception $e) {
        $error = t('admin_ads_settings_update_error') . ': ' . $e->getMessage();
    }
}

// Mevcut ayarları getir
$settings = [
    'ad_status' => getSetting('ad_status'),
    'ad_article_interval' => getSetting('ad_article_interval'),
    'ad_header' => getSetting('ad_header'),
    'ad_header_below' => getSetting('ad_header_below'), // Header altı reklam ayarını ekledik
    'ad_sidebar_top' => getSetting('ad_sidebar_top'),
    'ad_sidebar_bottom' => getSetting('ad_sidebar_bottom'),
    'ad_article_top' => getSetting('ad_article_top'),
    'ad_article_bottom' => getSetting('ad_article_bottom'),
    'ad_between_articles' => getSetting('ad_between_articles'),
    'ad_article_middle' => getSetting('ad_article_middle'),
    'ad_footer_top' => getSetting('ad_footer_top'),
    'ad_footer' => getSetting('ad_footer'), // Footer alt reklam ayarını ekledik
    'ad_mobile_sticky' => getSetting('ad_mobile_sticky') // Mobil sticky reklam ayarını ekledik
];

?>
<!DOCTYPE html>
<html lang="<?php echo getActiveLang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="max-w-full mx-auto"><div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo t('admin_ad_management'); ?>
        </h1>    </div><?php if ($success): ?>
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            <?php echo t('admin_ad_settings'); ?>
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-6">                            <!-- Genel Ayarlar -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-b dark:border-gray-700">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-200"><?php echo t('admin_general_settings'); ?></h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_ad_status'); ?></label>
                        <select name="ad_status" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="active" <?php echo $settings['ad_status'] === 'active' ? 'selected' : ''; ?>><?php echo t('admin_active'); ?></option>
                            <option value="inactive" <?php echo $settings['ad_status'] === 'inactive' ? 'selected' : ''; ?>><?php echo t('admin_inactive'); ?></option>
                        </select>
                        <p class="mt-1 text-sm <?php echo $settings['ad_status'] === 'active' ? 'text-green-500' : 'text-red-500'; ?>">
                            <?php echo t('admin_current_status'); ?>: <strong><?php echo $settings['ad_status'] === 'active' ? t('admin_active') : t('admin_inactive'); ?></strong>
                            <?php if ($settings['ad_status'] !== 'active'): ?>
                            <br><span class="font-bold"><?php echo t('admin_ads_inactive_warning'); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_ad_interval'); ?></label>
                        <input type="number" name="ad_article_interval" value="<?php echo htmlspecialchars($settings['ad_article_interval']); ?>" 
                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500" 
                               min="1" max="10">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_ad_interval_description'); ?></p>
                    </div>
                </div>
            </div>            <!-- Header Ad -->                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-200"><?php echo t('admin_header_ad'); ?></h2>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_ad_code'); ?></label>
                        <textarea name="ad_header" rows="4" 
                              class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                              placeholder="<?php echo t('admin_ad_code_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_header']); ?></textarea>
                    </div>
                </div>
                
                <!-- Below Header Ad -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-200"><?php echo t('admin_header_below_ad'); ?></h2>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_ad_code'); ?></label>
                        <textarea name="ad_header_below" rows="4" 
                              class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                              placeholder="<?php echo t('admin_header_below_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_header_below']); ?></textarea>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_header_below_ad_description'); ?></p>
                    </div>
                </div>            <!-- Sidebar Ads -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-b dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4"><?php echo t('admin_sidebar_ads'); ?></h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_sidebar_top_ad'); ?></label>
                        <textarea name="ad_sidebar_top" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_sidebar_top_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_sidebar_top']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_sidebar_bottom_ad'); ?></label>
                        <textarea name="ad_sidebar_bottom" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_sidebar_bottom_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_sidebar_bottom']); ?></textarea>
                    </div>
                </div>
            </div>            <!-- Article Ads -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-b dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4"><?php echo t('admin_article_ads'); ?></h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_article_top_ad'); ?></label>
                        <textarea name="ad_article_top" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_article_top_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_article_top']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_article_middle_ad'); ?></label>
                        <textarea name="ad_article_middle" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_article_middle_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_article_middle']); ?></textarea>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <?php 
                            if (empty($settings['ad_article_middle'])) {
                                echo '<span class="text-red-500">' . t('admin_article_middle_ad_missing') . '</span>';
                            } else {
                                echo t('admin_article_middle_ad_exists', strlen($settings['ad_article_middle']));
                            }
                            ?>
                        </p>
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_article_bottom_ad'); ?></label>
                        <textarea name="ad_article_bottom" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_article_bottom_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_article_bottom']); ?></textarea>
                    </div>
                </div>
            </div>            <!-- Other Ads -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-b dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4"><?php echo t('admin_other_ads'); ?></h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_between_articles_ad'); ?></label>
                        <textarea name="ad_between_articles" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_between_articles_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_between_articles']); ?></textarea>
                    </div>                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_footer_top_ad'); ?></label>
                        <textarea name="ad_footer_top" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_footer_top_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_footer_top']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_footer_bottom_ad'); ?></label>
                        <textarea name="ad_footer" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_footer_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_footer']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Ads -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-b dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4"><?php echo t('admin_mobile_ads'); ?></h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_mobile_sticky_ad'); ?></label>
                        <textarea name="ad_mobile_sticky" rows="4" 
                                  class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                  placeholder="<?php echo t('admin_mobile_sticky_ad_placeholder'); ?>"><?php echo htmlspecialchars($settings['ad_mobile_sticky']); ?></textarea>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <?php echo t('admin_mobile_sticky_description'); ?>
                        </p>
                    </div>
                </div>
            </div><div class="flex justify-end pt-6 border-t dark:border-gray-700">                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white rounded-lg transition duration-300 focus:outline-none">
                                    <i class="fas fa-save mr-2"></i> <?php echo t('admin_save_settings'); ?>
                                </button>
                            </div>
                        </form>
                    </div>                </div>
</div>
<?php include 'includes/footer.php'; ?>
