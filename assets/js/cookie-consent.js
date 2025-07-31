/**
 * Cookie Consent Manager
 * 
 * Bu script, site çerez bildirimini görüntüler ve kullanıcı tercihlerini yönetir
 */
document.addEventListener('DOMContentLoaded', function() {
    // Kullanıcı daha önce çerezleri kabul ettiyse gösterme
    if (localStorage.getItem('cookieConsent') === 'true') {
        return;
    }
    
    // Ayarları al
    const cookieSettings = window.cookieSettings || {};
    const position = cookieSettings.position || 'bottom';
    const text = cookieSettings.text || 'Bu web sitesi, en iyi deneyimi sunmak için çerezleri kullanır.';
    const buttonText = cookieSettings.buttonText || 'Kabul Et';
    const bgColor = cookieSettings.bgColor || '#f3f4f6';
    const textColor = cookieSettings.textColor || '#1f2937';
    const buttonColor = cookieSettings.buttonColor || '#3b82f6';
    const buttonTextColor = cookieSettings.buttonTextColor || '#ffffff';
    
    // Bildirim elemanını oluştur
    const consentElement = document.createElement('div');
    consentElement.className = `cookie-consent ${position}`;
    consentElement.id = 'cookie-consent';
    consentElement.style.backgroundColor = bgColor;
    consentElement.style.color = textColor;
    
    // İçeriği oluştur
    // HTML etiketlerini (özellikle link etiketlerini) koruyan bir yaklaşım kullanıyoruz
    const textDiv = document.createElement('div');
    textDiv.className = 'cookie-consent-text';
    textDiv.innerHTML = text; // HTML içeriği ile birlikte

    const button = document.createElement('button');
    button.id = 'cookie-consent-button';
    button.className = 'cookie-consent-button';
    button.style.backgroundColor = buttonColor;
    button.style.color = buttonTextColor;
    button.textContent = buttonText;
    
    consentElement.appendChild(textDiv);
    consentElement.appendChild(button);
    
    // Elemantı sayfaya ekle
    document.body.appendChild(consentElement);
    
    // Buton tıklama olayını dinle
    button.addEventListener('click', function() {
        // Kullanıcının kararını kaydet
        localStorage.setItem('cookieConsent', 'true');
        
        // Bildirimi gizle
        consentElement.style.display = 'none';
        
        // Özel bir olay tetikle - diğer scriptler tarafından dinlenebilir
        document.dispatchEvent(new Event('cookieConsentAccepted'));
    });
});
