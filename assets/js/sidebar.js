document.addEventListener('DOMContentLoaded', function() {
    // Tüm açılır-kapanır sidebar başlıklarını seç
    const collapsibleHeaders = document.querySelectorAll('.collapsible-sidebar-header');
    
    // Her başlığa tıklama olayı ekle
    collapsibleHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // İlgili içerik bölümünü bul
            const content = this.nextElementSibling;
            
            // İçeriği aç veya kapat
            content.classList.toggle('open');
            
            // İkon rotasyonunu değiştir
            const icon = this.querySelector('.rotate-icon');
            if (icon) {
                icon.classList.toggle('open');
            }
            
            // Kullanıcı tercihini localStorage'a kaydet
            const sidebarId = this.getAttribute('data-sidebar-id');
            if (sidebarId) {
                localStorage.setItem('sidebar_' + sidebarId, content.classList.contains('open') ? 'open' : 'closed');
            }
        });
    });
    
    // Sayfa yüklendiğinde localStorage'dan tercihleri oku ve uygula
    collapsibleHeaders.forEach(header => {
        const sidebarId = header.getAttribute('data-sidebar-id');
        if (sidebarId) {
            const savedState = localStorage.getItem('sidebar_' + sidebarId);
            const content = header.nextElementSibling;
            const icon = header.querySelector('.rotate-icon');
            
            if (savedState === 'open') {
                content.classList.add('open');
                if (icon) {
                    icon.classList.add('open');
                }
            } else if (savedState === 'closed') {
                content.classList.remove('open');
                if (icon) {
                    icon.classList.remove('open');
                }
            } else {
                // Varsayılan olarak açık göster
                content.classList.add('open');
                if (icon) {
                    icon.classList.add('open');
                }
            }
        }
    });
});
