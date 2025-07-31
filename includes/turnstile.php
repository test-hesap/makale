<?php
/**
 * Cloudflare Turnstile spam koruması için yardımcı fonksiyonlar
 */

/**
 * Cloudflare Turnstile'ın etkin olup olmadığını kontrol eder
 * 
 * @param string $form_type Form tipi (login, register, contact, article)
 * @return bool
 */
function isTurnstileEnabled($form_type = null) {
    global $db;
    
    // Ayarları veritabanından al
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute(['turnstile_enabled']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $enabled = isset($result['value']) ? $result['value'] : '0';
    
    // Eğer Turnstile genel olarak etkin değilse, false döndür
    if ($enabled != '1') {
        return false;
    }
    
    // Eğer form tipi belirtilmişse, o form için etkin mi kontrol et
    if ($form_type) {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute(['turnstile_' . $form_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $form_enabled = isset($result['value']) ? $result['value'] : '1'; // Varsayılan olarak etkin
        
        return ($form_enabled == '1');
    }
    
    return true;
}

/**
 * Cloudflare Turnstile HTML kodunu oluşturur
 * 
 * @return string HTML kodu
 */
function turnstileWidget() {
    global $db;
    
    // Site key'i veritabanından al
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute(['turnstile_site_key']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $site_key = isset($result['value']) ? $result['value'] : '';
    
    // Tema ayarını al
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute(['turnstile_theme']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $theme = isset($result['value']) ? $result['value'] : 'auto';
    
    if (empty($site_key)) {
        return '<div class="text-red-500 my-2">Turnstile site anahtarı ayarlanmamış!</div>';
    }
    
    // Eğer tema "auto" ise, sitenin mevcut temasına göre ayarla
    if ($theme === 'auto') {
        // JavaScript ile mevcut temayı algılayıp widget'ı güncelleyecek kod
        $output = '<div class="cf-turnstile my-4" data-sitekey="' . htmlspecialchars($site_key) . '" id="dynamic-turnstile"></div>';
        $output .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Mevcut tema kontrolü
                const isDarkMode = document.documentElement.classList.contains("dark") || 
                                  localStorage.getItem("theme") === "dark" ||
                                  (!localStorage.getItem("theme") && window.matchMedia("(prefers-color-scheme: dark)").matches);
                
                // Turnstile widget\'ini güncelle
                const turnstileElement = document.getElementById("dynamic-turnstile");
                if (turnstileElement) {
                    turnstileElement.setAttribute("data-theme", isDarkMode ? "dark" : "light");
                }
                
                // Tema değişikliğini dinle
                const themeToggleBtn = document.getElementById("theme-toggle");
                const themeToggleMobileBtn = document.getElementById("theme-toggle-mobile");
                
                if (themeToggleBtn) {
                    themeToggleBtn.addEventListener("click", updateTurnstileTheme);
                }
                
                if (themeToggleMobileBtn) {
                    themeToggleMobileBtn.addEventListener("click", updateTurnstileTheme);
                }
                
                function updateTurnstileTheme() {
                    setTimeout(function() {
                        const isDarkMode = document.documentElement.classList.contains("dark");
                        const turnstileElement = document.getElementById("dynamic-turnstile");
                        if (turnstileElement) {
                            turnstileElement.setAttribute("data-theme", isDarkMode ? "dark" : "light");
                            // Turnstile widget\'ini yeniden oluştur
                            if (window.turnstile && typeof window.turnstile.render === "function") {
                                window.turnstile.remove("#dynamic-turnstile");
                                window.turnstile.render("#dynamic-turnstile");
                            }
                        }
                    }, 100);
                }
            });
        </script>';
        $output .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    } else {
        // Normal widget oluştur
        $output = '<div class="cf-turnstile my-4" data-sitekey="' . htmlspecialchars($site_key) . '" data-theme="' . htmlspecialchars($theme) . '"></div>';
        $output .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
    
    return $output;
}

/**
 * Cloudflare Turnstile doğrulamasını yapar
 * 
 * @param string $token Turnstile token değeri
 * @return bool Doğrulama başarılı mı
 */
function verifyTurnstile($token) {
    global $db;
    
    // Secret key'i veritabanından al
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute(['turnstile_secret_key']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $secret_key = isset($result['value']) ? $result['value'] : '';
    
    if (empty($secret_key) || empty($token)) {
        return false;
    }
    
    // Cloudflare Turnstile API'ye istek gönder
    $data = [
        'secret' => $secret_key,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    
    if ($response === false) {
        error_log('Turnstile doğrulama hatası: API\'ye erişilemedi.');
        return false;
    }
    
    $result = json_decode($response, true);
    
    // Hata durumunda loglama yap
    if (!isset($result['success']) || $result['success'] !== true) {
        $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'Bilinmeyen hata';
        error_log('Turnstile doğrulama başarısız: ' . $error_codes);
        return false;
    }
    
    return true;
} 