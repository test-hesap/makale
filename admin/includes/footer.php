<?php
// Admin paneli footer template'i

// Varsa açık olan HTML etiketlerini kapat
?>

<!-- Admin Footer -->
<footer class="bg-white dark:bg-gray-800 shadow mt-6 py-4 w-full">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <p class="text-center text-gray-600 dark:text-gray-300 text-sm mb-2 md:mb-0">
                <?php echo sprintf(t('admin_footer_copyright'), date('Y'), getSetting('site_title', 'Blog')); ?>
            </p>
            <div class="flex items-center space-x-4">
                <span class="text-xs text-gray-500 dark:text-gray-400">v1.0.0</span>
            </div>
        </div>
    </div>
</footer>
</div> <!-- .content-wrapper sonu -->

<!-- Ek script'ler buraya eklenebilir -->
<script>
    // Tema değiştirme fonksiyonu
    function toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            // Karanlık temadan aydınlık temaya geçiş
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
            document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
        } else {
            // Aydınlık temadan karanlık temaya geçiş
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            document.getElementById('theme-toggle-light-icon').classList.add('hidden');
            document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
        }
    }
    
    // DOM yüklendiğinde çalışacak kodlar
    document.addEventListener('DOMContentLoaded', function() {
        // Tema değiştirme butonunu ayarla
        const themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }
        
        // Kullanıcının tema tercihini al
        const userTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Tema tercihine göre ayarla
        if (userTheme === 'dark' || (!userTheme && systemPrefersDark)) {
            document.documentElement.classList.add('dark');
            document.getElementById('theme-toggle-light-icon').classList.add('hidden');
            document.getElementById('theme-toggle-dark-icon').classList.remove('hidden');
        } else {
            document.documentElement.classList.remove('dark');
            document.getElementById('theme-toggle-dark-icon').classList.add('hidden');
            document.getElementById('theme-toggle-light-icon').classList.remove('hidden');
        }    });
    
    // Mobil menü kontrolü - tıklandığında sidebar'ı kapat
    document.addEventListener('click', function(e) {
        // Alpine.js sidebar state'i dışarıdan erişim için
        const alpineData = document.querySelector('[x-data]').__x.$data;
        
        // Eğer sidebar açıksa ve tıklanan öğe sidebar veya hamburger menüsü değilse, sidebar'ı kapat
        if (alpineData.sidebarOpen && 
            !e.target.closest('aside') && 
            !e.target.closest('button[class*="fa-bars"]')) {
            alpineData.sidebarOpen = false;
        }
    });
    
    // Şifre görünürlüğünü değiştiren fonksiyon
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const showIcon = document.getElementById(inputId + '_show');
        const hideIcon = document.getElementById(inputId + '_hide');
        
        if (input.type === 'password') {
            input.type = 'text';
            showIcon.classList.add('hidden');
            hideIcon.classList.remove('hidden');
        } else {
            input.type = 'password';
            hideIcon.classList.add('hidden');
            showIcon.classList.remove('hidden');
        }
    }
</script>
