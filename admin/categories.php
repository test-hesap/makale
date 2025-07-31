<?php
require_once '../includes/config.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Alt kategori işaretleyici için doğrudan SVG tanımı

include 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Çoklu kategori silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['selected_categories'])) {
    $selectedIds = $_POST['selected_categories'];
    $deletedCount = 0;
    $errorCount = 0;
    
    foreach ($selectedIds as $id) {
        // Kategoride makale var mı kontrol et
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ?");
        $checkStmt->execute([(int)$id]);
        $hasArticles = $checkStmt->fetchColumn() > 0;
        
        if (!$hasArticles) {
            try {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([(int)$id]);
                $deletedCount++;
            } catch(PDOException $e) {
                $errorCount++;
            }
        } else {
            $errorCount++;
        }
    }
    
    if ($deletedCount > 0) {
        $success = sprintf(t('admin_categories_deleted_success'), $deletedCount);
    }
    
    if ($errorCount > 0) {
        $error = sprintf(t('admin_categories_delete_error'), $errorCount);
    }
    
    $action = 'list';
}

// Kategori silme
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $success = t('admin_category_deleted_success');
        $action = 'list';
    } catch(PDOException $e) {
        $error = t('admin_category_has_articles_error');
    }
}

// Kategori güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name = clean($_POST['name']);
    $id = (int)$_POST['id'];
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Kendi kendini parent yapamaz
    if ($parent_id == $id) {
        $parent_id = null;
    }
    
    if (empty($name)) {
        $error = t('admin_category_name_required');
    } else {
        try {
            $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, parent_id = ? WHERE id = ?");
            $stmt->execute([$name, generateSlug($name), $parent_id, $id]);
            $success = t('admin_category_updated_success');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_category_updated_error');
        }
    }
}

// Kategori ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $name = clean($_POST['name']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if (empty($name)) {
        $error = t('admin_category_name_required');
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, generateSlug($name), $parent_id]);
            $success = t('admin_category_added_success');
            $action = 'list';
        } catch(PDOException $e) {
            $error = t('admin_category_added_error');
        }
    }
}

?>                <?php if ($error): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-500 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-500 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <?php if ($action === 'list'): 
                    // Ana kategorileri ve alt kategorileri daha düzgün görüntülemek için
                    // Önce ana kategorileri (parent_id NULL olanları) getir
                    $mainCategoriesStmt = $db->query("
                        SELECT c.*, NULL as parent_name, COUNT(a.id) as article_count 
                        FROM categories c
                        LEFT JOIN articles a ON c.id = a.category_id
                        WHERE c.parent_id IS NULL
                        GROUP BY c.id
                        ORDER BY c.name ASC
                    ");
                    $mainCategories = $mainCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Sonra her ana kategori için alt kategorilerini getir
                    $categories = [];
                    foreach ($mainCategories as $mainCategory) {
                        $categories[] = $mainCategory;
                        
                        $subcategoriesStmt = $db->prepare("
                            SELECT c.*, p.name as parent_name, COUNT(a.id) as article_count 
                            FROM categories c
                            LEFT JOIN categories p ON c.parent_id = p.id
                            LEFT JOIN articles a ON c.id = a.category_id
                            WHERE c.parent_id = ?
                            GROUP BY c.id
                            ORDER BY c.name ASC
                        ");
                        $subcategoriesStmt->execute([$mainCategory['id']]);
                        $subcategories = $subcategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($subcategories as $subcategory) {
                            $categories[] = $subcategory;
                        }
                    }
                ?>                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo t('admin_categories'); ?></h2>
                    <div class="flex items-center space-x-2">
                        <button type="submit" name="bulk_delete" form="categoriesForm" id="bulkDeleteBtn" class="bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500 text-white px-4 py-2 rounded inline-flex items-center hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                             <?php echo t('admin_delete'); ?>
                        </button>
                        <a href="?action=create" class="bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500 text-white px-4 py-2 rounded inline-flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <?php echo t('admin_new_category'); ?>
                        </a>
                    </div>
                </div>
                
                <form method="post" id="categoriesForm">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div class="flex items-center p-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center">
                                <input type="checkbox" id="selectAll" class="mr-2 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <label for="selectAll" class="text-sm text-gray-700 dark:text-gray-300"><?php echo t('admin_select_all'); ?></label>
                            </div>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider w-10"><?php echo t('admin_select'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_category_name'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_parent_category'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_article_count'); ?></th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo t('admin_actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <input type="checkbox" name="selected_categories[]" value="<?php echo $category['id']; ?>" 
                                               class="category-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               <?php echo $category['article_count'] > 0 ? 'disabled title="' . t('admin_category_has_articles_tooltip') . '"' : ''; ?>>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                            <?php
                                            // Alt kategori ise girinti ve işaret ekle
                                            if (!empty($category['parent_id'])) {
                                                echo '<div class="flex items-center">';
                                                // Unicode karakter kodu ile └─ kullan
                                                echo '<span class="text-gray-400 dark:text-gray-500 mr-2 inline-block" style="margin-left: 20px; font-family: monospace, Consolas, \'Courier New\', Courier;">&#9492;&#9472;</span>';
                                                echo '<span>' . $category['name'] . '</span>';
                                                echo '</div>';
                                            } else {
                                                // Ana kategoriler için kalın yazı
                                                echo '<strong>' . $category['name'] . '</strong>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo !empty($category['parent_name']) ? $category['parent_name'] : ''; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo $category['article_count']; ?> <?php echo t('admin_articles'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        <a href="?action=edit&id=<?php echo $category['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3"><?php echo t('admin_edit'); ?></a>
                                        <?php if ($category['article_count'] == 0): ?>
                                        <a href="?action=delete&id=<?php echo $category['id']; ?>" 
                                           onclick="return confirm('<?php echo t('admin_confirm_delete_category'); ?>')"
                                           class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"><?php echo t('admin_delete'); ?></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const selectAllCheckbox = document.getElementById('selectAll');
                        const categoryCheckboxes = document.querySelectorAll('.category-checkbox:not([disabled])');
                        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
                        const categoriesForm = document.getElementById('categoriesForm');
                        
                        // Tümünü seç/kaldır
                        selectAllCheckbox.addEventListener('change', function() {
                            categoryCheckboxes.forEach(checkbox => {
                                checkbox.checked = selectAllCheckbox.checked;
                            });
                            updateBulkDeleteButton();
                        });
                        
                        // Her checkbox değiştiğinde kontrol et
                        categoryCheckboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                updateBulkDeleteButton();
                                updateSelectAllCheckbox();
                            });
                        });
                        
                        // Toplu silme butonunu güncelle
                        function updateBulkDeleteButton() {
                            const anyChecked = Array.from(categoryCheckboxes).some(checkbox => checkbox.checked);
                            if (anyChecked) {
                                bulkDeleteBtn.classList.remove('hidden');
                            } else {
                                bulkDeleteBtn.classList.add('hidden');
                            }
                        }
                        
                        // Tümünü seç checkbox'ını güncelle
                        function updateSelectAllCheckbox() {
                            const allChecked = Array.from(categoryCheckboxes).every(checkbox => checkbox.checked);
                            selectAllCheckbox.checked = allChecked && categoryCheckboxes.length > 0;
                        }
                        
                        // Toplu silme işlemi onayı
                        categoriesForm.addEventListener('submit', function(e) {
                            if (e.submitter && e.submitter.name === 'bulk_delete') {
                                const checkedCount = document.querySelectorAll('.category-checkbox:checked').length;
                                if (!confirm(`<?php echo t('admin_confirm_delete_categories'); ?>`.replace('{count}', checkedCount))) {
                                    e.preventDefault();
                                }
                            }
                        });
                    });
                </script>
                <?php endif; ?>

                <?php if ($action === 'edit'): 
                    $id = (int)$_GET['id'];
                    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $category = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$category) {
                        header('Location: categories.php');
                        exit;
                    }
                ?>                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white"><?php echo t('admin_edit_category'); ?></h2>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_category_name'); ?></label>
                            <input type="text" name="name" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                   value="<?php echo htmlspecialchars($category['name']); ?>">
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_parent_category'); ?></label>
                            <select name="parent_id" 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value=""><?php echo t('admin_no_parent_category'); ?></option>
                                <?php 
                                // Önce ana kategorileri (parent_id NULL olanları) getir
                                $mainCategoriesStmt = $db->prepare("SELECT id, name FROM categories WHERE parent_id IS NULL AND id != ? ORDER BY name");
                                $mainCategoriesStmt->execute([$category['id']]);
                                $mainCategories = $mainCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Sadece ana kategorileri listeleme
                                foreach ($mainCategories as $mainCategory) {
                                    // Ana kategoriye kendisini seçme imkanı verme
                                    $isSubcategory = false;
                                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ? AND id = ?");
                                    $checkStmt->execute([$category['id'], $mainCategory['id']]);
                                    $isSubcategory = ($checkStmt->fetchColumn() > 0);
                                    
                                    if (!$isSubcategory) {
                                        // Ana kategoriyi listele
                                        echo '<option value="' . $mainCategory['id'] . '" ';
                                        echo ($category['parent_id'] == $mainCategory['id']) ? 'selected' : '';
                                        echo '>' . htmlspecialchars($mainCategory['name']) . '</option>';
                                        // Alt kategoriler artık listelenmeyecek
                                    }
                                } 
                                ?>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" name="update" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500">
                                <?php echo t('admin_update'); ?>
                            </button>
                            <a href="categories.php" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                                <?php echo t('admin_cancel'); ?>
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($action === 'create'): ?>                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white"><?php echo t('admin_new_category'); ?></h2>
                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_category_name'); ?></label>
                            <input type="text" name="name" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-200 mb-2"><?php echo t('admin_parent_category'); ?></label>
                            <select name="parent_id" 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value=""><?php echo t('admin_no_parent_category'); ?></option>
                                <?php 
                                // Önce tüm ana kategorileri getir
                                $mainCategoriesStmt = $db->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC");
                                $mainCategories = $mainCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Sadece ana kategorileri listele
                                foreach ($mainCategories as $mainCategory) {
                                    // Ana kategoriyi listele
                                    echo '<option value="' . $mainCategory['id'] . '">';
                                    echo htmlspecialchars($mainCategory['name']);
                                    echo '</option>';
                                    
                                    // Alt kategoriler artık listelenmeyecek
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" name="create" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500">
                                <?php echo t('admin_add'); ?>
                            </button>
                            <a href="categories.php" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                                <?php echo t('admin_cancel'); ?>                            </a>
                        </div>
                    </form>
                </div>                <?php endif; ?>

<?php include 'includes/footer.php'; ?>
