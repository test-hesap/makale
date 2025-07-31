<?php
require_once '../includes/config.php';
checkAuth(true);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';
$search_results = [];
$search_term = '';

// Kullanıcı arama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_user'])) {
    $search_term = clean($_POST['search_term'] ?? '');
    $search_role = clean($_POST['search_role'] ?? '');
    $search_status = clean($_POST['search_status'] ?? '');
    $search_date = clean($_POST['search_date'] ?? '');
    
    global $db;
    try {
        $query = "
            SELECT id, username, email, role, status, last_login, created_at 
            FROM users 
            WHERE role != 'admin' 
            AND status != 'banned'
        ";
        
        $params = [];
        
        // Kullanıcı adı veya email araması
        if (!empty($search_term)) {
            $query .= " AND (username LIKE ? OR email LIKE ?)";
            $params[] = "%$search_term%";
            $params[] = "%$search_term%";
        }
        
        // Rol filtresi
        if (!empty($search_role)) {
            $query .= " AND role = ?";
            $params[] = $search_role;
        }
        
        // Durum filtresi
        if (!empty($search_status)) {
            $query .= " AND status = ?";
            $params[] = $search_status;
        }
        
        // Kayıt tarihi filtresi
        if (!empty($search_date)) {
            $query .= " AND DATE(created_at) = ?";
            $params[] = $search_date;
        }
        
        $query .= " ORDER BY username ASC LIMIT 50";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($search_results)) {
            $error = getActiveLang() == 'en' ? 'No users found matching your search criteria.' : 'Arama kriterlerine uygun kullanıcı bulunamadı.';
        }
    } catch (PDOException $e) {
        $error = getActiveLang() == 'en' ? 'An error occurred during user search: ' . $e->getMessage() : 'Kullanıcı arama sırasında bir hata oluştu: ' . $e->getMessage();
    }
}

// Ban ekleme/güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $user_id = (int)$_POST['user_id'];
    $reason = clean($_POST['reason']);
    $ip_address = filter_var($_POST['ip_address'], FILTER_VALIDATE_IP) ? $_POST['ip_address'] : null;
    $is_ip_banned = isset($_POST['is_ip_banned']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    if (empty($user_id)) {
        $error = getActiveLang() == 'en' ? 'You must select a user.' : 'Kullanıcı seçmelisiniz.';
    } else {
        if (banUser($user_id, $_SESSION['user_id'], $reason, $ip_address, $is_ip_banned, $expires_at)) {
            $success = getActiveLang() == 'en' ? 'User banned successfully. User sessions have been terminated and they will be redirected to the ban page on their next page load.' : 'Kullanıcı başarıyla banlandı. Kullanıcının aktif oturumları sonlandırıldı ve bir sonraki sayfa yüklemelerinde ban sayfasına yönlendirilecek.';
            $action = 'list';
        } else {
            $error = getActiveLang() == 'en' ? 'An error occurred while banning the user.' : 'Kullanıcı banlanırken bir hata oluştu.';
        }
    }
}

// Ban kaldırma
if ($action === 'unban' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    if (unbanUser($user_id)) {
        $success = getActiveLang() == 'en' ? 'User unbanned successfully.' : 'Kullanıcının banı başarıyla kaldırıldı.';
    } else {
        $error = getActiveLang() == 'en' ? 'An error occurred while unbanning the user.' : 'Kullanıcının banı kaldırılırken bir hata oluştu.';
    }
    $action = 'list';
}

include 'includes/header.php';

// Banlanan kullanıcıları listele
$banned_users = getBannedUsers();

// Tüm kullanıcıları listele (ban formunda seçmek için)
$users = $db->query("
    SELECT id, username, email, role, status, last_login
    FROM users 
    WHERE role != 'admin' AND status != 'banned'
    ORDER BY username ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-full px-4 sm:px-6 mx-auto">
    <h2 class="my-4 sm:my-6 text-xl sm:text-2xl font-semibold text-gray-700 dark:text-gray-200">
        <?php echo getActiveLang() == 'en' ? t('admin_ban_management') : 'Üye Banlama Yönetimi'; ?>
    </h2>
    
    <?php
    // Veritabanı tablo kontrolü - Hata ayıklama için
    try {
        $tables = $db->query("SHOW TABLES LIKE 'banned_users'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <strong>' . (getActiveLang() == 'en' ? t('admin_db_table_error') . ':</strong> ' . t('admin_db_table_not_found') . ' <a href="../install/add_banned_users.php" class="underline">' . t('admin_installation_page') : 'Hata:</strong> "banned_users" tablosu bulunamadı. Lütfen <a href="../install/add_banned_users.php" class="underline">kurulum sayfasını') . '</a> ziyaret edin.
                  </div>';
        }
        
        // Users tablosunda status alanı kontrolü
        $columns = $db->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($columns && strpos($columns['Type'], 'banned') === false) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <strong>' . (getActiveLang() == 'en' ? t('admin_db_table_error') . ':</strong> ' . t('admin_status_field_error') . ' <a href="../install/add_banned_status.php" class="underline">' . t('admin_installation_page') : 'Hata:</strong> "users" tablosundaki "status" alanında "banned" değeri bulunamadı. Lütfen <a href="../install/add_banned_status.php" class="underline">kurulum sayfasını') . '</a> ziyaret edin.
                  </div>';
        }
        
        // Users tablosunda last_ip alanı kontrolü
        $ip_columns = $db->query("SHOW COLUMNS FROM users LIKE 'last_ip'")->fetch(PDO::FETCH_ASSOC);
        if (!$ip_columns) {
            echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    <strong>' . (getActiveLang() == 'en' ? t('admin_last_ip_error') . ':</strong> ' . t('admin_last_ip_not_found') . ' <a href="install_last_ip.php" class="underline">' . t('admin_ip_installation_page') . '</a> ' . t('admin_to_auto_fill_ip') : 'Uyarı:</strong> "users" tablosunda "last_ip" alanı bulunamadı. IP adreslerini otomatik doldurmak için <a href="install_last_ip.php" class="underline">IP kurulum sayfasını</a> ziyaret edin.') . '
                  </div>';
        }
    } catch (PDOException $e) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>' . (getActiveLang() == 'en' ? t('admin_db_error') . ':</strong> ' : 'Veritabanı Hatası:</strong> ') . $e->getMessage() . '
              </div>';
    }
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
    
    <!-- Kullanıcı Arama Formu -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-200">
                <?php echo getActiveLang() == 'en' ? t('admin_user_search') : 'Kullanıcı Arama'; ?>
            </h3>
        </div>
        <div class="p-6">
            <form method="post" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="search_term" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_username_or_email') : 'Kullanıcı Adı veya E-posta:'; ?></label>
                        <div class="mt-1">
                            <input type="text" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md py-2 px-3" 
                                id="search_term" name="search_term" placeholder="<?php echo getActiveLang() == 'en' ? 'Username or email address to search...' : 'Aramak istediğiniz kullanıcı adı veya e-posta adresi...'; ?>" 
                                value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    
                    <div>
                        <label for="search_role" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_user_role') : 'Kullanıcı Rolü:'; ?></label>
                        <select class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm" 
                                id="search_role" name="search_role">
                            <option value=""><?php echo getActiveLang() == 'en' ? t('admin_all_roles') : 'Tüm Roller'; ?></option>
                            <option value="user"><?php echo getActiveLang() == 'en' ? t('admin_user') : 'Kullanıcı'; ?></option>
                            <option value="editor"><?php echo getActiveLang() == 'en' ? t('admin_editor') : 'Editör'; ?></option>
                            <option value="premium"><?php echo getActiveLang() == 'en' ? t('admin_premium_user') : 'Premium'; ?></option>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" name="search_user" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i> <?php echo getActiveLang() == 'en' ? t('admin_search_user') : 'Kullanıcı Ara'; ?>
                        </button>
                        <button type="reset" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-times mr-2"></i> <?php echo getActiveLang() == 'en' ? t('admin_clear') : 'Temizle'; ?>
                        </button>
                    </div>
                </div>
                
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="search_status" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_status') : 'Durum:'; ?></label>
                        <select class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm" 
                                id="search_status" name="search_status">
                            <option value=""><?php echo getActiveLang() == 'en' ? t('admin_all_statuses') : 'Tüm Durumlar'; ?></option>
                            <option value="active"><?php echo getActiveLang() == 'en' ? t('admin_active') : 'Aktif'; ?></option>
                            <option value="inactive"><?php echo getActiveLang() == 'en' ? t('admin_inactive') : 'Pasif'; ?></option>
                            <option value="pending"><?php echo getActiveLang() == 'en' ? t('admin_pending') : 'Onay Bekliyor'; ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="search_date" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_registration_date') : 'Kayıt Tarihi:'; ?></label>
                        <input type="date" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md py-2 px-3" 
                               id="search_date" name="search_date">
                    </div>
                </div>
            </form>
            
            <?php if (!empty($search_results)): ?>
            <div class="mt-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                        <?php echo getActiveLang() == 'en' ? t('admin_search_results') . ' (' . count($search_results) . ' ' . t('admin_users') . ')' : 'Arama Sonuçları (' . count($search_results) . ' kullanıcı)'; ?>
                    </h4>
                    <div>
                        <button type="button" class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-file-export mr-2"></i> <?php echo getActiveLang() == 'en' ? t('admin_export') : 'Dışa Aktar'; ?>
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-md rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                    <div class="flex items-center">
                                        <input id="select-all" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                        <span class="ml-3"><?php echo getActiveLang() == 'en' ? t('admin_user_column') : 'Kullanıcı'; ?></span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? t('admin_role') : 'Rol'; ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? t('admin_status') : 'Durum'; ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? t('admin_membership_date') : 'Üyelik Tarihi'; ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? t('admin_last_login') : 'Son Giriş'; ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider"><?php echo getActiveLang() == 'en' ? t('admin_actions') : 'İşlemler'; ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($search_results as $user): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                        <div class="flex items-center ml-4">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full object-cover" 
                                                    src="<?php echo isset($user['avatar']) ? '../uploads/avatars/'.htmlspecialchars($user['avatar']) : '../assets/img/default-avatar.png'; ?>" 
                                                    alt="<?php echo htmlspecialchars($user['username']); ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $roleClasses = [
                                        'admin' => 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-200',
                                        'editor' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200',
                                        'premium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200',
                                        'user' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200'
                                    ];
                                    $roleClass = $roleClasses[$user['role']] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $roleClass; ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClasses = [
                                        'active' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200',
                                        'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200',
                                        'banned' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200'
                                    ];
                                    $statusClass = $statusClasses[$user['status']] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d.m.Y', strtotime($user['created_at'] ?? 'now')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : (getActiveLang() == 'en' ? t('admin_login_not_made') : 'Giriş yapılmadı'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button type="button" onclick="selectUserForBan(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', '<?php echo addslashes($user['email']); ?>')" 
                                           class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <i class="fas fa-ban mr-1"></i> <?php echo getActiveLang() == 'en' ? t('admin_ban') : 'Banla'; ?>
                                        </button>
                                        <a href="../profile.php?id=<?php echo $user['id']; ?>" target="_blank" 
                                           class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-user mr-1"></i> <?php echo getActiveLang() == 'en' ? t('admin_profile') : 'Profil'; ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 flex justify-between items-center">
                    <div>
                        <button type="button" id="batch-action" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-ban mr-2"></i> <?php echo getActiveLang() == 'en' ? t('admin_ban_users') : 'Kullanıcıları Banla'; ?>
                        </button>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-700 dark:text-gray-300 mr-4">
                            <?php echo getActiveLang() == 'en' ? t('admin_total') . ': <span class="font-medium">' . count($search_results) . '</span> ' . t('admin_users') : 'Toplam: <span class="font-medium">' . count($search_results) . '</span> kullanıcı'; ?>
                        </span>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">1</a>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-900 text-sm font-medium text-blue-600 dark:text-blue-200">2</span>
                            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">3</a>
                            <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ban Ekleme Formu -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-200">
                <?php echo getActiveLang() == 'en' ? t('admin_ban_user') : 'Kullanıcı Banla'; ?>
            </h3>
        </div>
        <div class="p-6">
            <form method="post" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_user_column') : 'Kullanıcı:'; ?></label>
                        <select class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                                id="user_id" name="user_id" required>
                            <option value=""><?php echo getActiveLang() == 'en' ? t('admin_select_user') : '-- Kullanıcı Seçin --'; ?></option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_ban_reason') : 'Ban Sebebi:'; ?></label>
                        <input type="text" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md py-2 px-3" 
                               id="reason" name="reason" placeholder="<?php echo getActiveLang() == 'en' ? t('admin_optional_ban_reason') : 'Opsiyonel: Ban sebebi girin'; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="ip_address" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_ip_address') : 'IP Adresi (opsiyonel):'; ?></label>
                        <?php
                        // Seçilen kullanıcının son IP adresini almak için JavaScript değişkeni
                        $user_ip_data = [];
                        try {
                            // users tablosunda last_ip alanı varsa, tüm kullanıcıların IP adreslerini al
                            $ip_stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'last_ip'");
                            $ip_stmt->execute();
                            if ($ip_stmt->rowCount() > 0) {
                                $user_ip_stmt = $db->prepare("SELECT id, last_ip FROM users WHERE last_ip IS NOT NULL AND last_ip != ''");
                                $user_ip_stmt->execute();
                                while ($row = $user_ip_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $user_ip_data[$row['id']] = $row['last_ip'];
                                }
                            }
                        } catch (PDOException $e) {
                            error_log("IP adreslerini alırken hata: " . $e->getMessage());
                        }
                        ?>
                        <input type="text" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md py-2 px-3" 
                               id="ip_address" name="ip_address" placeholder="<?php echo getActiveLang() == 'en' ? t('admin_enter_ip') : 'IP adresi girin'; ?>">
                        <div class="mt-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" class="form-checkbox" name="is_ip_banned" value="1">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? t('admin_ban_ip_also') : 'IP Adresini de Banla'; ?></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2"><?php echo getActiveLang() == 'en' ? t('admin_expiry_date') : 'Bitiş Tarihi (opsiyonel):'; ?></label>
                        <input type="datetime-local" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md py-2 px-3" 
                               id="expires_at" name="expires_at">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo getActiveLang() == 'en' ? t('admin_permanent_ban') : 'Boş bırakırsanız süresiz ban uygulanır.'; ?></p>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="submit" name="ban_user" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-ban mr-2"></i> <?php echo getActiveLang() == 'en' ? t('admin_ban_user_button') : 'Kullanıcıyı Banla'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
</div>

<!-- JavaScript ile kullanıcı işlemleri -->
<script>
function selectUserForBan(userId, username, email) {
    // Kullanıcı select box'ını seç
    const userSelect = document.getElementById('user_id');
    
    // Option elementini bul veya oluştur
    let option = Array.from(userSelect.options).find(opt => opt.value == userId);
    
    if (!option) {
        // Eğer kullanıcı select box'ta yoksa, yeni option ekle
        option = new Option(`${username} (${email})`, userId);
        userSelect.add(option);
    }
    
    // Kullanıcıyı seç
    userSelect.value = userId;
    
    // IP adresini doldur
    const ipAddressInput = document.getElementById('ip_address');
    const userIpData = <?php echo json_encode($user_ip_data ?? []); ?>;
    if (ipAddressInput && userIpData[userId]) {
        ipAddressInput.value = userIpData[userId];
    }
    
    // Ban formuna odaklan
    document.getElementById('reason').focus();
    
    // Sayfayı ban formuna kaydır
    document.querySelector('.bg-white.dark\\:bg-gray-800.shadow.rounded-lg.mb-6:nth-of-type(2)').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
}

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // Kullanıcı seçildiğinde IP adresini doldur
    const userSelect = document.getElementById('user_id');
    const ipAddressInput = document.getElementById('ip_address');
    
    // IP verileri JavaScript değişkenine aktar
    const userIpData = <?php echo json_encode($user_ip_data ?? []); ?>;
    
    if (userSelect && ipAddressInput) {
        userSelect.addEventListener('change', function() {
            const userId = this.value;
            if (userId && userIpData[userId]) {
                ipAddressInput.value = userIpData[userId];
            } else {
                ipAddressInput.value = '';
            }
        });
    }
    
    // Tüm checkbox'ları seçme/seçimi kaldırma
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Temizle butonunu işlevselleştirme
    const resetButton = document.querySelector('button[type="reset"]');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            setTimeout(() => {
                document.querySelector('button[name="search_user"]').click();
            }, 100);
        });
    }
    
    // Toplu işlem butonunu işlevselleştirme
    const batchActionButton = document.getElementById('batch-action');
    if (batchActionButton) {
        batchActionButton.addEventListener('click', function() {
            const selectedUsers = document.querySelectorAll('input[name="selected_users[]"]:checked');
            
            if (selectedUsers.length === 0) {
                alert(getActiveLang() == 'en' ? 'Please select at least one user to perform this action.' : 'Lütfen işlem yapmak için en az bir kullanıcı seçin.');
                return;
            }
            
            if (confirm(getActiveLang() == 'en' ? 
                `Are you sure you want to ban ${selectedUsers.length} users?` : 
                `${selectedUsers.length} kullanıcıyı banlamak istediğinizden emin misiniz?`)) {
                // Burada toplu ban işlemi yapılabilir
                const userIds = Array.from(selectedUsers).map(checkbox => checkbox.value);
                alert(getActiveLang() == 'en' ? 
                    `Selected user IDs: ${userIds.join(', ')}\nThis function has not been implemented yet.` : 
                    `Seçilen kullanıcı ID'leri: ${userIds.join(', ')}\nBu işlev henüz uygulanmamıştır.`);
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
