<?php
// Çıktı tamponlamasını başlat
ob_start();

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Üye adını veya ID'sini URL'den al
$username = isset($_GET['username']) ? $_GET['username'] : '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Hem kullanıcı adı hem de ID boş ise ana sayfaya yönlendir
if (empty($username) && $user_id <= 0) {
    header("Location: index.php");
    exit();
}

try {
    // Üye bilgilerini getir - username veya id'ye göre
    if (!empty($username)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        error_log("Username sorgusu: $username");
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        error_log("User_id sorgusu: $user_id");
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ekstra debug bilgisi
    error_log("SQL sorgusu sonucu: " . ($user ? "Kullanıcı bulundu" : "Kullanıcı bulunamadı"));
      // Kullanıcı bulunamadıysa ana sayfaya yönlendir
    if (!$user) {
        header("Location: index.php");
        exit();
    }
    
    // Debug: Kullanıcı bilgilerini yazdır
    error_log("Kullanıcı Bilgileri: " . print_r($user, true));
    
    // Kullanıcı ID'sini atama (username ile geldiyse)
    $user_id = $user['id'];
    
    // SEO için canonical URL
    $canonical_url = getSetting('site_url') . "/uyeler/" . ($user['username'] ?? 'kullanici');
    
    // Kullanıcı bilgilerini güvenli şekilde al
    $username = isset($user['username']) ? $user['username'] : 'İsimsiz Kullanıcı';
    $role = isset($user['role']) ? $user['role'] : 'user';
    $is_premium = isset($user['is_premium']) ? (bool)$user['is_premium'] : false;
    $avatar = isset($user['avatar']) && !empty($user['avatar']) ? $user['avatar'] : 'default-avatar.jpg';
    $created_at = isset($user['created_at']) && !empty($user['created_at']) ? $user['created_at'] : null;
    $last_login = isset($user['last_login']) && !empty($user['last_login']) ? $user['last_login'] : null;
    
    // Kullanıcının sosyal medya bilgileri
    $social_info = [
        'bio' => isset($user['bio']) ? $user['bio'] : '',
        'location' => isset($user['location']) ? $user['location'] : '',
        'website' => isset($user['website']) ? $user['website'] : '',
        'twitter' => isset($user['twitter']) ? $user['twitter'] : '',
        'facebook' => isset($user['facebook']) ? $user['facebook'] : '',
        'instagram' => isset($user['instagram']) ? $user['instagram'] : '',
        'linkedin' => isset($user['linkedin']) ? $user['linkedin'] : '',
        'youtube' => isset($user['youtube']) ? $user['youtube'] : '',
        'tiktok' => isset($user['tiktok']) ? $user['tiktok'] : '',
        'github' => isset($user['github']) ? $user['github'] : '',
    ];
    
    // Kullanıcının istatistiklerini getir
    // 1. Makale sayısı
    $article_stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE author_id = ? AND status = 'published'");
    $article_stmt->execute([$user_id]);
    $article_count = $article_stmt->fetchColumn();
    
    // 2. Toplam görüntülenme sayısı
    $views_stmt = $db->prepare("
        SELECT COALESCE(SUM(view_count), 0) AS total_views 
        FROM articles 
        WHERE author_id = ? AND status = 'published'
    ");
    $views_stmt->execute([$user_id]);
    $total_views = $views_stmt->fetchColumn();
    
    // 3. Üyelik süresi
    if (isset($user['created_at']) && !empty($user['created_at'])) {
        $registration_date = new DateTime($user['created_at']);
        $current_date = new DateTime();
        $membership_days = $current_date->diff($registration_date)->days;
    } else {
        $membership_days = 0; // Eğer kayıt tarihi yoksa varsayılan değer
    }
    
} catch (PDOException $e) {
    // Hata durumunda logla ve ana sayfaya yönlendir
    error_log("Üye bilgisi alma hatası: " . $e->getMessage());
    header("Location: index.php");
    exit();
}

// Kullanıcının engellenme durumunu kontrol et
$is_blocked = false;
if (isLoggedIn() && $user_id != $_SESSION['user_id']) {
    $is_blocked = isUserBlocked($_SESSION['user_id'], $user_id);
}

// Sayfa başlığını kullanıcı adıyla ayarla
$page_title = isset($user['username']) ? htmlspecialchars($user['username']) . ' - ' . t('member_title') : t('member_title');

// SEO meta etiketleri için bilgileri hazırla
$meta_description = !empty($social_info['bio']) ? mb_substr(strip_tags($social_info['bio']), 0, 160) : (isset($user['username']) ? htmlspecialchars($user['username']) . ' ' . t('member_title') : t('member_title'));
$meta_canonical = $canonical_url;

// Header'ı dahil et
require_once 'templates/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Profil Başlığı -->
    <div class="flex flex-col md:flex-row items-start md:items-center md:justify-between mb-6">
        <div class="flex items-center mb-4 md:mb-0">
            <div class="mr-4">
 
            </div>
        </div>
        
        <?php if (isLoggedIn() && $user_id != $_SESSION['user_id']): ?>
            <div class="flex space-x-2">
                <?php if ($is_blocked): ?>
                    <a href="engellenen-kullanicilar.php?action=unblock&id=<?php echo $user_id; ?>" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                       onclick="return confirm('<?php echo t('member_unblock_confirm'); ?>');">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo t('member_unblock'); ?>
                    </a>
                <?php else: ?>
                    <button type="button" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            onclick="document.getElementById('blockUserModal').classList.remove('hidden');">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                        <?php echo t('member_block'); ?>
                    </button>
                <?php endif; ?>
                
                <?php 
                // Kullanıcının premium veya admin olup olmadığını kontrol et
                $isPremiumOrAdmin = isPremium() || isAdmin();
                
                // Kullanıcının onaylanmış olup olmadığını kontrol et
                $user_status_stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
                $user_status_stmt->execute([$_SESSION['user_id']]);
                $user_status = $user_status_stmt->fetchColumn();
                $isUserActive = ($user_status === 'active');
                
                // Mesaj gönderme izni var mı?
                $canSendMessage = $isPremiumOrAdmin && $isUserActive;
                
                // Eğer izin yoksa tooltip mesajını hazırla
                $tooltipMessage = '';
                if (!$isPremiumOrAdmin) {
                    $tooltipMessage = 'Mesaj gönderme özelliği sadece premium üyeler ve yöneticiler için geçerlidir.';
                } elseif (!$isUserActive) {
                    $tooltipMessage = 'Mesaj gönderebilmek için üyeliğinizin onaylanmış olması gerekmektedir.';
                }
                ?>
                
                <?php if ($canSendMessage): ?>
                <a href="/mesaj-gonder.php?alici=<?php echo $user_id; ?>" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <?php else: ?>
                <div class="relative">
                    <span 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 opacity-50 cursor-not-allowed"
                        title="<?php echo htmlspecialchars($tooltipMessage); ?>">
                <?php endif; ?>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <?php echo t('member_send_message'); ?>
                <?php if (!$canSendMessage): ?>
                    </span>
                    <div class="tooltip hidden absolute bottom-full left-1/2 transform -translate-x-1/2 px-3 py-2 bg-gray-900 text-white text-xs rounded whitespace-nowrap mb-2 z-10">
                        <?php echo htmlspecialchars($tooltipMessage); ?>
                        <div class="tooltip-arrow absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                    </div>
                </div>
                <script>
                    // Tooltip gösterme/gizleme işlemleri
                    document.addEventListener('DOMContentLoaded', function() {
                        const tooltipTrigger = document.querySelector('.cursor-not-allowed');
                        const tooltip = document.querySelector('.tooltip');
                        
                        if (tooltipTrigger && tooltip) {
                            tooltipTrigger.addEventListener('mouseenter', function() {
                                tooltip.classList.remove('hidden');
                            });
                            
                            tooltipTrigger.addEventListener('mouseleave', function() {
                                tooltip.classList.add('hidden');
                            });
                        }
                    });
                </script>
                <?php else: ?>
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white dark:bg-[#121212] rounded-lg shadow-lg overflow-hidden">
        <div class="md:flex">
            <!-- Sol Taraf - Profil Bilgileri -->
            <div class="md:w-1/3 p-8 bg-gray-50 dark:bg-[#1e1e1e] border-r border-gray-200 dark:border-[#2d2d2d] text-gray-800 dark:text-white">
                <div class="flex flex-col items-center text-center">
                    <!-- Profil Resmi -->
                    <div class="w-32 h-32 mb-4">
                        <img class="w-full h-full object-cover rounded-full border-4 border-white dark:border-blue-400 shadow-lg" 
                             src="<?php echo getAvatarBase64($avatar); ?>" 
                             alt="<?php echo htmlspecialchars($username); ?>" />
                    </div>
                    
                    <!-- Kullanıcı Adı -->
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <?php echo htmlspecialchars($username); ?>
                        <?php echo getUserStatusHtml(isUserOnline($user_id, $user['last_activity'] ?? null)); ?>
                    </h1>
                    
                    <!-- Kullanıcı Rolü -->
                    <div class="mt-2">
                        <?php if ($role == 'admin'): ?>
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-gray-900 dark:text-gray-300">
                                <svg class="-ml-1 mr-1.5 h-2 w-2 text-red-400 dark:text-gray-600" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                <?php echo t('member_admin'); ?>
                            </span>
                        <?php elseif ($is_premium): ?>
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-gray-900 dark:text-gray-300">
                                <svg class="-ml-1 mr-1.5 h-2 w-2 text-red-400 dark:text-gray-600" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                <?php echo t('member_premium'); ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                <svg class="-ml-1 mr-1.5 h-2 w-2 text-blue-400" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                <?php echo t('member_user'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Üye Bilgileri -->
                    <div class="mt-6 w-full">
                        <div class="text-left">
                            <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4"><?php echo t('member_info'); ?></h2>
                            
                            <div class="flex items-center mb-3">
                                <svg class="h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                                <div>                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo t('member_since'); ?></p>
                                    <p class="text-gray-800 dark:text-gray-200">
                                        <?php 
                                        if ($created_at) {
                                            echo date('d.m.Y', strtotime($created_at)) . ' (' . $membership_days . ' ' . t('days_ago') . ')';
                                        } else {
                                            echo t('unknown');
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center mb-3">
                                <svg class="h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                                <div>                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo t('last_login'); ?></p>
                                    <p class="text-gray-800 dark:text-gray-200">
                                        <?php
                                        if ($last_login) {
                                            echo date('d.m.Y H:i', strtotime($last_login));
                                        } else {
                                            echo t('no_info');
                                        }
                                        ?>
                                    </p></div>
                            </div>
                            
                            <?php if (!empty($social_info['location'])): ?>
                            <div class="flex items-center mb-3">
                                <svg class="h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo t('location'); ?></p>
                                    <p class="text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($social_info['location']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                              <?php
                            // E-posta gösterme mantığını düzelttik
                            $should_show_email = false;
                            $email_to_show = '';
                            
                            // Kullanıcı kendi profilini görüntülüyorsa e-postayı göster
                            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                                $should_show_email = true;
                                
                                // E-postayı al (önce $user dizisinden, sonra SESSION'dan, sonra veritabanından)
                                if (isset($user['email']) && !empty($user['email'])) {
                                    $email_to_show = $user['email'];
                                } elseif (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
                                    $email_to_show = $_SESSION['user_email'];
                                } else {
                                    // Veritabanından tekrar sorgulamayı dene
                                    try {
                                        $email_stmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                                        $email_stmt->execute([$user_id]);
                                        $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($email_result && !empty($email_result['email'])) {
                                            $email_to_show = $email_result['email'];
                                            $_SESSION['user_email'] = $email_to_show; // Session'a kaydet
                                        }
                                    } catch (Exception $e) {
                                        error_log("Üye sayfasında e-posta alma hatası: " . $e->getMessage());
                                    }
                                }
                                
                                // Eğer e-posta hala boşsa, varsayılan bir e-posta oluşturalım
                                if (empty($email_to_show)) {
                                    $email_to_show = $username . '@mail.com';
                                }
                            }
                            
                            if ($should_show_email && !empty($email_to_show)):
                            ?>
                            <div class="flex items-center mb-3">
                                <svg class="h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo t('email'); ?></p>
                                    <p class="text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($email_to_show); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sağ Taraf - Hakkında ve İstatistikler -->
            <div class="md:w-2/3 p-8">
                <!-- Hakkında -->                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4"><?php echo t('about'); ?></h2>
                    <div class="bg-gray-50 dark:bg-[#252525] p-4 rounded-md">
                        <?php if (!empty($social_info['bio'])): ?>
                            <p class="text-gray-700 dark:text-gray-300"><?php echo nl2br(htmlspecialchars($social_info['bio'])); ?></p>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 italic"><?php echo t('no_bio'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- İstatistikler -->                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4"><?php echo t('statistics'); ?></h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Makale Sayısı -->
                        <div class="bg-blue-50 dark:bg-[#2a2a2a] p-5 rounded-lg text-center">
                            <div class="flex justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500 dark:text-blue-400 mb-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-1"><?php echo t('member_total_articles'); ?></h3>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $article_count; ?></p>
                        </div>
                        
                        <!-- Toplam Görüntülenme -->
                        <div class="bg-green-50 dark:bg-[#2a2a2a] p-5 rounded-lg text-center">
                            <div class="flex justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500 dark:text-green-400 mb-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-1"><?php echo t('member_total_views'); ?></h3>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo number_format($total_views); ?></p>
                        </div>
                          <!-- Üyelik Süresi -->
                        <div class="bg-purple-50 dark:bg-[#2a2a2a] p-5 rounded-lg text-center">
                            <div class="flex justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500 dark:text-purple-400 mb-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-1"><?php echo t('membership_duration'); ?></h3>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo $membership_days; ?> <?php echo t('days'); ?></p>
                        </div>
                    </div>
                </div>
                  <!-- Sosyal Medya -->
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4"><?php echo t('social_media'); ?></h2>
                    <div class="flex flex-wrap gap-3">
                        <?php if (!empty($social_info['website'])): ?>
                            <a href="<?php echo htmlspecialchars($social_info['website']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-blue-100 dark:bg-[#2a2a2a] hover:bg-blue-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
                                </svg>
                                <?php echo t('website'); ?>
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['twitter'])): ?>
                            <a href="https://x.com/<?php echo htmlspecialchars($social_info['twitter']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-blue-100 dark:bg-[#2a2a2a] hover:bg-blue-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" />
                                </svg>
                                Twitter
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['facebook'])): ?>
                            <a href="https://facebook.com/<?php echo htmlspecialchars($social_info['facebook']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-blue-100 dark:bg-[#2a2a2a] hover:bg-blue-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                                Facebook
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['instagram'])): ?>
                            <a href="https://instagram.com/<?php echo htmlspecialchars($social_info['instagram']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-pink-100 dark:bg-[#2a2a2a] hover:bg-pink-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2 text-pink-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                </svg>
                                Instagram
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['linkedin'])): ?>
                            <a href="https://tr.linkedin.com/<?php echo htmlspecialchars($social_info['linkedin']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-blue-100 dark:bg-[#2a2a2a] hover:bg-blue-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2 text-blue-700 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                                </svg>
                                LinkedIn
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['youtube'])): ?>
                            <a href="https://www.youtube.com/@<?php echo htmlspecialchars($social_info['youtube']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-red-100 dark:bg-[#2a2a2a] hover:bg-red-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
                                </svg>
                                YouTube
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['tiktok'])): ?>
                            <a href="https://tiktok.com/@<?php echo htmlspecialchars($social_info['tiktok']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-black dark:bg-[#2a2a2a] text-white hover:bg-gray-800 dark:hover:bg-[#333333]">
                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" />
                                </svg>
                                TikTok
                            </a>
                        <?php endif; ?>
                          <?php if (!empty($social_info['github'])): ?>
                            <a href="https://github.com/<?php echo htmlspecialchars($social_info['github']); ?>" target="_blank" class="flex items-center px-4 py-2 rounded-full bg-blue-100 dark:bg-[#2a2a2a] hover:bg-blue-200 dark:hover:bg-[#333333] text-gray-800 dark:text-gray-200">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
                                </svg>
                                GitHub
                            </a>
                        <?php endif; ?>
                        
                        <?php if (
                            empty($social_info['website']) && 
                            empty($social_info['twitter']) && 
                            empty($social_info['facebook']) && 
                            empty($social_info['instagram']) && 
                            empty($social_info['linkedin']) && 
                            empty($social_info['youtube']) && 
                            empty($social_info['tiktok']) && 
                            empty($social_info['github'])
                        ): ?>
                            <p class="text-gray-500 dark:text-gray-400 italic"><?php echo t('no_social_media'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>      <!-- Kullanıcının Son Makaleleri -->    <div class="mt-8">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4"><?php echo sprintf(t('articles_by_user'), htmlspecialchars($username)); ?></h2>
        <?php
        // Kullanıcının son 5 makalesini getir
        $article_list_stmt = $db->prepare("
            SELECT a.*, c.name as category_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.author_id = ? AND a.status = 'published'
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $article_list_stmt->execute([$user_id]);
        $user_articles = $article_list_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($user_articles) > 0):
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">            <?php foreach ($user_articles as $article): ?>
                <div class="rounded-lg shadow-md overflow-hidden flex flex-col h-full">
                    <?php if (!empty($article['featured_image'])): ?>
                        <?php
                        // Kontrol et: Eğer URL tam bir URL ise doğrudan kullan, değilse uploads/ai_images/ dizinini ekle
                        $imgSrc = (strpos($article['featured_image'], 'http://') === 0 || strpos($article['featured_image'], 'https://') === 0) 
                            ? $article['featured_image'] 
                            : (strpos($article['featured_image'], '/') === 0 
                                ? $article['featured_image'] 
                                : "/uploads/ai_images/" . $article['featured_image']);
                        ?>
                        <img src="<?php echo $imgSrc; ?>" 
                             class="w-full h-48 object-cover" 
                             alt="<?php echo htmlspecialchars($article['title']); ?>">
                    <?php else: ?>
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-8 flex items-center justify-center">
                            <span class="text-white text-4xl"><i class="far fa-file-alt"></i></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-4 bg-white dark:bg-[#1e1e1e] flex flex-col flex-grow">
                        <div class="flex items-center mb-2">
                            <?php if ($article['is_premium']): ?>
                            <span class="mr-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                <span class="mr-1">★</span> <?php echo t('premium'); ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($article['category_name'] ?? 'Kategori Yok'); ?>
                            </span>
                        </div>
                          <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                            <a href="/makale/<?php echo $article['slug']; ?>" class="hover:text-blue-600">
                                <?php echo htmlspecialchars($article['title']); ?>
                            </a>
                        </h3>
                        
                        <p class="text-gray-600 dark:text-gray-300 mb-4">
                            <?php echo mb_substr(strip_tags(html_entity_decode($article['content'], ENT_QUOTES, 'UTF-8')), 0, 100) . '...'; ?>
                        </p>
                        
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <div class="flex items-center">
                                <span class="mr-1"><i class="far fa-calendar-alt"></i></span>
                                <?php echo date('d.m.Y', strtotime($article['created_at'])); ?>
                            </div>
                            <div class="flex items-center">
                                <span class="mr-1"><i class="far fa-eye"></i></span>
                                <?php echo number_format($article['view_count'] ?? 0); ?> <?php echo t('views'); ?>
                            </div>
                        </div>
                        <div class="mt-auto pt-4 flex justify-end">
                            <a href="/makale/<?php echo $article['slug']; ?>" class="inline-block bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200 transition duration-300">
                                <?php echo t('read_more'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>          <?php if (isset($article_count) && $article_count > 5): ?>
            <div class="mt-6 text-center">
                <a href="/makalelerim.php?user=<?php echo $user_id; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    <?php echo t('view_all_articles'); ?> (<?php echo $article_count; ?>)
                </a>
            </div>
        <?php endif; ?>
          <?php else: ?>
            <div class="bg-white dark:bg-[#1e1e1e] p-6 rounded-lg shadow-md">
                <p class="text-gray-600 dark:text-gray-300"><?php echo t('member_no_articles'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Engelleme Modal -->
<div id="blockUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex justify-between items-center pb-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    <?php echo t('block_user'); ?>
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="document.getElementById('blockUserModal').classList.add('hidden');">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form action="/engellenen-kullanicilar.php?action=block&id=<?php echo $user_id; ?>" method="post">
                <div class="mt-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <?php echo sprintf(t('block_user_confirm'), htmlspecialchars($username)); ?>
                    </p>
                    <div class="mt-4">
                        <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            <?php echo t('block_reason'); ?>
                        </label>
                        <textarea id="reason" name="reason" rows="3" 
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" 
                            class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            onclick="document.getElementById('blockUserModal').classList.add('hidden');">
                        <?php echo t('cancel'); ?>
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <?php echo t('block'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; 

// Eğer başlatılmış bir çıktı tamponlama varsa, sonlandır
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
