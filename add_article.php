<?php
require_once 'includes/config.php';
require_once 'includes/turnstile.php'; // Turnstile fonksiyonlarını dahil et
checkAuth(); // Giriş kontrolü

// Kullanıcının üyelik onayı ve makale ekleme yetkisi kontrolü
$stmt = $db->prepare("SELECT approved, can_post FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ((!$user['approved'] || !$user['can_post']) && !isAdmin()) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

// Kategorileri hiyerarşik olarak getir (admin paneldeki gibi)
$categories = $db->query("
    SELECT c.id, c.name, c.parent_id, p.name as parent_name
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY COALESCE(c.parent_id, c.id), c.name
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hata ayıklama
    error_log('Kullanıcı makale ekleme formu gönderildi - ' . date('Y-m-d H:i:s'));
    
    $title = clean($_POST['title']);
    $content = $_POST['content'];
    
    // TinyMCE içerik kontrolü
    if (empty($content) && isset($_POST['editor_content'])) {
        error_log('TinyMCE içeriği manuel olarak alınıyor');
        $content = $_POST['editor_content'];
    }
    
    error_log('Makale başlık: ' . $title);
    error_log('Makale içerik uzunluğu: ' . strlen($content));
    error_log('İçerik başlangıcı: ' . substr($content, 0, 100));
    
    // SEO alanları
    $meta_title = clean($_POST['meta_title'] ?? '', true);
    $meta_description = clean($_POST['meta_description'] ?? '', true);
    $tags = clean($_POST['tags'] ?? '', true);
    
    $category_id = (int)$_POST['category_id'];
    $featured_image = clean($_POST['featured_image'] ?? '');
    
    // Premium seçeneğini sadece admin kullanıcıları için ekle
    $is_premium = 0;
    if (isAdmin() && isset($_POST['is_premium'])) {
        $is_premium = (int)$_POST['is_premium'];
    }
    
        if (empty($title) || empty($content)) {
        $error = 'title_content_required';
    } else {
        // Turnstile kontrolü
        $turnstileEnabled = isTurnstileEnabled('article');
        if ($turnstileEnabled) {
            $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
            if (!verifyTurnstile($turnstileToken)) {
                $error = 'turnstile_validation_failed';
            }
        }        // Eğer Turnstile hatası yoksa devam et
        if (empty($error)) {
            try {
                // Temel slug oluştur
                $base_slug = generateSlug($title);
                $slug = $base_slug;
                
                // Slug zaten var mı kontrol et
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
                $check_stmt->execute([$slug]);
                $count = $check_stmt->fetchColumn();
                
                // Eğer slug zaten varsa, benzersiz hale getir
                $counter = 1;
                while ($count > 0) {
                    $slug = $base_slug . '-' . $counter;
                    $check_stmt->execute([$slug]);
                    $count = $check_stmt->fetchColumn();
                    $counter++;
                }
                
                $stmt = $db->prepare("INSERT INTO articles (title, meta_title, meta_description, tags, slug, content, featured_image, category_id, author_id, status, is_premium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)");
                $stmt->execute([$title, $meta_title, $meta_description, $tags, $slug, $content, $featured_image, $category_id, $_SESSION['user_id'], $is_premium]);
                $article_id = $db->lastInsertId();
                $success = 'article_submitted_successfully';
                
                // Admin bildirim sistemi için yeni makale bildirimi ekle
                if (file_exists('admin/includes/notifications.php')) {
                    require_once 'admin/includes/notifications.php';
                    // Yeni makale bildirimi ekle
                    $message = "{$_SESSION['username']} tarafından \"{$title}\" başlıklı yeni bir makale eklendi";
                    $link = "/admin/articles.php?action=edit&id={$article_id}";
                    addAdminNotification('new_article', $_SESSION['user_id'], $message, $link, $article_id);
                }
            } catch(PDOException $e) {
                $error = 'Makale eklenirken bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}
?>
<?php include 'templates/header.php'; ?>

<!-- TinyMCE Editör -->
<script src="https://cdn.tiny.cloud/1/YOUT_TİNYMCE_API/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<!-- Form doğrulama fonksiyonu -->
<script>
    // Form gönderimleri için güvenlik kontrolü
    function validateForm() {
        // TinyMCE'nin yüklü olup olmadığını kontrol et
        if (typeof tinymce === 'undefined') {
            console.error('TinyMCE yüklenmemiş!');
            alert('Editör yüklenemedi. Lütfen sayfayı yenileyin.');
            return false;
        }
        
        // TinyMCE editörünün varlığını kontrol et
        const editor = tinymce.get('content');
        if (!editor) {
            console.error('TinyMCE editörü bulunamadı!');
            alert('Editör hazır değil. Lütfen sayfayı yenileyin ve tekrar deneyin.');
            return false;
        }
        
        // İçeriği al
        const content = editor.getContent();
        console.log('İçerik alındı, uzunluk:', content.length);
        
        // İçerik boş mu kontrol et
        if (content.trim() === '') {
            alert('İçerik alanı boş olamaz!');
            return false;
        }
        
        // İçeriği gizli input olarak ekle
        const form = document.getElementById('articleForm');
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'editor_content';
        hiddenInput.value = content;
        form.appendChild(hiddenInput);
        
        // Olay kaydı
        console.log('Form doğrulaması başarılı, gönderiliyor...');
        return true;
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM yüklendi, TinyMCE başlatılıyor...');
          // TinyMCE başlatma
        tinymce.init({
            selector: 'textarea#content',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            height: 400,
            // Paragraf yapısını koru ve düzgün boşluklar ekle
            formats: {
                p: { block: 'p', styles: { 'margin-bottom': '1.5rem', 'line-height': '1.8' } }
            },
            content_style: `
                p { margin-bottom: 1.5rem; line-height: 1.8; }
                h2, h3 { margin-top: 2rem; margin-bottom: 1rem; font-weight: bold; }
                h2 { font-size: 1.75rem; }
                h3 { font-size: 1.5rem; }
                ul, ol { margin-bottom: 1.5rem; padding-left: 2rem; }
                ul li, ol li { margin-bottom: 0.5rem; }
                img { max-width: 100%; height: auto; margin: 1.5rem 0; }
                a { color: #2563eb; text-decoration: underline; }
                a:hover { color: #1d4ed8; }
            `,
            setup: function(editor) {
                // Editör hazır olduğunda
                editor.on('init', function() {
                    console.log('TinyMCE editör başarıyla başlatıldı');
                });
                
                // İçerik değiştiğinde
                editor.on('change', function() {
                    console.log('Editör içeriği değiştirildi');
                    // İçeriği textarea'ya güncelle
                    editor.save();
                });
            }
        });
        
        // Form gönderim işlemi için hata yakalama
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            console.log('Form gönderilmeye çalışılıyor...');
            
            // TinyMCE içeriğini manuel olarak al ve kaydet
            if (tinymce.get('content')) {
                const content = tinymce.get('content').getContent();
                console.log('TinyMCE içerik uzunluğu:', content.length);
                
                // İçerik boş ise uyarı göster
                if (content.trim() === '') {
                    e.preventDefault();
                    alert('İçerik alanı boş olamaz!');
                    console.error('İçerik alanı boş!');
                    return false;
                }
                
                // İçeriği hidden bir input olarak ekle
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'editor_content';
                hiddenInput.value = content;
                form.appendChild(hiddenInput);
            } else {
                console.error('TinyMCE editör bulunamadı!');
            }
            
            console.log('Form gönderiliyor...');
        });
    });
</script>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white"><?php echo t('add_new_article'); ?></h1>

            <?php if ($error): ?>
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                <?php echo t($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                <?php echo t($success); ?>
            </div>
            <?php endif; ?>            <form method="post" id="articleForm" class="space-y-6" onsubmit="return validateForm();">
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        <?php echo t('title'); ?>
                    </label>
                    <input type="text" name="title" required
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600">
                </div>

                <div>
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        <?php echo t('category'); ?>
                    </label>
                    <select name="category_id" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600">
                        <option value=""><?php echo t('select_category'); ?></option>
                        <?php 
                        // Ana kategorileri ve alt kategorileri düzenli şekilde listele
                        $main_categories = []; // Ana kategorileri tut
                        $sub_categories = []; // Alt kategorileri tut
                        
                        foreach ($categories as $cat) {
                            if (empty($cat['parent_id'])) {
                                $main_categories[] = $cat;
                            } else {
                                $sub_categories[] = $cat;
                            }
                        }
                        
                        // Önce ana kategorileri listele
                        foreach ($main_categories as $main_cat): ?>
                            <option value="<?php echo $main_cat['id']; ?>" class="font-medium">
                                <?php echo clean($main_cat['name']); ?>
                            </option>
                            <?php 
                            // Sonra bu ana kategoriye ait alt kategorileri listele
                            foreach ($sub_categories as $sub_cat): 
                                if ($sub_cat['parent_id'] == $main_cat['id']): ?>
                                    <option value="<?php echo $sub_cat['id']; ?>" class="pl-4">
                                        &nbsp;&nbsp;└─ <?php echo clean($sub_cat['name']); ?>
                                    </option>
                                <?php endif;
                            endforeach;
                        endforeach;
                        
                        // Ana kategorisi olmayan ya da silinmiş ana kategoriye sahip alt kategorileri listele
                        $orphan_sub_cats = array_filter($sub_categories, function($sub) use ($main_categories) {
                            $has_parent = false;
                            foreach ($main_categories as $main) {
                                if ($sub['parent_id'] == $main['id']) {
                                    $has_parent = true;
                                    break;
                                }
                            }
                            return !$has_parent;
                        });
                        
                        foreach ($orphan_sub_cats as $orphan): ?>
                            <option value="<?php echo $orphan['id']; ?>">
                                <?php echo clean($orphan['name']); ?> 
                                <?php if (!empty($orphan['parent_name'])): ?>
                                    (<?php echo clean($orphan['parent_name']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        <?php echo t('featured_image_url'); ?>
                    </label>
                    <input type="text" name="featured_image"
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600"
                           placeholder="https://...">
                </div>                <?php if (isAdmin()): ?>
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                        <?php echo t('premium_content'); ?>
                    </label>
                    <select name="is_premium" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600">
                        <option value="0"><?php echo t('no'); ?></option>
                        <option value="1"><?php echo t('yes'); ?></option>
                    </select>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <?php echo t('premium_content_description'); ?>
                    </p>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <?php echo t('content'); ?>
                    </label>
                    <textarea name="content" id="content" required rows="10"
                              class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"></textarea>
                </div>
                
                <!-- SEO Ayarları Bölümü -->
                <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-600 my-4">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4"><?php echo t('seo_settings'); ?></h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="meta_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo t('meta_title'); ?></label>
                            <input type="text" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600" 
                                id="meta_title" name="meta_title" placeholder="<?php echo t('meta_title_placeholder'); ?>">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo t('meta_title_note'); ?></p>
                        </div>
                        
                        <div>
                            <label for="meta_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo t('meta_description'); ?></label>
                            <textarea class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600" 
                                id="meta_description" name="meta_description" rows="3" placeholder="<?php echo t('meta_description_placeholder'); ?>"></textarea>
                        </div>
                        
                        <div>
                            <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo t('tags'); ?></label>
                            <input type="text" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-white border-gray-300 dark:border-gray-600" 
                                id="tags" name="tags" placeholder="<?php echo t('tags_placeholder'); ?>">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo t('tags_note'); ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if (isTurnstileEnabled('article')): ?>
                <div class="my-4">
                    <?php echo turnstileWidget(); ?>
                </div>
                <?php endif; ?>

                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        <?php echo t('article_approval_notice'); ?>
                    </p>
                    <button type="submit" id="submitButton"
                            class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                        <?php echo t('submit'); ?>
                    </button>
                </div>
                
                <script>
                    document.getElementById('submitButton').addEventListener('click', function(e) {
                        console.log('Gönder butonuna tıklandı');
                        
                        // TinyMCE içeriğini manuel kontrol et
                        if (tinymce.get('content')) {
                            const content = tinymce.get('content').getContent();
                            console.log('Gönderilen içerik uzunluğu:', content.length);
                            
                            // Debug amaçlı içerik
                            console.log('İçerik sample:', content.substring(0, 50));
                        } else {
                            console.error('TinyMCE editör bulunamadı!');
                        }
                    });
                </script>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
