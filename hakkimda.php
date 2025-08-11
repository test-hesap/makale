<?php
require_once 'includes/config.php';

// Sayfa için canonical URL
$canonical_url = getSetting('site_url') . "/hakkimda";

// Ayarlardan hakkımızda içeriğini getir
$stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
$stmt->execute(['about_page']);
$aboutContent = $stmt->fetch(PDO::FETCH_ASSOC);

    // Varsayılan içerik
if(!$aboutContent || empty($aboutContent['value'])) {
    $aboutContent['value'] = "<h2>" . __('about_default_title') . "</h2>
    <p>" . __('about_default_text1') . "</p>
    <p>" . __('about_default_text2') . "</p>";
}// Ekip üyelerini getir
$team_members = $db->query("
    SELECT tm.*, u.username
    FROM team_members tm
    LEFT JOIN users u ON tm.user_id = u.id
    WHERE tm.is_active = 1
    ORDER BY tm.order_num ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Admin işlemleri
$message = '';
    $error = '';

    // Admin kullanıcısı mı kontrol et
    $isAdmin = isset($_SESSION['user_id']) && isAdmin();

    // Ekip üyesi ekleme
    if ($isAdmin && isset($_POST['add_member'])) {
        $name = trim($_POST['name']);
        $title = trim($_POST['title']);
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $bio = trim($_POST['bio']);
        
        // Avatar yükleme
        $avatar = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = 'uploads/avatars/' . $new_filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    $avatar = $new_filename;
                } else {
                    $error = __('about_avatar_upload_error');
                }
            } else {
                $error = __('about_invalid_file_type');
            }
        }    if (empty($error)) {
        // En yüksek sıra numarasını bul
        $max_order = $db->query("SELECT MAX(order_num) as max_order FROM team_members")->fetch(PDO::FETCH_ASSOC);
        $order_num = ($max_order['max_order'] ?? 0) + 1;
        
        $stmt = $db->prepare("
            INSERT INTO team_members (user_id, name, title, avatar, bio, order_num) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$user_id, $name, $title, $avatar, $bio, $order_num])) {
            $message = __('about_member_added');
            // Sayfayı yenile
            header("Location: hakkimda.php?success=added");
            exit;
        } else {
            $error = __('about_member_add_error');
        }
    }
}

// Ekip üyesi silme
if ($isAdmin && isset($_GET['delete_member'])) {
    $member_id = (int)$_GET['delete_member'];
    
    // Üyeyi getir
    $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($member) {
        // Üyeyi sil
        $stmt = $db->prepare("DELETE FROM team_members WHERE id = ?");
        if ($stmt->execute([$member_id])) {
            // Avatar dosyasını sil (eğer varsa ve kullanıcıya ait değilse)
            if (!empty($member['avatar']) && file_exists('uploads/avatars/' . $member['avatar'])) {
                @unlink('uploads/avatars/' . $member['avatar']);
            }
            
            $message = __('about_member_deleted');
            // Sayfayı yenile
            header("Location: hakkimda.php?success=deleted");
            exit;
        } else {
            $error = __('about_member_delete_error');
        }
    } else {
        $error = __('about_member_not_found');
    }
}

// Sıralama güncelleme
if ($isAdmin && isset($_POST['update_order'])) {
    $member_ids = $_POST['member_id'];
    $orders = $_POST['order'];
    
    foreach ($member_ids as $key => $id) {
        $order = (int)$orders[$key];
        $stmt = $db->prepare("UPDATE team_members SET order_num = ? WHERE id = ?");
        $stmt->execute([$order, $id]);
    }
    
    $message = __('about_order_updated');
    // Sayfayı yenile
    header("Location: hakkimda.php?success=ordered");
    exit;
}

// Ekip üyesi düzenleme
if ($isAdmin && isset($_GET['edit_member'])) {
    $member_id = (int)$_GET['edit_member'];
    
    // Üyeyi getir
    $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->execute([$member_id]);
    $edit_member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_member) {
        $error = __('about_member_edit_not_found');
    }
}

// Ekip üyesi güncelleme
if ($isAdmin && isset($_POST['update_member'])) {
    $member_id = (int)$_POST['member_id'];
    $name = trim($_POST['name']);
    $title = trim($_POST['title']);
    $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $bio = trim($_POST['bio']);
    
    // Avatar yükleme
    $avatar = '';
    $update_avatar = false;
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/avatars/' . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                $avatar = $new_filename;
                $update_avatar = true;
            } else {
                $error = __('about_avatar_upload_error');
            }
        } else {
            $error = __('about_invalid_file_type');
        }
    }
    
    if (empty($error)) {
        // Mevcut avatar bilgisini al
        if (!$update_avatar) {
            $stmt = $db->prepare("SELECT avatar FROM team_members WHERE id = ?");
            $stmt->execute([$member_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $avatar = $current['avatar'];
        }
        
        // Üyeyi güncelle
        if ($update_avatar) {
            $stmt = $db->prepare("
                UPDATE team_members 
                SET user_id = ?, name = ?, title = ?, avatar = ?, bio = ? 
                WHERE id = ?
            ");
            $result = $stmt->execute([$user_id, $name, $title, $avatar, $bio, $member_id]);
        } else {
            $stmt = $db->prepare("
                UPDATE team_members 
                SET user_id = ?, name = ?, title = ?, bio = ? 
                WHERE id = ?
            ");
            $result = $stmt->execute([$user_id, $name, $title, $bio, $member_id]);
        }
        
        if ($result) {
            $message = __('about_member_updated');
            // Sayfayı yenile
            header("Location: hakkimda.php?success=updated");
            exit;
        } else {
            $error = __('about_member_update_error');
        }
    }
}

// Başarı mesajları
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = __('about_member_added_success');
            break;
        case 'deleted':
            $message = __('about_member_deleted_success');
            break;
        case 'ordered':
            $message = __('about_order_updated_success');
            break;
        case 'updated':
            $message = __('about_member_updated_success');
            break;
    }
}

// Kullanıcı listesi (admin için)
$users = [];
if ($isAdmin) {
    $users = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Kategorileri getir - ana ve alt kategorileri hiyerarşik olarak
$categories = $db->query("
    SELECT c.*, parent.name as parent_name, COUNT(a.id) as article_count
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.id
    LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
    GROUP BY c.id, c.name, c.slug, c.parent_id, parent.name
    ORDER BY COALESCE(c.parent_id, c.id), c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

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

// Popüler makaleleri getir
$popular_articles = $db->query("
    SELECT a.*, a.slug, c.name as category_name, u.username,
           (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count,
           IFNULL(a.view_count, 0) as view_count
    FROM articles a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.author_id = u.id
    WHERE a.status = 'published'
    ORDER BY a.views DESC, a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Site başlığını al
$site_title = getSetting('site_title');
$site_description = getSetting('site_description');

// Sayfa başlığı
$page_title = __('about_title') . " - " . $site_title;
?>
<?php include 'templates/header.php'; ?>

<div class="container mx-auto px-4 py-0 mt-1">
    <!-- Boşluk eklendi -->
</div>

<div class="container mx-auto px-4 py-8">
    <?php if (!empty($message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?php echo $message; ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?php echo $error; ?></p>
    </div>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Ana İçerik -->
        <div class="w-full lg:w-2/3">
                                        <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg overflow-hidden">
                <div class="px-6 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><?php echo __('about_title'); ?></h1>
                    <div class="prose max-w-none dark:prose-invert dark:text-gray-200">
                        <?php echo $aboutContent['value']; ?>
                    </div>
                    
                    <!-- Ekip bölümü -->
                    <div class="mt-12" id="ekibimiz">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?php echo __('about_team'); ?></h2>
                            <?php if ($isAdmin): ?>
                            <button id="add-team-member-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm flex items-center">
                                <i class="fas fa-plus mr-2"></i> <?php echo __('about_add_member'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($isAdmin): ?>
                        <!-- Ekip Üyesi Ekleme Formu (varsayılan olarak gizli) -->
                        <div id="add-team-member-form" class="bg-gray-50 dark:bg-[#292929] p-6 rounded-lg mb-8 hidden">
                            <h3 class="text-lg font-semibold mb-4 dark:text-gray-200"><?php echo __('about_add_team_member'); ?></h3>
                            <form action="hakkimda.php" method="post" enctype="multipart/form-data">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="name">
                                            <?php echo __('about_name'); ?>
                                        </label>
                                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                               id="name" name="name" type="text" required>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="title">
                                            <?php echo __('about_position'); ?>
                                        </label>
                                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                               id="title" name="title" type="text" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="user_id">
                                        <?php echo __('about_user_optional'); ?>
                                    </label>
                                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                            id="user_id" name="user_id">
                                        <option value=""><?php echo __('about_select_user'); ?></option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-1"><?php echo __('about_user_avatar_note'); ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="avatar">
                                        <?php echo __('about_avatar_optional'); ?>
                                    </label>
                                    <div class="relative">
                                        <input class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" 
                                               id="avatar" name="avatar" type="file" accept="image/*"
                                               onchange="updateFileName(this, 'avatar-filename')">
                                        <div class="flex items-center">
                                            <button type="button" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium py-2 px-4 rounded-l border border-gray-300 dark:border-gray-600">
                                                <?php echo __('about_choose_file'); ?>
                                            </button>
                                            <div id="avatar-filename" class="flex-1 px-3 py-2 border-t border-r border-b border-gray-300 dark:border-gray-600 rounded-r bg-white dark:bg-[#292929] text-gray-700 dark:text-gray-200 text-sm">
                                                <?php echo __('about_no_file_chosen'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-1"><?php echo __('about_avatar_note'); ?></p>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="bio">
                                        <?php echo __('about_bio_optional'); ?>
                                    </label>
                                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                              id="bio" name="bio" rows="4"></textarea>
                                </div>
                                
                                <div class="flex items-center justify-end">
                                    <button type="button" id="cancel-add-member" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                        <?php echo __('about_cancel'); ?>
                                    </button>
                                    <button type="submit" name="add_member" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        <?php echo __('about_add'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Sıralama Formu (admin için) -->
                        <?php if (!empty($team_members)): ?>
                        <form action="hakkimda.php" method="post" class="mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white dark:bg-[#292929] border border-gray-200 dark:border-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo __('about_order'); ?></th>
                                            <th class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo __('about_avatar'); ?></th>
                                            <th class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo __('about_fullname'); ?></th>
                                            <th class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo __('about_title'); ?></th>
                                            <th class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo __('about_actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($team_members as $index => $member): ?>
                                        <tr>
                                            <td class="px-4 py-2 border-b dark:border-gray-700">
                                                <input type="hidden" name="member_id[]" value="<?php echo $member['id']; ?>">
                                                <input type="number" name="order[]" value="<?php echo $member['order_num']; ?>" 
                                                       class="w-16 px-2 py-1 border rounded dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200">
                                            </td>
                                            <td class="px-4 py-2 border-b dark:border-gray-700">
                                                <?php 
                                                // Avatar yolunu belirle
                                                if (!empty($member['avatar'])) {
                                                    $avatar = '/uploads/avatars/' . $member['avatar'];
                                                } else {
                                                    $avatar = '/uploads/avatars/default-avatar.jpg';
                                                }
                                                ?>
                                                <img src="<?php echo $avatar; ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                                     class="w-10 h-10 rounded-full object-cover">
                                            </td>
                                            <td class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($member['name']); ?></td>
                                            <td class="px-4 py-2 border-b dark:border-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($member['title']); ?></td>
                                            <td class="px-4 py-2 border-b dark:border-gray-700">
                                                <a href="hakkimda.php?delete_member=<?php echo $member['id']; ?>" 
                                                   onclick="return confirm('<?php echo __('about_confirm_delete'); ?>');"
                                                   class="text-red-500 hover:text-red-700 mr-2">
                                                    <i class="fas fa-trash"></i> <?php echo __('about_delete'); ?>
                                                </a>
                                                <a href="hakkimda.php?edit_member=<?php echo $member['id']; ?>" 
                                                   class="text-blue-500 hover:text-blue-700">
                                                    <i class="fas fa-edit"></i> <?php echo __('about_edit'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 text-right">
                                <button type="submit" name="update_order" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                                    <?php echo __('about_update_order'); ?>
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Ekip Üyesi Düzenleme Formu -->
                        <?php if ($isAdmin && isset($edit_member)): ?>
                        <div id="edit-team-member-form" class="bg-gray-50 dark:bg-[#292929] p-6 rounded-lg mb-8">
                            <h3 class="text-lg font-semibold mb-4 dark:text-gray-200"><?php echo __('about_edit_team_member'); ?></h3>
                            <form action="hakkimda.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="member_id" value="<?php echo $edit_member['id']; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="edit_name">
                                            <?php echo __('about_name'); ?>
                                        </label>
                                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                               id="edit_name" name="name" type="text" value="<?php echo htmlspecialchars($edit_member['name']); ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="edit_title">
                                            <?php echo __('about_position'); ?>
                                        </label>
                                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                               id="edit_title" name="title" type="text" value="<?php echo htmlspecialchars($edit_member['title']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="edit_user_id">
                                        <?php echo __('about_user_optional_edit'); ?>
                                    </label>
                                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                            id="edit_user_id" name="user_id">
                                        <option value=""><?php echo __('about_select_user_optional'); ?></option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($edit_member['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-1"><?php echo __('about_user_avatar_note'); ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="edit_avatar">
                                        <?php echo __('about_avatar_optional'); ?>
                                    </label>
                                    <?php if (!empty($edit_member['avatar'])): ?>
                                    <div class="mb-2">
                                        <img src="/uploads/avatars/<?php echo $edit_member['avatar']; ?>" 
                                             alt="<?php echo __('about_current_avatar'); ?>" 
                                             class="w-16 h-16 object-cover rounded">
                                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo __('about_current_avatar_text'); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div class="relative">
                                        <input class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" 
                                               id="edit_avatar" name="avatar" type="file" accept="image/*"
                                               onchange="updateFileName(this, 'edit-avatar-filename')">
                                        <div class="flex items-center">
                                            <button type="button" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium py-2 px-4 rounded-l border border-gray-300 dark:border-gray-600">
                                                <?php echo __('about_choose_file'); ?>
                                            </button>
                                            <div id="edit-avatar-filename" class="flex-1 px-3 py-2 border-t border-r border-b border-gray-300 dark:border-gray-600 rounded-r bg-white dark:bg-[#292929] text-gray-700 dark:text-gray-200 text-sm">
                                                <?php echo __('about_no_file_chosen'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-1"><?php echo __('about_change_avatar_note'); ?></p>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="edit_bio">
                                        <?php echo __('about_bio_optional_edit'); ?>
                                    </label>
                                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 leading-tight focus:outline-none focus:shadow-outline" 
                                              id="edit_bio" name="bio" rows="4"><?php echo htmlspecialchars($edit_member['bio']); ?></textarea>
                                </div>
                                
                                <div class="flex items-center justify-end">
                                    <a href="hakkimda.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                        <?php echo __('about_cancel_edit'); ?>
                                    </a>
                                    <button type="submit" name="update_member" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        <?php echo __('about_update_edit'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Ekip Üyeleri Listesi -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php 
                            if (count($team_members) > 0):
                                foreach($team_members as $member):
                                    // Avatar yolunu düzgün şekilde oluştur
                                    if (!empty($member['avatar'])) {
                                        $avatar = '/uploads/avatars/' . $member['avatar'];
                                    } else {
                                        $avatar = '/uploads/avatars/default-avatar.jpg';
                                    }
                            ?>
                            <div class="bg-gray-50 dark:bg-[#292929] p-4 rounded-lg text-center">
                                <img src="<?php echo $avatar; ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                     class="w-24 h-24 rounded-full mx-auto mb-4 object-cover border-4 border-white dark:border-gray-700 shadow"
                                     onerror="this.src='/uploads/avatars/default-avatar.jpg'">
                                <h3 class="text-lg font-semibold dark:text-gray-200">
                                    <?php if (!empty($member['username'])): ?>
                                    <a href="/uyeler/<?php echo urlencode($member['username']); ?>" class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($member['name']); ?>
                                    </a>
                                    <?php else: ?>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-blue-600"><?php echo htmlspecialchars($member['title']); ?></p>
                                <?php if (!empty($member['bio'])): ?>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400"><?php echo nl2br(htmlspecialchars($member['bio'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php 
                                endforeach; 
                            else:
                            ?>
                            <div class="bg-gray-50 dark:bg-[#292929] p-4 rounded-lg text-center">
                                <img src="/uploads/avatars/default-avatar.jpg" alt="<?php echo __('about_admin'); ?>" 
                                     class="w-24 h-24 rounded-full mx-auto mb-4 object-cover border-4 border-white dark:border-gray-700 shadow">
                                <h3 class="text-lg font-semibold dark:text-gray-200"><?php echo __('about_site_admin'); ?></h3>
                                <p class="text-blue-600"><?php echo __('about_admin'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
          <!-- Sağ Sidebar -->
        <div class="w-full lg:w-1/3 space-y-6">
            <?php echo showAd('sidebar'); // Sidebar reklamı ?>

            <!-- Arama -->
            <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold mb-4 sidebar-heading dark:text-gray-200"><?php echo __('search'); ?></h3>               <form action="/search.php" method="get" class="flex">                    <input type="text" name="q" placeholder="<?php echo __('search_article_placeholder'); ?>" 
                           class="flex-1 px-4 py-2 border rounded-l focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-[#292929] dark:border-gray-700 dark:text-gray-200 dark:focus:ring-blue-500 dark:placeholder-gray-400">                    <button type="submit" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-r hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-300">
                        <i class="fas fa-search"></i>
                    </button>
                </form>            </div>
            
            <!-- Kategoriler -->
            <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg p-6">
                <div class="collapsible-sidebar-header" data-sidebar-id="categories">
                    <h3 class="text-lg font-semibold dark:text-gray-200"><?php echo __('categories'); ?></h3>
                    <i class="fas fa-chevron-down rotate-icon dark:text-gray-200"></i>
                </div>
                <div class="collapsible-sidebar-content space-y-2 mt-4">
                    <?php foreach ($categoriesHierarchy as $mainCategory): 
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
                            <span class="font-medium dark:text-gray-200"><?php echo htmlspecialchars($mainCategory['name']); ?></span>
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
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Çevrimiçi Üyeler -->
            <?php include 'includes/online_users_sidebar.php'; ?>
            
            <!-- Popüler Makaleler -->
            <div class="bg-white dark:bg-[#292929] rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold mb-4 dark:text-gray-200"><?php echo __('popular_articles'); ?></h3>
                <div class="space-y-4">
                    <?php foreach ($popular_articles as $article): ?>
                    <a href="/makale/<?php echo urlencode($article['slug']); ?>" 
                       class="flex items-start space-x-4 group">
                        <?php if (!empty($article['featured_image'])): ?>
                        <?php
                        // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                        $imgSrc = !empty($article['featured_image']) ? 
                            ((strpos($article['featured_image'], 'http://') === 0 || strpos($article['featured_image'], 'https://') === 0) 
                                ? $article['featured_image'] 
                                : (strpos($article['featured_image'], '/') === 0 
                                    ? $article['featured_image'] 
                                    : "/uploads/ai_images/" . $article['featured_image'])) 
                            : '/assets/img/default-article.jpg';
                        ?>
                        <img src="<?php echo $imgSrc; ?>" 
                             alt="<?php echo safeTitle($article['title']); ?>" 
                             class="w-20 h-20 object-cover rounded">
                        <?php else: ?>
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                            <i class="fas fa-image text-gray-400 dark:text-gray-500 text-xl"></i>
                        </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-gray-200 group-hover:text-blue-600 line-clamp-2">
                                <?php echo safeTitle($article['title']); ?>
                            </h4>
                            <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <span><?php echo date('d.m.Y', strtotime($article['created_at'])); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo $article['comment_count']; ?> <?php echo __('comments_count'); ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>                </div>
            </div>
            
            <!-- Son Yorumlar -->
            <?php include 'includes/recent_comments_sidebar.php'; ?>
            
            <!-- İstatistikler -->
            <?php include 'includes/stats_sidebar.php'; ?>

            <?php echo showAd('sidebar_bottom'); // Sidebar altı reklam ?></div>
    </div>
</div>

<!-- JavaScript (Ekip üyesi ekleme formunu göster/gizle) -->
<?php if ($isAdmin): ?>
<script>
// Dosya seçildiğinde dosya adını güncelle
function updateFileName(input, targetId) {
    const target = document.getElementById(targetId);
    const noFileText = '<?php echo __('about_no_file_chosen'); ?>';
    
    if (input.files && input.files[0]) {
        target.textContent = input.files[0].name;
    } else {
        target.textContent = noFileText;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.getElementById('add-team-member-btn');
    const addForm = document.getElementById('add-team-member-form');
    const cancelButton = document.getElementById('cancel-add-member');
    
    const cancelText = '<?php echo __('about_cancel_js'); ?>';
    const addMemberText = '<?php echo __('about_add_member_js'); ?>';
    
    if (addButton) {
        addButton.addEventListener('click', function() {
            addForm.classList.toggle('hidden');
            addButton.classList.toggle('bg-red-500');
            addButton.classList.toggle('bg-blue-500');
            
            if (!addForm.classList.contains('hidden')) {
                addButton.innerHTML = '<i class="fas fa-times mr-2"></i> ' + cancelText;
            } else {
                addButton.innerHTML = '<i class="fas fa-plus mr-2"></i> ' + addMemberText;
            }
        });
    }
    
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            addForm.classList.add('hidden');
            addButton.classList.remove('bg-red-500');
            addButton.classList.add('bg-blue-500');
            addButton.innerHTML = '<i class="fas fa-plus mr-2"></i> ' + addMemberText;
        });
    }
});
</script>
<?php endif; ?>

<?php require_once 'templates/footer.php'; ?>
