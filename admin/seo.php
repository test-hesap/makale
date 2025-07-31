<?php
require_once '../includes/config.php';
checkAuth('admin');

include 'includes/header.php';

$message = '';
$error = '';

// Sitemap oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_sitemap'])) {
    try {
        // Ayarları veritabanından al
        $settings = [];
        $stmt = $db->query("SELECT * FROM settings WHERE `key` LIKE 'sitemap_%' OR `key` LIKE 'seo_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        // Sitemap dosya adını belirle
        $siteMapFileName = $settings['sitemap_filename'] ?? 'sitemap.xml';
        $sitemap_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $siteMapFileName;
        
        // getSiteURL fonksiyonunu kontrol et, sitemap.php'yi dahil etmeden önce
        if (!function_exists('getSiteURLForSitemap')) {
            function getSiteURLForSitemap() {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                return $protocol . '://' . $host;
            }
        }
        
        // Gerekli verileri doğrudan burada oluşturalım
        $baseURL = getSiteURL(); // Şimdi bu fonksiyon veritabanını da kontrol ediyor
        
        // XML başlangıcı
        $sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $sitemap_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
        $sitemap_content .= '      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL;
        $sitemap_content .= '      xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . PHP_EOL;
        $sitemap_content .= '            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;
        
        // Ana sayfa
        $sitemap_content .= '<url>' . PHP_EOL;
        $sitemap_content .= '  <loc>' . $baseURL . '/</loc>' . PHP_EOL;
        $sitemap_content .= '  <changefreq>daily</changefreq>' . PHP_EOL;
        $sitemap_content .= '  <priority>1.0</priority>' . PHP_EOL;
        $sitemap_content .= '</url>' . PHP_EOL;
        
        // Sabit sayfalar
        $static_pages = [
            'hakkimda' => 'monthly',
            'iletisim' => 'monthly',
            'kategoriler' => 'weekly'
        ];
        
        foreach ($static_pages as $page => $freq) {
            $sitemap_content .= '<url>' . PHP_EOL;
            $sitemap_content .= '  <loc>' . $baseURL . '/' . $page . '</loc>' . PHP_EOL;
            $sitemap_content .= '  <changefreq>' . $freq . '</changefreq>' . PHP_EOL;
            $sitemap_content .= '  <priority>0.8</priority>' . PHP_EOL;
            $sitemap_content .= '</url>' . PHP_EOL;
        }
          // Kategoriler
        $categories = [];
        try {
            // Önce updated_at sütununun var olup olmadığını kontrol et
            $checkColumnStmt = $db->query("SHOW COLUMNS FROM categories LIKE 'updated_at'");
            $columnExists = $checkColumnStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($columnExists) {
                $catStmt = $db->query("SELECT slug, updated_at FROM categories ORDER BY name");
            } else {
                $catStmt = $db->query("SELECT slug FROM categories ORDER BY name");
            }
            
            while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)) {
                $categories[] = $cat;
            }
        } catch (Exception $e) {
            // Hata durumunda sadece slug'ları getir
            $catStmt = $db->query("SELECT slug FROM categories ORDER BY name");
            while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)) {
                $categories[] = $cat;
            }
        }
        
        foreach ($categories as $category) {
            $sitemap_content .= '<url>' . PHP_EOL;
            $sitemap_content .= '  <loc>' . $baseURL . '/kategori/' . $category['slug'] . '</loc>' . PHP_EOL;
            // updated_at sütunu varsa lastmod ekle
            if (isset($category['updated_at']) && !empty($category['updated_at'])) {
                $sitemap_content .= '  <lastmod>' . date('Y-m-d', strtotime($category['updated_at'])) . '</lastmod>' . PHP_EOL;
            } else {
                // Güncel tarihi ekle
                $sitemap_content .= '  <lastmod>' . date('Y-m-d') . '</lastmod>' . PHP_EOL;
            }
            $sitemap_content .= '  <changefreq>weekly</changefreq>' . PHP_EOL;
            $sitemap_content .= '  <priority>0.7</priority>' . PHP_EOL;
            $sitemap_content .= '</url>' . PHP_EOL;
        }
          // Makaleler
        $articles = [];
        try {
            // Önce tablo yapısını kontrol et
            $hasUpdatedAt = false;
            $hasStatus = false;
            
            $checkColumnStmt = $db->query("SHOW COLUMNS FROM articles LIKE 'updated_at'");
            $hasUpdatedAt = ($checkColumnStmt->fetch(PDO::FETCH_ASSOC) !== false);
            
            $checkStatusStmt = $db->query("SHOW COLUMNS FROM articles LIKE 'status'");
            $hasStatus = ($checkStatusStmt->fetch(PDO::FETCH_ASSOC) !== false);
            
            // Sütunların varlığına göre SQL sorgusunu oluştur
            if ($hasStatus) {
                if ($hasUpdatedAt) {
                    $artStmt = $db->query("SELECT slug, updated_at, created_at FROM articles WHERE status = 'published' ORDER BY created_at DESC");
                } else {
                    $artStmt = $db->query("SELECT slug, created_at FROM articles WHERE status = 'published' ORDER BY created_at DESC");
                }
            } else {
                if ($hasUpdatedAt) {
                    $artStmt = $db->query("SELECT slug, updated_at, created_at FROM articles ORDER BY created_at DESC");
                } else {
                    $artStmt = $db->query("SELECT slug, created_at FROM articles ORDER BY created_at DESC");
                }
            }
            
            while ($art = $artStmt->fetch(PDO::FETCH_ASSOC)) {
                $articles[] = $art;
            }
        } catch (Exception $e) {
            // Hata durumunda en basit sorguyu kullan
            try {
                $artStmt = $db->query("SELECT slug FROM articles ORDER BY id DESC");
                while ($art = $artStmt->fetch(PDO::FETCH_ASSOC)) {
                    $articles[] = $art;
                }
            } catch (Exception $e) {
                // Hiçbir şey yapma, boş array kalacak
            }
        }
        
        foreach ($articles as $article) {
            $sitemap_content .= '<url>' . PHP_EOL;
            $sitemap_content .= '  <loc>' . $baseURL . '/makale/' . $article['slug'] . '</loc>' . PHP_EOL;
            
            // Tarih bilgisi için öncelik sırası: updated_at, created_at, şu an
            if (isset($article['updated_at']) && !empty($article['updated_at'])) {
                $sitemap_content .= '  <lastmod>' . date('Y-m-d', strtotime($article['updated_at'])) . '</lastmod>' . PHP_EOL;
            } elseif (isset($article['created_at']) && !empty($article['created_at'])) {
                $sitemap_content .= '  <lastmod>' . date('Y-m-d', strtotime($article['created_at'])) . '</lastmod>' . PHP_EOL;
            } else {
                $sitemap_content .= '  <lastmod>' . date('Y-m-d') . '</lastmod>' . PHP_EOL;
            }
            
            $sitemap_content .= '  <changefreq>monthly</changefreq>' . PHP_EOL;
            $sitemap_content .= '  <priority>0.6</priority>' . PHP_EOL;
            $sitemap_content .= '</url>' . PHP_EOL;
        }
        
        // XML bitişi
        $sitemap_content .= '</urlset>';
        
        // Dosyaya yazmayı dene
        if (file_put_contents($sitemap_path, $sitemap_content)) {
            $message = t('admin_sitemap_generated_success', $siteMapFileName);
        } else {
            $error = t('admin_sitemap_generation_error');
        }
    } catch (Exception $e) {
        $error = t('admin_error') . ": " . $e->getMessage();
    }
}

// Robots.txt oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_robots'])) {
    try {
        // Ayarları getir
        $stmt = $db->query("SELECT * FROM settings WHERE `key` LIKE 'seo_%' OR `key` LIKE 'sitemap_%'");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        // Sitemap dosya adı
        $sitemap_filename = $settings['sitemap_filename'] ?? 'sitemap.xml';
        
        // Site URL'ini veritabanından al, yoksa mevcut URL'i kullan
        $site_url = getSetting('site_url');
        if (empty($site_url)) {
            $site_url = getSiteURL();
        }
        
        // Robots.txt içeriği
        $robots_content = "User-agent: *\n";
        
        // İndeksleme izni kontrolü
        if (isset($settings['seo_allow_indexing']) && $settings['seo_allow_indexing'] != '1') {
            $robots_content .= "Disallow: /\n";
        } else {
            // İndekslenmeyen sayfalar
            $no_index_pages = $settings['seo_noindex_pages'] ?? '';
            $no_index_array = array_filter(explode("\n", $no_index_pages));
            
            foreach ($no_index_array as $path) {
                $path = trim($path);
                if (!empty($path)) {
                    $robots_content .= "Disallow: $path\n";
                }
            }
            
            // Özel admin klasörleri ve vendor
            $robots_content .= "Disallow: /vendor/\n";
            $robots_content .= "Disallow: /admin/\n";
            $robots_content .= "Disallow: /includes/\n";
        }
        
        // Sitemap URL'si
        $robots_content .= "\nSitemap: $site_url/$sitemap_filename\n";
        
        // Robots.txt dosyasını kaydet
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
        if (file_put_contents($file_path, $robots_content)) {
            $message = t('admin_robots_generated_success');
        } else {
            $error = t('admin_robots_generation_error');
        }
    } catch (Exception $e) {
        $error = t('admin_error') . ": " . $e->getMessage();
    }
}

// Site URL fonksiyonu
function getSiteURL() {
    // Önce veritabanından site_url ayarını kontrol et
    global $db;
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'site_url'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['value'])) {
            return rtrim($result['value'], '/');
        }
    } catch (Exception $e) {
        // Veritabanı hatası durumunda varsayılan değeri kullan
    }
    
    // Veritabanında yoksa mevcut URL'i kullan
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

?>
<!DOCTYPE html>
<html lang="<?php echo getActiveLang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo t('admin_seo_management'); ?>
        </h1>
    </div>                <?php if ($error): ?>
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($message): ?>
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sitemap Yönetimi -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4"><?php echo t('admin_sitemap_management'); ?></h2>
                        
                        <div class="mb-4">                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                <?php echo t('admin_sitemap_description'); ?>
                            </p>
                            
                            <?php
                            // Sitemap dosyasının durumunu kontrol et
                            $sitemap_settings = $db->query("SELECT value FROM settings WHERE `key` = 'sitemap_filename'")->fetch(PDO::FETCH_ASSOC);
                            $sitemap_filename = $sitemap_settings['value'] ?? 'sitemap.xml';
                            $sitemap_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $sitemap_filename;
                            $sitemap_exists = file_exists($sitemap_path);
                            $sitemap_url = getSiteURL() . '/' . $sitemap_filename;
                            ?>
                              <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded mb-4">
                                <p class="font-semibold dark:text-gray-300"><?php echo t('admin_sitemap_status'); ?>:</p>                                <?php if($sitemap_exists): ?>
                                    <p class="text-green-600 dark:text-green-400">
                                        <i class="fas fa-check-circle mr-2"></i> 
                                        <?php echo t('admin_sitemap_exists', date('d.m.Y H:i', filemtime($sitemap_path))); ?>
                                    </p>
                                    <p class="mt-2">
                                        <a href="<?php echo $sitemap_url; ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            <i class="fas fa-external-link-alt mr-1"></i> <?php echo t('admin_view_sitemap'); ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="text-red-600 dark:text-red-400">
                                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                                        <?php echo t('admin_sitemap_not_exists'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <form method="post">                                <button type="submit" name="generate_sitemap" class="w-full bg-blue-500 text-white font-semibold py-2 px-4 rounded hover:bg-blue-600 dark:bg-blue-700 dark:hover:bg-blue-600 transition-colors">
                                    <i class="fas fa-sync-alt mr-2"></i> <?php echo t('admin_generate_update_sitemap'); ?>
                                </button>
                            </form>
                        </div>
                          <div class="mt-6 border-t dark:border-gray-700 pt-4">
                            <h3 class="font-semibold text-lg mb-2 dark:text-gray-300"><?php echo t('admin_auto_update'); ?></h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-2"><?php echo t('admin_sitemap_auto_update_description'); ?></p>
                            <div class="bg-gray-800 text-white dark:bg-gray-900 dark:text-gray-200 p-3 rounded font-mono text-sm">
                                0 0 * * * wget -q -O /dev/null <?php echo getSiteURL(); ?>/sitemap.php?save=true
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-2"><?php echo t('admin_sitemap_cron_description'); ?></p>
                        </div>
                    </div>                    <!-- Robots.txt Yönetimi -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4"><?php echo t('admin_robots_management'); ?></h2>
                        
                        <div class="mb-4">
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                <?php echo t('admin_robots_description'); ?>
                            </p>
                            
                            <?php
                            // Robots.txt dosyasının durumunu kontrol et
                            $robots_path = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
                            $robots_exists = file_exists($robots_path);
                            $robots_url = getSiteURL() . '/robots.txt';
                            ?>
                              <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded mb-4">
                                <p class="font-semibold dark:text-gray-300"><?php echo t('admin_robots_status'); ?>:</p>
                                <?php if($robots_exists): ?>
                                    <p class="text-green-600 dark:text-green-400">
                                        <i class="fas fa-check-circle mr-2"></i> 
                                        <?php echo t('admin_robots_exists', date('d.m.Y H:i', filemtime($robots_path))); ?>
                                    </p>
                                    <p class="mt-2">
                                        <a href="<?php echo $robots_url; ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            <i class="fas fa-external-link-alt mr-1"></i> <?php echo t('admin_view_robots'); ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="text-red-600 dark:text-red-400">
                                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                                        <?php echo t('admin_robots_not_exists'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <form method="post">                                <button type="submit" name="generate_robots" class="w-full bg-blue-500 text-white font-semibold py-2 px-4 rounded hover:bg-blue-600 dark:bg-blue-700 dark:hover:bg-blue-600 transition-colors">
                                    <i class="fas fa-sync-alt mr-2"></i> <?php echo t('admin_generate_update_robots'); ?>
                                </button>
                            </form>
                        </div>
                          <div class="mt-6 border-t dark:border-gray-700 pt-4">
                            <h3 class="font-semibold text-lg mb-2 dark:text-gray-300"><?php echo t('admin_seo_tips'); ?></h3>
                            <ul class="list-disc pl-5 space-y-2 text-gray-600 dark:text-gray-400">
                                <li><?php echo t('admin_seo_tip_1'); ?></li>
                                <li><?php echo t('admin_seo_tip_2'); ?></li>
                                <li><?php echo t('admin_seo_tip_3'); ?></li>
                                <li><?php echo t('admin_seo_tip_4'); ?></li>
                                <li><?php echo t('admin_seo_tip_5'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>                <!-- SEO Durum Analizi -->
                <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4"><?php echo t('admin_seo_status_analysis'); ?></h2>
                    
                    <?php
                    // SEO durumu analizi
                    $seo_checks = [
                        'sitemap' => [
                            'status' => $sitemap_exists,
                            'message' => $sitemap_exists ? t('admin_sitemap_exists_short') : t('admin_sitemap_not_exists_short')
                        ],
                        'robots' => [
                            'status' => $robots_exists,
                            'message' => $robots_exists ? t('admin_robots_exists_short') : t('admin_robots_not_exists_short')
                        ],
                        'meta_desc' => [
                            'status' => !empty($db->query("SELECT value FROM settings WHERE `key` = 'site_description'")->fetch(PDO::FETCH_ASSOC)['value'] ?? ''),
                            'message' => !empty($db->query("SELECT value FROM settings WHERE `key` = 'site_description'")->fetch(PDO::FETCH_ASSOC)['value'] ?? '') ? t('admin_site_description_defined') : t('admin_site_description_not_defined')
                        ],
                        'social_meta' => [
                            'status' => ($db->query("SELECT value FROM settings WHERE `key` = 'seo_open_graph'")->fetch(PDO::FETCH_ASSOC)['value'] ?? 0) == 1,
                            'message' => ($db->query("SELECT value FROM settings WHERE `key` = 'seo_open_graph'")->fetch(PDO::FETCH_ASSOC)['value'] ?? 0) == 1 ? t('admin_social_meta_enabled') : t('admin_social_meta_disabled')
                        ]
                    ];
                    
                    // Başarılı kontrol sayısı
                    $success_count = 0;
                    foreach ($seo_checks as $check) {
                        if ($check['status']) $success_count++;
                    }
                    
                    $success_rate = round(($success_count / count($seo_checks)) * 100);
                    ?>
                      <div class="mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo t('admin_seo_status_score'); ?>: <?php echo $success_rate; ?>%</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?php echo $success_count; ?>/<?php echo count($seo_checks); ?> <?php echo t('admin_successful'); ?></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div class="bg-blue-600 dark:bg-blue-500 h-2.5 rounded-full" style="width: <?php echo $success_rate; ?>%"></div>
                        </div>
                    </div>
                      <div class="space-y-4">
                        <?php foreach ($seo_checks as $key => $check): ?>
                            <div class="flex items-center p-3 border rounded <?php echo $check['status'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20'; ?>">
                                <div class="mr-3">
                                    <?php if($check['status']): ?>
                                        <span class="bg-green-500 dark:bg-green-600 text-white rounded-full w-6 h-6 flex items-center justify-center">
                                            <i class="fas fa-check text-xs"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-red-500 dark:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center">
                                            <i class="fas fa-times text-xs"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="<?php echo $check['status'] ? 'text-green-800 dark:text-green-400' : 'text-red-800 dark:text-red-400'; ?> font-semibold"><?php echo $check['message']; ?></p>
                                    <?php if(!$check['status']): ?>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <?php 
                                                switch($key) {
                                                    case 'sitemap':
                                                        echo t('admin_sitemap_create_hint');
                                                        break;
                                                    case 'robots':
                                                        echo t('admin_robots_create_hint');
                                                        break;
                                                    case 'meta_desc':
                                                        echo t('admin_meta_desc_hint');
                                                        break;
                                                    case 'social_meta':
                                                        echo t('admin_social_meta_hint');
                                                        break;
                                                }
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>                        <?php endforeach; ?>
                    </div>
                </div>
</div>
<?php include 'includes/footer.php'; ?>
