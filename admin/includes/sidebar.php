<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Kullanıcı bilgileri ve avatarını al
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'Admin';

// Kullanıcı avatarını güvenceye al
$avatar = ensureUserAvatar($user_id);

// Dosyanın varlığını kontrol edelim
$avatar_path = '../uploads/avatars/' . $avatar;
$avatar_url = '/uploads/avatars/' . $avatar;

// Debug bilgisi için
error_log("Admin sidebar: Avatar dosyası - $avatar_path - " . (file_exists($avatar_path) ? "Mevcut" : "Yok"));
?>
<aside 
    class="sidebar-fixed bg-gray-800 text-white dark:bg-gray-900 dark:text-white transform top-0 left-0 md:relative md:translate-x-0 fixed h-full z-40 transition-transform duration-300 ease-in-out flex flex-col"
    :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}"
    x-cloak
>    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <img src="<?php echo $avatar_url; ?>" alt="<?php echo $username; ?>" class="w-10 h-10 rounded-full object-cover" onerror="this.src='/assets/img/default-avatar.jpg'">
                <div>
                    <h2 class="text-lg font-semibold"><?php echo $username; ?></h2>
                    <p class="text-xs text-gray-300">Admin</p>
                </div>
            </div>
            <!-- Mobil Kapatma Butonu -->
            <button @click="sidebarOpen = false" class="md:hidden text-white focus:outline-none">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <h2 class="text-2xl font-bold">
            <?php echo t('admin_panel'); ?>
        </h2>
    </div>
    <nav class="mt-6 flex-1 overflow-y-auto sidebar-scroll" x-data="{ 
        contentOpen: <?php echo in_array($current_page, ['articles', 'categories', 'comments', 'ai_article_bot', 'ai_bot_settings', 'article_view_settings', 'headlines']) ? 'true' : 'false'; ?>, 
        usersOpen: <?php echo in_array($current_page, ['users', 'subscriptions', 'premium_manager', 'ban_users', 'banned_users', 'payments']) ? 'true' : 'false'; ?>, 
        siteOpen: <?php echo in_array($current_page, ['settings', 'seo', 'ads', 'bots', 'cookies', 'backup', 'payment_methods', 'maintenance']) ? 'true' : 'false'; ?>,
        paymentOpen: <?php echo ($current_page === 'payments') ? 'true' : 'false'; ?>
    }">
        <!-- Dashboard -->
        <a href="index.php" class="flex items-center px-6 py-3 menu-item-stable <?php echo $current_page === 'index' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
            <i class="fas fa-tachometer-alt mr-3"></i>
            <?php echo t('admin_dashboard'); ?>
        </a>

        <!-- İçerik Yönetimi -->
        <div class="mt-2">
            <button @click="contentOpen = !contentOpen" class="flex items-center justify-between w-full px-6 py-3 text-left hover:bg-gray-700 focus:outline-none menu-item-stable">
                <div class="flex items-center">
                    <i class="fas fa-file-alt mr-3"></i>
                    <?php echo t('admin_content_management'); ?>
                </div>
                <i class="fas fa-chevron-down transform transition-transform duration-200" :class="{'rotate-180': contentOpen}"></i>
            </button>
            <div x-show="contentOpen" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-200" 
                 x-transition:enter-start="opacity-0 transform -translate-y-2" 
                 x-transition:enter-end="opacity-100 transform translate-y-0" 
                 x-transition:leave="transition ease-in duration-150" 
                 x-transition:leave-start="opacity-100 transform translate-y-0" 
                 x-transition:leave-end="opacity-0 transform -translate-y-2" 
                 class="ml-6">
                <a href="articles.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'articles' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-newspaper mr-3"></i>
                    <?php echo t('admin_articles'); ?>
                </a>
                <a href="categories.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'categories' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-folder mr-3"></i>
                    <?php echo t('admin_categories'); ?>
                </a>
                <a href="comments.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'comments' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-comments mr-3"></i>
                    <?php echo t('admin_comments'); ?>
                </a>
                <a href="article_view_settings.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'article_view_settings' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-th-large mr-3"></i>
                    <?php echo t('admin_article_view'); ?>
                </a>
                <a href="headlines.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'headlines' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-newspaper mr-3"></i>
                    <?php echo t('admin_article_headlines'); ?>
                </a>
                <a href="ai_article_bot.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'ai_article_bot' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-robot mr-3"></i>
                    <?php echo t('admin_article_ai'); ?>
                </a>
                <a href="ai_bot_settings.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'ai_bot_settings' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-key mr-3"></i>
                    <?php echo t('admin_ai_settings'); ?>
                </a>
            </div>
        </div>

        <!-- Kullanıcı Yönetimi -->
        <div class="mt-2">
            <button @click="usersOpen = !usersOpen" class="flex items-center justify-between w-full px-6 py-3 text-left hover:bg-gray-700 focus:outline-none menu-item-stable">
                <div class="flex items-center">
                    <i class="fas fa-users mr-3"></i>
                    <?php echo t('admin_user_management'); ?>
                </div>
                <i class="fas fa-chevron-down transform transition-transform duration-200" :class="{'rotate-180': usersOpen}"></i>
            </button>
            <div x-show="usersOpen" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-200" 
                 x-transition:enter-start="opacity-0 transform -translate-y-2" 
                 x-transition:enter-end="opacity-100 transform translate-y-0" 
                 x-transition:leave="transition ease-in duration-150" 
                 x-transition:leave-start="opacity-100 transform translate-y-0" 
                 x-transition:leave-end="opacity-0 transform -translate-y-2" 
                 class="ml-6">
                <a href="users.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'users' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-user mr-3"></i>
                    <?php echo t('admin_users'); ?>
                </a>
                <a href="ban_users.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'ban_users' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-ban mr-3"></i>
                    <?php echo t('admin_ban_users'); ?>
                </a>
                <a href="banned_users.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'banned_users' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-user-slash mr-3"></i>
                    <?php echo t('admin_banned_users'); ?>
                </a>
                <a href="subscriptions.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'subscriptions' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-star mr-3"></i>
                    <?php echo t('admin_subscriptions'); ?>
                </a>
                <a href="premium_manager.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'premium_manager' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-crown mr-3"></i>
                    <?php echo t('admin_premium_management'); ?>
                </a>
                <a href="payments.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'payments' && !isset($_GET['action']) ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-money-bill mr-3"></i>
                    <?php echo t('admin_payments'); ?>
                </a>
                <a href="payments.php?action=refund_requests" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'payments' && isset($_GET['action']) && $_GET['action'] === 'refund_requests' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-undo mr-3"></i>
                    <?php echo t('admin_refund_requests'); ?>
                </a>
            </div>
        </div>

        <!-- İletişim -->
        <a href="messages.php" class="flex items-center px-6 py-3 menu-item-stable <?php echo $current_page === 'messages' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
            <i class="fas fa-envelope mr-3"></i>
            <?php echo t('admin_messages'); ?>
            <?php
            // Okunmamış mesaj sayısını al
            $unread_count_query = $db->query("SELECT COUNT(*) FROM contacts WHERE status = 'unread'");
            if ($unread_count_query) {
                $unread_count = $unread_count_query->fetchColumn();
                if ($unread_count > 0) {
                    echo '<span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1 ml-1">' . $unread_count . '</span>';
                }
            }
            ?>
        </a>

        <!-- Site Yönetimi -->
        <div class="mt-2">
            <button @click="siteOpen = !siteOpen" class="flex items-center justify-between w-full px-6 py-3 text-left hover:bg-gray-700 focus:outline-none menu-item-stable">
                <div class="flex items-center">
                    <i class="fas fa-cogs mr-3"></i>
                    <?php echo t('admin_site_management'); ?>
                </div>
                <i class="fas fa-chevron-down transform transition-transform duration-200" :class="{'rotate-180': siteOpen}"></i>
            </button>
            <div x-show="siteOpen" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-200" 
                 x-transition:enter-start="opacity-0 transform -translate-y-2" 
                 x-transition:enter-end="opacity-100 transform translate-y-0" 
                 x-transition:leave="transition ease-in duration-150" 
                 x-transition:leave-start="opacity-100 transform translate-y-0" 
                 x-transition:leave-end="opacity-0 transform -translate-y-2" 
                 class="ml-6">
                <a href="settings.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'settings' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-cog mr-3"></i>
                    <?php echo t('admin_settings'); ?>
                </a>
                <a href="seo.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'seo' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-search mr-3"></i>
                    <?php echo t('admin_seo'); ?>
                </a>
                <a href="ads.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'ads' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-ad mr-3"></i>
                    <?php echo t('admin_ads'); ?>
                </a>
                <a href="payment_methods.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'payment_methods' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-credit-card mr-3"></i>
                    <?php echo t('admin_payment_methods'); ?>
                </a>
                <a href="bots.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'bots' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-robot mr-3"></i>
                    <?php echo t('admin_bots'); ?>
                </a>
                <a href="cookies.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'cookies' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-cookie mr-3"></i>
                    <?php echo t('admin_cookies'); ?>
                </a>
                <a href="backup.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'backup' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-database mr-3"></i>
                    <?php echo t('admin_backup'); ?>
                </a>
                <a href="maintenance.php" class="submenu-item flex items-center px-6 py-2 text-sm menu-item-stable <?php echo $current_page === 'maintenance' ? 'active bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                    <i class="fas fa-tools mr-3"></i>
                    <?php echo t('maintenance_mode'); ?>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Hızlı Erişim -->
    <div class="mt-auto pt-4 border-t border-gray-700 flex-shrink-0">
        <a href="/" class="flex items-center px-6 py-3 hover:bg-gray-700 menu-item-stable">
            <i class="fas fa-home mr-3"></i>
            <span class="md:inline"><?php echo t('admin_return_to_site'); ?></span>
        </a>
        <a href="../logout.php" class="flex items-center px-6 py-3 hover:bg-red-700 bg-red-600 mt-2 menu-item-stable">
            <i class="fas fa-sign-out-alt mr-3"></i>
            <span class="md:inline"><?php echo t('admin_logout'); ?></span>
        </a>
    </div>
    
    <!-- Mobilde sidebar'ın altında gösterilecek versiyon bilgisi -->
    <div class="md:hidden px-6 py-4 text-center text-sm text-gray-400 flex-shrink-0">
        <p>v1.0.0</p>
    </div>
</aside>
