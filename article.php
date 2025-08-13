<?php
require_once 'includes/config.php';
require_once 'includes/session_init.php'; // Session işlemleri burada yapılıyor

$slug = clean($_GET['slug'] ?? '');

if (empty($slug)) {
    header('Location: /');
    exit;
}

// Makaleyi getir
$stmt = $db->prepare("
    SELECT a.*, c.name as category_name, u.username
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.slug = ? AND a.status = 'published'
");
$stmt->execute([$slug]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    header('Location: /');
    exit;
}

// Premium içerik kontrolü
if (isset($article['is_premium']) && $article['is_premium'] == 1) {
    // Kullanıcı giriş yapmamış veya premium üye değilse premium sayfasına yönlendir
    if (!isset($_SESSION['user_id'])) {
        // Makalenin ID'sini premium sayfasına parametre olarak gönder
        header('Location: /premium.php?article_id=' . $article['id']);
        exit;
    }
    
    // Kullanıcı giriş yapmış ama premium üye değilse premium sayfasına yönlendir
    if (!checkPremiumStatus($_SESSION['user_id'])) {
        header('Location: /premium.php?article_id=' . $article['id']);
        exit;
    }
}

// Varsayılan featured_image değeri
if (empty($article['featured_image'])) {
    $article['featured_image'] = '/assets/images/default-article.jpg';
}

// Makale görüntülenme sayısını artır
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$ip_address = $_SERVER['REMOTE_ADDR'];

// Bu kullanıcının (giriş yapmış veya yapmamış) bu makaleyi daha önce görüntüleyip görüntülemediğini kontrol et
$view_check = $db->prepare("SELECT id FROM article_views WHERE article_id = ? AND (user_id = ? OR (user_id IS NULL AND ip_address = ?))");
$view_check->execute([$article['id'], $user_id, $ip_address]);
$existing_view = $view_check->fetch(PDO::FETCH_ASSOC);

// Eğer bu kullanıcı bu makaleyi daha önce görüntülemediyse, görüntüleme kaydını ekle
if (!$existing_view) {
    // Görüntüleme kaydını ekle
    $insert_view = $db->prepare("INSERT INTO article_views (article_id, user_id, ip_address) VALUES (?, ?, ?)");
    $insert_view->execute([$article['id'], $user_id, $ip_address]);
    
    // Makale tablosundaki görüntülenme sayısını güncelle
    $update_count = $db->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?");
    $update_count->execute([$article['id']]);
    
    // Oturum değişkeni ile makale görüntüleme bilgisini güncelle
    $article['view_count']++;
}

// Yorumları getir - Ana yorumları ve cevapları ayrı ayrı alarak düzenleyeceğiz
// Önce ana yorumları getir (parent_id = NULL olan yorumlar)
$stmt = $db->prepare("
    SELECT c.*, u.username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.article_id = ? AND c.status = 'approved' AND c.parent_id IS NULL
    ORDER BY c.created_at DESC
");
$stmt->execute([$article['id']]);
$main_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sonra tüm cevapları getir
$stmt = $db->prepare("
    SELECT c.*, u.username, pc.user_id as parent_user_id, pu.username as parent_username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    JOIN comments pc ON c.parent_id = pc.id
    JOIN users pu ON pc.user_id = pu.id
    WHERE c.article_id = ? AND c.status = 'approved' AND c.parent_id IS NOT NULL
    ORDER BY c.parent_id, c.created_at
");
$stmt->execute([$article['id']]);
$replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cevapları ana yorumlara bağlayalım
$comments = $main_comments;
foreach ($comments as $key => $comment) {
    $comments[$key]['replies'] = [];
}

// Cevapları ilgili ana yorumlara ekleyelim
foreach ($replies as $reply) {
    foreach ($comments as $key => $comment) {
        if ($comment['id'] == $reply['parent_id']) {
            $comments[$key]['replies'][] = $reply;
        }
    }
}

// Yorum ekleme
$error = '';
$success = '';

// URL'den mesaj parametrelerini al
if (isset($_GET['success'])) {
    $success = __('comment_approval_notice');
}

if (isset($_GET['error'])) {
    $error = $_GET['error'] === 'empty' ? __('comment_empty_error') : __('comment_add_error');
}

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $content = clean($_POST['content']);
    
    // Eğer bir yoruma cevap ise parent_id'yi al
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Eğer parent_id varsa, bu yorumun makaleye ait olup olmadığını kontrol et
    if ($parent_id) {
        $check_stmt = $db->prepare("SELECT id FROM comments WHERE id = ? AND article_id = ?");
        $check_stmt->execute([$parent_id, $article['id']]);
        if (!$check_stmt->fetch()) {
            $parent_id = null; // Eğer böyle bir yorum yoksa veya bu makaleye ait değilse, normal yorum olarak kaydet
        }
    }
    
    if (empty($content)) {
        // Hata durumunda yönlendirme
        header('Location: ' . $_SERVER['REQUEST_URI'] . '?error=empty');
        exit;
    } else {
        try {
            if ($parent_id) {
                $stmt = $db->prepare("INSERT INTO comments (article_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$article['id'], $_SESSION['user_id'], $content, $parent_id]);
                $comment_id = $db->lastInsertId();
            } else {
                $stmt = $db->prepare("INSERT INTO comments (article_id, user_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$article['id'], $_SESSION['user_id'], $content]);
                $comment_id = $db->lastInsertId();
            }
            
            // Admin bildirim sistemi için yeni yorum bildirimi ekle
            if (file_exists('admin/includes/notifications.php')) {
                require_once 'admin/includes/notifications.php';
                // Yeni yorum bildirimi ekle
                $message = "{$_SESSION['username']} tarafından \"{$article['title']}\" başlıklı makalede yeni bir yorum yapıldı";
                $link = "/admin/comments.php?action=view&id={$comment_id}";
                addAdminNotification('new_comment', $_SESSION['user_id'], $message, $link, $comment_id);
            }
            
            // Başarılı durumda yönlendirme
            header('Location: ' . $_SERVER['REQUEST_URI'] . '?success=1');
            exit;
        } catch(PDOException $e) {
            // Hata durumunda yönlendirme
            header('Location: ' . $_SERVER['REQUEST_URI'] . '?error=db');
            exit;
        }
    }
}

// Benzer makaleleri getir (Aynı kategoriden, mevcut makale hariç, en popüler 3 makale)
$similar_stmt = $db->prepare("
    SELECT a.*, c.name as category_name, u.username
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.category_id = ? AND a.id != ? AND a.status = 'published'
    ORDER BY a.view_count DESC
    LIMIT 3
");
$similar_stmt->execute([$article['category_id'], $article['id']]);
$similar_articles = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr" class="dark-mode-transition">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article['title']; ?> - <?php echo getSetting('site_title'); ?></title>
    <meta name="description" content="<?php echo mb_substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 160); ?>">
    <meta property="og:title" content="<?php echo $article['title']; ?>">
    <meta property="og:description" content="<?php echo mb_substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 160); ?>">    <?php if (isset($article['featured_image']) && $article['featured_image']): ?>
    <meta property="og:image" content="<?php echo $article['featured_image']; ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/makale/' . $article['slug']; ?>" />
    <?php
    // Favicon ekle
    $favicon = getSetting('favicon');
    if (!empty($favicon)) {
        echo '<link rel="icon" href="/' . $favicon . '">';
    }
    ?>
    <!-- Tema ayarlarını sayfa yüklenmeden önce kontrol et ve uygula -->
    <script>
        // Mevcut tema tercihini kontrol et ve uygula
        if (localStorage.getItem('theme') === 'dark' || 
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
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
    <style>
        /* Makale içeriği için gelişmiş stil */
        .article-content {
            font-family: 'Georgia', 'Times New Roman', serif;
            line-height: 1.8;
            color: #2d3748;
        }
        
        .dark .article-content {
            color: #e2e8f0;
        }
        
        .article-content p {
            margin-bottom: 1.8rem;
            line-height: 1.8;
            font-size: 1.1rem;
            text-align: justify;
        }
        
        .article-content h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-top: 3rem;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid #3182ce;
            padding-bottom: 0.5rem;
        }
        
        .dark .article-content h1 {
            color: #f7fafc;
            border-bottom-color: #4299e1;
        }
        
        .article-content h2 {
            font-size: 1.6rem;
            font-weight: 600;
            color: #2d3748;
            margin-top: 2.5rem;
            margin-bottom: 1.2rem;
            border-left: 4px solid #3182ce;
            padding-left: 1rem;
            background-color: #f7fafc;
            padding: 0.8rem 1rem;
            border-radius: 0.375rem;
        }
        
        .dark .article-content h2 {
            color: #e2e8f0;
            background-color: #2d3748;
            border-left-color: #4299e1;
        }
        
        .article-content h3 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2d3748;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        .dark .article-content h3 {
            color: #e2e8f0;
        }
        
        .article-content h4, .article-content h5, .article-content h6 {
            font-weight: 600;
            color: #4a5568;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
        }
        
        .dark .article-content h4, 
        .dark .article-content h5, 
        .dark .article-content h6 {
            color: #cbd5e0;
        }
        
        .article-content ul, .article-content ol {
            margin-bottom: 1.5rem;
            margin-left: 2rem;
        }
        
        .article-content li {
            margin-bottom: 0.5rem;
            line-height: 1.7;
        }
        
        .article-content blockquote {
            border-left: 4px solid #cbd5e0;
            margin: 1.5rem 0;
            padding-left: 1rem;
            font-style: italic;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
        }
        
        .dark .article-content blockquote {
            background-color: #2d3748;
            border-left-color: #4a5568;
            color: #e2e8f0;
        }
        
        .article-content strong {
            font-weight: 600;
            color: #1a202c;
        }
        
        .dark .article-content strong {
            color: #f7fafc;
        }
        
        .article-content em {
            font-style: italic;
        }
        
        /* İlk paragraf özel stili */
        .article-content p:first-child {
            font-size: 1.15rem;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 2rem;
        }
        
        /* İlk paragraf için koyu mod desteği */
        .dark .article-content p:first-child {
            color: #e2e8f0;
        }
        
        /* Mobil responsive */
        @media (max-width: 768px) {
            .article-content {
                font-size: 1rem;
            }
            
            .article-content h1 {
                font-size: 1.8rem;
            }
            
            .article-content h2 {
                font-size: 1.4rem;
                margin-top: 2rem;
            }
            
            .article-content p {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }
        }
        
        /* Print stili */
        @media print {
            .article-content {
                font-size: 12pt;
                line-height: 1.6;
            }
            
            .article-content h2 {
                page-break-after: avoid;
            }
        }
        }
        .article-content h2 {
            font-size: 1.75rem;
        }
        .article-content h3 {
            font-size: 1.5rem;
        }
        .article-content h4 {
            font-size: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .article-content h5, .article-content h6 {
            font-size: 1.1rem;
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .article-content ul, .article-content ol {
            margin-bottom: 1.5rem;
            padding-left: 2rem;
        }
        .article-content ul li, .article-content ol li {
            margin-bottom: 0.5rem;
        }
        .article-content img {
            max-width: 100%;
            height: auto;
            margin: 1.5rem 0;
            display: block; /* Blok element olarak gösterilsin */
        }
        .article-content a {
            color: #2563eb;
            text-decoration: underline;
        }
        
        /* Reklam stilleri */
        #article-middle-ad, #article-top-ad {
            margin: 30px auto;
            text-align: center;
            clear: both;
            display: block;
            overflow: visible;
        }
        .article-content a:hover {
            color: #1d4ed8;
        }
        
        /* Dark mode geçiş efekti */
        .dark-mode-transition {
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .dark-mode-transition * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
    </style>
    <script>
        // Yorum cevaplama formunu göster
        function showReplyForm(commentId) {
            // Önce tüm açık formları kapat
            const allForms = document.querySelectorAll('[id^="replyForm_"]');
            allForms.forEach(form => form.classList.add('hidden'));
            
            // İlgili formu göster
            document.getElementById('replyForm_' + commentId).classList.remove('hidden');
            
            // Textarea'ya odaklan
            document.querySelector('#replyForm_' + commentId + ' textarea').focus();
        }
        
        // Yorum cevaplama formunu gizle
        function hideReplyForm(commentId) {
            document.getElementById('replyForm_' + commentId).classList.add('hidden');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-dark-bg dark-mode-transition">
    <?php include 'templates/header.php'; ?>

    <div class="container mx-auto px-4 py-0 mt-1">
        <!-- Boşluk eklendi -->
    </div>

    <?php echo showAd('header'); // Header altı reklam ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Ana İçerik - Makale -->
            <div class="w-full lg:w-2/3">
                <article class="bg-white dark:bg-dark-card rounded-lg shadow-lg overflow-hidden">
                    <?php if (isset($article['featured_image']) && $article['featured_image']): ?>
                    <div class="relative">
                        <?php
                        // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                        $imgSrc = (strpos($article['featured_image'], 'http://') === 0 || strpos($article['featured_image'], 'https://') === 0)
                            ? $article['featured_image'] 
                            : (strpos($article['featured_image'], '/') === 0 
                                ? $article['featured_image'] 
                                : "/uploads/ai_images/" . $article['featured_image']);
                        ?>
                        <img src="<?php echo $imgSrc; ?>" 
                             alt="<?php echo $article['title']; ?>"
                             class="w-full h-96 object-cover">
                        
                        <?php if (isset($article['is_premium']) && $article['is_premium'] == 1): ?>
                        <div class="absolute top-4 right-4 bg-purple-600 text-white py-2 px-4 rounded-md flex items-center shadow">
                            <i class="fas fa-crown text-yellow-300 mr-2"></i> <?php echo __('premium_content'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-6">                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 mb-4">                            <span class="mr-4">
                                <i class="fas fa-user mr-2"></i>
                                <a href="<?php echo '/uyeler/' . urlencode($article['username']); ?>" class="hover:text-blue-600">
                                    <?php echo $article['username']; ?>
                                </a>
                            </span>                            <span class="mr-4">                                <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <?php echo $article['category_name']; ?>
                                </span>
                            </span>
                            <span class="mr-4">
                                <i class="fas fa-eye mr-2"></i>
                                <?php echo number_format($article['view_count'] ?? 0); ?> <?php echo __('views'); ?>
                            </span>
                            <span>
                                <i class="fas fa-calendar mr-2"></i>
                                <?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
                            </span>
                        </div>
                          <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-gray-100">
                            <?php echo $article['title']; ?>
                        </h1>
                        
                        <?php if (!empty($article['tags'])): ?>
                        <div class="flex flex-wrap gap-2 mb-6">
                            <?php
                            $tags = explode(',', $article['tags']);
                            foreach ($tags as $tag):
                                $tag = trim($tag);
                                if (!empty($tag)):
                            ?>
                                <a href="/etiket/<?php echo urlencode($tag); ?>" class="inline-block bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-700 dark:text-blue-300 rounded-full px-3 py-1 text-sm transition-colors duration-200">
                                    <i class="fas fa-tag text-xs mr-1"></i> <?php echo htmlspecialchars($tag); ?>
                                </a>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="prose max-w-none article-content">
                            <!-- Makale içi üst reklam kısmı -->
                            <?php 
                            // Makale üst reklam kodunu al
                            $article_top_ad = showAd('article_top');
                            if (!empty($article_top_ad)) {
                                echo '<div id="article-top-ad" class="my-6 mx-auto text-center" style="clear:both; margin:20px auto;">' . $article_top_ad . '</div>';
                            }
                            ?>
                            
                            <?php 
                            // Makale içeriğinin yarısında reklam göster
                            $content = html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8');
                            
                            // Debug için içerik bilgisini loglayalım
                            error_log("Makale içeriği karakter sayısı: " . strlen($content));
                            
                            // Orta reklam kodunu al
                            $middle_ad = showAd('article');
                            error_log("Makale orta reklam kodu alındı: " . (!empty($middle_ad) ? "Dolu" : "Boş"));
                            
                            // İçeriği direk olduğu gibi yazdır
                            echo $content;
                            
                            // Eğer orta reklam kodu varsa, içerikten sonra ekle
                            if (!empty($middle_ad)) {
                                // İçerikten sonra, sosyal butonlardan önce reklamı ekle
                                echo '<div id="article-middle-ad" class="my-8 mx-auto text-center" style="clear:both; margin:20px auto;">' . $middle_ad . '</div>';
                                
                                // JavaScript ile reklamı makale ortasına yerleştir
                                echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    // Makale içeriğindeki tüm paragrafları bul
                                    var content = document.querySelector(".article-content");
                                    var paragraphs = content.querySelectorAll("p");
                                    
                                    if (paragraphs.length > 3) {
                                        // Ortadaki paragrafı bul
                                        var middleIndex = Math.floor(paragraphs.length / 2);
                                        var middleParagraph = paragraphs[middleIndex];
                                        
                                        // Reklam elementini al
                                        var adElement = document.getElementById("article-middle-ad");
                                        
                                        if (middleParagraph && adElement) {
                                            // Reklamı orta paragrafın sonrasına ekle
                                            middleParagraph.parentNode.insertBefore(adElement, middleParagraph.nextSibling);
                                            console.log("Reklam makale ortasına yerleştirildi");
                                        }
                                    }
                                });
                                </script>';
                                
                                error_log("Reklam içerik sonrasına eklendi, JS ile taşınacak");
                            }
                            ?>                        </div>
                        
                        <!-- Makale içi resimlerin ve reklamların stillerini düzenleyen script -->
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Tüm resimlerin CSS özelliklerini ayarla
                            var images = document.querySelectorAll('.article-content img');
                            images.forEach(function(img) {
                                img.style.maxWidth = '100%';
                                img.style.height = 'auto';
                                img.style.display = 'block';
                                img.style.margin = '20px auto';
                            });
                            
                            // Reklamların görünürlüğünü ayarla
                            var middleAdElement = document.getElementById('article-middle-ad');
                            if (middleAdElement) {
                                middleAdElement.style.margin = '30px auto';
                                middleAdElement.style.textAlign = 'center';
                                middleAdElement.style.clear = 'both';
                                middleAdElement.style.display = 'block';
                            }

                            // Üst reklamın görünürlüğünü ayarla
                            var topAdElement = document.getElementById('article-top-ad');
                            if (topAdElement) {
                                topAdElement.style.margin = '20px auto';
                                topAdElement.style.textAlign = 'center';
                                topAdElement.style.clear = 'both';
                                topAdElement.style.display = 'block';
                                
                                // Üst reklamı ilk paragraftan sonraya taşı
                                var firstParagraph = document.querySelector('.article-content p');
                                if (firstParagraph) {
                                    firstParagraph.parentNode.insertBefore(topAdElement, firstParagraph.nextSibling);
                                }
                            }
                        });
                        </script>
                        
                        <!-- Sosyal Medya Paylaşım Butonları -->
                        <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100"><?php echo __('share_article'); ?></h3>
                            <script>
                            // AdGuard tarafından engellenmemesi için JavaScript ile paylaşım işlevleri
                            function shareSocial(platform, url, title) {
                                let shareUrl = '';
                                switch (platform) {
                                    case 'facebook':
                                        shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
                                        break;
                                    case 'twitter':
                                        shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title;
                                        break;
                                    case 'linkedin':
                                        shareUrl = 'https://www.linkedin.com/shareArticle?mini=true&url=' + url + '&title=' + title;
                                        break;
                                    case 'whatsapp':
                                        shareUrl = 'https://api.whatsapp.com/send?text=' + title + ': ' + url;
                                        break;
                                }
                                if (shareUrl) {
                                    window.open(shareUrl, '_blank', 'width=600,height=400');
                                    return false;
                                }
                            }
                            </script>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                // URL ve başlık için kodlamalar
                                $encoded_url = urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                                $encoded_title = urlencode($article['title']);
                                ?>
                                
                                <!-- Facebook -->
                                <button onclick="shareSocial('facebook', '<?php echo $encoded_url; ?>', '<?php echo $encoded_title; ?>')" 
                                   class="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors duration-200">
                                    <i class="fab fa-facebook-f mr-2"></i> Facebook
                                </button>
                                
                                <!-- Twitter/X -->
                                <button onclick="shareSocial('twitter', '<?php echo $encoded_url; ?>', '<?php echo $encoded_title; ?>')"
                                   class="flex items-center justify-center bg-black hover:bg-gray-800 text-white px-4 py-2 rounded-md transition-colors duration-200">
                                    <i class="fab fa-x-twitter mr-2"></i> X (Twitter)
                                </button>
                                
                                <!-- LinkedIn -->
                                <button onclick="shareSocial('linkedin', '<?php echo $encoded_url; ?>', '<?php echo $encoded_title; ?>')"
                                   class="flex items-center justify-center bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md transition-colors duration-200">
                                    <i class="fab fa-linkedin-in mr-2"></i> LinkedIn
                                </button>
                                
                                <!-- WhatsApp -->
                                <button onclick="shareSocial('whatsapp', '<?php echo $encoded_url; ?>', '<?php echo $encoded_title; ?>')"
                                   class="flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors duration-200">
                                    <i class="fab fa-whatsapp mr-2"></i> WhatsApp
                                </button>
                                
                                <!-- Email -->
                                <a href="mailto:?subject=<?php echo $encoded_title; ?>&body=<?php echo $encoded_url; ?>" 
                                   class="flex items-center justify-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition-colors duration-200">
                                    <i class="fas fa-envelope mr-2"></i> <?php echo __('email'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Makale altı reklam - sosyal paylaşım butonları ile arasında boşluk -->
                        <div class="mt-12 mb-4">
                            <?php echo showAd('article_bottom'); // Makale altı reklam ?>
                        </div>
                    </div>
                </article>                <!-- Benzer Makaleler -->
                <div class="mt-8 mb-12">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-100"><?php echo __('similar_articles'); ?></h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if (count($similar_articles) > 0): ?>
                            <?php foreach ($similar_articles as $similar): ?>
                                <?php 
                                // Varsayılan resim kontrolü
                                if (!empty($similar['featured_image'])) {
                                    // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                                    $featured_img = (strpos($similar['featured_image'], 'http://') === 0 || strpos($similar['featured_image'], 'https://') === 0)
                                        ? $similar['featured_image'] 
                                        : (strpos($similar['featured_image'], '/') === 0 
                                            ? $similar['featured_image'] 
                                            : "/uploads/ai_images/" . $similar['featured_image']);
                                } else {
                                    $featured_img = '/assets/images/default-article.jpg';
                                }
                                ?>                                <div class="bg-white dark:bg-dark-card rounded-lg shadow-md overflow-hidden transition-transform duration-300 hover:scale-105">
                                    <a href="/makale/<?php echo $similar['slug']; ?>">
                                        <img src="<?php echo $featured_img; ?>" alt="<?php echo $similar['title']; ?>" class="w-full h-48 object-cover">
                                    </a>
                                    <div class="p-4">
                                        <a href="/makale/<?php echo $similar['slug']; ?>">
                                            <h3 class="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200"><?php echo $similar['title']; ?></h3>
                                        </a>
                                        <p class="text-gray-600 dark:text-gray-300 text-sm mb-3">
                                            <?php echo mb_substr(strip_tags(html_entity_decode($similar['content'], ENT_QUOTES, 'UTF-8')), 0, 100) . '...'; ?>
                                        </p>
                                        <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                            <span><i class="fas fa-user mr-1"></i> <?php echo $similar['username']; ?></span>
                                            <span><i class="fas fa-eye mr-1"></i> <?php echo number_format($similar['view_count']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>                        <?php else: ?>
                            <div class="col-span-3 text-center py-8 bg-gray-50 dark:bg-dark-surface rounded-lg">
                                <p class="text-gray-500 dark:text-gray-400"><?php echo __('no_similar_articles'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Yorumlar -->
                <div class="mt-8">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-100"><?php echo __('comments'); ?></h2>

                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                    <?php endif; ?>                    <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="post" class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6 mb-8" id="commentForm">
                        <div class="mb-4">
                            <label class="block text-gray-700 dark:text-gray-200 mb-2"><?php echo __('your_comment'); ?></label>
                            <textarea name="content" required rows="4"
                                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-dark-surface dark:text-gray-200 dark:border-dark-border"></textarea>
                        </div>
                        <button type="submit" 
                                class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition duration-300">
                            <?php echo __('post_comment'); ?>
                        </button>
                    </form>
                    <script>
                    // URL'den sorgu parametrelerini temizle
                    if (window.history.replaceState) {
                        const cleanUrl = window.location.href.split('?')[0];
                        window.history.replaceState(null, document.title, cleanUrl);
                    }
                    
                    // Form gönderildiğinde çift gönderimi önlemek için butonu devre dışı bırak
                    document.getElementById('commentForm').addEventListener('submit', function(e) {
                        const submitButton = this.querySelector('button[type="submit"]');
                        submitButton.disabled = true;
                        submitButton.textContent = '<?php echo __('sending'); ?>';
                    });
                    </script>
                    <?php else: ?>                    <div id="login-notice" class="px-4 py-3 rounded mb-8">
                        <?php echo __('comment_login_required'); ?> <a href="/login" class="font-bold login-link"><?php echo __('login_required'); ?></a>.
                    </div>
                    
                    <script>
                    // Tema kontrolü ve login bildirimi için stil ayarı
                    document.addEventListener('DOMContentLoaded', function() {
                        const loginNotice = document.getElementById('login-notice');
                        const loginLink = document.querySelector('.login-link');
                        
                        function updateLoginNoticeStyle() {
                            if (document.documentElement.classList.contains('dark')) {
                                // Karanlık mod için stil
                                loginNotice.style.backgroundColor = '#292929';
                                loginNotice.style.borderColor = '#3d3d3d';
                                loginNotice.style.color = '#e0e0e0';
                                loginNotice.style.border = '1px solid #3d3d3d';
                                if (loginLink) {
                                    loginLink.style.color = '#e0e0e0';
                                    loginLink.onmouseover = function() { this.style.color = '#b3b3b3'; };
                                    loginLink.onmouseout = function() { this.style.color = '#e0e0e0'; };
                                }
                            } else {
                                // Aydınlık mod için stil
                                loginNotice.style.backgroundColor = '#fff9c4';
                                loginNotice.style.borderColor = '#f9e79f';
                                loginNotice.style.color = '#8a6d3b';
                                loginNotice.style.border = '1px solid #f9e79f';
                                if (loginLink) {
                                    loginLink.style.color = '#8a6d3b';
                                    loginLink.onmouseover = function() { this.style.color = '#66512c'; };
                                    loginLink.onmouseout = function() { this.style.color = '#8a6d3b'; };
                                }
                            }
                        }
                        
                        // Sayfa yüklendiğinde stil ayarla
                        updateLoginNoticeStyle();
                        
                        // Tema değiştiğinde stili güncelle
                        const observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.attributeName === 'class') {
                                    updateLoginNoticeStyle();
                                }
                            });
                        });
                        
                        observer.observe(document.documentElement, { attributes: true });
                    });
                    </script>
                    <?php endif; ?>                    <div class="space-y-6">
                        <?php foreach ($comments as $comment): ?>
                        <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-user-circle text-gray-400 text-2xl mr-3"></i>
                                    <div>
                                        <div class="font-semibold">
                                            <?php echo $comment['username']; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (isset($_SESSION['user_id'])): ?>
                                <button type="button" 
                                        onclick="showReplyForm('<?php echo $comment['id']; ?>')"
                                        class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                                    <i class="fas fa-reply mr-1"></i> <?php echo __('reply'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-gray-700">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </p>
                            
                            <!-- Yorum cevap formu (başlangıçta gizli) -->                            <?php if (isset($_SESSION['user_id'])): ?>
                            <div id="replyForm_<?php echo $comment['id']; ?>" class="mt-4 pl-6 border-l-2 border-gray-200 dark:border-gray-600 hidden">
                                <form method="post" class="bg-gray-50 dark:bg-dark-surface rounded-lg p-4">
                                    <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                    <div class="mb-3">
                                        <label class="block text-gray-700 dark:text-gray-200 text-sm font-bold mb-2">
                                            <i class="fas fa-reply mr-1"></i> <?php echo sprintf(__('replying_to'), $comment['username']); ?>:
                                        </label>
                                        <textarea name="content" required rows="3"
                                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-dark-surface dark:text-gray-200 dark:border-dark-border"></textarea>
                                    </div>
                                    <div class="flex justify-between">
                                        <button type="submit" 
                                                class="bg-blue-500 text-white px-4 py-1 rounded hover:bg-blue-600 transition duration-300 text-sm">
                                            <?php echo __('send_reply'); ?>
                                        </button>
                                        <button type="button" 
                                                onclick="hideReplyForm('<?php echo $comment['id']; ?>')"
                                                class="text-gray-500 hover:text-gray-700 px-4 py-1 text-sm">
                                            <?php echo __('cancel'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Cevapları göster -->
                            <?php if (!empty($comment['replies'])): ?>                            <div class="mt-4 space-y-4 pl-6 border-l-2 border-gray-200 dark:border-gray-600">
                                <?php foreach ($comment['replies'] as $reply): ?>
                                <div class="bg-gray-50 dark:bg-dark-surface rounded-lg p-4">
                                    <div class="flex items-start mb-2">
                                        <i class="fas fa-user-circle text-gray-400 text-xl mr-2 mt-1"></i>
                                        <div>
                                            <div class="flex items-center">
                                                <span class="font-semibold"><?php echo $reply['username']; ?></span>
                                                <span class="mx-2 text-gray-400">→</span>
                                                <span class="text-gray-600"><?php echo $reply['parent_username']; ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('d.m.Y H:i', strtotime($reply['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 pl-7">
                                        <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                    </p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
              <!-- Sağ Sidebar -->
            <div class="w-full lg:w-1/3 space-y-6">
                <?php echo showAd('sidebar_top'); // Sidebar üst reklam ?>
                
              <!-- Arama -->
              <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4 sidebar-heading dark:text-gray-200"><?php echo __('search'); ?></h3>
                    <form action="/search.php" method="get" class="flex">
                        <input type="text" name="q" placeholder="<?php echo __('search_article_placeholder'); ?>" 
                               class="flex-1 px-4 py-2 border rounded-l focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-dark-surface dark:border-dark-border dark:text-gray-200 dark:focus:ring-blue-500 dark:placeholder-gray-400">
                        <button type="submit" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-r hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-300">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Kategoriler -->
                <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4 sidebar-heading cursor-pointer flex justify-between items-center dark:text-gray-200" id="categoriesHeading">
                        <span><?php echo __('categories'); ?></span>
                        <i class="fas fa-chevron-down transition-transform" id="categoriesIcon" style="transform: rotate(180deg);"></i>
                    </h3>
                    <div class="space-y-2" id="categoriesContent">
                        <?php
                        // Kategorileri getir - ana ve alt kategorileri hiyerarşik olarak
                        $cat_stmt = $db->query("
                            SELECT c.*, parent.name as parent_name, COUNT(a.id) as article_count
                            FROM categories c
                            LEFT JOIN categories parent ON c.parent_id = parent.id
                            LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
                            GROUP BY c.id, c.name, c.slug, c.parent_id, parent.name
                            ORDER BY COALESCE(c.parent_id, c.id), c.name ASC
                        ");
                        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Kategorileri ana ve alt kategori olarak düzenle
                        $categoriesHierarchy = [];
                        $subcategories = [];
                        
                        foreach ($categories as $category) {
                            if (empty($category['parent_id'])) {
                                // Ana kategori
                                $categoriesHierarchy[$category['id']] = $category;
                                $categoriesHierarchy[$category['id']]['subcategories'] = [];
                            } else {
                                // Alt kategori
                                $subcategories[$category['id']] = $category;
                            }
                        }
                        
                        // Alt kategorileri ana kategorilere ekle
                        foreach ($subcategories as $subcategory) {
                            if (isset($categoriesHierarchy[$subcategory['parent_id']])) {
                                $categoriesHierarchy[$subcategory['parent_id']]['subcategories'][] = $subcategory;
                            } else {
                                // Eğer üst kategori yoksa, doğrudan ana kategoriler listesine ekle
                                $categoriesHierarchy[$subcategory['id']] = $subcategory;
                            }
                        }
                        
                        foreach ($categoriesHierarchy as $mainCategory): 
                            // Ana kategori makale sayısı
                            $count = 0;
                            $count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                            $count_stmt->execute([$mainCategory['id']]);
                            $count = $count_stmt->fetchColumn();
                            
                            // Alt kategorilerdeki makale sayılarını da ekle
                            $subcategoryCount = 0;
                            if (!empty($mainCategory['subcategories'])) {
                                foreach ($mainCategory['subcategories'] as $sub) {
                                    $sub_count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                                    $sub_count_stmt->execute([$sub['id']]);
                                    $subcategoryCount += $sub_count_stmt->fetchColumn();
                                }
                            }
                            $totalCount = $count + $subcategoryCount;
                        ?>
                        <div>
                            <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>" 
                               class="flex items-center justify-between py-2 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                <span class="font-medium"><?php echo htmlspecialchars($mainCategory['name']); ?></span>
                                <span class="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs font-semibold px-2.5 py-0.5 rounded inline-block min-w-[1.5rem] text-center">
                                    <?php echo $totalCount; ?>
                                </span>
                            </a>
                            
                            <?php if (!empty($mainCategory['subcategories'])): ?>
                            <div class="ml-4 mt-1">
                                <?php foreach ($mainCategory['subcategories'] as $subCategory): 
                                    $subCount = 0;
                                    $sub_count_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ? AND status = 'published'");
                                    $sub_count_stmt->execute([$subCategory['id']]);
                                    $subCount = $sub_count_stmt->fetchColumn();
                                ?>
                                <a href="/kategori/<?php echo urlencode($mainCategory['slug']); ?>/<?php echo urlencode($subCategory['slug']); ?>" 
                                   class="flex items-center justify-between py-1 px-3 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">└─ <?php echo htmlspecialchars($subCategory['name']); ?></span>
                                    <span class="bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 text-xs px-2 py-0.5 rounded inline-block min-w-[1.5rem] text-center">
                                        <?php echo $subCount; ?>
                                    </span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>                    </div>
                </div>
                
                <!-- Çevrimiçi Üyeler -->
                <?php include 'includes/online_users_sidebar.php'; ?>
                
                <!-- Popüler Makaleler -->
                <div class="bg-white dark:bg-dark-card rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4 sidebar-heading dark:text-gray-200"><?php echo __('popular_articles'); ?></h3>
                    <div class="space-y-4">
                        <?php 
                        $popular_stmt = $db->query("
                            SELECT a.*, c.name as category_name, u.username,
                                   (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count
                            FROM articles a
                            LEFT JOIN categories c ON a.category_id = c.id
                            LEFT JOIN users u ON a.author_id = u.id
                            WHERE a.status = 'published'
                            ORDER BY a.view_count DESC, a.created_at DESC
                            LIMIT 5
                        ");
                        $popular_articles = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($popular_articles as $pop_article): 
                        ?>                        <a href="/makale/<?php echo urlencode($pop_article['slug']); ?>" 
                           class="flex items-start space-x-4 group">
                        <?php if (!empty($pop_article['featured_image'])): 
                            // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                            $imgSrc = (strpos($pop_article['featured_image'], 'http://') === 0 || strpos($pop_article['featured_image'], 'https://') === 0)
                                ? $pop_article['featured_image'] 
                                : (strpos($pop_article['featured_image'], '/') === 0 
                                    ? $pop_article['featured_image'] 
                                    : "/uploads/ai_images/" . $pop_article['featured_image']);
                            ?>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                 alt="<?php echo htmlspecialchars($pop_article['title']); ?>" 
                                 class="w-20 h-20 object-cover rounded">
                            <?php else: ?>
                            <div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center">
                                <i class="fas fa-image text-gray-400 text-xl"></i>
                            </div>
                            <?php endif; ?>                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 group-hover:text-blue-600 line-clamp-2 sidebar-article-title">
                                    <?php echo htmlspecialchars($pop_article['title']); ?>
                                </h4>
                                <div class="flex items-center text-sm text-gray-500 mt-1">
                                    <span><?php echo date('d.m.Y', strtotime($pop_article['created_at'])); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo $pop_article['comment_count']; ?> <?php echo __('comments_count'); ?></span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>                    </div>
                </div>
                
                <!-- Son Yorumlar -->
                <?php include 'includes/recent_comments_sidebar.php'; ?>
                
                <!-- İstatistikler -->
                <?php include 'includes/stats_sidebar.php'; ?>

                <?php echo showAd('sidebar_bottom'); // Sidebar altı reklam ?>
            </div>
        </div>
    </div>

    <script>
    // Kategoriler açılır kapanır bölüm için script
    document.addEventListener('DOMContentLoaded', function() {
        const categoriesHeading = document.getElementById('categoriesHeading');
        const categoriesContent = document.getElementById('categoriesContent');
        const categoriesIcon = document.getElementById('categoriesIcon');
        
        // Kategori bölümünün açılır kapanır olması için event listener ekleyelim
        if (categoriesHeading && categoriesContent && categoriesIcon) {
            categoriesHeading.addEventListener('click', function() {
                // Toggle kategoriler içeriği
                categoriesContent.classList.toggle('hidden');
                
                // İkon döndürme animasyonu
                if (categoriesContent.classList.contains('hidden')) {
                    categoriesIcon.style.transform = 'rotate(0deg)';
                } else {
                    categoriesIcon.style.transform = 'rotate(180deg)';
                }
            });
        }
    });
    </script>

    <script>
    // Tema Değiştirme Fonksiyonu
    function toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            // Karanlık temadan aydınlık temaya geçiş
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            
            // İkonları güncelle (varsa)
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.remove('hidden');
            }
        } else {
            // Aydınlık temadan karanlık temaya geçiş
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            
            // İkonları güncelle (varsa)
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.remove('hidden');
            }
        }
    }

    // Tema butonlarına event listener ekle
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggleMobileBtn = document.getElementById('theme-toggle-mobile');
        if (themeToggleMobileBtn) {
            themeToggleMobileBtn.addEventListener('click', toggleTheme);
        }
        
        const themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }
        
        // Mevcut tema ayarına göre ikonları başlangıçta doğru şekilde ayarla
        updateThemeIcons();
    });
    
    // İkonları mevcut temaya göre güncelle
    function updateThemeIcons() {
        if (localStorage.getItem('theme') === 'dark' ||
            (!('theme' in localStorage) &&
            window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            // Koyu mod ikonları
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.remove('hidden');
            }
        } else {
            // Açık mod ikonları
            if (document.getElementById('theme-toggle-dark-icon')) {
                document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon')) {
                document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
            }
            
            if (document.getElementById('theme-toggle-dark-icon-mobile')) {
                document.getElementById('theme-toggle-dark-icon-mobile').classList.add('hidden');
            }
            if (document.getElementById('theme-toggle-light-icon-mobile')) {
                document.getElementById('theme-toggle-light-icon-mobile').classList.remove('hidden');
            }
        }
    }
    </script>

    <?php require_once 'templates/footer.php'; ?>
</body>
</html>
