<?php
require_once 'includes/config.php';

// Bakım modu ayarlarını getir
function getMaintenanceSettings() {
    global $db;
    $settings = [];
    try {
        $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('maintenance_mode', 'maintenance_title', 'maintenance_message', 'maintenance_title_en', 'maintenance_message_en', 'maintenance_end_time', 'maintenance_countdown_enabled', 'maintenance_contact_email')");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        // Hata durumunda varsayılan değerler döndür
    }
    return $settings;
}

$maintenance_settings = getMaintenanceSettings();
$maintenance_mode = $maintenance_settings['maintenance_mode'] ?? '0';

// Dil dosyasını dahil et
$current_lang = $_SESSION['lang'] ?? 'tr';
require_once "includes/lang/{$current_lang}.php";

// Dil bazlı başlık ve mesaj seçimi
if ($current_lang === 'en') {
    $maintenance_title = $maintenance_settings['maintenance_title_en'] ?? $maintenance_settings['maintenance_title'] ?? $lang['maintenance_title'];
    $maintenance_message = $maintenance_settings['maintenance_message_en'] ?? $maintenance_settings['maintenance_message'] ?? $lang['maintenance_message'];
} else {
    $maintenance_title = $maintenance_settings['maintenance_title'] ?? $lang['maintenance_title'];
    $maintenance_message = $maintenance_settings['maintenance_message'] ?? $lang['maintenance_message'];
}
$maintenance_end_time = $maintenance_settings['maintenance_end_time'] ?? '';
$maintenance_countdown_enabled = $maintenance_settings['maintenance_countdown_enabled'] ?? '1';
$maintenance_contact_email = $maintenance_settings['maintenance_contact_email'] ?? 'info@' . $_SERVER['HTTP_HOST'];

// Admin kullanıcısı ise normal siteye yönlendir
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: /');
    exit;
}

// Bakım modu kapalıysa ana sayfaya yönlendir
if ($maintenance_mode !== '1') {
    header('Location: /');
    exit;
}

// Site ayarlarını al
function getSiteSettings() {
    global $db;
    $settings = [];
    try {
        $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('site_title', 'site_logo', 'site_logo_dark')");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        // Hata durumunda varsayılan değerler
    }
    return $settings;
}

$site_settings = getSiteSettings();
$site_title = $site_settings['site_title'] ?? 'Makale Sitesi';
$site_logo = $site_settings['site_logo'] ?? '/assets/logo.png';
$site_logo_dark = $site_settings['site_logo_dark'] ?? '/assets/logo1.png';

// Geri sayım süresini hesapla
$countdown_active = false;
$countdown_timestamp = 0;
if ($maintenance_countdown_enabled === '1' && !empty($maintenance_end_time)) {
    $end_time = new DateTime($maintenance_end_time);
    $now = new DateTime();
    if ($end_time > $now) {
        $countdown_active = true;
        $countdown_timestamp = $end_time->getTimestamp() * 1000; // JavaScript için milisaniye
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($maintenance_title); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="<?php echo htmlspecialchars($maintenance_message); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/uploads/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/uploads/favicon/favicon-16x16.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 1s ease-in-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'bounce-slow': 'bounce 2s infinite',
                        'spin-slow': 'spin 3s linear infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .dark .glass-effect {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            opacity: 0.7;
            animation: particle 10s linear infinite;
        }
        
        @keyframes particle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.7;
            }
            90% {
                opacity: 0.7;
            }
            100% {
                transform: translateY(-10vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .toast {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: toastSlideIn 0.4s ease-out;
            min-width: 300px;
            max-width: 400px;
        }
        
        .toast.success {
            border-left: 4px solid #10b981;
        }
        
        .toast.error {
            border-left: 4px solid #ef4444;
        }
        
        .toast.warning {
            border-left: 4px solid #f59e0b;
        }
        
        .toast.info {
            border-left: 4px solid #3b82f6;
        }
        
        .toast-message {
            color: #1f2937;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .toast-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
        }
        
        .toast.success .toast-icon {
            background: #10b981;
        }
        
        .toast.error .toast-icon {
            background: #ef4444;
        }
        
        .toast.warning .toast-icon {
            background: #f59e0b;
        }
        
        .toast.info .toast-icon {
            background: #3b82f6;
        }
        
        @keyframes toastSlideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes toastSlideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg overflow-hidden">
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>
    
    <!-- Animated Particles -->
    <div id="particles-container" class="fixed inset-0 z-0"></div>
    
    <!-- Dark Mode Toggle -->
    <div class="fixed top-4 right-4 z-50">
        <button id="darkModeToggle" class="p-2 rounded-full glass-effect text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300">
            <i class="fas fa-moon text-lg"></i>
        </button>
    </div>
    
    <div class="min-h-screen flex items-center justify-center p-4 relative z-10">
        <div class="max-w-2xl w-full text-center animate-fade-in">
            <!-- Logo -->
            <div class="mb-8 animate-slide-up">
                <img src="<?php echo htmlspecialchars($site_logo); ?>" 
                     alt="<?php echo htmlspecialchars($site_title); ?>" 
                     class="h-16 w-auto mx-auto floating dark:hidden"
                     onerror="this.style.display='none'">
                <img src="<?php echo htmlspecialchars($site_logo_dark); ?>" 
                     alt="<?php echo htmlspecialchars($site_title); ?>" 
                     class="h-16 w-auto mx-auto floating hidden dark:block"
                     onerror="this.style.display='none'">
            </div>
            
            <!-- Main Card -->
            <div class="glass-effect rounded-2xl p-8 mb-8 animate-slide-up">
                <!-- Maintenance Icon -->
                <div class="mb-6">
                    <div class="w-24 h-24 mx-auto bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-tools text-4xl text-white animate-bounce-slow"></i>
                    </div>
                </div>
                
                <!-- Title -->
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
                    <?php echo htmlspecialchars($maintenance_title); ?>
                </h1>
                
                <!-- Message -->
                <p class="text-xl text-white text-opacity-90 mb-8 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($maintenance_message)); ?>
                </p>
                
                <!-- Countdown -->
                <?php if ($countdown_active): ?>
                <div class="mb-8">
                    <div class="text-white text-opacity-80 mb-4">
                        <i class="fas fa-clock mr-2"></i>
                        <?php echo $lang['maintenance_estimated_end_time']; ?>
                    </div>
                    <div id="countdown" class="grid grid-cols-4 gap-4 max-w-md mx-auto">
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <div id="days" class="text-2xl md:text-3xl font-bold text-white">00</div>
                            <div class="text-sm text-white text-opacity-80"><?php echo $lang['maintenance_days']; ?></div>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <div id="hours" class="text-2xl md:text-3xl font-bold text-white">00</div>
                            <div class="text-sm text-white text-opacity-80"><?php echo $lang['maintenance_hours']; ?></div>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <div id="minutes" class="text-2xl md:text-3xl font-bold text-white">00</div>
                            <div class="text-sm text-white text-opacity-80"><?php echo $lang['maintenance_minutes']; ?></div>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <div id="seconds" class="text-2xl md:text-3xl font-bold text-white">00</div>
                            <div class="text-sm text-white text-opacity-80"><?php echo $lang['maintenance_seconds']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Progress Bar -->
                <div class="mb-6">
                    <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                        <div id="progress-bar" class="bg-white h-2 rounded-full transition-all duration-1000 animate-pulse-slow" style="width: 65%"></div>
                    </div>
                </div>
                
                <!-- Social Links -->
                <div class="flex justify-center space-x-4">
                    <a href="mailto:<?php echo htmlspecialchars($maintenance_contact_email); ?>" class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-white hover:bg-opacity-30 transition-all duration-300 transform hover:scale-110">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <button onclick="checkMaintenanceStatus()" class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-white hover:bg-opacity-30 transition-all duration-300 transform hover:scale-110" title="<?php echo $lang['maintenance_check_status']; ?>">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button onclick="window.location.reload()" class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-white hover:bg-opacity-30 transition-all duration-300 transform hover:scale-110" title="<?php echo $lang['maintenance_refresh_page']; ?>">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-white text-opacity-60 text-sm">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_title); ?>. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </div>

    <script>
        // Dil çevirileri
        const lang = {
            checkingStatus: '<?php echo addslashes($lang['maintenance_checking_status']); ?>...',
            maintenanceEnded: '<?php echo addslashes($lang['maintenance_ended']); ?>...',
            maintenanceStillActive: '<?php echo addslashes($lang['maintenance_still_active']); ?>.',
            statusCheckFailed: '<?php echo addslashes($lang['maintenance_status_check_failed']); ?>.',
            maintenanceCompleted: '<?php echo addslashes($lang['maintenance_completed']); ?>...'
        };
        
        // Toast Notification System
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            toast.innerHTML = `
                <div class="toast-message">
                    <div class="toast-icon">${icons[type] || icons.info}</div>
                    ${message}
                </div>
            `;
            
            container.appendChild(toast);
            
            // Auto remove
            setTimeout(() => {
                toast.style.animation = 'toastSlideOut 0.4s ease-in forwards';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 400);
            }, duration);
        }

        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;
        
        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            html.classList.add('dark');
            darkModeToggle.innerHTML = '<i class="fas fa-sun text-lg"></i>';
        }
        
        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            const isDark = html.classList.contains('dark');
            localStorage.setItem('darkMode', isDark);
            darkModeToggle.innerHTML = isDark ? '<i class="fas fa-sun text-lg"></i>' : '<i class="fas fa-moon text-lg"></i>';
        });

        // Countdown Timer
        <?php if ($countdown_active): ?>
        const countdownDate = <?php echo $countdown_timestamp; ?>;
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = countdownDate - now;
            
            if (distance < 0) {
                // Süre doldu, hemen kontrol et
                console.log('Geri sayım bitti, bakım durumu kontrol ediliyor...');
                fetch('/maintenance_check.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.maintenance_mode === '0') {
                            // Sessizce ana sayfaya yönlendir
                            console.log('Bakım süresi doldu, ana sayfaya yönlendiriliyor...');
                            window.location.href = '/';
                        } else {
                            // Hala aktifse sayfayı yenile
                            window.location.reload();
                        }
                    })
                    .catch(() => {
                        window.location.reload();
                    });
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('days').textContent = days.toString().padStart(2, '0');
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            
            // Progress bar güncelle (yaklaşık olarak)
            const totalTime = 24 * 60 * 60 * 1000; // 24 saat (milisaniye)
            const elapsed = totalTime - distance;
            const progress = Math.min((elapsed / totalTime) * 100, 100);
            document.getElementById('progress-bar').style.width = progress + '%';
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // Particle Animation
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            
            const size = Math.random() * 6 + 2;
            const startPosition = Math.random() * window.innerWidth;
            const animationDuration = Math.random() * 10 + 10;
            const opacity = Math.random() * 0.5 + 0.2;
            
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = startPosition + 'px';
            particle.style.background = `rgba(255, 255, 255, ${opacity})`;
            particle.style.animationDuration = animationDuration + 's';
            particle.style.animationDelay = Math.random() * 2 + 's';
            
            document.getElementById('particles-container').appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, animationDuration * 1000);
        }
        
        // Create particles periodically
        setInterval(createParticle, 300);
        
        // Initial particles
        for (let i = 0; i < 10; i++) {
            setTimeout(createParticle, i * 100);
        }

        // Manuel bakım durumu kontrolü
        function checkMaintenanceStatus() {
            console.log('Manuel bakım durumu kontrol ediliyor...');
            
            // Loading göster
            showToast(lang.checkingStatus, 'info');
            
            fetch('/maintenance_check.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Bakım durumu yanıtı:', data);
                    if (data.maintenance_mode === '0') {
                        showToast(lang.maintenanceEnded, 'success');
                        setTimeout(() => {
                            window.location.href = '/';
                        }, 1500);
                    } else {
                        showToast(lang.maintenanceStillActive, 'warning');
                    }
                })
                .catch(error => {
                    console.error('Bakım durumu kontrol hatası:', error);
                    showToast(lang.statusCheckFailed, 'error');
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 2000);
                });
        }

        // Auto-refresh page every 15 seconds to check maintenance status (daha sık kontrol)
        setInterval(() => {
            fetch('/maintenance_check.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Otomatik bakım durumu kontrolü:', data);
                    if (data.maintenance_mode === '0') {
                        console.log('Bakım modu kapandı, ana sayfaya yönlendiriliyor...');
                        showToast(lang.maintenanceCompleted, 'success', 2000);
                        setTimeout(() => {
                            window.location.href = '/';
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Bakım durumu API hatası:', error);
                    // Hata durumunda ana sayfayı dene
                    fetch('/')
                        .then(response => {
                            if (response.ok) {
                                window.location.href = '/';
                            }
                        })
                        .catch(() => {
                            // API ve ana sayfa da çalışmıyorsa sayfayı yenile
                            window.location.reload();
                        });
                });
        }, 15000); // 15 saniyede bir kontrol et (daha agresif)

        // İlk yüklemede de kontrol et
        setTimeout(() => {
            fetch('/maintenance_check.php')
                .then(response => response.json())
                .then(data => {
                    if (data.maintenance_mode === '0') {
                        window.location.href = '/';
                    }
                })
                .catch(() => {
                    // API çalışmıyorsa ana sayfayı dene
                    fetch('/')
                        .then(response => {
                            if (response.ok) {
                                window.location.href = '/';
                            }
                        })
                        .catch(() => {
                            // Hiçbir şey yapma, bakım sayfasında kal
                        });
                });
        }, 2000);
    </script>
</body>
</html>
