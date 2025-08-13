<?php
// Admin kontrolü
require_once '../includes/config.php';
require_once 'includes/notifications.php';
if (!isAdmin()) {
    header('Location: /');
    exit;
}

// Her sayfa yüklendiğinde avatar bilgisini tazeleyerek sorun olmasını önle
if (isset($_SESSION['user_id'])) {
    // Oturum bilgilerini tamamen yenileyelim
    $stmt = $db->prepare("SELECT id, username, email, role, avatar, is_premium, premium_until FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?: 'default-avatar.jpg';
        $_SESSION['is_premium'] = (int)$user['is_premium'];
        $_SESSION['premium_until'] = $user['premium_until'];
    }
    
    // Avatar kontrolünü yapalım
    $_SESSION['avatar'] = ensureUserAvatar($_SESSION['user_id']);
    error_log("Admin header: Avatar yeniden kontrol edildi - " . $_SESSION['avatar']);
    
    // Oturum bilgilerini kaydet
    session_write_close();
    // Oturumu tekrar başlat
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?php echo getActiveLang(); ?>" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('admin_panel'); ?></title>
    
    <!-- FOUC (Flash of Unstyled Content) önleme - Koyu mod için -->
    <script>
        // Sayfa yüklenmeden önce tema durumunu kontrol et ve uygula
        (function() {
            const userTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (userTheme === 'dark' || (!userTheme && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
                // Body için de anlık stil uygula
                document.documentElement.style.backgroundColor = '#1a1a1a';
                document.documentElement.style.color = '#e0e0e0';
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.backgroundColor = '#f9fafb';
                document.documentElement.style.color = '#111827';
            }
        })();
    </script>
    
    <!-- CSS stilleri en üstte yükle -->
    <style>
        /* Anlık yükleme için kritik CSS */
        html.dark {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }
        
        html:not(.dark) {
            background-color: #f9fafb !important;
            color: #111827 !important;
        }
        
        /* Body için de aynı stilleri uygula */
        html.dark body {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }
        
        html:not(.dark) body {
            background-color: #f9fafb !important;
            color: #111827 !important;
        }
        
        /* Sayfa yüklenirken gizleme */
        .admin-content {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        
        .admin-content.loaded {
            opacity: 1;
        }
    </style>
    <?php
    // Favicon ekle
    $favicon = getSetting('favicon');
    $site_logo = getSetting('site_logo');
    if (!empty($favicon)) {
        echo '<link rel="icon" href="/' . $favicon . '">';
    }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            bg: '#1a1a1a',
                            card: '#2a2a2a',
                            text: '#e0e0e0',
                            border: '#3a3a3a'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        /* Alpine.js için x-cloak stilleri */
        [x-cloak] { display: none !important; }
        
        /* Geçiş animasyonları */
        * {
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }
        
        /* Koyu tema stilleri - daha spesifik seçiciler */
        .dark body {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }
        
        .dark .bg-white {
            background-color: #2a2a2a !important;
        }
        
        .dark .bg-gray-100 {
            background-color: #1a1a1a !important;
        }
        
        .dark .bg-gray-50 {
            background-color: #374151 !important;
        }
        
        .dark .text-gray-500,
        .dark .text-gray-600,
        .dark .text-gray-700,
        .dark .text-gray-800,
        .dark .text-gray-900 {
            color: #e0e0e0 !important;
        }
        
        .dark .shadow-sm,
        .dark .shadow-lg,
        .dark .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2) !important;
        }
        
        .dark .border,
        .dark .border-gray-200 {
            border-color: #3a3a3a !important;
        }
        
        .dark .hover\:bg-gray-100:hover {
            background-color: #333333 !important;
        }
        
        .dark a.hover\:text-red-900:hover {
            color: #ffcdd2 !important;
        }
        
        .dark a.text-red-600 {
            color: #ef9a9a !important;
        }
        
        .dark .hover\:text-gray-900:hover {
            color: #ffffff !important;
        }
        
        /* Menü açılır kapanır animasyonları */
        .menu-transition {
            transition: all 0.3s ease-in-out;
        }
        
        /* Alt menü öğeleri için hover efekti */
        .submenu-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease-in-out;
        }
        
        .submenu-item:hover {
            border-left-color: #3b82f6;
            transform: translateX(2px);
        }
        
        .submenu-item.active {
            border-left-color: #ef4444;
            background-color: rgba(17, 24, 39, 0.8);
        }
        
        /* Sidebar scroll düzenlemeleri */
        .sidebar-scroll {
            /* Firefox için */
            scrollbar-width: none;
            -ms-overflow-style: none; /* IE 10+ için */
            /* Layout'u sabitlemek için */
            overflow-y: auto;
        }
        
        /* Webkit tarayıcılar için scrollbar'ı gizle */
        .sidebar-scroll::-webkit-scrollbar {
            display: none;
        }
        
        /* Sidebar sabit genişlik */
        .sidebar-fixed {
            width: 256px; /* w-64 = 16rem = 256px */
            min-width: 256px;
            max-width: 256px;
        }
        
        /* Menü öğeleri için sabit padding */
        .menu-item-stable {
            padding-right: 1.5rem; /* Daha az padding */
            margin-right: 0;
        }
        
        /* Hover efektlerini stabilize et */
        .hover-stable:hover {
            transform: none;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 admin-content">
    <div class="flex h-screen" x-data="{ sidebarOpen: false }">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <header class="bg-white shadow-sm dark:bg-gray-800">
                <div class="max-w-full mx-auto px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <!-- Hamburger menü butonu -->
                            <button @click="sidebarOpen = !sidebarOpen" class="mr-3 text-gray-500 md:hidden focus:outline-none">
                                <i class="fas fa-bars text-xl"></i>
                            </button>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            <?php
                            switch($currentPage) {
                                case 'index.php':
                                    echo t('admin_dashboard');
                                    break;
                                case 'articles.php':
                                    echo t('admin_articles');
                                    break;
                                case 'categories.php':
                                    echo t('admin_categories');
                                    break;
                                case 'comments.php':
                                    echo t('admin_comments');
                                    break;
                                case 'users.php':
                                    echo t('admin_users');
                                    break;
                                case 'ban_users.php':
                                    echo 'Üye Banlama Yönetimi';
                                    break;
                                case 'subscriptions.php':
                                    echo t('admin_subscriptions');
                                    break;
                                case 'ads.php':
                                    echo t('admin_ads');
                                    break;
                                case 'settings.php':
                                    echo t('admin_settings');
                                    break;
                                case 'ai_article_bot.php':
                                    echo t('admin_article_ai');
                                    break;
                                case 'ai_bot_settings.php':
                                    echo t('admin_ai_settings');
                                    break;
                                case 'article_view_settings.php':
                                    echo t('admin_article_view');
                                    break;
                                case 'headlines.php':
                                    echo t('admin_article_headlines');
                                    break;
                                case 'messages.php':
                                    echo t('admin_messages');
                                    break;
                                case 'premium_manager.php':
                                    echo t('admin_premium_management');
                                    break;
                                case 'payments.php':
                                    echo 'Ödemeler';
                                    break;
                                case 'payment_methods.php':
                                    echo 'Ödeme Yöntemleri';
                                    break;
                                case 'seo.php':
                                    echo t('admin_seo');
                                    break;
                                case 'bots.php':
                                    echo t('admin_bots');
                                    break;
                                case 'cookies.php':
                                    echo t('admin_cookies');
                                    break;
                                case 'backup.php':
                                    echo t('admin_backup');
                                    break;
                                default:
                                    echo t('admin_panel');
                            }                            ?>
                        </h1>
                        </div>
                        <div class="flex space-x-2 sm:space-x-4 items-center">
                            <!-- Tema değiştirme düğmesi -->
                            <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5 flex items-center">
                                <span id="theme-toggle-dark-icon" class="hidden">
                                    <i class="fas fa-moon"></i>
                                </span>
                                <span id="theme-toggle-light-icon">
                                    <i class="fas fa-sun"></i>
                                </span>
                            </button>
                            
                            <!-- Bildirim ikonu -->
                            <div class="relative" x-data="{ notificationsOpen: false }">
                                <button @click="notificationsOpen = !notificationsOpen" 
                                        @click.away="notificationsOpen = false"
                                        class="text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5 flex items-center relative">
                                    <i class="fas fa-bell"></i>
                                    <span id="notification-badge" class="notification-badge hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
                                </button>
                                
                                <!-- Bildirim dropdown -->
                                <div x-show="notificationsOpen" 
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-md shadow-lg z-50 overflow-hidden"
                                     style="max-height: 400px; overflow-y: auto;">
                                    <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Bildirimler</h3>
                                        <button id="mark-all-read" class="text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400">Tümünü Okundu İşaretle</button>
                                    </div>
                                    <div id="notifications-container" class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-spinner fa-spin mr-2"></i> Bildirimler yükleniyor...
                                        </div>
                                    </div>
                                    <div class="p-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
                                        <a href="javascript:void(0)" id="refresh-notifications" class="text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400">
                                            <i class="fas fa-sync-alt mr-1"></i> Yenile
                                        </a>
                                        <a href="all_notifications.php" class="text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400">
                                            Tüm Bildirimleri Gör
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dil değiştirme dropdown menüsü -->
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm p-2.5">
                                    <i class="fas fa-globe mr-1"></i>
                                    <span class="font-medium"><?php echo strtoupper(getActiveLang()); ?></span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div x-show="open" 
                                     x-cloak
                                     @click.outside="open = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-800 rounded-md shadow-lg z-50">
                                    <div class="py-1">
                                        <a href="/change_lang.php?lang=tr&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                           class="<?php echo getActiveLang() == 'tr' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 dark:text-gray-200'; ?> block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-check mr-2 <?php echo getActiveLang() == 'tr' ? '' : 'invisible'; ?>"></i>Türkçe
                                        </a>
                                        <a href="/change_lang.php?lang=en&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                           class="<?php echo getActiveLang() == 'en' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-700 dark:text-gray-200'; ?> block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-check mr-2 <?php echo getActiveLang() == 'en' ? '' : 'invisible'; ?>"></i>English
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="/" class="hidden sm:flex text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white items-center">
                                <i class="fas fa-external-link-alt mr-1"></i><span class="hidden md:inline"><?php echo t('admin_view_site'); ?></span>
                            </a>
                            <a href="/logout.php" class="text-red-600 hover:text-red-900 flex items-center">
                                <i class="fas fa-sign-out-alt mr-1"></i><span class="hidden md:inline"><?php echo t('admin_logout'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>            </header>
            <!-- Overlay - mobil görünümde sidebar açıkken arka planı karartma -->
            <div 
                class="fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden transition-opacity duration-300"
                x-show="sidebarOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="sidebarOpen = false"
            ></div>
            <main class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8">

<script>
// Bildirim sistemi JS kodları
document.addEventListener('DOMContentLoaded', function() {
    // Bildirim sayısı ve listesini yükle
    loadNotifications();
    
    // 60 saniyede bir bildirim kontrolü yap
    setInterval(loadNotifications, 60000);
    
    // Tümünü okundu işaretle butonu
    document.getElementById('mark-all-read').addEventListener('click', function(e) {
        e.preventDefault();
        markAllNotificationsAsRead();
    });
    
    // Yenile butonu
    document.getElementById('refresh-notifications').addEventListener('click', function(e) {
        e.preventDefault();
        loadNotifications();
    });
});

// Bildirimleri yükle
function loadNotifications() {
    // Yükleniyor mesajını göster
    const container = document.getElementById('notifications-container');
    container.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i> Bildirimler yükleniyor...</div>';
    
    // Zamanaşımı kontrolü için
    let timeoutId = setTimeout(() => {
        container.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">'+
            'Sunucu yanıt vermedi. <button id="retry-notifications-timeout" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Tekrar Dene</button>'+
            '</div>';
        
        document.getElementById('retry-notifications-timeout').addEventListener('click', function() {
            loadNotifications();
        });
    }, 10000); // 10 saniye sonra timeout
    
    // Sunucudan bildirimleri al
    fetch('ajax_notifications.php?action=get_notifications&cache=' + new Date().getTime()) // önbelleği engellemek için
        .then(response => {
            clearTimeout(timeoutId); // timeout iptal et
            
            if (!response.ok) {
                throw new Error('Sunucu yanıt vermedi: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Önce bildirim sayısını güncelle
                updateNotificationBadge(data.unread_count);
                
                // Sonra bildirimleri göster
                renderNotifications(data.notifications, data.unread_count);
                
                // Debug için konsola yaz
                console.log('Bildirim sayısı:', data.unread_count);
                console.log('Bildirimler:', data.notifications);
                
                // Bildirim sayısını tarayıcı başlığında göster
                if (data.unread_count > 0) {
                    document.title = `(${data.unread_count}) ${document.title.replace(/^\(\d+\)\s/, '')}`;
                } else {
                    document.title = document.title.replace(/^\(\d+\)\s/, '');
                }
                
                // Bildirim tablosu yoksa, kurulum sayfasına yönlendirme seçeneği göster
                if (data.error && data.error.includes('Bildirim tablosu bulunamadı')) {
                    container.innerHTML += '<div class="mt-2 p-2 bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 text-xs rounded">'+
                        'Bildirim tablosu eksik. <a href="install_notifications.php" class="font-medium underline">Kurulum için tıklayın</a>'+
                        '</div>';
                }
            } else {
                console.error('Bildirim verisi başarısız:', data);
                container.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">'+
                    'Bildirimler alınamadı. <button id="retry-notifications" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Tekrar Dene</button>'+
                    '</div>';
                
                if (data.message) {
                    container.innerHTML += '<div class="p-2 text-center text-xs text-red-500">Hata: ' + data.message + '</div>';
                }
                
                document.getElementById('retry-notifications').addEventListener('click', function() {
                    loadNotifications();
                });
            }
        })
        .catch(error => {
            clearTimeout(timeoutId); // timeout iptal et
            console.error('Bildirim yükleme hatası:', error);
            container.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">'+
                'Bildirimler yüklenirken bir hata oluştu. <button id="retry-notifications" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Tekrar Dene</button>'+
                '<div class="mt-2 text-xs text-red-500">' + error.message + '</div>'+
                '</div>';
            
            // Kurulum sayfasına yönlendirme seçeneği
            container.innerHTML += '<div class="mt-2 p-2 bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 text-xs rounded text-center">'+
                'Bildirim sisteminde bir sorun var. <a href="install_notifications.php" class="font-medium underline">Kurulum sayfasını açın</a>'+
                '</div>';
            
            document.getElementById('retry-notifications').addEventListener('click', function() {
                loadNotifications();
            });
        });
}

// Bildirim sayısını güncelle
function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
        
        // Bildirim butonunu vurgula
        const notificationButton = badge.parentElement;
        notificationButton.classList.add('animate-pulse', 'text-blue-500');
        setTimeout(() => {
            notificationButton.classList.remove('animate-pulse');
        }, 2000);
    } else {
        badge.classList.add('hidden');
        badge.parentElement.classList.remove('text-blue-500');
    }
}

// Bildirimleri render et
function renderNotifications(notifications, unreadCount) {
    const container = document.getElementById('notifications-container');
    
    // Debug için konsola yaz
    console.log('renderNotifications çağrıldı:', { notifications, unreadCount });
    
    // Doğrudan veritabanından gelen bildirimleri göster
    let html = '';
    
    // notifications null veya undefined ise boş dizi olarak işle
    if (!notifications) {
        notifications = [];
    }
    
    // unreadCount tanımlı değilse badge'den al
    if (unreadCount === undefined || unreadCount === null) {
        const badge = document.getElementById('notification-badge');
        unreadCount = parseInt(badge.textContent) || 0;
    }
    
    if (notifications.length === 0) {
        if (unreadCount > 0) {
            // Okunmamış bildirim var ama liste boş - bu bir tutarsızlık durumu
            container.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">'+
               '<p>Okunmamış bildirimleriniz var ancak listelenemedi.</p>'+
               '<button id="refresh-notifications-empty" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Yenile</button>'+
               '</div>';
            
            // Durumu düzeltmek için bir buton daha ekle
            container.innerHTML += '<div class="mt-2 p-2 text-center">'+
               '<button id="fix-notifications" class="text-xs text-red-500 hover:underline">Bildirim Verilerini Onar</button>'+
               '</div>';
            
            // Yenile butonuna tıklandığında bildirimleri tekrar yükle
            document.getElementById('refresh-notifications-empty').addEventListener('click', function() {
                loadNotifications();
            });
            
            // Onarım butonu - tüm bildirimleri okundu olarak işaretler
            document.getElementById('fix-notifications').addEventListener('click', function() {
                markAllNotificationsAsRead();
                setTimeout(() => {
                    loadNotifications();
                }, 1000);
            });
        } else {
            // Bildirim yok
            container.innerHTML = '<div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">Yeni bildirim yok</div>';
        }
        return;
    }
    
    notifications.forEach(notification => {
        // Bildirim tipi için renk ve ikon belirle
        let typeIcon, typeColor, notifTitle;
        
        switch(notification.type) {
            case 'new_user':
                typeIcon = 'fa-user-plus';
                typeColor = 'text-blue-500';
                notifTitle = 'Yeni Üye';
                break;
            case 'new_article':
                typeIcon = 'fa-newspaper';
                typeColor = 'text-green-500';
                notifTitle = 'Yeni Makale';
                break;
            case 'new_comment':
                typeIcon = 'fa-comment';
                typeColor = 'text-amber-500';
                notifTitle = 'Yeni Yorum';
                break;
            case 'system':
                typeIcon = 'fa-cogs';
                typeColor = 'text-purple-500';
                notifTitle = 'Sistem Bildirimi';
                break;
            default:
                typeIcon = 'fa-bell';
                typeColor = 'text-gray-500';
                notifTitle = 'Bildirim';
        }
        
        // Tarih formatı
        const date = new Date(notification.created_at);
        const formattedDate = date.toLocaleString('tr-TR');
        
        // Bildirim türüne göre avatar gösterme kontrolü
        let showAvatar = false;
        let avatarHtml = '';
        
        // Sadece belirli bildirim türlerinde avatar göster
        if (notification.type === 'new_comment' || notification.type === 'system') {
            const avatar = notification.avatar ? '/' + notification.avatar : '/assets/img/default-avatar.jpg';
            avatarHtml = `
                <div class="flex-shrink-0 mr-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden">
                        <img src="${avatar}" alt="${notification.username}" class="w-full h-full object-cover">
                    </div>
                </div>`;
            showAvatar = true;
        } else if (notification.type === 'new_user') {
            // Yeni üye bildirimlerinde avatar yerine icon göster
            avatarHtml = `
                <div class="flex-shrink-0 mr-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center bg-blue-100 dark:bg-blue-900">
                        <i class="fas fa-user-plus text-blue-500 text-lg"></i>
                    </div>
                </div>`;
        } else {
            // Diğer bildirim türlerinde ilgili icon göster
            avatarHtml = `
                <div class="flex-shrink-0 mr-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                        <i class="fas ${typeIcon} ${typeColor} text-lg"></i>
                    </div>
                </div>`;
        }
        
        // Okundu durumu için arka plan rengi
        const bgClass = notification.is_read == 0 ? 'bg-blue-50 dark:bg-blue-900/20' : '';
        
        html += `
            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700 ${bgClass}" data-id="${notification.id}">
                <div class="flex">
                    ${avatarHtml}
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                ${notification.type === 'new_user' ? notifTitle : (notification.username ? notification.username : 'Sistem')}
                            </p>
                            <span class="text-xs text-gray-500 dark:text-gray-400">${formattedDate}</span>
                        </div>
                        <div class="flex items-start mb-1">
                            <i class="fas ${typeIcon} mr-2 ${typeColor} mt-1"></i>
                            <p class="text-sm text-gray-600 dark:text-gray-300">${notification.message}</p>
                        </div>
                        ${notification.link ? `<a href="${notification.link}" class="text-xs text-blue-500 hover:underline">Detayları görüntüle</a>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Bildirime tıklandığında okundu olarak işaretle
    container.querySelectorAll('[data-id]').forEach(el => {
        el.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            markNotificationAsRead(id);
        });
    });
}

// Bildirimi okundu olarak işaretle
function markNotificationAsRead(id) {
    const formData = new FormData();
    formData.append('notification_id', id);
    
    fetch('ajax_notifications.php?action=mark_read', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(error => console.error('Bildirim okundu işaretleme hatası:', error));
}

// Tüm bildirimleri okundu olarak işaretle
function markAllNotificationsAsRead() {
    fetch('ajax_notifications.php?action=mark_all_read', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(error => console.error('Tüm bildirimler okundu işaretleme hatası:', error));
}
</script>

<style>
/* Bildirim sistemi CSS kodları */
.notification-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 5px rgba(239, 68, 68, 0);
    }
    
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}
</style>
