<?php
// Header reklamı göster - premium üyelere gösterilmez
echo '<div class="header-ad-container" style="margin-bottom: 0.25rem;">' . showAd('header') . '</div>';
?>
<nav class="bg-white dark:bg-[#292929] shadow-sm">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-19">
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <a href="/" class="text-2xl font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                        <h1 class="text-2xl font-bold m-0 p-0">
                        <?php 
                        $site_title = getSetting('site_title');
                        echo !empty($site_title) ? htmlspecialchars($site_title) : 'Blog Sitesi'; 
                        ?>
                        </h1>
                    </a>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="/" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Ana Sayfa
                    </a>
                    <div class="inline-flex relative">
                        <button onclick="toggleCatMenu()" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-list mr-1"></i> Kategoriler <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                        <div id="catDropdownMenu" class="hidden absolute top-full left-0 bg-white dark:bg-gray-800 mt-1 py-2 w-56 rounded shadow-lg z-50">
                            <?php
                            // Kategorileri getir
                            $catStmt = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
                            if ($catStmt) {
                                $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($categories as $cat): ?>
                                    <a href="/kategori/<?php echo $cat['slug']; ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <?php echo clean($cat['name']); ?>
                                    </a>
                                <?php endforeach;
                            } ?>
                        </div>
                    </div>
                    <a href="/hakkimda" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-info-circle mr-1"></i> Hakkımda
                    </a>
                    
                    <a href="/iletisim" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-envelope mr-1"></i> İletişim
                    </a>
                    
                    <?php 
                    if (isLoggedIn()) {
                        $stmt = $db->prepare("SELECT approved, can_post FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Null kontrolü ekle
                        $user_approved = isset($user['approved']) ? $user['approved'] : false;
                        $user_can_post = isset($user['can_post']) ? $user['can_post'] : false;
                        
                        if (($user_approved && $user_can_post) || isAdmin()):
                    ?>
                    <a href="/makale_ekle" class="border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-plus-circle mr-1"></i> Makale Ekle
                    </a>
                    <?php 
                        endif;
                    }
                    ?>
                </div>
            </div>
            <div class="hidden sm:ml-6 sm:flex sm:items-center">
                <!-- Tema değiştirme düğmesi -->
                <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5 flex items-center mr-2">
                    <span id="theme-toggle-dark-icon" class="hidden">
                        <i class="fas fa-moon"></i>
                    </span>
                    <span id="theme-toggle-light-icon">
                        <i class="fas fa-sun"></i>
                    </span>
                </button>
                
                <?php if (isLoggedIn()): ?>
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-4">
                            <?php if (isAdmin()): ?>
                                <a href="/admin" class="text-gray-500 dark:text-gray-300 hover:text-blue-600">
                                    <i class="fas fa-cog"></i> Panel
                                </a>
                            <?php endif; ?>
                            <div class="flex items-center space-x-3">
                                <a href="/profil" class="flex items-center hover:opacity-75">
                                    <img src="<?php 
                                        // Kullanıcı avatarını güvenceye al
                                        $avatar = ensureUserAvatar($_SESSION['user_id']);
                                        // Base64 kodlu avatar kullan (LiteSpeed için)
                                        echo getAvatarBase64($avatar);
                                    ?>" 
                                         alt="<?php echo $_SESSION['username']; ?>" 
                                         class="w-8 h-8 rounded-full object-cover">
                                    <span class="ml-2 text-gray-600 dark:text-gray-300"><?php echo $_SESSION['username']; ?></span>
                                </a>
                                
                                <!-- Mesajlar Bağlantısı -->
                                <a href="/mesajlarim.php" class="text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-2 relative mr-2">
                                    <i class="fas fa-envelope"></i>
                                    <?php
                                    // Okunmamış mesaj sayısını al
                                    try {
                                        $unread_count_query = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id = ? AND status = 'unread' AND is_deleted_by_receiver = 0");
                                        $unread_count_query->execute([$_SESSION['user_id']]);
                                        $unread_count = $unread_count_query->fetchColumn();
                                        
                                        if ($unread_count > 0) {
                                            echo '<span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">' . $unread_count . '</span>';
                                        }
                                    } catch (Exception $e) {
                                        // Hata durumunda sessizce devam et
                                        error_log("Okunmamış mesaj sayısı alma hatası: " . $e->getMessage());
                                    }
                                    ?>
                                </a>
                                <a href="/logout.php" class="text-red-500 hover:text-red-600">
                                    <i class="fas fa-sign-out-alt"></i> Çıkış
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center space-x-4">
                        <a href="/login" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-sign-in-alt"></i> Giriş
                        </a>
                        <a href="/register" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-user-plus"></i> Kayıt Ol
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Mobil menü butonu -->
            <div class="flex items-center sm:hidden">
                <button id="theme-toggle-mobile" type="button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5 flex items-center mr-2">
                    <span id="theme-toggle-dark-icon-mobile" class="hidden">
                        <i class="fas fa-moon"></i>
                    </span>
                    <span id="theme-toggle-light-icon-mobile">
                        <i class="fas fa-sun"></i>
                    </span>
                </button>
                
                <?php if (isLoggedIn()): ?>
                    <a href="/profil" class="flex items-center hover:opacity-75 mr-2">
                        <img src="<?php 
                            // Kullanıcı avatarını güvenceye al
                            $avatar = ensureUserAvatar($_SESSION['user_id']);
                            // Base64 kodlu avatar kullan (LiteSpeed için)
                            echo getAvatarBase64($avatar);
                        ?>" 
                             alt="<?php echo $_SESSION['username']; ?>" 
                             class="w-8 h-8 rounded-full object-cover">
                    </a>
                    
                    <!-- Mesajlar Bağlantısı -->
                    <a href="mesajlarim.php" class="text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-2 relative mr-2">
                        <i class="fas fa-envelope"></i>
                        <?php
                        // Okunmamış mesaj sayısını al
                        try {
                            $unread_count_query = $db->prepare("SELECT COUNT(*) FROM user_messages WHERE receiver_id = ? AND status = 'unread' AND is_deleted_by_receiver = 0");
                            $unread_count_query->execute([$_SESSION['user_id']]);
                            $unread_count = $unread_count_query->fetchColumn();
                            
                            if ($unread_count > 0) {
                                echo '<span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">' . $unread_count . '</span>';
                            }
                        } catch (Exception $e) {
                            // Hata durumunda sessizce devam et
                            error_log("Okunmamış mesaj sayısı alma hatası: " . $e->getMessage());
                        }
                        ?>
                    </a>
                <?php endif; ?>
                
                <button id="mobile-menu-button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-2">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobil menü -->
        <div id="mobile-menu" class="sm:hidden hidden">
            <div class="pt-2 pb-3 space-y-1">
                <a href="/" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-home mr-1"></i> Ana Sayfa
                </a>
                <button onclick="toggleMobileCatMenu()" class="w-full text-left pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex justify-between items-center">
                    <span><i class="fas fa-list mr-1"></i> Kategoriler</span>
                    <i class="fas fa-chevron-down text-xs mr-2"></i>
                </button>
                <div id="mobileCatDropdownMenu" class="hidden pl-6 pr-4 py-2 bg-gray-50 dark:bg-gray-700">
                    <?php
                    // Kategorileri getir (mobil için tekrar)
                    $catStmt = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
                    if ($catStmt) {
                        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($categories as $cat): ?>
                            <a href="/kategori/<?php echo $cat['slug']; ?>" class="block py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                &bull; <?php echo clean($cat['name']); ?>
                            </a>
                        <?php endforeach;
                    } ?>
                </div>
                <a href="/hakkimda" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-info-circle mr-1"></i> Hakkımda
                </a>
                <a href="/iletisim" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-envelope mr-1"></i> İletişim
                </a>
                <?php if (isLoggedIn()): 
                    $stmt = $db->prepare("SELECT approved, can_post FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (($user['approved'] && $user['can_post']) || isAdmin()): ?>
                    <a href="/makale_ekle" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-plus-circle mr-1"></i> Makale Ekle
                    </a>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                    <a href="/admin" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-cog mr-1"></i> Yönetim Paneli
                    </a>
                    <?php endif; ?>
                    <a href="/logout.php" class="block pl-3 pr-4 py-2 text-base font-medium text-red-500 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i> Çıkış
                    </a>
                <?php else: ?>
                    <div class="flex pl-3 pr-4 py-2 space-x-4">
                        <a href="/login" class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                            <i class="fas fa-sign-in-alt mr-1"></i> Giriş
                        </a>
                        <a href="/register" class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                            <i class="fas fa-user-plus mr-1"></i> Kayıt Ol
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php
// Hata ve başarı mesajlarını göster
if (isset($_SESSION['error'])): ?>
<div class="max-w-7xl mx-auto px-4 py-3 mt-4">
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg class="fill-current h-6 w-6 text-red-500" onclick="this.parentElement.parentElement.style.display='none'" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Kapat</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
        </span>
    </div>
</div>
<?php 
    unset($_SESSION['error']); 
endif; 

if (isset($_SESSION['success'])): ?>
<div class="max-w-7xl mx-auto px-4 py-3 mt-4">
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg class="fill-current h-6 w-6 text-green-500" onclick="this.parentElement.parentElement.style.display='none'" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Kapat</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
        </span>
    </div>
</div>
<?php 
    unset($_SESSION['success']); 
endif; 
?>
