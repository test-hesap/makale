<?php 
// includes/ads.php içindeki showAd() fonksiyonu varsa onu kullan, yoksa kendi fonksiyonumuzu kullan
if (function_exists('showAd')) {
    echo showAd('footer_top'); 
} else {
    echo showAdFromSettings('footer_top');
}
// Footer üstü reklam
?>

<footer class="bg-gray-800 dark:bg-gray-900 text-white py-12">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap justify-between">            <div class="w-full md:w-1/3 mb-6 md:mb-0">
                <h3 class="text-xl font-bold mb-4 text-white"><?php echo getSetting('site_title'); ?></h3>
                <p class="text-gray-400 dark:text-gray-300"><?php echo getSetting('site_description'); ?></p>
            </div><div class="w-full md:w-1/3 mb-6 md:mb-0">
                <h3 class="text-xl font-bold mb-4 text-white"><?php echo __('quick_links'); ?></h3><ul class="text-gray-400 dark:text-gray-300">
                    <li class="mb-2">
                        <a href="<?php echo getSetting('site_url'); ?>/uyeler" class="hover:text-white">
                            <i class="fas fa-users mr-2"></i><?php echo __('members'); ?>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?php echo getSetting('site_url'); ?>/kategoriler" class="hover:text-white">
                            <i class="fas fa-list mr-2"></i><?php echo __('categories'); ?>
                        </a>
                    </li>                    <li class="mb-2">
                        <a href="<?php echo getSetting('site_url'); ?>/hakkimda" class="hover:text-white">
                            <i class="fas fa-info-circle mr-2"></i><?php echo __('about'); ?>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?php echo getSetting('site_url'); ?>/privacy-policy.php" class="hover:text-white">
                            <i class="fas fa-shield-alt mr-2"></i><?php echo __('privacy_policy'); ?>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?php echo getSetting('site_url'); ?>/cerez-politikasi.php" class="hover:text-white">
                            <i class="fas fa-cookie mr-2"></i><?php echo __('cookie_policy'); ?>
                        </a>
                    </li>
                </ul>
            </div>            <div class="w-full md:w-1/3">                <h3 class="text-xl font-bold mb-4 text-white"><?php echo __('contact'); ?></h3>
                <p class="text-gray-400 dark:text-gray-300">
                    <a href="<?php echo getSetting('site_url'); ?>/iletisim" class="hover:text-white">
                        <i class="fas fa-envelope mr-2"></i><?php echo __('contact'); ?>
                    </a>
                </p>
                <?php if ($social_links = getSetting('social_links')): ?>
                <div class="mt-4 flex space-x-3">
                    <?php 
                    $social_links = json_decode($social_links, true);
                    if (is_array($social_links)):
                        foreach ($social_links as $platform => $link):
                            if (empty($link)) continue;
                            $icon = '';
                            switch ($platform) {
                                case 'facebook': $icon = 'fab fa-facebook'; break;
                                case 'twitter': $icon = 'fab fa-twitter'; break;
                                case 'instagram': $icon = 'fab fa-instagram'; break;
                                case 'linkedin': $icon = 'fab fa-linkedin'; break;
                                case 'youtube': $icon = 'fab fa-youtube'; break;
                                default: $icon = 'fas fa-link'; break;
                            }
                    ?>
                    <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="text-gray-400 hover:text-white">
                        <i class="<?php echo $icon; ?>"></i>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php 
        // includes/ads.php içindeki showAd() fonksiyonu varsa onu kullan, yoksa kendi fonksiyonumuzu kullan
        if (function_exists('showAd')) {
            echo showAd('footer'); 
        } else {
            echo showAdFromSettings('footer');
        }
        // Footer reklamı
        ?>
          <div class="border-t border-gray-700 dark:border-gray-800 mt-8 pt-8 text-center text-gray-400 dark:text-gray-300">
            <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting('site_title'); ?>. Tüm hakları saklıdır.</p>
        </div>
    </div>
</footer>

<?php
// Çerez ayarlarını al
try {
    $cookie_settings = $db->query("SELECT cookie_text, cookie_button, cookie_position, cookie_enabled, 
                                 cookie_bg_color, cookie_text_color, cookie_button_color, cookie_button_text_color 
                                 FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($cookie_settings && $cookie_settings['cookie_enabled'] == 1) {
        // CSS ve JS dosyaları
        echo '<link rel="stylesheet" href="/assets/css/cookie-consent.css">';
        
        // Cookie ayarlarını JavaScript'e aktar
        echo '<script>
            window.cookieSettings = {
                position: "' . htmlspecialchars($cookie_settings['cookie_position']) . '",
                text: ' . json_encode($cookie_settings['cookie_text']) . ',
                buttonText: "' . htmlspecialchars($cookie_settings['cookie_button']) . '",
                bgColor: "' . htmlspecialchars($cookie_settings['cookie_bg_color']) . '",
                textColor: "' . htmlspecialchars($cookie_settings['cookie_text_color']) . '",
                buttonColor: "' . htmlspecialchars($cookie_settings['cookie_button_color']) . '",
                buttonTextColor: "' . htmlspecialchars($cookie_settings['cookie_button_text_color']) . '"
            };
        </script>';
        echo '<script src="/assets/js/cookie-consent.js"></script>';
    }
} catch(PDOException $e) {
    // Hata durumunda sessizce devam et
    error_log("Çerez bildirimi ayarları alınamadı: " . $e->getMessage());
}

// Mobil sticky reklam
if (function_exists('showAd')) {
    echo showAd('mobile_sticky'); 
}

// Sidebar açılır-kapanır özelliği için JavaScript
echo '<script src="/assets/js/sidebar.js"></script>';
?>

</body>
</html>