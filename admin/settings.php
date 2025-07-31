<?php
require_once '../includes/config.php';
checkAuth('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        
        // Logo silme kontrolü
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
            // Mevcut logo bilgisini al
            $logo_query = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
            $logo_query->execute(['site_logo']);
            $current_logo = $logo_query->fetchColumn();
            
            if ($current_logo) {
                // Dosyayı sil
                $logo_file_path = '../' . $current_logo;
                if (file_exists($logo_file_path)) {
                    unlink($logo_file_path);
                }
                
                // Veritabanından logoyu kaldır
                $stmt->execute(['site_logo', '', '']);
            }
        }
        
        // Koyu mod logo silme kontrolü
        if (isset($_POST['remove_logo_dark']) && $_POST['remove_logo_dark'] == '1') {
            // Mevcut koyu mod logo bilgisini al
            $logo_query = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
            $logo_query->execute(['site_logo_dark']);
            $current_logo = $logo_query->fetchColumn();
            
            if ($current_logo) {
                // Dosyayı sil
                $logo_file_path = '../' . $current_logo;
                if (file_exists($logo_file_path)) {
                    unlink($logo_file_path);
                }
                
                // Veritabanından logoyu kaldır
                $stmt->execute(['site_logo_dark', '', '']);
            }
        }
        
        // Favicon silme kontrolü
        if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] == '1') {
            // Mevcut favicon bilgisini al
            $favicon_query = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
            $favicon_query->execute(['favicon']);
            $current_favicon = $favicon_query->fetchColumn();
            
            if ($current_favicon) {
                // Dosyayı sil
                $favicon_file_path = '../' . $current_favicon;
                if (file_exists($favicon_file_path)) {
                    unlink($favicon_file_path);
                }
                
                // Veritabanından favicon'u kaldır
                $stmt->execute(['favicon', '', '']);
            }
        }
        
        // Önce tüm checkbox türündeki ayarları 0 olarak ayarla
        $checkbox_settings = [
            'turnstile_enabled',
            'turnstile_login',
            'turnstile_register',
            'turnstile_contact',
            'turnstile_article',
            'smtp_enabled'
        ];
        
        foreach ($checkbox_settings as $key) {
            if (!isset($_POST['settings'][$key])) {
                $stmt->execute([$key, '0', '0']);
            }
        }
        
        // Sonra gelen tüm ayarları kaydet
        foreach ($_POST['settings'] as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        
        // Favicon yükleme işlemi
        if(isset($_FILES['favicon']) && $_FILES['favicon']['error'] == 0) {
            $allowed = ['ico', 'png'];
            $ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
            
            if(in_array(strtolower($ext), $allowed)) {
                $upload_dir = '../uploads/favicon/';
                $filename = 'favicon.' . $ext;
                $target_file = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['favicon']['tmp_name'], $target_file)) {
                    // Veritabanına favicon bilgisini kaydet
                    $favicon_path = 'uploads/favicon/' . $filename;
                    $stmt->execute(['favicon', $favicon_path, $favicon_path]);
                } else {
                    $error = t('admin_favicon_upload_error');
                }
            } else {
                $error = t('admin_favicon_extension_error');
            }
        }
        
        // Logo yükleme işlemi
        if(isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $ext = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
            
            if(in_array(strtolower($ext), $allowed)) {
                $upload_dir = '../uploads/logo/';
                
                // Dizin yoksa oluştur
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Benzersiz dosya adı oluştur (önbellek sorunlarını önlemek için)
                $filename = 'site_logo_' . time() . '_' . uniqid() . '.' . $ext;
                $target_file = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['site_logo']['tmp_name'], $target_file)) {
                    // Önce eski logoyu sil
                    $old_logo_query = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
                    $old_logo_query->execute(['site_logo']);
                    $old_logo = $old_logo_query->fetchColumn();
                    
                    if ($old_logo && file_exists('../' . $old_logo)) {
                        @unlink('../' . $old_logo);
                    }
                    
                    // Veritabanına logo bilgisini kaydet
                    $logo_path = 'uploads/logo/' . $filename;
                    $stmt->execute(['site_logo', $logo_path, $logo_path]);
                } else {
                    $error = t('admin_logo_upload_error');
                }
            } else {
                $error = t('admin_logo_extension_error');
            }
        }
        
        // Koyu mod logo yükleme işlemi
        if(isset($_FILES['site_logo_dark']) && $_FILES['site_logo_dark']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $ext = pathinfo($_FILES['site_logo_dark']['name'], PATHINFO_EXTENSION);
            
            if(in_array(strtolower($ext), $allowed)) {
                $upload_dir = '../uploads/logo/';
                
                // Dizin yoksa oluştur
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Benzersiz dosya adı oluştur (önbellek sorunlarını önlemek için)
                $filename = 'site_logo_dark_' . time() . '_' . uniqid() . '.' . $ext;
                $target_file = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['site_logo_dark']['tmp_name'], $target_file)) {
                    // Önce eski koyu mod logoyu sil
                    $old_logo_query = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
                    $old_logo_query->execute(['site_logo_dark']);
                    $old_logo = $old_logo_query->fetchColumn();
                    
                    if ($old_logo && file_exists('../' . $old_logo)) {
                        @unlink('../' . $old_logo);
                    }
                    
                    // Veritabanına koyu mod logo bilgisini kaydet
                    $logo_path = 'uploads/logo/' . $filename;
                    $stmt->execute(['site_logo_dark', $logo_path, $logo_path]);
                } else {
                    $error = t('admin_logo_dark_upload_error');
                }
            } else {
                $error = t('admin_logo_dark_extension_error');
            }
        }
        
        if(empty($error)) {
            $success = t('admin_settings_updated');
        }
    } catch(PDOException $e) {
        $error = t('admin_settings_update_error') . ': ' . $e->getMessage();
    }
}

// Mevcut ayarları getir
$settings = [];
$stmt = $db->query("SELECT * FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}

// Debug için site başlığı değerini kontrol et
error_log("Site Başlığı Değeri: " . ($settings['site_title'] ?? 'Bulunamadı'));
?>
<?php include 'includes/header.php'; ?>

<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo t('admin_site_settings'); ?>
        </h1>
    </div><?php if ($error): ?>
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <form method="post" class="space-y-6" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">                            <!-- Genel Ayarlar -->
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_general_settings'); ?></h2>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_site_title'); ?></label>
                                    <input type="text" name="settings[site_title]" 
                                           value="<?php echo $settings['site_title'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_site_url'); ?></label>
                                    <input type="text" name="settings[site_url]" 
                                           value="<?php echo $settings['site_url'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="https://example.com">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?php echo t('admin_site_url_description'); ?></p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_site_description'); ?></label>
                                    <textarea name="settings[site_description]" rows="3"
                                              class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"><?php echo $settings['site_description'] ?? ''; ?></textarea>
                                </div>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_keywords'); ?></label>
                                    <input type="text" name="settings[site_keywords]" 
                                           value="<?php echo $settings['site_keywords'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="<?php echo t('admin_keywords_placeholder'); ?>">
                                </div>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_favicon'); ?></label>
                                    <div class="flex items-center space-x-4">
                                        <?php if (!empty($settings['favicon'])): ?>
                                        <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded relative group">
                                            <img src="../<?php echo $settings['favicon']; ?>" alt="Mevcut Favicon" class="h-8 w-8">
                                            <button type="button" class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" 
                                                    onclick="if(confirm('<?php echo t('admin_remove_favicon_confirm'); ?>')) document.getElementById('remove_favicon').value = '1'; this.closest('form').submit();">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <input type="hidden" id="remove_favicon" name="remove_favicon" value="0">
                                        </div>
                                        <?php endif; ?>
                                        <div class="file-input-container">
                                            <label for="favicon-input" class="file-input-button">
                                                <?php echo t('admin_choose_file'); ?>
                                            </label>
                                            <span class="file-input-name" id="favicon-name"></span>
                                            <input type="file" name="favicon" id="favicon-input" class="file-input hidden">
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?php echo t('admin_favicon_description'); ?></p>
                                </div>
                            </div>

                            <!-- İçerik Ayarları -->
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_content_settings'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_posts_per_page'); ?></label>
                                    <input type="number" name="settings[posts_per_page]" 
                                           value="<?php echo $settings['posts_per_page'] ?? '10'; ?>"
                                           min="1" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_pagination_type'); ?></label>
                                    <select name="settings[pagination_type]" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="numbered" <?php echo isset($settings['pagination_type']) && $settings['pagination_type'] == 'numbered' ? 'selected' : ''; ?>><?php echo t('admin_numbered_pagination'); ?></option>
                                        <option value="infinite_scroll" <?php echo isset($settings['pagination_type']) && $settings['pagination_type'] == 'infinite_scroll' ? 'selected' : ''; ?>><?php echo t('admin_infinite_scroll'); ?></option>
                                        <option value="load_more" <?php echo isset($settings['pagination_type']) && $settings['pagination_type'] == 'load_more' ? 'selected' : ''; ?>><?php echo t('admin_load_more_button'); ?></option>
                                    </select>
                                </div>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_allow_comments'); ?></label>
                                    <select name="settings[allow_comments]"
                                            class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="1" <?php echo ($settings['allow_comments'] ?? '1') == '1' ? 'selected' : ''; ?>><?php echo t('admin_comments_enabled'); ?></option>
                                        <option value="0" <?php echo ($settings['allow_comments'] ?? '1') == '0' ? 'selected' : ''; ?>><?php echo t('admin_comments_disabled'); ?></option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_site_logo'); ?> (<?php echo t('admin_light_mode'); ?>)</label>
                                    <div class="flex items-center space-x-4">
                                        <?php if (!empty($settings['site_logo'])): ?>
                                        <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded relative group">
                                            <img src="../<?php echo $settings['site_logo']; ?>" alt="Mevcut Logo" class="h-12">
                                            <button type="button" class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" 
                                                    onclick="if(confirm('<?php echo t('admin_remove_logo_confirm'); ?>')) document.getElementById('remove_logo').value = '1'; this.closest('form').submit();">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <input type="hidden" id="remove_logo" name="remove_logo" value="0">
                                        </div>
                                        <?php endif; ?>
                                        <div class="file-input-container">
                                            <label for="site-logo-input" class="file-input-button">
                                                <?php echo t('admin_choose_file'); ?>
                                            </label>
                                            <span class="file-input-name" id="site-logo-name"></span>
                                            <input type="file" name="site_logo" id="site-logo-input" class="file-input hidden">
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?php echo t('admin_site_logo_description'); ?></p>
                                    
                                    <!-- Koyu Mod Logo -->
                                    <div class="mt-4">
                                        <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_site_logo'); ?> (<?php echo t('admin_dark_mode'); ?>)</label>
                                        <div class="flex items-center space-x-4">
                                            <?php if (!empty($settings['site_logo_dark'])): ?>
                                            <div class="bg-gray-700 p-2 rounded relative group">
                                                <img src="../<?php echo $settings['site_logo_dark']; ?>" alt="Koyu Mod Logo" class="h-12">
                                                <button type="button" class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" 
                                                        onclick="if(confirm('<?php echo t('admin_remove_dark_logo_confirm'); ?>')) document.getElementById('remove_logo_dark').value = '1'; this.closest('form').submit();">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                                <input type="hidden" id="remove_logo_dark" name="remove_logo_dark" value="0">
                                            </div>
                                            <?php endif; ?>
                                            <div class="file-input-container">
                                                <label for="site-logo-dark-input" class="file-input-button">
                                                    <?php echo t('admin_choose_file'); ?>
                                                </label>
                                                <span class="file-input-name" id="site-logo-dark-name"></span>
                                                <input type="file" name="site_logo_dark" id="site-logo-dark-input" class="file-input hidden">
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?php echo t('admin_site_logo_dark_description'); ?></p>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <input type="checkbox" id="show_site_title" name="settings[show_site_title_with_logo]" 
                                               value="1" <?php echo isset($settings['show_site_title_with_logo']) && $settings['show_site_title_with_logo'] == '1' ? 'checked' : ''; ?>
                                               class="mr-2 dark:accent-blue-600">
                                        <label for="show_site_title" class="dark:text-gray-300"><?php echo t('admin_show_site_title_with_logo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <!-- SEO Ayarları -->
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_seo_settings'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_google_analytics'); ?></label>
                                    <input type="text" name="settings[google_analytics]" 
                                           value="<?php echo $settings['google_analytics'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="UA-XXXXX-Y">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_robots_txt'); ?></label>
                                    <textarea name="settings[robots_txt]" rows="4"
                                              class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                              placeholder="User-agent: *"><?php echo $settings['robots_txt'] ?? ''; ?></textarea>
                                </div>
                            </div>

                            <!-- Sosyal Medya -->
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_social_media'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_facebook'); ?></label>
                                    <input type="text" name="settings[social_facebook]" 
                                           value="<?php echo $settings['social_facebook'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="https://facebook.com/...">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_twitter'); ?></label>
                                    <input type="text" name="settings[social_twitter]" 
                                           value="<?php echo $settings['social_twitter'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="https://twitter.com/...">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_instagram'); ?></label>
                                    <input type="text" name="settings[social_instagram]" 
                                           value="<?php echo $settings['social_instagram'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="https://instagram.com/...">
                                </div>
                            </div>
                        </div>
                          <!-- Premium Abonelik Fiyatları -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_premium_prices'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_monthly_price'); ?></label>
                                    <input type="number" step="0.01" min="0" name="settings[premium_monthly_price]" 
                                           value="<?php echo $settings['premium_monthly_price'] ?? '29.99'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_yearly_price'); ?></label>
                                    <input type="number" step="0.01" min="0" name="settings[premium_yearly_price]" 
                                           value="<?php echo $settings['premium_yearly_price'] ?? '239.99'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_yearly_discount'); ?></label>
                                    <input type="number" step="1" min="0" max="100" name="settings[premium_yearly_discount]" 
                                           value="<?php echo $settings['premium_yearly_discount'] ?? '33'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_premium_features'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_premium_features'); ?></label>
                                    <textarea name="settings[premium_features]" rows="6" 
                                              class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                              placeholder="Reklamsız deneyim&#10;Özel içeriklere erişim&#10;Öncelikli destek"><?php echo $settings['premium_features'] ?? "Reklamsız deneyim\nÖzel içeriklere erişim\nÖncelikli destek\nVe daha fazlası..."; ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Gelişmiş SEO Ayarları -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_advanced_seo_settings'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_seo_title_format'); ?></label>
                                    <input type="text" name="settings[seo_title_format]" 
                                           value="<?php echo $settings['seo_title_format'] ?? '%title% - %sitename%'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="%title% - %sitename%">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo t('admin_seo_title_format_description'); ?></p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_seo_meta_desc_limit'); ?></label>
                                    <input type="number" name="settings[seo_meta_desc_limit]" 
                                           value="<?php echo $settings['seo_meta_desc_limit'] ?? '160'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           min="50" max="320">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_seo_canonical_format'); ?></label>
                                    <input type="text" name="settings[seo_canonical_format]" 
                                           value="<?php echo $settings['seo_canonical_format'] ?? '%protocol%://%domain%%path%'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo t('admin_seo_canonical_format_description'); ?></p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_seo_custom_meta'); ?></label>
                                    <textarea name="settings[seo_custom_meta]" rows="4" 
                                              class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                              placeholder="<meta name='author' content='Site Adı'>..."><?php echo $settings['seo_custom_meta'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_social_media_seo'); ?></h2>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_seo_default_image'); ?></label>
                                    <input type="text" name="settings[seo_default_image]" 
                                           value="<?php echo $settings['seo_default_image'] ?? '/assets/img/social-default.jpg'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="seo_open_graph" name="settings[seo_open_graph]" 
                                           value="1" <?php echo ($settings['seo_open_graph'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="seo_open_graph" class="dark:text-gray-300"><?php echo t('admin_enable_facebook_open_graph'); ?></label>
                                </div>
                                
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="seo_twitter_cards" name="settings[seo_twitter_cards]" 
                                           value="1" <?php echo ($settings['seo_twitter_cards'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="seo_twitter_cards" class="dark:text-gray-300"><?php echo t('admin_enable_twitter_cards'); ?></label>
                                </div>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_twitter_site_username'); ?></label>
                                    <input type="text" name="settings[seo_twitter_site]" 
                                           value="<?php echo $settings['seo_twitter_site'] ?? '@siteadi'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="@siteadi">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_facebook_page_id'); ?></label>
                                    <input type="text" name="settings[seo_fb_page_id]" 
                                           value="<?php echo $settings['seo_fb_page_id'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="123456789012345">
                                </div>
                            </div>
                        </div>

                        <!-- Sitemap ve İndeksleme Ayarları -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_sitemap_settings'); ?></h2>
                                  <div class="flex items-center mt-4">
                                    <input type="checkbox" id="sitemap_enabled" name="settings[sitemap_enabled]" 
                                           value="1" <?php echo ($settings['sitemap_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="sitemap_enabled" class="dark:text-gray-300"><?php echo t('admin_enable_auto_sitemap'); ?></label>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_sitemap_filename'); ?></label>
                                    <input type="text" name="settings[sitemap_filename]" 
                                           value="<?php echo $settings['sitemap_filename'] ?? 'sitemap.xml'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_sitemap_frequency'); ?></label>
                                    <select name="settings[sitemap_frequency]" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="daily" <?php echo ($settings['sitemap_frequency'] ?? 'daily') == 'daily' ? 'selected' : ''; ?>><?php echo t('admin_daily_frequency'); ?></option>
                                        <option value="weekly" <?php echo ($settings['sitemap_frequency'] ?? 'daily') == 'weekly' ? 'selected' : ''; ?>><?php echo t('admin_weekly_frequency'); ?></option>
                                        <option value="monthly" <?php echo ($settings['sitemap_frequency'] ?? 'daily') == 'monthly' ? 'selected' : ''; ?>><?php echo t('admin_monthly_frequency'); ?></option>
                                    </select>
                                </div>
                                  <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_sitemap_priorities'); ?></label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-gray-600 dark:text-gray-400 text-sm mb-1"><?php echo t('admin_home_page'); ?></label>
                                            <input type="text" name="settings[sitemap_priority_home]" 
                                                value="<?php echo $settings['sitemap_priority_home'] ?? '1.0'; ?>"
                                                class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-gray-600 dark:text-gray-400 text-sm mb-1"><?php echo t('admin_categories'); ?></label>
                                            <input type="text" name="settings[sitemap_priority_categories]" 
                                                value="<?php echo $settings['sitemap_priority_categories'] ?? '0.8'; ?>"
                                                class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-gray-600 dark:text-gray-400 text-sm mb-1"><?php echo t('admin_articles'); ?></label>
                                            <input type="text" name="settings[sitemap_priority_articles]" 
                                                value="<?php echo $settings['sitemap_priority_articles'] ?? '0.6'; ?>"
                                                class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-gray-600 dark:text-gray-400 text-sm mb-1"><?php echo t('admin_pages'); ?></label>
                                            <input type="text" name="settings[sitemap_priority_pages]" 
                                                value="<?php echo $settings['sitemap_priority_pages'] ?? '0.5'; ?>"
                                                class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_indexing_archive_settings'); ?></h2>
                                  <div class="flex items-center mt-4">
                                    <input type="checkbox" id="seo_allow_indexing" name="settings[seo_allow_indexing]" 
                                           value="1" <?php echo ($settings['seo_allow_indexing'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="seo_allow_indexing" class="dark:text-gray-300"><?php echo t('admin_allow_search_engine_indexing'); ?></label>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_noindex_pages'); ?></label>
                                    <textarea name="settings[seo_noindex_pages]" rows="3" 
                                            class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                            placeholder="/etiketler/&#10;/aramalar/&#10;/uye-profil/"><?php echo $settings['seo_noindex_pages'] ?? "/etiketler/\n/aramalar/\n/uye-profil/"; ?></textarea>
                                </div>
                                
                                <div class="mt-4">
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_archives_robots'); ?></label>
                                    <select name="settings[seo_archives_robots]" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="index,follow" <?php echo ($settings['seo_archives_robots'] ?? 'index,follow') == 'index,follow' ? 'selected' : ''; ?>><?php echo t('admin_index_follow'); ?></option>
                                        <option value="noindex,follow" <?php echo ($settings['seo_archives_robots'] ?? 'index,follow') == 'noindex,follow' ? 'selected' : ''; ?>><?php echo t('admin_noindex_follow'); ?></option>
                                        <option value="index,nofollow" <?php echo ($settings['seo_archives_robots'] ?? 'index,follow') == 'index,nofollow' ? 'selected' : ''; ?>><?php echo t('admin_index_nofollow'); ?></option>
                                        <option value="noindex,nofollow" <?php echo ($settings['seo_archives_robots'] ?? 'index,follow') == 'noindex,nofollow' ? 'selected' : ''; ?>><?php echo t('admin_noindex_nofollow'); ?></option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_google_verification_code'); ?></label>
                                    <input type="text" name="settings[seo_google_verification]" 
                                           value="<?php echo $settings['seo_google_verification'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="google-site-verification=xxxxxxxxxxxxxxxx">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_bing_verification_code'); ?></label>
                                    <input type="text" name="settings[seo_bing_verification]" 
                                           value="<?php echo $settings['seo_bing_verification'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="msvalidate.01=xxxxxxxxxxxxxxxx">
                                </div>
                            </div>
                        </div>

                        <!-- E-posta (SMTP) Ayarları -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_email_settings'); ?></h2>
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="smtp_enabled" name="settings[smtp_enabled]" 
                                           value="1" <?php echo ($settings['smtp_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="smtp_enabled" class="dark:text-gray-300"><?php echo t('admin_enable_smtp_email'); ?></label>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_host'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="smtp_host" name="settings[smtp_host]" 
                                               value="<?php echo $settings['smtp_host'] ?? 'smtp.example.com'; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="smtp.gmail.com">
                                        <button type="button" onclick="togglePasswordVisibility('smtp_host')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_port'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="smtp_port" name="settings[smtp_port]" 
                                               value="<?php echo $settings['smtp_port'] ?? '587'; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="587">
                                        <button type="button" onclick="togglePasswordVisibility('smtp_port')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_security'); ?></label>
                                    <select name="settings[smtp_secure]" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="tls" <?php echo ($settings['smtp_secure'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>><?php echo t('admin_tls'); ?></option>
                                        <option value="ssl" <?php echo ($settings['smtp_secure'] ?? 'tls') == 'ssl' ? 'selected' : ''; ?>><?php echo t('admin_ssl'); ?></option>
                                        <option value="none" <?php echo ($settings['smtp_secure'] ?? 'tls') == 'none' ? 'selected' : ''; ?>><?php echo t('admin_none'); ?></option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_smtp_credentials'); ?></h2>
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_username'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="smtp_username" name="settings[smtp_username]" 
                                               value="<?php echo $settings['smtp_username'] ?? ''; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="user@example.com">
                                        <button type="button" onclick="togglePasswordVisibility('smtp_username')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_password'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="smtp_password" name="settings[smtp_password]" 
                                               value="<?php echo $settings['smtp_password'] ?? ''; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="••••••••">
                                        <button type="button" onclick="togglePasswordVisibility('smtp_password')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg id="smtp_password_show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg id="smtp_password_hide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_from_email'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="smtp_from_email" name="settings[smtp_from_email]" 
                                               value="<?php echo $settings['smtp_from_email'] ?? $settings['contact_email'] ?? 'info@example.com'; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="info@example.com">
                                        <button type="button" onclick="togglePasswordVisibility('smtp_from_email')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_smtp_from_name'); ?></label>
                                    <input type="text" name="settings[smtp_from_name]" 
                                           value="<?php echo $settings['smtp_from_name'] ?? $settings['site_title'] ?? 'Site Adı'; ?>"
                                           class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                           placeholder="Site Adı">
                                </div>
                            </div>
                        </div>

                        <!-- Cloudflare Turnstile Spam Koruması -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_cloudflare_turnstile_spam_protection'); ?></h2>
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="turnstile_enabled" name="settings[turnstile_enabled]" 
                                           value="1" <?php echo ($settings['turnstile_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="turnstile_enabled" class="dark:text-gray-300"><?php echo t('admin_enable_cloudflare_turnstile'); ?></label>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_turnstile_site_key'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="turnstile_site_key" name="settings[turnstile_site_key]" 
                                               value="<?php echo $settings['turnstile_site_key'] ?? ''; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="1x00000000000000000000AA">
                                        <button type="button" onclick="togglePasswordVisibility('turnstile_site_key')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg id="turnstile_site_key_show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg id="turnstile_site_key_hide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_turnstile_secret_key'); ?></label>
                                    <div class="relative">
                                        <input type="password" id="turnstile_secret_key" name="settings[turnstile_secret_key]" 
                                               value="<?php echo $settings['turnstile_secret_key'] ?? ''; ?>"
                                               class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500"
                                               placeholder="1x0000000000000000000000000000000AA">
                                        <button type="button" onclick="togglePasswordVisibility('turnstile_secret_key')" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                                            <svg id="turnstile_secret_key_show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg id="turnstile_secret_key_hide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200"><?php echo t('admin_turnstile_application_settings'); ?></h2>
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="turnstile_login" name="settings[turnstile_login]" 
                                           value="1" <?php echo ($settings['turnstile_login'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="turnstile_login" class="dark:text-gray-300"><?php echo t('admin_enable_turnstile_login'); ?></label>
                                </div>
                                
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="turnstile_register" name="settings[turnstile_register]" 
                                           value="1" <?php echo ($settings['turnstile_register'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="turnstile_register" class="dark:text-gray-300"><?php echo t('admin_enable_turnstile_register'); ?></label>
                                </div>
                                
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="turnstile_contact" name="settings[turnstile_contact]" 
                                           value="1" <?php echo ($settings['turnstile_contact'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="turnstile_contact" class="dark:text-gray-300"><?php echo t('admin_enable_turnstile_contact'); ?></label>
                                </div>
                                
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" id="turnstile_article" name="settings[turnstile_article]" 
                                           value="1" <?php echo ($settings['turnstile_article'] ?? '1') == '1' ? 'checked' : ''; ?>
                                           class="mr-2 dark:accent-blue-600">
                                    <label for="turnstile_article" class="dark:text-gray-300"><?php echo t('admin_enable_turnstile_article'); ?></label>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 mb-2"><?php echo t('admin_turnstile_theme'); ?></label>
                                    <select name="settings[turnstile_theme]" class="w-full px-4 py-2 border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="auto" <?php echo ($settings['turnstile_theme'] ?? 'auto') == 'auto' ? 'selected' : ''; ?>><?php echo t('admin_auto_theme'); ?></option>
                                        <option value="light" <?php echo ($settings['turnstile_theme'] ?? 'auto') == 'light' ? 'selected' : ''; ?>><?php echo t('admin_light_theme'); ?></option>
                                        <option value="dark" <?php echo ($settings['turnstile_theme'] ?? 'auto') == 'dark' ? 'selected' : ''; ?>><?php echo t('admin_dark_theme'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-t dark:border-gray-700 pt-6">
                            <button type="submit" class="bg-blue-500 dark:bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                                <?php echo t('admin_save_settings'); ?>
                            </button>
                        </div>
                    </form>                </div>
</div>

<!-- Şifre görünürlüğünü değiştirme JavaScript kodu -->
<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    
    // Sayısal alanlar için özel işlem
    if (inputId === 'smtp_port') {
        if (input.type === "password") {
            input.type = "number";
        } else {
            input.type = "password";
        }
    } else {
        // Metin alanları için standart işlem
        if (input.type === "password") {
            input.type = "text";
        } else {
            input.type = "password";
        }
    }
}

// Özel dosya seçme butonu işlevselliği
document.addEventListener('DOMContentLoaded', function() {
    // Favicon dosya seçimi
    const faviconInput = document.getElementById('favicon-input');
    const faviconName = document.getElementById('favicon-name');
    
    if (faviconInput) {
        faviconInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                faviconName.textContent = this.files[0].name;
            } else {
                faviconName.textContent = '';
            }
        });
    }
    
    // Site logo dosya seçimi
    const siteLogo = document.getElementById('site-logo-input');
    const siteLogoName = document.getElementById('site-logo-name');
    
    if (siteLogo) {
        siteLogo.addEventListener('change', function() {
            if (this.files.length > 0) {
                siteLogoName.textContent = this.files[0].name;
            } else {
                siteLogoName.textContent = '';
            }
        });
    }
    
    // Site dark mode logo dosya seçimi
    const siteLogoDark = document.getElementById('site-logo-dark-input');
    const siteLogoDarkName = document.getElementById('site-logo-dark-name');
    
    if (siteLogoDark) {
        siteLogoDark.addEventListener('change', function() {
            if (this.files.length > 0) {
                siteLogoDarkName.textContent = this.files[0].name;
            } else {
                siteLogoDarkName.textContent = '';
            }
        });
    }
});
</script>

<style>
    .file-input-container {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    
    .file-input-button {
        display: inline-block;
        padding: 0.5rem 1rem;
        background-color: #4b5563;
        color: white;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .dark .file-input-button {
        background-color: #374151;
    }
    
    .file-input-button:hover {
        background-color: #374151;
    }
    
    .dark .file-input-button:hover {
        background-color: #4b5563;
    }
    
    .file-input-name {
        margin-left: 10px;
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .dark .file-input-name {
        color: #9ca3af;
    }
    
    .hidden {
        display: none !important;
    }
</style>

<?php include 'includes/footer.php'; ?>
