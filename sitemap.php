<?php
/**
 * Otomatik Sitemap Üretici
 * Bu dosya site ayarlarında belirtilen ayarlara göre XML sitemap dosyası oluşturur.
 */

// Sitemap için session başlatmayı engelle
define('NO_SESSION_START', true);

// Output buffering başlat - XML'den önce gelen çıktıları önlemek için
ob_start();

try {
    require_once 'includes/config.php';
    
    // Önceki çıktıları temizle
    ob_clean();
    
    // Ayarları veritabanından al
    $settings = [];
    $stmt = $db->query("SELECT * FROM settings WHERE `key` LIKE 'sitemap_%' OR `key` LIKE 'seo_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    
    // Sitemap etkin değilse çıkış yap
    if (isset($settings['sitemap_enabled']) && $settings['sitemap_enabled'] != '1') {
        die('Sitemap oluşturma devre dışı bırakılmıştır.');
    }
    
    // URL fonksiyonları
    function getSiteURL() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Eğer localhost veya IP adresi ise ve HTTP_HOST değeri yoksa
        if (empty($host) || $host == 'localhost' || $host == '127.0.0.1') {
            // Yapılandırma dosyasından site URL'sini al
            global $db;
            $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'site_url'");
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $row['value'];
            }
        }
        
        return $protocol . '://' . $host;
    }
    
    function formatPriority($priority) {
        $priority = (float) $priority;
        if ($priority > 1) $priority = 1.0;
        if ($priority < 0) $priority = 0.1;
        return number_format($priority, 1, '.', '');
    }
    
    // Sitemap XML başlık - önceki tüm çıktıları temizle
    ob_clean();
    header('Content-Type: application/xml; charset=utf-8');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
                http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;
    
    $baseURL = getSiteURL();
    $changefreq = $settings['sitemap_frequency'] ?? 'daily';
    $currentDate = date('Y-m-d');
    
    // 1. Ana Sayfa
    echo '<url>' . PHP_EOL;
    echo '  <loc>' . $baseURL . '/</loc>' . PHP_EOL;
    echo '  <lastmod>' . $currentDate . '</lastmod>' . PHP_EOL;
    echo '  <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
    echo '  <priority>' . formatPriority($settings['sitemap_priority_home'] ?? '1.0') . '</priority>' . PHP_EOL;
    echo '</url>' . PHP_EOL;
    
    // 2. Kategoriler
    try {
        $stmt = $db->query("SELECT id, slug, updated_at FROM categories WHERE status = 'active'");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categories as $category) {
            echo '<url>' . PHP_EOL;
            echo '  <loc>' . $baseURL . '/kategori/' . $category['slug'] . '</loc>' . PHP_EOL;
            if (!empty($category['updated_at'])) {
                echo '  <lastmod>' . date('Y-m-d', strtotime($category['updated_at'])) . '</lastmod>' . PHP_EOL;
            } else {
                echo '  <lastmod>' . $currentDate . '</lastmod>' . PHP_EOL;
            }
            echo '  <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
            echo '  <priority>' . formatPriority($settings['sitemap_priority_categories'] ?? '0.8') . '</priority>' . PHP_EOL;
            echo '</url>' . PHP_EOL;
        }
    } catch (Exception $e) {
        error_log('Kategoriler sitemap hatası: ' . $e->getMessage());
        // Kategoriler eklenemezse devam et
    }
    
    // 3. Makaleler (Yayınlanmış)
    try {
        $stmt = $db->query("SELECT id, slug, updated_at FROM articles WHERE status = 'published'");
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($articles as $article) {
            echo '<url>' . PHP_EOL;
            echo '  <loc>' . $baseURL . '/makale/' . $article['slug'] . '</loc>' . PHP_EOL;
            if (!empty($article['updated_at'])) {
                echo '  <lastmod>' . date('Y-m-d', strtotime($article['updated_at'])) . '</lastmod>' . PHP_EOL;
            } else {
                echo '  <lastmod>' . $currentDate . '</lastmod>' . PHP_EOL;
            }
            echo '  <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
            echo '  <priority>' . formatPriority($settings['sitemap_priority_articles'] ?? '0.6') . '</priority>' . PHP_EOL;
            echo '</url>' . PHP_EOL;
        }
    } catch (Exception $e) {
        error_log('Makaleler sitemap hatası: ' . $e->getMessage());
        // Makaleler eklenemezse devam et
    }
    
    // 4. Sabit Sayfalar (manuel olarak eklenen)
    $staticPages = [
        'hakkimda' => [
            'url' => '/hakkimda',
            'lastmod' => file_exists('hakkimda.php') ? date('Y-m-d', filemtime('hakkimda.php')) : $currentDate
        ],
        'kategoriler' => [
            'url' => '/kategoriler',
            'lastmod' => file_exists('categories.php') ? date('Y-m-d', filemtime('categories.php')) : $currentDate
        ],
        'iletisim' => [
            'url' => '/iletisim',
            'lastmod' => file_exists('iletisim.php') ? date('Y-m-d', filemtime('iletisim.php')) : $currentDate
        ]
    ];
    
    foreach ($staticPages as $page) {
        echo '<url>' . PHP_EOL;
        echo '  <loc>' . $baseURL . $page['url'] . '</loc>' . PHP_EOL;
        echo '  <lastmod>' . $page['lastmod'] . '</lastmod>' . PHP_EOL;
        echo '  <changefreq>monthly</changefreq>' . PHP_EOL;
        echo '  <priority>' . formatPriority($settings['sitemap_priority_pages'] ?? '0.5') . '</priority>' . PHP_EOL;
        echo '</url>' . PHP_EOL;
    }
    
    // Sitemap XML bitiş
    echo '</urlset>';
    
    // Eğer dosya olarak kaydetme istenirse
    if (isset($_GET['save']) && $_GET['save'] == 'true') {
        $siteMapFileName = $settings['sitemap_filename'] ?? 'sitemap.xml';
        
        // Mevcut output buffer içeriğini al
        $sitemap_content = ob_get_contents();
        
        // Dosyaya kaydet
        $result = file_put_contents($siteMapFileName, $sitemap_content);
        
        if ($result) {
            echo "<!-- Sitemap dosyası $siteMapFileName olarak başarıyla kaydedildi -->";
        } else {
            echo "<!-- HATA: Sitemap dosyası kaydedilemedi! Yazma izinlerini kontrol edin. -->";
        }
    }
    
    // Output buffer'ı temizle ve çıktıyı gönder
    ob_end_flush();
} catch (Exception $e) {
    // Hata durumunda output buffer'ı temizle
    ob_clean();
    
    // XML formatında hata mesajı
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    
    // Sabit sayfalar için manuel URL'ler ekle
    $baseURL = isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : 'https://herbilgi.net';
    
    // Ana sayfa
    echo '<url>' . PHP_EOL;
    echo '  <loc>' . $baseURL . '/</loc>' . PHP_EOL;
    echo '  <priority>1.0</priority>' . PHP_EOL;
    echo '</url>' . PHP_EOL;
    
    // Sabit sayfalar
    $staticPages = [
        ['url' => '/hakkimda', 'priority' => '0.8'],
        ['url' => '/kategoriler', 'priority' => '0.8'],
        ['url' => '/iletisim', 'priority' => '0.8']
    ];
    
    foreach ($staticPages as $page) {
        echo '<url>' . PHP_EOL;
        echo '  <loc>' . $baseURL . $page['url'] . '</loc>' . PHP_EOL;
        echo '  <priority>' . $page['priority'] . '</priority>' . PHP_EOL;
        echo '</url>' . PHP_EOL;
    }
    
    echo '</urlset>' . PHP_EOL;
    
    // Hata mesajını log dosyasına kaydet
    error_log('Sitemap oluşturma hatası: ' . $e->getMessage());
    
    // Output buffer'ı temizle ve çıktıyı gönder
    ob_end_flush();
}

// Otomatik yenileme için cron görevi:
// Sunucunuza aşağıdaki komutu ekleyebilirsiniz (günlük yenileme için):
// 0 0 * * * wget -q -O /dev/null http://siteadresiniz/sitemap.php?save=true
?>
