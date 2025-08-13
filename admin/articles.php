<?php
require_once '../includes/config.php';

// Sadece admin kullanıcılarına izin ver
if (!isAdmin()) {
    header('Location: /');
    exit;
}

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

include 'includes/header.php';

// Tüm kategorileri ana ve alt kategoriler olarak getir
$categories = $db->query("
    SELECT c.id, c.name, c.parent_id, p.name as parent_name
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY COALESCE(c.parent_id, c.id), c.name
")->fetchAll(PDO::FETCH_ASSOC);

// Makale silme
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $success = t('admin_article_deleted_success');
        $action = 'list';
    } catch(PDOException $e) {
        $error = t('admin_article_deleted_error');
    }
}

// Çoklu makale silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['selected_articles'])) {
    try {
        $selected_ids = $_POST['selected_articles'];
        
        if (count($selected_ids) > 0) {
            // Güvenli bir şekilde çoklu silme işlemi için placeholder oluştur
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            
            $stmt = $db->prepare("DELETE FROM articles WHERE id IN ($placeholders)");
            $stmt->execute($selected_ids);
            
            $success = sprintf(t('admin_articles_deleted_success'), count($selected_ids));
        } else {
            $error = t('admin_select_article_error');
        }
        $action = 'list';
    } catch(PDOException $e) {
        $error = t('admin_articles_deleted_error') . ': ' . $e->getMessage();
    }
}

// Makale ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {    // Hata ayıklama
    error_log('Makale ekleme formu gönderildi');
    
    $title = clean($_POST['title'], true); // true parametresi ile özel karakterleri koru
    $content = $_POST['content'];
    
    // TinyMCE içerik kontrolü
    if (empty($content) && isset($_POST['editor_content'])) {
        error_log('TinyMCE içeriği manuel olarak alınıyor');
        $content = $_POST['editor_content'];
    }
    
    error_log('Makale içerik uzunluğu: ' . strlen($content));
    
    // SEO alanları
    $meta_title = clean($_POST['meta_title'] ?? '', true);
    $meta_description = clean($_POST['meta_description'] ?? '', true);
    $tags = clean($_POST['tags'] ?? '', true);
    
    $category_id = (int)$_POST['category_id'];
    $status = clean($_POST['status']);
    $featured_image = clean($_POST['featured_image'] ?? '');
    $is_premium = isset($_POST['is_premium']) ? (int)$_POST['is_premium'] : 0;
    $slug = generateSlug($title);
    
    if (empty($title) || empty($content)) {
        $error = t('admin_article_fields_required');
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO articles (title, meta_title, meta_description, tags, slug, content, featured_image, category_id, author_id, status, is_premium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $meta_title, $meta_description, $tags, $slug, $content, $featured_image, $category_id, $_SESSION['user_id'], $status, $is_premium]);
            $success = t('admin_article_added_success');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_article_added_error') . ': ' . $e->getMessage();
        }
    }
}

// Makale güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {    // Hata ayıklama
    error_log('Makale güncelleme formu gönderildi');
    
    $title = clean($_POST['title'], true); // true parametresi ile özel karakterleri koru
    $content = $_POST['content'];
    
    // TinyMCE içerik kontrolü
    if (empty($content) && isset($_POST['editor_content'])) {
        error_log('TinyMCE içeriği manuel olarak alınıyor (güncelleme)');
        $content = $_POST['editor_content'];
    }
    
    error_log('Güncellenen makale içerik uzunluğu: ' . strlen($content));
    
    // SEO alanları
    $meta_title = clean($_POST['meta_title'] ?? '', true);
    $meta_description = clean($_POST['meta_description'] ?? '', true);
    $tags = clean($_POST['tags'] ?? '', true);
    
    $category_id = (int)$_POST['category_id'];
    $status = clean($_POST['status']);
    $featured_image = clean($_POST['featured_image'] ?? '');
    $is_premium = isset($_POST['is_premium']) ? (int)$_POST['is_premium'] : 0;
    $id = (int)$_POST['id'];
    
    if (empty($title) || empty($content)) {
        $error = t('admin_article_fields_required');
    } else {
        try {
            $stmt = $db->prepare("UPDATE articles SET title = ?, meta_title = ?, meta_description = ?, tags = ?, content = ?, featured_image = ?, category_id = ?, status = ?, slug = ?, is_premium = ? WHERE id = ?");
            $stmt->execute([$title, $meta_title, $meta_description, $tags, $content, $featured_image, $category_id, $status, generateSlug($title), $is_premium, $id]);
            $success = t('admin_article_updated_success');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_article_updated_error') . ': ' . $e->getMessage();
        }
    }
}

// Makale listesi
if ($action === 'list') {
    // Sayfalama için gerekli parametreler
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10; // Her sayfada 10 makale göster
    $offset = ($page - 1) * $per_page;
    
    // Toplam makale sayısını al
    $total_stmt = $db->query("SELECT COUNT(*) FROM articles");
    $total_articles = $total_stmt->fetchColumn();
    $total_pages = ceil($total_articles / $per_page);
    
    // Makaleleri sayfalı olarak getir
    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, u.username 
        FROM articles a 
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id 
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?php echo getActiveLang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('admin_article_management'); ?> - <?php echo t('admin_panel'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php echo getTinyMCEScript(); ?>
    
    <!-- Form güvenlik kontrolü -->
    <script>
        // Form gönderimleri için güvenlik kontrolü
        function validateForm(formId) {
            // Referans alınacak form
            const form = document.getElementById(formId);
            if (!form) return true;
            
            // TinyMCE'nin yüklü olup olmadığını kontrol et
            if (typeof tinymce === 'undefined') {
                console.error('TinyMCE yüklenmemiş!');
                alert('<?php echo t('admin_editor_not_loaded'); ?>');
                return false;
            }
            
            // TinyMCE editörünün varlığını kontrol et
            const editor = tinymce.get('content');
            if (!editor) {
                console.error('TinyMCE editörü bulunamadı!');
                alert('<?php echo t('admin_editor_not_ready'); ?>');
                return false;
            }
            
            // İçeriği al
            const content = editor.getContent();
            console.log('İçerik alındı, uzunluk:', content.length);
            
            // İçerik boş mu kontrol et
            if (content.trim() === '') {
                alert('<?php echo t('admin_content_cannot_be_empty'); ?>');
                return false;
            }
            
            // İçeriği gizli input olarak ekle
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'editor_content';
            hiddenInput.value = content;
            form.appendChild(hiddenInput);
            
            return true;
        }
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            tinymce.init({
                selector: 'textarea#content',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                height: 500,
                setup: function(editor) {
                    // Editör hazır olduğunda
                    editor.on('init', function() {
                        console.log('TinyMCE editör başarıyla başlatıldı');
                    });
                    
                    // İçerik değiştiğinde
                    editor.on('change', function() {
                        console.log('Editör içeriği değiştirildi');
                        // İçeriği textarea'ya güncelle (TinyMCE içeriği gizli textarea'ya kaydetmeyebiliyor)
                        editor.save();
                    });
                }
            });
            
            // Form gönderim işlemi için hata yakalama
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    console.log('Form gönderilmeye çalışılıyor...');
                    
                    // TinyMCE içeriğini manuel olarak al ve kaydet
                    if (tinymce.get('content')) {
                        const content = tinymce.get('content').getContent();
                        console.log('TinyMCE içerik uzunluğu:', content.length);
                        
                        // İçerik boş ise uyarı göster
                        if (content.trim() === '') {
                            e.preventDefault();
                            alert('<?php echo t('admin_content_cannot_be_empty'); ?>');
                            console.error('<?php echo t('admin_content_cannot_be_empty'); ?>');
                            return false;
                        }
                        
                        // İçeriği hidden bir input olarak ekle
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'editor_content';
                        hiddenInput.value = content;
                        form.appendChild(hiddenInput);
                    }
                    
                    console.log('Form gönderiliyor...');
                });
            });        });    </script>                <?php if ($error): ?>
                    <div class="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Yeni Makale butonu kaldırıldı -->

        <?php if ($action === 'create'): ?>                <div class="w-full">
                    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                        <div class="p-8">
                            <form method="post" id="addArticleForm" class="space-y-6" onsubmit="return validateForm('addArticleForm');">
                                <!-- İki sütunlu düzen için container -->
                                <div class="flex flex-col md:flex-row gap-6">
                                    <!-- Sol sütun - Ana içerik -->
                                    <div class="w-full md:w-2/3 space-y-6">
                                        <div>
                                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_article_title'); ?>:</label>
                                            <input type="text" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                id="title" name="title" required>
                                        </div>

                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_category'); ?>:</label>
                                    <select class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                            id="category_id" name="category_id" required>
                                        <option value=""><?php echo t('admin_select_category'); ?></option>
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
                                </div>                                <div>
                                    <label for="featured_image" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_featured_image'); ?>:</label>
                                    
                                    <!-- Resim Yükleme Sekmeli Arayüz -->
                                    <div class="mt-1 border border-gray-300 dark:border-gray-600 rounded-lg">
                                        <div class="flex border-b border-gray-300 dark:border-gray-600">
                                            <button type="button" id="admin-url-tab" class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900 dark:text-blue-300 border-b-2 border-blue-600 rounded-tl-lg">
                                                URL ile
                                            </button>
                                            <button type="button" id="admin-upload-tab" class="px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                                Dosya Yükle
                                            </button>
                                        </div>
                                        
                                        <!-- URL Girişi -->
                                        <div id="admin-url-content" class="p-4">
                                            <input type="text" class="w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 rounded" 
                                                   id="featured_image" name="featured_image" placeholder="<?php echo t('admin_image_url'); ?>">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Resim URL'sini buraya yapıştırın</p>
                                        </div>
                                        
                                        <!-- Dosya Yükleme -->
                                        <div id="admin-upload-content" class="p-4 hidden">
                                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                                                <input type="file" id="admin_image_upload" accept="image/*" class="hidden">
                                                <div id="admin-upload-area" class="cursor-pointer">
                                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                                    <p class="text-gray-600 dark:text-gray-400">Resim dosyasını sürükleyip bırakın veya <span class="text-blue-600 underline">dosya seçin</span></p>
                                                    <p class="text-xs text-gray-500 mt-1">Maksimum 5MB, JPG, PNG, GIF, WebP formatları desteklenir</p>
                                                </div>
                                                <div id="admin-upload-progress" class="hidden mt-4">
                                                    <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                        <div id="admin-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                                    </div>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Yükleniyor...</p>
                                                </div>
                                                <div id="admin-upload-success" class="hidden mt-4 p-3 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 rounded">
                                                    <p class="text-green-700 dark:text-green-300 text-sm">
                                                        <i class="fas fa-check-circle mr-2"></i>
                                                        Resim başarıyla yüklendi!
                                                    </p>
                                                </div>
                                                <div id="admin-upload-error" class="hidden mt-4 p-3 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 rounded">
                                                    <p class="text-red-700 dark:text-red-300 text-sm">
                                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                                        <span id="admin-error-message">Yükleme hatası!</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_image_url_optional'); ?></p>
                                </div>

                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_content'); ?>:</label>
                                    <textarea class="mt-1 block w-full h-96 border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                              id="content" name="content" required></textarea>
                                </div>

                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_status'); ?>:</label>
                                    <select class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                            id="status" name="status" required>
                                        <option value="draft"><?php echo t('admin_draft'); ?></option>
                                        <option value="published"><?php echo t('admin_published'); ?></option>
                                    </select>
                                </div>

                                <div>
                                    <label for="is_premium" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_premium_content'); ?>:</label>
                                    <select class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                            id="is_premium" name="is_premium">
                                        <option value="0"><?php echo t('admin_no'); ?></option>
                                        <option value="1"><?php echo t('admin_yes'); ?></option>
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_premium_content_description'); ?></p>
                                </div>
                                    </div>
                                    
                                    <!-- Sağ sütun - SEO ayarları -->
                                    <div class="w-full md:w-1/3 space-y-6">
                                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4"><?php echo t('admin_seo_settings'); ?></h3>
                                            
                                            <div class="space-y-4">
                                                <div>
                                                    <label for="meta_title" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_meta_title'); ?></label>
                                                    <input type="text" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                           id="meta_title" name="meta_title" placeholder="<?php echo t('admin_meta_title_placeholder'); ?>">
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo t('admin_meta_title_help'); ?></p>
                                                </div>
                                                
                                                <div>
                                                    <label for="meta_description" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_meta_description'); ?></label>
                                                    <textarea class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                              id="meta_description" name="meta_description" rows="4" placeholder="<?php echo t('admin_meta_description_placeholder'); ?>"></textarea>
                                                </div>
                                                
                                                <div>
                                                    <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_tags'); ?></label>
                                                    <input type="text" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                           id="tags" name="tags" placeholder="<?php echo t('admin_tags_placeholder'); ?>">
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo t('admin_tags_help'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3 mt-6">
                                    <a href="articles.php" 
                                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-times mr-2"></i> <?php echo t('admin_cancel'); ?>
                                    </a>
                                    <button type="submit" name="add" id="saveButton"
                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-save mr-2"></i> <?php echo t('admin_save'); ?>
                                    </button>
                                </div>
                                
                                <script>
                                    document.getElementById('saveButton').addEventListener('click', function(e) {
                                        console.log('Kaydet butonuna tıklandı');
                                        // TinyMCE içeriğini manuel kontrol et
                                        if (tinymce.get('content')) {
                                            const content = tinymce.get('content').getContent();
                                            console.log('Form gönderiliyor, içerik uzunluğu:', content.length);
                                        } else {
                                            console.error('TinyMCE editör bulunamadı!');
                                        }
                                    });
                                </script>
                            </form>
                        </div>
                    </div>
                </div>
        <?php endif; ?>

        <?php if ($action === 'edit'): 
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$id]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$article) {
                header('Location: articles.php');
                exit;
            }
        ?>                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="p-6">
                        <form method="post" id="editArticleForm" class="space-y-6" onsubmit="return validateForm('editArticleForm');">
                            <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                            
                            <!-- İki sütunlu düzen için container -->
                            <div class="flex flex-col md:flex-row gap-6">
                                <!-- Sol sütun - Ana içerik -->
                                <div class="w-full md:w-2/3 space-y-6">
                                    <div>
                                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_article_title'); ?>:</label>
                                        <input type="text" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                               id="title" name="title" required value="<?php echo htmlspecialchars($article['title']); ?>">
                            </div>

                            <div class="mb-4">
                                <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_category'); ?>:</label>
                                <select class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                        id="category_id" name="category_id" required>
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
                                        <option value="<?php echo $main_cat['id']; ?>" class="font-medium" <?php echo $article['category_id'] == $main_cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo clean($main_cat['name']); ?>
                                        </option>
                                        <?php 
                                        // Sonra bu ana kategoriye ait alt kategorileri listele
                                        foreach ($sub_categories as $sub_cat): 
                                            if ($sub_cat['parent_id'] == $main_cat['id']): ?>
                                                <option value="<?php echo $sub_cat['id']; ?>" class="pl-4" <?php echo $article['category_id'] == $sub_cat['id'] ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $orphan['id']; ?>" <?php echo $article['category_id'] == $orphan['id'] ? 'selected' : ''; ?>>
                                            <?php echo clean($orphan['name']); ?> 
                                            <?php if (!empty($orphan['parent_name'])): ?>
                                                (<?php echo clean($orphan['parent_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>                            <div class="mb-4">
                                <label for="featured_image" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_featured_image'); ?>:</label>
                                
                                <!-- Resim Yükleme Sekmeli Arayüz -->
                                <div class="border border-gray-300 dark:border-gray-600 rounded-lg">
                                    <div class="flex border-b border-gray-300 dark:border-gray-600">
                                        <button type="button" id="edit-url-tab" class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900 dark:text-blue-300 border-b-2 border-blue-600 rounded-tl-lg">
                                            URL ile
                                        </button>
                                        <button type="button" id="edit-upload-tab" class="px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                            Dosya Yükle
                                        </button>
                                    </div>
                                    
                                    <!-- URL Girişi -->
                                    <div id="edit-url-content" class="p-4">
                                        <input type="text" class="w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 rounded" 
                                               id="featured_image" name="featured_image" 
                                               value="<?php echo htmlspecialchars($article['featured_image']); ?>" 
                                               placeholder="<?php echo t('admin_image_url'); ?>">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Resim URL'sini buraya yapıştırın</p>
                                    </div>
                                    
                                    <!-- Dosya Yükleme -->
                                    <div id="edit-upload-content" class="p-4 hidden">
                                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                                            <input type="file" id="edit_image_upload" accept="image/*" class="hidden">
                                            <div id="edit-upload-area" class="cursor-pointer">
                                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                                <p class="text-gray-600 dark:text-gray-400">Resim dosyasını sürükleyip bırakın veya <span class="text-blue-600 underline">dosya seçin</span></p>
                                                <p class="text-xs text-gray-500 mt-1">Maksimum 5MB, JPG, PNG, GIF, WebP formatları desteklenir</p>
                                            </div>
                                            <div id="edit-upload-progress" class="hidden mt-4">
                                                <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                    <div id="edit-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                                </div>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Yükleniyor...</p>
                                            </div>
                                            <div id="edit-upload-success" class="hidden mt-4 p-3 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 rounded">
                                                <p class="text-green-700 dark:text-green-300 text-sm">
                                                    <i class="fas fa-check-circle mr-2"></i>
                                                    Resim başarıyla yüklendi!
                                                </p>
                                            </div>
                                            <div id="edit-upload-error" class="hidden mt-4 p-3 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 rounded">
                                                <p class="text-red-700 dark:text-red-300 text-sm">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <span id="edit-error-message">Yükleme hatası!</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_image_url_optional'); ?></p>
                            </div>

                            <div class="mb-4">
                                <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_content'); ?>:</label>
                                <textarea class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                          id="content" name="content" rows="10" required><?php echo htmlspecialchars($article['content']); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_status'); ?>:</label>
                                <select class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                        id="status" name="status" required>
                                    <option value="draft" <?php echo $article['status'] === 'draft' ? 'selected' : ''; ?>><?php echo t('admin_draft'); ?></option>
                                    <option value="published" <?php echo $article['status'] === 'published' ? 'selected' : ''; ?>><?php echo t('admin_published'); ?></option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="is_premium" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_premium_content'); ?>:</label>
                                <select class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                        id="is_premium" name="is_premium">
                                    <option value="0" <?php echo $article['is_premium'] == 0 ? 'selected' : ''; ?>><?php echo t('admin_no'); ?></option>
                                    <option value="1" <?php echo $article['is_premium'] == 1 ? 'selected' : ''; ?>><?php echo t('admin_yes'); ?></option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_premium_content_description'); ?></p>
                            </div>
                                </div>
                                
                                <!-- Sağ sütun - SEO ayarları -->
                                <div class="w-full md:w-1/3 space-y-6">
                                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                        <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4"><?php echo t('admin_seo_settings'); ?></h3>
                                        
                                        <div class="space-y-4">
                                            <div>
                                                <label for="meta_title" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_meta_title'); ?></label>
                                                <input type="text" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                       id="meta_title" name="meta_title" placeholder="<?php echo t('admin_meta_title_placeholder'); ?>" 
                                                       value="<?php echo htmlspecialchars($article['meta_title'] ?? ''); ?>">
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo t('admin_meta_title_help'); ?></p>
                                            </div>
                                            
                                            <div>
                                                <label for="meta_description" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_meta_description'); ?></label>
                                                <textarea class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                          id="meta_description" name="meta_description" rows="4" placeholder="<?php echo t('admin_meta_description_placeholder'); ?>"><?php echo htmlspecialchars($article['meta_description'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <div>
                                                <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo t('admin_tags'); ?></label>
                                                <input type="text" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 p-2 focus:outline-none focus:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200" 
                                                       id="tags" name="tags" placeholder="<?php echo t('admin_tags_placeholder'); ?>" 
                                                       value="<?php echo htmlspecialchars($article['tags'] ?? ''); ?>">
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo t('admin_tags_help'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-2 mt-6">                                <a href="articles.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-times mr-2"></i> <?php echo t('admin_cancel'); ?>
                                </a>                                <button type="submit" name="update" id="updateButton" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save mr-2"></i> <?php echo t('admin_update'); ?>
                                </button>
                            </div>
                            
                            <script>
                                document.getElementById('updateButton').addEventListener('click', function(e) {
                                    console.log('Güncelle butonuna tıklandı');
                                    // TinyMCE içeriğini manuel kontrol et
                                    if (tinymce.get('content')) {
                                        const content = tinymce.get('content').getContent();
                                        console.log('Form gönderiliyor, içerik uzunluğu:', content.length);
                                    } else {
                                        console.error('TinyMCE editör bulunamadı!');
                                    }
                                });
                            </script>
                </form>
            </div>
        </div>
        <?php endif; ?>        <?php if ($action === 'list'): ?>
                <form method="post" id="bulkActionForm">
                <div class="mb-4 flex justify-between items-center">
                    <div id="bulkActionButtons" class="hidden">
                        <button type="submit" name="bulk_delete" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="return confirm('<?php echo t('admin_confirm_delete_articles'); ?>')">
                            <i class="fas fa-trash mr-2"></i> <?php echo t('admin_delete_selected'); ?>
                        </button>
                    </div>
                    <div class="ml-auto">
                        <a href="?action=create" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus mr-2"></i> <?php echo t('admin_new_article'); ?>
                        </a>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="w-full overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700">
                                    <th scope="col" class="w-1/12 px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <input type="checkbox" id="selectAll" class="form-checkbox h-5 w-5 text-blue-600 dark:text-blue-500 border-gray-300 dark:border-gray-700 rounded focus:ring-blue-500">
                                    </th>
                                    <th scope="col" class="w-3/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_title'); ?>
                                    </th>
                                    <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_category'); ?>
                                    </th>
                                    <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_author'); ?>
                                    </th>
                                    <th scope="col" class="w-1/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_status'); ?>
                                    </th>
                                    <th scope="col" class="w-1/12 px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_premium'); ?>
                                    </th>
                                    <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_created_at'); ?>
                                    </th>
                                    <th scope="col" class="w-2/12 px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                        <?php echo t('admin_actions'); ?>
                                    </th>
                                </tr>                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($articles as $article): ?>                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                    <td class="w-1/12 px-6 py-4 text-center">
                                        <input type="checkbox" name="selected_articles[]" value="<?php echo $article['id']; ?>" class="article-checkbox form-checkbox h-5 w-5 text-blue-600 dark:text-blue-500 border-gray-300 dark:border-gray-700 rounded focus:ring-blue-500">
                                    </td>                                    <td class="w-3/12 px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200 truncate">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                            <?php if (isset($article['is_premium']) && $article['is_premium'] == 1): ?>
                                                <span class="ml-1 px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded-full">Premium</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>                                    <td class="w-2/12 px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                            <?php echo htmlspecialchars($article['category_name']); ?>
                                        </div>
                                    </td>                                    <td class="w-2/12 px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                            <?php echo htmlspecialchars($article['username']); ?>
                                        </div>
                                    </td>                                    <td class="w-1/12 px-6 py-4">
                                        <span class="w-20 px-3 py-1 inline-flex justify-center text-xs leading-5 font-semibold rounded-full <?php echo $article['status'] === 'published' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200'; ?>">
                                            <?php echo $article['status'] === 'published' ? 'Yayında' : 'Taslak'; ?>
                                        </span>
                                    </td>                                    <td class="w-1/12 px-6 py-4 text-center">
                                        <?php if (isset($article['is_premium']) && $article['is_premium'] == 1): ?>
                                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-purple-100 bg-purple-600 dark:bg-purple-700 dark:text-purple-200 rounded-full">
                                            <i class="fas fa-crown text-yellow-300 mr-1"></i> Evet
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-gray-100 bg-gray-500 dark:bg-gray-600 dark:text-gray-200 rounded-full">
                                            Hayır
                                        </span>
                                        <?php endif; ?>
                                    </td><td class="w-2/12 px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                            <?php echo date('d.m.Y H:i', strtotime($article['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="w-2/12 px-6 py-4">
                                        <div class="flex justify-center space-x-4">                                            <a href="?action=edit&id=<?php echo $article['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 transition-colors duration-200">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $article['id']; ?>"                                               onclick="return confirm('<?php echo t('admin_confirm_delete_article'); ?>')"
                                               class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 transition-colors duration-200">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                  <!-- Sayfalama Kontrolleri -->
                <div class="px-6 py-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-center items-center">
                        <ul class="flex space-x-2"><?php if($page > 1): ?>
                            <li>
                                <a href="?action=list&page=<?php echo $page-1; ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-left"></i> <?php echo t('admin_previous'); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            // Sayfa numaralarını göster
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // İlk sayfa linkini göster
                            if ($start_page > 1): ?>                                <li>
                                    <a href="?action=list&page=1" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        1
                                    </a>
                                </li>                                <?php if($start_page > 2): ?>
                                    <li class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                        ...
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for($i = $start_page; $i <= $end_page; $i++): ?>                                <li>
                              <a href="?action=list&page=<?php echo $i; ?>" class="px-3 py-2 border <?php echo $i == $page ? 'border-gray-500 bg-gray-50 dark:bg-gray-600 text-gray-700 dark:text-gray-300' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> rounded-md text-sm font-medium">                                       
                                 <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php 
                            // Son sayfa linkini göster
                            if ($end_page < $total_pages): ?>                                <?php if($end_page < $total_pages - 1): ?>
                                    <li class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                        ...
                                    </li>
                                <?php endif; ?><li>
                                    <a href="?action=list&page=<?php echo $total_pages; ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <?php echo $total_pages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                              <?php if($page < $total_pages): ?>
                            <li>
                                <a href="?action=list&page=<?php echo $page+1; ?>" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <?php echo t('admin_next'); ?> <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>                    <div class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">
                        <?php echo t('admin_total_articles'); ?> <?php echo $total_articles; ?>, <?php echo t('admin_total_pages'); ?> <?php echo $total_pages; ?>
                    </div>
                </div>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const selectAllCheckbox = document.getElementById('selectAll');
                        const articleCheckboxes = document.querySelectorAll('.article-checkbox');
                        const bulkActionButtons = document.getElementById('bulkActionButtons');
                        
                        // Tümünü seç checkbox'ı değiştiğinde
                        if (selectAllCheckbox) {
                            selectAllCheckbox.addEventListener('change', function() {
                                const isChecked = this.checked;
                                
                                // Tüm makale checkbox'larını güncelle
                                articleCheckboxes.forEach(checkbox => {
                                    checkbox.checked = isChecked;
                                });
                                
                                // Toplu işlem butonlarının görünürlüğünü güncelle
                                updateBulkActionButtonsVisibility();
                            });
                        }
                        
                        // Her bir makale checkbox'ı değiştiğinde
                        articleCheckboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                // Tümünü seç checkbox'ını güncelle
                                updateSelectAllCheckbox();
                                
                                // Toplu işlem butonlarının görünürlüğünü güncelle
                                updateBulkActionButtonsVisibility();
                            });
                        });
                        
                        // Tümünü seç checkbox'ını güncelle
                        function updateSelectAllCheckbox() {
                            if (selectAllCheckbox) {
                                const totalCheckboxes = articleCheckboxes.length;
                                const checkedCheckboxes = document.querySelectorAll('.article-checkbox:checked').length;
                                
                                selectAllCheckbox.checked = totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes;
                                selectAllCheckbox.indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
                            }
                        }
                        
                        // Toplu işlem butonlarının görünürlüğünü güncelle
                        function updateBulkActionButtonsVisibility() {
                            const checkedCheckboxes = document.querySelectorAll('.article-checkbox:checked').length;
                            
                            if (bulkActionButtons) {
                                bulkActionButtons.classList.toggle('hidden', checkedCheckboxes === 0);
                            }
                        }
                    });

                    // Admin Resim Yükleme JavaScript'i
                    function initImageUpload(prefix) {
                        const urlTab = document.getElementById(prefix + '-url-tab');
                        const uploadTab = document.getElementById(prefix + '-upload-tab');
                        const urlContent = document.getElementById(prefix + '-url-content');
                        const uploadContent = document.getElementById(prefix + '-upload-content');
                        
                        if (!urlTab || !uploadTab) return;
                        
                        // Sekme değiştirme
                        urlTab.addEventListener('click', function() {
                            urlTab.className = 'px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900 dark:text-blue-300 border-b-2 border-blue-600 rounded-tl-lg';
                            uploadTab.className = 'px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300';
                            urlContent.classList.remove('hidden');
                            uploadContent.classList.add('hidden');
                        });
                        
                        uploadTab.addEventListener('click', function() {
                            uploadTab.className = 'px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900 dark:text-blue-300 border-b-2 border-blue-600 rounded-tl-lg';
                            urlTab.className = 'px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300';
                            uploadContent.classList.remove('hidden');
                            urlContent.classList.add('hidden');
                        });
                        
                        // Dosya yükleme
                        const fileInput = document.getElementById(prefix + '_image_upload');
                        const uploadArea = document.getElementById(prefix + '-upload-area');
                        const progressDiv = document.getElementById(prefix + '-upload-progress');
                        const progressBar = document.getElementById(prefix + '-progress-bar');
                        const successDiv = document.getElementById(prefix + '-upload-success');
                        const errorDiv = document.getElementById(prefix + '-upload-error');
                        const errorMessage = document.getElementById(prefix + '-error-message');
                        const featuredImageInput = document.getElementById('featured_image');
                        
                        if (!fileInput || !uploadArea) return;
                        
                        uploadArea.addEventListener('click', function() {
                            fileInput.click();
                        });
                        
                        // Drag & Drop
                        uploadArea.addEventListener('dragover', function(e) {
                            e.preventDefault();
                            uploadArea.classList.add('border-blue-400', 'bg-blue-50');
                        });
                        
                        uploadArea.addEventListener('dragleave', function(e) {
                            e.preventDefault();
                            uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
                        });
                        
                        uploadArea.addEventListener('drop', function(e) {
                            e.preventDefault();
                            uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
                            
                            const files = e.dataTransfer.files;
                            if (files.length > 0) {
                                handleFileUpload(files[0], prefix);
                            }
                        });
                        
                        fileInput.addEventListener('change', function(e) {
                            if (e.target.files.length > 0) {
                                handleFileUpload(e.target.files[0], prefix);
                            }
                        });
                    }

                    function handleFileUpload(file, prefix) {
                        const progressDiv = document.getElementById(prefix + '-upload-progress');
                        const progressBar = document.getElementById(prefix + '-progress-bar');
                        const successDiv = document.getElementById(prefix + '-upload-success');
                        const errorDiv = document.getElementById(prefix + '-upload-error');
                        const errorMessage = document.getElementById(prefix + '-error-message');
                        const featuredImageInput = document.getElementById('featured_image');
                        
                        // Durumları sıfırla
                        if (successDiv) successDiv.classList.add('hidden');
                        if (errorDiv) errorDiv.classList.add('hidden');
                        if (progressDiv) progressDiv.classList.remove('hidden');
                        if (progressBar) progressBar.style.width = '0%';
                        
                        // Dosya kontrolü
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            showError('Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.', prefix);
                            return;
                        }
                        
                        if (file.size > 5 * 1024 * 1024) { // 5MB
                            showError('Dosya boyutu çok büyük. Maksimum 5MB olmalıdır.', prefix);
                            return;
                        }
                        
                        // FormData oluştur
                        const formData = new FormData();
                        formData.append('image', file);
                        
                        // XMLHttpRequest ile yükle
                        const xhr = new XMLHttpRequest();
                        
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable && progressBar) {
                                const percentComplete = (e.loaded / e.total) * 100;
                                progressBar.style.width = percentComplete + '%';
                            }
                        });
                        
                        xhr.addEventListener('load', function() {
                            if (progressDiv) progressDiv.classList.add('hidden');
                            
                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        if (featuredImageInput) featuredImageInput.value = response.file_url;
                                        if (successDiv) successDiv.classList.remove('hidden');
                                        
                                        setTimeout(function() {
                                            if (successDiv) successDiv.classList.add('hidden');
                                        }, 3000);
                                    } else {
                                        showError(response.message || 'Yükleme başarısız', prefix);
                                    }
                                } catch (e) {
                                    showError('Sunucu yanıtı işlenirken hata oluştu', prefix);
                                }
                            } else {
                                showError('Sunucu hatası: ' + xhr.status, prefix);
                            }
                        });
                        
                        xhr.addEventListener('error', function() {
                            if (progressDiv) progressDiv.classList.add('hidden');
                            showError('Ağ hatası oluştu', prefix);
                        });
                        
                        xhr.open('POST', '../includes/image_upload.php');
                        xhr.send(formData);
                    }
                    
                    function showError(message, prefix) {
                        const progressDiv = document.getElementById(prefix + '-upload-progress');
                        const errorDiv = document.getElementById(prefix + '-upload-error');
                        const errorMessage = document.getElementById(prefix + '-error-message');
                        
                        if (progressDiv) progressDiv.classList.add('hidden');
                        if (errorMessage) errorMessage.textContent = message;
                        if (errorDiv) errorDiv.classList.remove('hidden');
                        
                        setTimeout(function() {
                            if (errorDiv) errorDiv.classList.add('hidden');
                        }, 5000);
                    }

                    // Admin makale ekleme ve düzenleme sayfalarında çalıştır
                    document.addEventListener('DOMContentLoaded', function() {
                        initImageUpload('admin');
                        initImageUpload('edit');
                    });
                </script>
            <?php endif; ?>

<?php include 'includes/footer.php'; ?>
