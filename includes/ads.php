<?php
// Eğer showAd fonksiyonu zaten tanımlanmışsa tekrar tanımlamayalım
if (!function_exists('showAd')) {
    // Reklam gösterme fonksiyonu
    function showAd($position = 'sidebar') {        
        // Debug için bilgileri kontrol edelim
        $user_id = $_SESSION['user_id'] ?? 0;
        
        // Admin kontrolü - Adminlere reklam gösterme
        if (function_exists('isAdmin') && isAdmin()) {
            error_log("showAd: isAdmin() fonksiyonu true döndü, adminlere reklam gösterilmiyor");
            return ''; // Admin kullanıcılara reklam gösterme
        }
        
        // Her zaman güncel durumu almak için veritabanını kontrol et
        if (function_exists('isPremium')) {
            // Premium durumunu zorunlu olarak veritabanından taze alalım (cache kullanmayalım)
            $is_premium_func = isPremium(true); // forceRefresh = true
            error_log("showAd: isPremium(true) sonucu: " . ($is_premium_func ? 'true' : 'false'));
            
            if ($is_premium_func) {
                error_log("showAd: isPremium() fonksiyonu true döndü, reklam gösterilmiyor");
                return ''; // Premium üyelere reklam gösterme
            }
        } else {
            // isPremium fonksiyonu mevcut değilse, session kontrolü yap
            $is_premium = isset($_SESSION['is_premium']) ? (int)$_SESSION['is_premium'] : 0;
            $premium_until = $_SESSION['premium_until'] ?? null;
            
            // Log bilgisi
            error_log("showAd: isPremium() fonksiyonu mevcut değil - session kontrolü yapılıyor - user_id: $user_id, is_premium: $is_premium, premium_until: " . ($premium_until ?? 'null'));
            
            // Direkt session kontrolü
            if ($is_premium && $premium_until && strtotime($premium_until) >= time()) {
                error_log("showAd: Session bilgisine göre premium üye, reklam gösterilmiyor");
                return ''; // Premium üyelere reklam gösterme
            }
        }
        
        // Reklam durumunu kontrol et
        $ad_status = 'active'; // Varsayılan olarak aktif kabul ediyoruz, misafirlere ve normal üyelere göstermek için
        try {
            $setting_ad_status = getSetting('ad_status');
            if ($setting_ad_status) {
                $ad_status = $setting_ad_status;
            }
        } catch (Exception $e) {
            error_log("showAd: Reklam durumu alınırken hata: " . $e->getMessage());
        }
        
        // Reklam aktif değilse gösterme
        if ($ad_status !== 'active') {
            return '';
        }
        
        // Reklam içeriği - sabit reklam kodları ve admin panelinden girilen kodları göster
        $ad_content = '';
        
        // Admin panelinden girilen reklam kodlarını kullanmaya çalış, hata olursa varsayılan reklamları göster
        switch ($position) {
            case 'header':                // Önce veritabanından özel kodu almaya çalış
                try {
                    $custom_code = getSetting('ad_header');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-header">'.$custom_code.'</div>';
                    } else {
                        // Özel reklam kodu girilmemiş ise hiçbir şey gösterme
                        return '';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Header reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
            
            case 'header_below':
                // Header altı reklam kodu
                try {
                    $custom_code = getSetting('ad_header_below');
                    // Premium abone uyarısı - sadece mobil cihazlar için (boyut küçültüldü)
                    $premium_alert = '
                    <div class="premium-alert bg-gradient-to-r from-blue-600 to-purple-600 text-white py-2 px-3 rounded-md shadow-sm text-center mb-3 md:hidden">
                        <p class="font-medium text-sm">Premium üyelere özel içeriklere erişin!</p>
                        <a href="/premium.php" class="inline-block mt-1 bg-white text-blue-700 font-bold py-1 px-3 text-xs rounded-full hover:bg-blue-100 transition-colors">Hemen Abone Ol</a>
                    </div>';
                    
                    if (!empty($custom_code)) {
                        return $premium_alert . '<div class="ad-container ad-header-below w-full max-w-7xl mx-auto my-4 px-4 flex justify-center">'.$custom_code.'</div>';
                    } else {
                        // Özel reklam kodu girilmemiş olsa bile premium uyarısını göster
                        return $premium_alert;
                    }
                } catch (Exception $e) {
                    error_log("showAd: Header altı reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="bg-gray-100 py-2 mb-4">
                    <div class="max-w-7xl mx-auto px-4">
                        <div class="bg-white border border-gray-200 rounded-md p-3 shadow-sm text-center">
                            <p class="text-sm text-gray-500">
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">REKLAM</span>
                                Reklamları görmek istemiyor musunuz? 
                                <a href="/premium.php" class="text-blue-600 hover:underline">Premium üye olun</a>
                            </p>
                            <div class="py-2">
                                <a href="#" class="block">
                                    <img src="https://via.placeholder.com/728x90?text=728x90+Header+Banner" alt="Reklam" class="mx-auto">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>';
                break;
                  case 'sidebar_top':
                try {
                    $custom_code = getSetting('ad_sidebar_top');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-sidebar-top">'.$custom_code.'<div class="px-4 py-2 bg-gray-50 text-center"><a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a></div></div>';
                    } else {
                        // Özel reklam kodu girilmemişse sadece premium link'ini göster
                        return '<div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                                <div class="px-4 py-2 bg-gray-50 text-center">
                                    <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                                </div>
                            </div>';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Sidebar_top reklam kodu alınırken hata: " . $e->getMessage());
                    // Hata durumunda da sadece premium linki göster
                    return '<div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                            <div class="px-4 py-2 bg-gray-50 text-center">
                                <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                            </div>
                        </div>';
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                    <div class="border-b border-gray-200 px-4 py-2">
                        <p class="text-sm text-gray-500">
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">REKLAM</span>
                        </p>
                    </div>
                    <div class="p-4">
                        <a href="#" class="block">
                            <img src="https://via.placeholder.com/300x250?text=300x250+Sidebar+Top+Ad" alt="Reklam" class="mx-auto">
                        </a>
                    </div>
                    <div class="px-4 py-2 bg-gray-50 text-center">
                        <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                    </div>
                </div>';
                break;
                  case 'sidebar':
                try {
                    $custom_code = getSetting('ad_sidebar_top');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-sidebar">'.$custom_code.'<div class="px-4 py-2 bg-gray-50 text-center"><a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a></div></div>';
                    } else {
                        // Özel reklam kodu girilmemişse sadece premium link'ini göster
                        return '<div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                                <div class="px-4 py-2 bg-gray-50 text-center">
                                    <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                                </div>
                            </div>';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Sidebar reklam kodu alınırken hata: " . $e->getMessage());
                    // Hata durumunda da sadece premium linki göster
                    return '<div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                            <div class="px-4 py-2 bg-gray-50 text-center">
                                <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                            </div>
                        </div>';
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                    <div class="border-b border-gray-200 px-4 py-2">
                        <p class="text-sm text-gray-500">
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">REKLAM</span>
                        </p>
                    </div>
                    <div class="p-4">
                        <a href="#" class="block">
                            <img src="https://via.placeholder.com/300x250?text=300x250+Sidebar+Ad" alt="Reklam" class="mx-auto">
                        </a>
                    </div>
                    <div class="px-4 py-2 bg-gray-50 text-center">
                        <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                    </div>
                </div>';
                break;
                  case 'sidebar_bottom':
                try {
                    $custom_code = getSetting('ad_sidebar_bottom');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-sidebar-bottom">'.$custom_code.'<div class="px-4 py-2 bg-gray-50 text-center"><a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a></div></div>';
                    } else {
                        // Özel reklam kodu girilmemişse sadece premium link'ini göster
                        return '<div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                                <div class="px-4 py-2 bg-gray-50 text-center">
                                    <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                                </div>
                            </div>';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Sidebar_bottom reklam kodu alınırken hata: " . $e->getMessage());
                    // Hata durumunda da sadece premium linki göster
                    return '<div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                            <div class="px-4 py-2 bg-gray-50 text-center">
                                <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                            </div>
                        </div>';
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="bg-white border border-gray-200 rounded-md shadow-sm mb-4">
                    <div class="border-b border-gray-200 px-4 py-2">
                        <p class="text-sm text-gray-500">
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">REKLAM</span>
                        </p>
                    </div>
                    <div class="p-4">
                        <a href="#" class="block">
                            <img src="https://via.placeholder.com/300x250?text=300x250+Sidebar+Bottom+Ad" alt="Reklam" class="mx-auto">
                        </a>
                    </div>
                    <div class="px-4 py-2 bg-gray-50 text-center">
                        <a href="/premium.php" class="text-xs text-blue-600 hover:underline">Premium üye olarak reklamları kaldırın</a>
                    </div>
                </div>';
                break;
                  case 'article_top':
                try {
                    $custom_code = getSetting('ad_article_top');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-article-top">'.$custom_code.'</div>';
                    } else {
                        // Özel reklam kodu girilmemişse hiçbir şey gösterme
                        return '';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Article_top reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="bg-white border-b border-gray-200 p-4 text-center">
                    <p class="text-sm text-gray-500 mb-2">
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">REKLAM</span>
                        Makale üstü reklam
                    </p>
                    <div class="py-2">
                        <a href="#" class="block">
                            <img src="https://via.placeholder.com/728x90?text=728x90+Article+Top+Banner" alt="Reklam" class="mx-auto">
                        </a>
                    </div>
                </div>';
                break;
                  case 'article':
                try {
                    // Debug bilgisi ekleyelim
                    error_log("showAd: article case'i çalıştı");
                    
                    $custom_code = getSetting('ad_article_middle');
                    error_log("showAd: ad_article_middle değeri: " . (empty($custom_code) ? "BOŞ" : "DOLU (" . strlen($custom_code) . " karakter)"));
                    
                    if (!empty($custom_code)) {
                        // Reklamları basit bir şekilde göster
                        $ad_html = '<div class="ad-container ad-article text-center p-4 bg-gray-50 border border-gray-200 rounded-md my-6">' . 
                            '<p class="text-sm text-gray-500 mb-2"><span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">REKLAM</span> Bu reklamları görmemek için <a href="/premium.php" class="text-blue-600 hover:underline">premium üye</a> olabilirsiniz</p>' .
                            '<div class="ad-content">' . $custom_code . '</div>' . 
                        '</div>';
                        error_log("showAd: article reklam HTML oluşturuldu: " . substr($ad_html, 0, 50) . "...");
                        return $ad_html;
                    } else {
                        // Özel reklam kodu girilmemişse varsayılan mesajı göster
                        $default_ad = '<div class="my-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 mb-2">
                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">BİLGİ</span>
                                        Bu makale içi reklamları görmemek için 
                                        <a href="/premium.php" class="text-blue-600 hover:underline">premium üye</a> olabilirsiniz
                                    </p>
                                </div>
                            </div>';
                        error_log("showAd: article için varsayılan mesaj döndürülüyor");
                        return $default_ad;
                    }
                } catch (Exception $e) {
                    error_log("showAd: Article_middle reklam kodu alınırken hata: " . $e->getMessage());
                    // Hata durumunda da sadece premium mesajını göster
                    return '<div class="my-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-2">
                                    <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">BİLGİ</span>
                                    Bu makale içi reklamları görmemek için 
                                    <a href="/premium.php" class="text-blue-600 hover:underline">premium üye</a> olabilirsiniz
                                </p>
                            </div>
                        </div>';
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="my-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="text-center">
                        <p class="text-sm text-gray-500 mb-2">
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">REKLAM</span>
                            Bu makale içi reklamı görmemek için 
                            <a href="/premium.php" class="text-blue-600 hover:underline">premium üye</a> olabilirsiniz
                        </p>
                        <a href="#" class="block">
                            <img src="https://via.placeholder.com/580x150?text=580x150+Article+Ad" alt="Reklam" class="mx-auto">
                        </a>
                    </div>
                </div>';
                break;
                  case 'article_bottom':
                try {
                    $custom_code = getSetting('ad_article_bottom');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-article-bottom">'.$custom_code.'</div>';
                    } else {
                        // Özel reklam kodu girilmemişse hiçbir şey gösterme
                        return '';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Article_bottom reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
                break;
                  case 'footer_top':
                try {
                    $custom_code = getSetting('ad_footer_top');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-footer-top max-w-7xl mx-auto my-4 px-4 flex justify-center">'.$custom_code.'</div>';
                    } else {
                        // Özel reklam kodu girilmemişse hiçbir şey gösterme
                        return '';
                    }                } catch (Exception $e) {
                    error_log("showAd: Footer_top reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
                break;
                
                case 'footer':
                try {
                    $custom_code = getSetting('ad_footer');
                    if (!empty($custom_code)) {
                        return '<div class="ad-container ad-footer">'.$custom_code.'</div>';
                    } else {
                        // Özel reklam kodu girilmemişse hiçbir şey gösterme
                        return '';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Footer reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
                
                // Bu kısım artık çalışmayacak çünkü yukarıda return ettik, ama bırakıyoruz
                $ad_content = '
                <div class="container mx-auto px-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-md p-3 shadow-sm text-center">
                        <p class="text-sm text-gray-500 mb-2">
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded mr-1">REKLAM</span>
                            Footer üstü reklam
                        </p>
                        <div class="py-2">
                            <a href="#" class="block">
                                <img src="https://via.placeholder.com/728x90?text=728x90+Footer+Top+Banner" alt="Reklam" class="mx-auto">
                            </a>
                        </div>
                    </div>
                </div>';
                break;
                
                case 'mobile_sticky':
                try {
                    $custom_code = getSetting('ad_mobile_sticky');
                    if (!empty($custom_code)) {
                        // Mobil cihaz kontrolü
                        $is_mobile = false;
                        
                        // Basit bir mobil cihaz kontrolü
                        if(isset($_SERVER['HTTP_USER_AGENT'])) {
                            $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
                            $is_mobile = (strpos($user_agent, 'mobile') !== false || 
                                         strpos($user_agent, 'android') !== false || 
                                         strpos($user_agent, 'iphone') !== false || 
                                         strpos($user_agent, 'ipad') !== false || 
                                         strpos($user_agent, 'ipod') !== false);
                        }
                        
                        // Sadece mobil cihazlarda göster
                        if($is_mobile) {
                            // Reklam container'ı CSS ile sabit (sticky) olarak ayarlanıyor
                            return '<div id="mobile-sticky-ad" class="ad-container ad-mobile-sticky" style="position:fixed; bottom:0; left:0; width:100%; z-index:1000; background-color:#fff; box-shadow:0 -2px 5px rgba(0,0,0,0.1);">
                                <div class="mobile-sticky-close" style="position:absolute; top:-20px; right:5px; background:#fff; border-radius:50%; width:20px; height:20px; text-align:center; line-height:20px; box-shadow:0 -1px 3px rgba(0,0,0,0.2); cursor:pointer;" onclick="document.getElementById(\'mobile-sticky-ad\').style.display=\'none\';">✕</div>
                                '.$custom_code.'
                            </div>';
                        }
                        return ''; // Mobil değilse gösterme
                    } else {
                        // Özel reklam kodu girilmemişse hiçbir şey gösterme
                        return '';
                    }
                } catch (Exception $e) {
                    error_log("showAd: Mobile_sticky reklam kodu alınırken hata: " . $e->getMessage());
                    return ''; // Hata durumunda da hiçbir şey gösterme
                }
                break;
        }
    
        return $ad_content;
    }
} // function_exists kontrolünün kapatması

/**
 * Google AdSense Auto Ads kodunu döndürür
 * Header bölümüne eklenmek için kullanılır
 */
function getAdSenseAutoAdsCode() {
    // AdSense Auto Ads etkin mi kontrol et
    $auto_ads_enabled = getSetting('adsense_auto_ads');
    
    if ($auto_ads_enabled == '1') {
        $auto_ads_code = getSetting('adsense_auto_ads_code');
        
        if (!empty($auto_ads_code)) {
            return $auto_ads_code;
        }
    }
    
    return '';
}

/**
 * AdSense durum bilgilerini döndürür
 */
function getAdSenseStatus() {
    $publisher_id = getSetting('adsense_publisher_id');
    $auto_ads = getSetting('adsense_auto_ads');
    
    return [
        'publisher_id' => $publisher_id,
        'auto_ads_enabled' => $auto_ads == '1',
        'is_configured' => !empty($publisher_id)
    ];
}

/**
 * AdSense reklam birimi kodu üretir
 */
function getAdSenseAdUnit($slot_id, $width = 'auto', $height = 'auto', $format = 'auto') {
    $publisher_id = getSetting('adsense_publisher_id');
    
    if (empty($publisher_id) || empty($slot_id)) {
        return '';
    }
    
    $style = "display:block";
    if ($width !== 'auto' && $height !== 'auto') {
        $style = "display:inline-block;width:{$width}px;height:{$height}px";
    }
    
    return '
    <ins class="adsbygoogle"
         style="' . $style . '"
         data-ad-client="' . htmlspecialchars($publisher_id) . '"
         data-ad-slot="' . htmlspecialchars($slot_id) . '"
         data-ad-format="' . htmlspecialchars($format) . '"
         data-full-width-responsive="true"></ins>
    <script>
         (adsbygoogle = window.adsbygoogle || []).push({});
    </script>';
}

/**
 * AdSense manuel reklamlarını konuma göre döndürür
 */
function showAdSenseAd($position) {
    // Admin veya premium kullanıcılara reklam gösterme
    if ((function_exists('isAdmin') && isAdmin()) || 
        (function_exists('isPremium') && isPremium())) {
        return '';
    }
    
    $ad_code = '';
    
    switch ($position) {
        case 'header':
            $ad_code = getSetting('adsense_header_ad');
            break;
        case 'sidebar':
            $ad_code = getSetting('adsense_sidebar_ad');
            break;
        case 'article':
            $ad_code = getSetting('adsense_article_ad');
            break;
        case 'mobile':
            $ad_code = getSetting('adsense_mobile_ad');
            break;
    }
    
    if (!empty($ad_code)) {
        return '<div class="adsense-ad adsense-' . $position . '">' . $ad_code . '</div>';
    }
    
    return '';
}
