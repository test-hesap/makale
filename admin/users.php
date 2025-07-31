<?php
require_once '../includes/config.php';
checkAuth(true);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

include 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Çoklu kullanıcı silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['selected_users'])) {
    $selected_users = $_POST['selected_users'];
    $deleted_count = 0;
    
    if (in_array($_SESSION['user_id'], $selected_users)) {
        $error = getActiveLang() == 'en' ? 'You cannot delete your own account.' : 'Kendi hesabınızı silemezsiniz.';
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
            $stmt = $db->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role != 'admin'");
            $stmt->execute($selected_users);
            $deleted_count = $stmt->rowCount();
            $success = $deleted_count . (getActiveLang() == 'en' ? ' users successfully deleted.' : ' kullanıcı başarıyla silindi.');
            $action = 'list';
        } catch(PDOException $e) {
            $error = getActiveLang() == 'en' ? 'An error occurred while deleting the user.' : 'Kullanıcılar silinirken bir hata oluştu.';
        }
    }
}

// Çoklu kullanıcı onaylama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve']) && isset($_POST['selected_users'])) {
    $selected_users = $_POST['selected_users'];
    $approved_count = 0;
    
    try {
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $stmt = $db->prepare("UPDATE users SET approved = 1, can_post = 1 WHERE id IN ($placeholders) AND role != 'admin'");
        $stmt->execute($selected_users);
        $approved_count = $stmt->rowCount();
        $success = $approved_count . (getActiveLang() == 'en' ? ' users successfully approved.' : ' kullanıcı başarıyla onaylandı.');
        $action = 'list';
    } catch(PDOException $e) {
        $error = getActiveLang() == 'en' ? 'An error occurred while approving the user.' : 'Kullanıcılar onaylanırken bir hata oluştu.';
    }
}

// Tekli kullanıcı silme
if ($action === 'delete' && isset($_GET['id'])) {
    if ((int)$_GET['id'] === $_SESSION['user_id']) {
        $error = getActiveLang() == 'en' ? 'You cannot delete your own account.' : 'Kendi hesabınızı silemezsiniz.';
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$_GET['id']]);
            $success = getActiveLang() == 'en' ? 'User successfully deleted.' : 'Kullanıcı başarıyla silindi.';
            $action = 'list';
        } catch(PDOException $e) {
            $error = getActiveLang() == 'en' ? 'An error occurred while deleting the user.' : 'Kullanıcı silinirken bir hata oluştu.';
        }
    }
}

// Kullanıcı güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $username = clean($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $role = clean($_POST['role']);
    $status = clean($_POST['status']);
    $id = (int)$_POST['id'];
    
    if (empty($username) || empty($email)) {
        $error = getActiveLang() == 'en' ? 'Username and email fields are required.' : 'Kullanıcı adı ve e-posta alanları zorunludur.';
    } else {
        try {
            // Eğer kullanıcı yasaklı duruma getiriliyorsa, makale yazma izni otomatik olarak kaldırılır
            $can_post = 1; // Varsayılan olarak izinli
            if ($status === 'banned') {
                $can_post = 0; // Yasaklı kullanıcılar için izni kaldır
            } elseif ($status === 'active') {
                $can_post = 1; // Aktif kullanıcılar için izni geri ver
            }
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, status = ?, can_post = ? WHERE id = ?");
                $stmt->execute([$username, $email, $password, $role, $status, $can_post, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ?, can_post = ? WHERE id = ?");
                $stmt->execute([$username, $email, $role, $status, $can_post, $id]);
            }
            $success = getActiveLang() == 'en' ? 'User successfully updated.' : 'Kullanıcı başarıyla güncellendi.';
            $action = 'list';
        } catch(PDOException $e) {
            $error = getActiveLang() == 'en' ? 'An error occurred while updating the user.' : 'Kullanıcı güncellenirken bir hata oluştu.';
        }
    }
}

// Makale yazma izni güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_can_post'])) {
    $user_id = (int)$_POST['user_id'];
    $can_post = (int)$_POST['can_post'];
    
    try {
        $stmt = $db->prepare("UPDATE users SET can_post = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$can_post, $user_id]);
        $success = getActiveLang() == 'en' ? 'User\'s posting permission has been updated.' : 'Kullanıcının makale yazma izni güncellendi.';
    } catch(PDOException $e) {
        $error = getActiveLang() == 'en' ? 'An error occurred while updating the permission.' : 'İzin güncellenirken bir hata oluştu.';
    }
}

// Kullanıcı onaylama/reddetme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_approval'])) {
    $user_id = (int)$_POST['user_id'];
    $approved = (int)$_POST['approved'];
    
    try {
        $stmt = $db->prepare("UPDATE users SET approved = ?, can_post = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$approved, $approved, $user_id]);
        $success = getActiveLang() == 'en' ? 'User status has been updated.' : 'Kullanıcı durumu güncellendi.';
    } catch(PDOException $e) {
        $error = getActiveLang() == 'en' ? 'An error occurred while updating the user.' : 'Kullanıcı güncellenirken bir hata oluştu.';
    }
}

// Kullanıcı listesi
$users = $db->query("
    SELECT u.*, 
           COUNT(DISTINCT a.id) as article_count,
           COUNT(DISTINCT c.id) as comment_count,
           s.status as subscription_status
    FROM users u
    LEFT JOIN articles a ON u.id = a.author_id
    LEFT JOIN comments c ON u.id = c.user_id
    LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
    WHERE u.status != 'banned'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Son giriş tarihini de göster
$stmt = $db->query("SHOW COLUMNS FROM users LIKE 'last_login'");
$has_last_login = $stmt->rowCount() > 0;
?>
<?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

        <?php if ($action === 'edit' && isset($_GET['id'])): ?>
            <?php
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-200">
                        <?php echo getActiveLang() == 'en' ? 'Edit User' : 'Kullanıcı Düzenle'; ?>
                    </h3>
                </div>
                <div class="p-6">
                    <form method="post" action="">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                          <div class="mb-4">
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? 'Username:' : 'Kullanıcı Adı:'; ?></label>
                            <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                   id="username" name="username" value="<?php echo $user['username']; ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? 'Email:' : 'E-posta:'; ?></label>
                            <input type="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                   id="email" name="email" value="<?php echo $user['email']; ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? 'New Password (fill to change):' : 'Yeni Şifre (değiştirmek için doldurun):'; ?></label>
                            <input type="password" class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                   id="password" name="password">
                        </div>                          <div class="mb-4">
                            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? 'Role:' : 'Rol:'; ?></label>
                            <select class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                    id="role" name="role" <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'User' : 'Kullanıcı'; ?></option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? 'Status:' : 'Durum:'; ?></label>
                            <select class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                    id="status" name="status" <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Active' : 'Aktif'; ?></option>
                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>><?php echo getActiveLang() == 'en' ? 'Inactive' : 'Pasif'; ?></option>
                            </select>
                        </div>
                          <div class="flex space-x-2 mt-6">
                            <button type="submit" name="update" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i> <?php echo getActiveLang() == 'en' ? 'Update' : 'Güncelle'; ?>
                            </button>
                            <a href="users.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i> <?php echo getActiveLang() == 'en' ? 'Cancel' : 'İptal'; ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                    <form method="post" id="bulkActionForm">
                        <div class="mb-4 flex justify-between items-center">
                            <div id="bulkActionButtons" style="display:none;">
                                <button type="submit" name="bulk_delete" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 mr-2" onclick="return confirm('<?php echo getActiveLang() == 'en' ? 'Are you sure you want to delete the selected users?' : 'Seçili kullanıcıları silmek istediğinizden emin misiniz?'; ?>')">
                                    <i class="fas fa-trash mr-2"></i> <?php echo getActiveLang() == 'en' ? 'Delete Selected' : 'Seçilenleri Sil'; ?>
                                </button>
                                <button type="submit" name="bulk_approve" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-check mr-2"></i> <?php echo getActiveLang() == 'en' ? 'Approve Selected' : 'Seçilenleri Onayla'; ?>
                                </button>
                            </div>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Username' : 'Kullanıcı Adı'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Email' : 'E-posta'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Role' : 'Rol'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Status' : 'Durum'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Approval' : 'Üyelik Onayı'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Posting Permission' : 'Makale İzni'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Articles' : 'Makaleler'; ?></th>                                    
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Comments' : 'Yorumlar'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Subscription' : 'Abonelik'; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Registration Date' : 'Kayıt Tarihi'; ?></th>
                                    <?php if($has_last_login): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Last Login' : 'Son Giriş'; ?></th>
                                    <?php endif; ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? 'Actions' : 'İşlemler'; ?></th>
                                </tr>
                            </thead>                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <?php if ($user['id'] !== $_SESSION['user_id'] && $user['role'] !== 'admin'): ?>
                                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="form-checkbox h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 user-checkbox">
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo $user['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo clean($user['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo clean($user['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo $user['role']; ?></td>                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        if ($user['status'] === 'active') {
                                            $status_class = 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200';
                                            $status_text = getActiveLang() == 'en' ? 'Active' : 'Aktif';
                                        } elseif ($user['status'] === 'inactive') {
                                            $status_class = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200';
                                            $status_text = getActiveLang() == 'en' ? 'Inactive' : 'Pasif';
                                        } else {
                                            $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                            $status_text = $user['status'];
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="approved" value="<?php echo $user['approved'] ? '0' : '1'; ?>">
                                            <button type="submit" name="toggle_approval" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['approved'] ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-700' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-700'; ?>">
                                                <?php echo $user['approved'] ? (getActiveLang() == 'en' ? 'Approved' : 'Onaylı') : (getActiveLang() == 'en' ? 'Awaiting Approval' : 'Onay Bekliyor'); ?>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">Admin</span>
                                        <?php endif; ?>
                                    </td>                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <form method="post" class="inline-block">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="can_post" value="<?php echo $user['can_post'] ? '0' : '1'; ?>">
                                                <button type="submit" name="toggle_can_post" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['can_post'] ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-700' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200 hover:bg-red-200 dark:hover:bg-red-700'; ?>">
                                                    <?php echo $user['can_post'] ? (getActiveLang() == 'en' ? 'Permission Granted' : 'İzin Var') : (getActiveLang() == 'en' ? 'Permission Denied' : 'İzin Yok'); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">Admin</span>
                                        <?php endif; ?>
                                    </td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo $user['article_count']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo $user['comment_count']; ?></td>                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['subscription_status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
                                            <?php echo $user['subscription_status'] === 'active' ? (getActiveLang() == 'en' ? 'Active' : 'Aktif') : (getActiveLang() == 'en' ? 'Inactive' : 'Pasif'); ?>
                                        </span>
                                    </td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo formatDate($user['created_at'], true, true); ?></td>
                                    <?php if($has_last_login): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo isset($user['last_login']) ? formatDate($user['last_login'], true, true) : '-'; ?></td>
                                    <?php endif; ?>                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex space-x-2">
                                            <a href="?action=edit&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>                                                <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                   class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                   onclick="return confirm('<?php echo getActiveLang() == 'en' ? 'Are you sure you want to delete this user?' : 'Bu kullanıcıyı silmek istediğinizden emin misiniz?'; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const bulkActionButtons = document.getElementById('bulkActionButtons');
    
    // Butonların görünürlüğünü kontrol eden fonksiyon
    function updateButtonsVisibility() {
        const anyChecked = Array.from(userCheckboxes).some(cb => cb.checked);
        if (anyChecked) {
            bulkActionButtons.style.display = 'block';
        } else {
            bulkActionButtons.style.display = 'none';
        }
    }
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateButtonsVisibility();
        });
    }
    
    // Eğer tüm kullanıcı checkboxları işaretliyse, "Tümünü Seç" checkbox'ını da işaretli yap
    if (userCheckboxes.length > 0) {
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(userCheckboxes).every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                updateButtonsVisibility();
            });
        });
    }
    
    // Sayfa yüklendiğinde butonların durumunu kontrol et
    updateButtonsVisibility();
});
</script>

<?php include 'includes/footer.php'; ?>
