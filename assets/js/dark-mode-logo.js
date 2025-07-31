document.addEventListener('DOMContentLoaded', function() {
    // Koyu mod durumunu kontrol et
    function checkDarkMode() {
        // HTML elementine 'dark' sınıfı eklenip eklenmediğini kontrol et
        const isDarkMode = document.documentElement.classList.contains('dark');
        
        // Logoları bul
        const lightModeLogos = document.querySelectorAll('.light-mode-logo');
        const darkModeLogos = document.querySelectorAll('.dark-mode-logo');
        
        // Dark mode aktifse koyu mod logosunu göster, değilse açık mod logosunu göster
        if (isDarkMode) {
            lightModeLogos.forEach(logo => logo.classList.add('hidden'));
            darkModeLogos.forEach(logo => logo.classList.remove('hidden'));
        } else {
            lightModeLogos.forEach(logo => logo.classList.remove('hidden'));
            darkModeLogos.forEach(logo => logo.classList.add('hidden'));
        }
    }
    
    // Sayfa yüklendiğinde çalıştır
    checkDarkMode();
    
    // Tema değişikliğini izlemek için bir MutationObserver oluştur
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                checkDarkMode();
            }
        });
    });
    
    // HTML elementini gözlemle
    observer.observe(document.documentElement, { attributes: true });
    
    // Koyu mod düğmesine tıklandığında logoları güncelle
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', checkDarkMode);
    }
});
