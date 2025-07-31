<?php
// Session kontrolü - eğer başlatılmamışsa başlat
if (session_status() === PHP_SESSION_NONE) {
    // Session çerezinin tüm sitede çalışmasını sağlamak için path parametresini ayarla
    $session_params = session_get_cookie_params();
    session_set_cookie_params(
        $session_params['lifetime'], 
        '/', // Tüm site için geçerli
        $session_params['domain'],
        $session_params['secure'],
        $session_params['httponly']
    );
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/ads.php';

// Debug için oturum bilgilerini logla
error_log("Header: Session ID = " . session_id());
error_log("Header: Session user_id = " . ($_SESSION['user_id'] ?? 'yok'));
error_log("Header: Session username = " . ($_SESSION['username'] ?? 'yok'));
error_log("Header: Session role = " . ($_SESSION['role'] ?? 'yok'));

// Kullanıcı giriş yapmışsa aktivitesini güncelle
if (isset($_SESSION['user_id'])) {
    // Oturum bilgilerini tazeleyelim
    $stmt = $db->prepare("SELECT id, username, email, role, avatar, is_premium, premium_until FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?: 'default-avatar.jpg';
        $_SESSION['is_premium'] = (int)$user['is_premium'];
        $_SESSION['premium_until'] = $user['premium_until'];
        
        // Debug için
        error_log("Header: Kullanıcı bilgileri yenilendi - " . $user['username']);
    } else {
        // Kullanıcı veritabanında bulunamadı - oturumu temizle
        error_log("Header: Kullanıcı ID: " . $_SESSION['user_id'] . " veritabanında bulunamadı!");
        session_unset();
        session_destroy();
        session_start();
    }
    
    updateUserActivity($_SESSION['user_id']);
    
    // Premium üyelik bilgilerini kontrol et - hata ayıklama için
    // Veritabanındaki premium bilgilerini yeniden yükle
    if (!isset($_SESSION['premium_checked'])) {
        $checkStmt = $db->prepare("SELECT is_premium, premium_until FROM users WHERE id = ?");
        $checkStmt->execute([$_SESSION['user_id']]);
        $premiumInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($premiumInfo) {
            $_SESSION['is_premium'] = (int)$premiumInfo['is_premium'];
            $_SESSION['premium_until'] = $premiumInfo['premium_until'];
            $_SESSION['premium_checked'] = true;
        }
    }
    
    // Oturum bilgilerini kaydet
    session_write_close();
    // Oturumu tekrar başlat
    session_start();
} else {
    // Kullanıcı giriş yapmamışsa misafir olarak izle
    trackGuests();
    
    // Bot kontrolü ve izlemesi yap
    trackBots();
}
?>
<!DOCTYPE html>
<html lang="tr" class="dark-mode-transition">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/seo.css">
    
    <?php
    // Favicon ekle
    $favicon = getSetting('favicon');
    if (!empty($favicon)) {
        echo '<link rel="icon" href="/' . $favicon . '">';
    }
    
    // SEO ayarları fonksiyonu
    function generateSEOTags() {
        global $db;
        
        // Mevcut sayfa bilgilerini al
        $current_url = $_SERVER['REQUEST_URI'];
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'];
        
        // Ayarları getir
        $settings = [];
        $stmt = $db->query("SELECT * FROM settings WHERE `key` LIKE 'seo_%' OR `key` LIKE 'sitemap_%' OR `key` IN ('site_title', 'site_description', 'site_keywords')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        // Temel ayarlar
        $site_title = $settings['site_title'] ?? 'Site Adı';
        $site_description = $settings['site_description'] ?? '';
        $site_keywords = $settings['site_keywords'] ?? '';
        $allow_indexing = $settings['seo_allow_indexing'] ?? '1';
        
        // Sayfa türüne göre özel başlık ve açıklama oluştur
        $page_title = $site_title;
        $page_description = $site_description;
        $canonical_url = $protocol . '://' . $domain . $current_url;
        
        // Makale sayfasında mıyız?
        if (strpos($current_url, 'article.php') !== false && isset($_GET['slug'])) {
            $article_slug = $_GET['slug'];
            $stmt = $db->prepare("SELECT title, description, category_id FROM articles WHERE slug = ?");
            $stmt->execute([$article_slug]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($article) {
                // Kategori adı
                $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
                $cat_stmt->execute([$article['category_id']]);
                $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                $category_name = $category ? $category['name'] : '';
                
                // SEO başlık formatını kullan
                $title_format = $settings['seo_title_format'] ?? '%title% - %sitename%';
                $page_title = str_replace(
                    ['%title%', '%sitename%', '%category%', '%tagline%'],
                    [$article['title'], $site_title, $category_name, $site_description],
                    $title_format
                );
                
                $page_description = $article['description'];
            }        } elseif (strpos($current_url, 'category.php') !== false && isset($_GET['slug'])) {
            // Kategori sayfası
            $category_slug = $_GET['slug'];
            $stmt = $db->prepare("SELECT name FROM categories WHERE slug = ?");
            $stmt->execute([$category_slug]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($category) {
                $page_title = $category['name'] . ' - ' . $site_title;
                $page_description = $site_description; // Varsayılan site açıklamasını kullan
            }
        }
        
        // Meta etiketleri oluştur
        echo "<title>{$page_title}</title>\n";
        echo "    <meta name='description' content='" . htmlspecialchars($page_description) . "'>\n";
        
        if (!empty($site_keywords)) {
            echo "    <meta name='keywords' content='" . htmlspecialchars($site_keywords) . "'>\n";
        }
        
        // İndeksleme kontrolü
        $no_index_pages = $settings['seo_noindex_pages'] ?? '';
        $no_index_array = array_filter(explode("\n", $no_index_pages));
        $should_index = $allow_indexing == '1';
        
        // İndekslenmeyen sayfaları kontrol et
        foreach ($no_index_array as $no_index_path) {
            if (strpos($current_url, trim($no_index_path)) !== false) {
                $should_index = false;
                break;
            }
        }
        
        // Arşiv sayfaları için meta robots
        if (isset($_GET['page']) && $_GET['page'] > 1) {
            $archive_robots = $settings['seo_archives_robots'] ?? 'index,follow';
            echo "    <meta name='robots' content='{$archive_robots}'>\n";
        } else {
            echo "    <meta name='robots' content='" . ($should_index ? 'index,follow' : 'noindex,follow') . "'>\n";
        }
        
        // Canonical URL
        echo "    <link rel='canonical' href='" . htmlspecialchars($canonical_url) . "'>\n";
        
        // Open Graph meta etiketleri
        if (isset($settings['seo_open_graph']) && $settings['seo_open_graph'] == '1') {
            echo "    <meta property='og:title' content='" . htmlspecialchars($page_title) . "'>\n";
            echo "    <meta property='og:description' content='" . htmlspecialchars($page_description) . "'>\n";
            echo "    <meta property='og:url' content='" . htmlspecialchars($canonical_url) . "'>\n";
            echo "    <meta property='og:type' content='" . (strpos($current_url, 'article.php') !== false ? 'article' : 'website') . "'>\n";
            
            // Varsayılan sosyal medya görseli
            $default_image = $settings['seo_default_image'] ?? '/assets/img/social-default.jpg';
            echo "    <meta property='og:image' content='" . $protocol . "://" . $domain . $default_image . "'>\n";
            
            if (!empty($settings['seo_fb_page_id'])) {
                echo "    <meta property='fb:pages' content='" . htmlspecialchars($settings['seo_fb_page_id']) . "'>\n";
            }
        }
        
        // Twitter Card meta etiketleri
        if (isset($settings['seo_twitter_cards']) && $settings['seo_twitter_cards'] == '1') {
            echo "    <meta name='twitter:card' content='summary_large_image'>\n";
            
            if (!empty($settings['seo_twitter_site'])) {
                echo "    <meta name='twitter:site' content='" . htmlspecialchars($settings['seo_twitter_site']) . "'>\n";
            }
            
            echo "    <meta name='twitter:title' content='" . htmlspecialchars($page_title) . "'>\n";
            echo "    <meta name='twitter:description' content='" . htmlspecialchars($page_description) . "'>\n";
            
            // Varsayılan sosyal medya görseli
            $default_image = $settings['seo_default_image'] ?? '/assets/img/social-default.jpg';
            echo "    <meta name='twitter:image' content='" . $protocol . "://" . $domain . $default_image . "'>\n";
        }
        
        // Site doğrulama kodları
        if (!empty($settings['seo_google_verification'])) {
            echo "    <meta name='google-site-verification' content='" . htmlspecialchars($settings['seo_google_verification']) . "'>\n";
        }
        
        if (!empty($settings['seo_bing_verification'])) {
            echo "    <meta name='msvalidate.01' content='" . htmlspecialchars($settings['seo_bing_verification']) . "'>\n";
        }
        
        // Özel meta etiketleri
        if (!empty($settings['seo_custom_meta'])) {
            echo "    " . $settings['seo_custom_meta'] . "\n";
        }
    }
    
    // SEO etiketlerini oluştur
    generateSEOTags();
    ?>
      <script src="https://cdn.tailwindcss.com"></script>    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            bg: '#121212',
                            card: '#1e1e1e',
                            surface: '#2a2a2a',
                            text: '#e0e0e0',
                            border: '#3a3a3a',
                            accent: '#64b5f6'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">    <style>
        /* Karanlık mod geçiş efekti */
        .dark-mode-transition {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        /* Koyu tema stilleri */
        .dark body {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        .dark .bg-white {
            background-color: #2a2a2a !important;
        }
        .dark .bg-gray-100 {
            background-color: #1a1a1a !important;
        }
        
        /* Kategori sayıları için badge stilleri */
        .dark .text-gray-600 {
            color: #e0e0e0 !important;
        }
        
        .dark span.bg-gray-100.text-gray-600 {
            background-color: #3a3a3a !important;
            color: #e0e0e0 !important;
            border: 1px solid #4a4a4a;
        }
        
        /* Badge hover durumu için ek stil */
        .dark a:hover span.bg-gray-100.text-gray-600 {
            background-color: #4a4a4a !important;
        }
        
        /* Karanlık mod için temel renkler */
        .dark .bg-gray-900 {
            background-color: #121212 !important;
        }
        
        .dark .bg-gray-800 {
            background-color: #1f1f1f !important;
        }
        
        .dark .bg-gray-700 {
            background-color: #2d2d2d !important;
        }
        
        /* Mobil görünüm için ek stiller */
        @media (max-width: 640px) {
            .mobile-menu-active {
                height: auto;
                opacity: 1;
                transition: all 0.3s ease;
            }
            
            .mobile-menu-inactive {
                height: 0;
                opacity: 0;
                transition: all 0.3s ease;
            }
        }
            /* Kategori ve makale başlık renklerini karanlık temada beyaz yapma */
        .dark h4.font-medium.text-gray-900,
        .dark .text-xl.font-semibold.text-gray-800,
        .dark .text-gray-800,
        .dark .text-gray-900,
        .dark a.text-gray-900,
        .dark .text-gray-700 {
            color: #e0e0e0 !important;
        }
        
        .dark .shadow-lg,
        .dark .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
        }
        
        .dark .border,
        .dark .border-gray-200 {
            border-color: #3a3a3a;
        }
        
        /* Karanlık mod için metin ve arka plan düzeltmeleri */
        .dark a.text-gray-500,
        .dark .text-gray-500 {
            color: #9e9e9e !important;
        }
        
        /* Premium üyelik reklamları kaldırma mesajı için karanlık tema ayarı */
        .dark .bg-gray-50 {
            background-color: #292929 !important;
        }
        
        .dark a.text-gray-500:hover,
        .dark .text-gray-500:hover,
        .dark a.hover\:text-gray-700:hover {
            color: #e0e0e0 !important;
        }
          /* Premium içerik stillemesi */
        .premium-badge {
            background: linear-gradient(45deg, #9333ea, #6b46c1);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(107, 70, 193, 0.1), 0 2px 4px -1px rgba(107, 70, 193, 0.06);
            margin-left: 0.5rem;
        }
        
        /* Karanlık mod için premium badge stillemesi */
        .dark .premium-badge {
            background: linear-gradient(45deg, #8b5cf6, #7c3aed);
            box-shadow: 0 4px 6px -1px rgba(139, 92, 246, 0.2), 0 2px 4px -1px rgba(139, 92, 246, 0.1);
        }
        
        .premium-badge i {
            color: #fbbf24;
            margin-right: 0.25rem;
        }
        
        .premium-card {
            position: relative;
            overflow: hidden;
        }
        
        .premium-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 50px 50px 0;
            border-color: transparent #9333ea transparent transparent;
            z-index: 1;
        }        .premium-card::after {
            content: "\f521";  /* Font Awesome crown icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 6px;
            right: 6px;
            color: #fbbf24;
            z-index: 2;
        }
        
        /* Karanlık tema için ek stilleri */        .dark .bg-white {
            background-color: #2a2a2a !important;
        }
        
        .dark .bg-yellow-100 {
            background-color: #483f1f !important;
            color: #fef08a;
        }
        
        .dark .border-yellow-200 {
            border-color: #594f2c;
        }
        
        .dark .text-yellow-700 {
            color: #fde68a;
        }
        
        /* Card ve container bileşenlerinin karanlık moddaki stilleri */
        .dark .container .bg-white,
        .dark .container div[class*="bg-white"] {
            background-color: #2a2a2a !important;
        }
        
        .dark article,
        .dark .card,
        .dark .bg-white.rounded-lg,
        .dark .bg-white.shadow-md,
        .dark .bg-white.shadow-lg {
            background-color: #2a2a2a !important;
        }
        .dark .hover\:bg-gray-100:hover {
            background-color: #333333;
        }
        
        .dark .text-gray-500 {
            color: #bdbdbd;
        }
          .dark .hover\:text-gray-700:hover {
            color: #f5f5f5;
        }
          /* Kategori ve makale başlık renklerini karanlık temada beyaz yapma */
        .dark h4.font-medium.text-gray-900,
        .dark .text-xl.font-semibold.text-gray-800,
        .dark .text-gray-900,
        .dark a.text-gray-900 {
            color: #e0e0e0 !important;
        }
          .dark h4.font-medium.text-gray-900.group-hover\:text-blue-600:hover,
        .dark a.text-gray-900.hover\:text-blue-600:hover,
        .dark .text-gray-900.hover\:text-blue-600:hover,
        .dark h2.text-xl.font-semibold.mb-2 a.hover\:text-blue-600:hover,
        .dark h2.text-xl.font-semibold.mb-2 a:hover {
            color: #93c5fd !important; /* Karanlık temada hover durumunda daha açık mavi */
        }
        
        .dark .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.4);
        }
        
        .dark a.hover\:text-blue-600:hover {
            color: #90caf9;
        }
        
        .dark a.text-blue-600 {
            color: #64b5f6;
        }
        
        .dark a.text-red-500 {
            color: #ef9a9a;
        }
        
        .dark a.hover\:text-red-600:hover {
            color: #f48fb1;
        }
        
        /* Manşet Sistemi Stilleri */
        .headline-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        
        .headline-slide.active {
            opacity: 1;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .headline-slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .headline-slider-nav:hover {
            background: rgba(0, 0, 0, 0.7);
            transform: translateY(-50%) scale(1.1);
        }
        
        .headline-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .headline-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .headline-indicator.active {
            background: white;
            transform: scale(1.2);
        }
        
        .headline-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .headline-grid-item:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
        }
        
        .headline-carousel-container {
            overflow: hidden;
            position: relative;
        }
        
        .headline-carousel-track {
            display: flex;
            transition: transform 0.5s ease;
        }
        
        .headline-carousel-item {
            flex: 0 0 auto;
            width: 100%;
        }
        
        @media (min-width: 768px) {
            .headline-carousel-item {
                width: 50%;
            }
        }
        
        @media (min-width: 1024px) {
            .headline-carousel-item {
                width: 33.333%;
            }
        }
        
        /* Karanlık mod manşet stilleri */
        .dark .headline-slide {
            color: #e0e0e0;
        }
        
        .dark .headline-grid-item {
            background-color: #2a2a2a !important;
            border-color: #404040;
        }
        
        .dark .headline-grid-item:hover {
            background-color: #333333 !important;
        }
        
        /* Responsive manşet stilleri */
        @media (max-width: 768px) {
            .headline-slide h2 {
                font-size: 1.5rem;
                line-height: 1.3;
            }
            
            .headline-slide p {
                font-size: 0.875rem;
                margin-bottom: 1rem;
            }
            
            .headline-slide .absolute.bottom-0 {
                padding: 1rem;
            }
        }
        
        /* Makale Üstü Manşet Stilleri */
        .top-articles-container {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 20px 40px rgba(245, 87, 108, 0.3);
        }
        
        .top-articles-item {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .top-articles-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .top-articles-number {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .top-articles-live {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Manşet header gradientleri */
        .headline-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%);
            position: relative;
            overflow: hidden;
        }
        
        .headline-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: .8;
                transform: scale(1.05);
            }
        }
        
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .aspect-video {
            aspect-ratio: 16 / 9;
        }
        
        /* Hover efektleri */
        .headline-main-image {
            transition: transform 0.5s ease;
        }
        
        .headline-main-image:hover {
            transform: scale(1.05);
        }
        
        /* Manşet kartları için özel stilleri */
        .headline-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease;
        }
        
        .headline-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        /* Karanlık mod için makale üstü stilleri */
        .dark .top-articles-container {
            background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
        }
        
        .dark .headline-card {
            background-color: #2d3748 !important;
            border-color: #4a5568;
        }
        
        .dark .headline-card:hover {
            background-color: #374151 !important;
        }
        
        .dark .headline-header {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 50%, #b91c1c 100%);
        }
        
        /* Responsive düzenlemeler */
        @media (max-width: 768px) {
            .headline-main-title {
                font-size: 1.5rem;
                line-height: 1.3;
            }
            
            .headline-main-description {
                font-size: 0.875rem;
                line-height: 1.4;
            }
        }
    </style>
      <script>
        // Kategoriler menüsü için JavaScript fonksiyonları
        function toggleCatMenu() {
            var menu = document.getElementById('catDropdownMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }
        
        // Mobil kategoriler menüsü için JavaScript fonksiyonları
        function toggleMobileCatMenu() {
            var menu = document.getElementById('mobileCatDropdownMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }
        
        // Tema değiştirme fonksiyonu
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                // Karanlık temadan aydınlık temaya geçiş
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
                document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
                
                // Mobil için de ikonu güncelle
                document.getElementById('theme-toggle-dark-icon-mobile').classList.add('hidden');
                document.getElementById('theme-toggle-light-icon-mobile').classList.remove('hidden');
            } else {
                // Aydınlık temadan karanlık temaya geçiş
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('theme-toggle-light-icon').classList.add('hidden');
                document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
                
                // Mobil için de ikonu güncelle
                document.getElementById('theme-toggle-light-icon-mobile').classList.add('hidden');
                document.getElementById('theme-toggle-dark-icon-mobile').classList.remove('hidden');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Mobil menüyü aç/kapat
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Sayfa dışına tıklandığında menüyü kapat
            document.addEventListener('click', function(e) {
                var menu = document.getElementById('catDropdownMenu');
                var button = e.target.closest('button');
                
                if (button && button.getAttribute('onclick') && button.getAttribute('onclick').includes('toggleCatMenu')) {
                    // Buton tıklamasını işleme, event bubbling'i önle
                    return;
                }
                
                if (menu && !menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            });
            
            // Mobil tema değiştirme butonu
            const themeToggleMobileBtn = document.getElementById('theme-toggle-mobile');
            if (themeToggleMobileBtn) {
                themeToggleMobileBtn.addEventListener('click', toggleTheme);
            }
            
            // Desktop tema değiştirme butonu
            const themeToggleBtn = document.getElementById('theme-toggle');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', toggleTheme);
            }
            
            // Kullanıcının tema tercihini al
            const userTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
              // Tema tercihine göre ayarla
            if (userTheme === 'dark' || (!userTheme && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
                // Desktop için ikonları ayarla
                if (document.getElementById('theme-toggle-light-icon')) {
                    document.getElementById('theme-toggle-light-icon').classList.add('hidden');
                }
                if (document.getElementById('theme-toggle-dark-icon')) {
                    document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
                }
                // Mobil için ikonları ayarla
                if (document.getElementById('theme-toggle-light-icon-mobile')) {
                    document.getElementById('theme-toggle-light-icon-mobile').classList.add('hidden');
                }
                if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                    document.getElementById('theme-toggle-dark-icon-mobile').classList.remove('hidden');
                }
            } else {
                document.documentElement.classList.remove('dark');
                // Desktop için ikonları ayarla
                if (document.getElementById('theme-toggle-dark-icon')) {
                    document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
                }
                if (document.getElementById('theme-toggle-light-icon')) {
                    document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
                }
                // Mobil için ikonları ayarla
                if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                    document.getElementById('theme-toggle-dark-icon-mobile').classList.add('hidden');
                }
                if (document.getElementById('theme-toggle-light-icon-mobile')) {
                    document.getElementById('theme-toggle-light-icon-mobile').classList.remove('hidden');
                }
            }
        });
    </script>
</head>
<body class="bg-gray-100 dark:bg-dark-bg dark-mode-transition">    <?php if (isLoggedIn()): 
        $stmt = $db->prepare("SELECT approved, can_post FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Null kontrolü ekle
        $user_approved = isset($user['approved']) ? $user['approved'] : false;
        
        if (!$user_approved && !isAdmin()): ?><div class="bg-yellow-100 dark:bg-yellow-900 border-b border-yellow-200 dark:border-yellow-800">
            <div class="max-w-7xl mx-auto px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 mr-2"></i>
                        <p class="text-yellow-700 dark:text-yellow-300">
                            Üyeliğiniz onay bekliyor. Onaylandıktan sonra makale ekleyebileceksiniz.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; 
    endif; ?>
    
    <?php 
    // Header reklamı göster - premium üyelere gösterilmez
    echo showAd('header');
    ?>
      <nav class="bg-white dark:bg-gray-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/" class="text-2xl font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                            <h1 class="text-2xl font-bold m-0 p-0">
                            <?php 
                            $site_title = getSetting('site_title');
                            echo !empty($site_title) ? htmlspecialchars($site_title) : 'Blog Sitesi'; 
                            ?>
                            </h1>
                        </a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="/" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Ana Sayfa
                        </a>
                        <div class="inline-flex relative">
                            <button onclick="toggleCatMenu()" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                <i class="fas fa-list mr-1"></i> Kategoriler <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div id="catDropdownMenu" class="hidden absolute top-full left-0 bg-white dark:bg-gray-800 mt-1 py-2 w-56 rounded shadow-lg z-50">
                                <?php
                                // Kategorileri getir
                                $catStmt = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
                                if ($catStmt) {
                                    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($categories as $cat): ?>                                        <a href="/kategori/<?php echo $cat['slug']; ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <?php echo clean($cat['name']); ?>
                                        </a>
                                    <?php endforeach;
                                } ?>
                            </div>
                        </div>                        <a href="/hakkimda" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-info-circle mr-1"></i> Hakkımda
                        </a>
                        
                        <a href="/iletisim" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-envelope mr-1"></i> İletişim
                        </a>

                        <a href="/privacy-policy.php" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-shield-alt mr-1"></i> Gizlilik Politikası
                        </a>
                        
                        <?php 
                        if (isLoggedIn()) {                            $stmt = $db->prepare("SELECT approved, can_post FROM users WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Null kontrolü ekle
                            $user_approved = isset($user['approved']) ? $user['approved'] : false;
                            $user_can_post = isset($user['can_post']) ? $user['can_post'] : false;
                            
                            if (($user_approved && $user_can_post) || isAdmin()):
                        ?>                        <a href="/makale_ekle" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-plus-circle mr-1"></i> Makale Ekle
                        </a>
                        <?php 
                            endif;
                        }
                        ?>
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <!-- Tema değiştirme düğmesi -->
                    <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5 flex items-center mr-2">
                        <span id="theme-toggle-dark-icon" class="hidden">
                            <i class="fas fa-moon"></i>
                        </span>
                        <span id="theme-toggle-light-icon">
                            <i class="fas fa-sun"></i>
                        </span>
                    </button>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="ml-3 relative">
                            <div class="flex items-center space-x-4">
                                <?php if (isAdmin()): ?>
                                    <a href="/admin" class="text-gray-500 dark:text-gray-300 hover:text-blue-600">
                                        <i class="fas fa-cog"></i> Panel
                                    </a>
                                <?php endif; ?>
                                <div class="flex items-center space-x-3">                                    <a href="/profil" class="flex items-center hover:opacity-75">
                                        <img src="<?php 
                                            // Kullanıcı avatarını güvenceye al
                                            $avatar = ensureUserAvatar($_SESSION['user_id']);
                                            // Base64 kodlu avatar kullan (LiteSpeed için)
                                            echo getAvatarBase64($avatar);
                                        ?>" 
                                             alt="<?php echo $_SESSION['username']; ?>" 
                                             class="w-8 h-8 rounded-full object-cover">
                                        <span class="ml-2 text-gray-600 dark:text-gray-300"><?php echo $_SESSION['username']; ?></span>
                                    </a>
                                    
                                    <!-- Mesajlar Bağlantısı -->
                                    <a href="/mesajlarim.php" class="text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-2 relative mr-2">
                                        <i class="fas fa-envelope"></i>
                                        <?php
                                        // Okunmamış mesaj sayısını al
                                        try {
                                            $unread_count_query = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id = ? AND status = 'unread' AND is_deleted_by_receiver = 0");
                                            $unread_count_query->execute([$_SESSION['user_id']]);
                                            $unread_count = $unread_count_query->fetchColumn();
                                            
                                            if ($unread_count > 0) {
                                                echo '<span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">' . $unread_count . '</span>';
                                            }
                                        } catch (Exception $e) {
                                            // Hata durumunda sessizce devam et
                                            error_log("Okunmamış mesaj sayısı alma hatası: " . $e->getMessage());
                                        }
                                        ?>
                                    </a>
                                    <a href="/logout.php" class="text-red-500 hover:text-red-600">
                                        <i class="fas fa-sign-out-alt"></i> Çıkış
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>                        <div class="flex items-center space-x-4">
                            <a href="/login" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-sign-in-alt"></i> Giriş
                            </a>
                            <a href="/register" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-user-plus"></i> Kayıt Ol
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Mobil menü butonu -->
                <div class="flex items-center sm:hidden">
                    <button id="theme-toggle-mobile" type="button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5 flex items-center mr-2">
                        <span id="theme-toggle-dark-icon-mobile" class="hidden">
                            <i class="fas fa-moon"></i>
                        </span>
                        <span id="theme-toggle-light-icon-mobile">
                            <i class="fas fa-sun"></i>
                        </span>
                    </button>
                      <?php if (isLoggedIn()): ?>                        <a href="/profil" class="flex items-center hover:opacity-75 mr-2">
                            <img src="<?php 
                                // Kullanıcı avatarını güvenceye al
                                $avatar = ensureUserAvatar($_SESSION['user_id']);
                                // Base64 kodlu avatar kullan (LiteSpeed için)
                                echo getAvatarBase64($avatar);
                            ?>" 
                                 alt="<?php echo $_SESSION['username']; ?>" 
                                 class="w-8 h-8 rounded-full object-cover">
                        </a>
                        
                        <!-- Mesajlar Bağlantısı -->
                        <a href="mesajlarim.php" class="text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-2 relative mr-2">
                            <i class="fas fa-envelope"></i>
                            <?php
                            // Okunmamış mesaj sayısını al
                            try {
                                $unread_count_query = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id = ? AND status = 'unread' AND is_deleted_by_receiver = 0");
                                $unread_count_query->execute([$_SESSION['user_id']]);
                                $unread_count = $unread_count_query->fetchColumn();
                                
                                if ($unread_count > 0) {
                                    echo '<span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">' . $unread_count . '</span>';
                                }
                            } catch (Exception $e) {
                                // Hata durumunda sessizce devam et
                                error_log("Okunmamış mesaj sayısı alma hatası: " . $e->getMessage());
                            }
                            ?>
                        </a>
                    <?php endif; ?>
                    
                    <button id="mobile-menu-button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-2">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobil menü -->
            <div id="mobile-menu" class="sm:hidden hidden">
                <div class="pt-2 pb-3 space-y-1">
                    <a href="/" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-home mr-1"></i> Ana Sayfa
                    </a>
                    <button onclick="toggleMobileCatMenu()" class="w-full text-left pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex justify-between items-center">
                        <span><i class="fas fa-list mr-1"></i> Kategoriler</span>
                        <i class="fas fa-chevron-down text-xs mr-2"></i>
                    </button>
                    <div id="mobileCatDropdownMenu" class="hidden pl-6 pr-4 py-2 bg-gray-50 dark:bg-gray-700">
                        <?php
                        // Kategorileri getir (mobil için tekrar)
                        $catStmt = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
                        if ($catStmt) {
                            $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($categories as $cat): ?>
                                <a href="/kategori/<?php echo $cat['slug']; ?>" class="block py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                    &bull; <?php echo clean($cat['name']); ?>
                                </a>
                            <?php endforeach;
                        } ?>
                    </div>                    <a href="/hakkimda" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-info-circle mr-1"></i> Hakkımda
                    </a>
                    <a href="/iletisim" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-envelope mr-1"></i> İletişim
                    </a>
                    <a href="/privacy-policy.php" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-shield-alt mr-1"></i> Gizlilik Politikası
                    </a>
                    <?php if (isLoggedIn()): 
                        if (($user['approved'] && $user['can_post']) || isAdmin()): ?>
                        <a href="/makale_ekle" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <i class="fas fa-plus-circle mr-1"></i> Makale Ekle
                        </a>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                        <a href="/admin" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <i class="fas fa-cog mr-1"></i> Yönetim Paneli
                        </a>
                        <?php endif; ?>
                        <a href="/logout.php" class="block pl-3 pr-4 py-2 text-base font-medium text-red-500 hover:text-red-700">
                            <i class="fas fa-sign-out-alt mr-1"></i> Çıkış
                        </a>
                    <?php else: ?>                        <div class="flex pl-3 pr-4 py-2 space-x-4">
                            <a href="/login" class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                <i class="fas fa-sign-in-alt mr-1"></i> Giriş
                            </a>
                            <a href="/register" class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                <i class="fas fa-user-plus mr-1"></i> Kayıt Ol
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <?php
    // Hata ve başarı mesajlarını göster
    if (isset($_SESSION['error'])): ?>
    <div class="max-w-7xl mx-auto px-4 py-3 mt-4">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-red-500" onclick="this.parentElement.parentElement.style.display='none'" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Kapat</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
    </div>
    <?php 
        unset($_SESSION['error']); 
    endif; 
    
    if (isset($_SESSION['success'])): ?>
    <div class="max-w-7xl mx-auto px-4 py-3 mt-4">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-green-500" onclick="this.parentElement.parentElement.style.display='none'" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Kapat</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
    </div>
    <?php 
        unset($_SESSION['success']); 
    endif; 
    ?>
