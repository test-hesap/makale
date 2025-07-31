<?php
require_once '../includes/config.php';
checkAuth(true); // true parametresi admin kontrolü için

// Ödeme yöntemlerini al
$payment_methods_json = getSetting('payment_methods', '');
$payment_methods = !empty($payment_methods_json) ? json_decode($payment_methods_json, true) : [];

// Varsayılan değerleri ayarla
if (empty($payment_methods)) {
    $payment_methods = [
        'paytr' => ['active' => true, 'name' => 'PayTR'],
        'iyzico' => ['active' => true, 'name' => 'iyzico']
    ];
}

// PayTR ayarlarını al
$paytr_merchant_id = getSetting('paytr_merchant_id', '');
$paytr_merchant_key = getSetting('paytr_merchant_key', '');
$paytr_merchant_salt = getSetting('paytr_merchant_salt', '');
$paytr_test_mode = getSetting('paytr_test_mode', '1');

// iyzico ayarlarını al
$iyzico_api_key = getSetting('iyzico_api_key', '');
$iyzico_secret_key = getSetting('iyzico_secret_key', '');
$iyzico_base_url = getSetting('iyzico_base_url', 'https://sandbox-api.iyzipay.com');

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Transaction başlat
        $db->beginTransaction();
        
        // Ödeme yöntemleri ayarları
        $payment_methods = [
            'paytr' => [
                'active' => isset($_POST['paytr_active']) ? 1 : 0,
                'name' => 'PayTR'
            ],
            'iyzico' => [
                'active' => isset($_POST['iyzico_active']) ? 1 : 0,
                'name' => 'iyzico'
            ]
        ];
        
        // Ödeme yöntemleri ayarlarını güncelle
        $update_payment_methods = $db->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES ('payment_methods', ?) 
            ON DUPLICATE KEY UPDATE `value` = ?
        ");
        $payment_methods_json = json_encode($payment_methods);
        $update_payment_methods->execute([$payment_methods_json, $payment_methods_json]);
        
        // PayTR ayarlarını güncelle
        $paytr_settings = [
            'paytr_merchant_id' => $_POST['paytr_merchant_id'] ?? '',
            'paytr_merchant_key' => $_POST['paytr_merchant_key'] ?? '',
            'paytr_merchant_salt' => $_POST['paytr_merchant_salt'] ?? '',
            'paytr_test_mode' => isset($_POST['paytr_test_mode']) ? '1' : '0'
        ];
        
        foreach ($paytr_settings as $key => $value) {
            $update_setting = $db->prepare("
                INSERT INTO settings (`key`, `value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = ?
            ");
            $update_setting->execute([$key, $value, $value]);
        }
        
        // iyzico ayarlarını güncelle
        $iyzico_settings = [
            'iyzico_api_key' => $_POST['iyzico_api_key'] ?? '',
            'iyzico_secret_key' => $_POST['iyzico_secret_key'] ?? '',
            'iyzico_base_url' => $_POST['iyzico_base_url'] ?? 'https://sandbox-api.iyzipay.com'
        ];
        
        foreach ($iyzico_settings as $key => $value) {
            $update_setting = $db->prepare("
                INSERT INTO settings (`key`, `value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = ?
            ");
            $update_setting->execute([$key, $value, $value]);
        }
        
        // Transaction'ı tamamla
        $db->commit();
        
        // Değerleri güncelle
        $paytr_merchant_id = $_POST['paytr_merchant_id'] ?? '';
        $paytr_merchant_key = $_POST['paytr_merchant_key'] ?? '';
        $paytr_merchant_salt = $_POST['paytr_merchant_salt'] ?? '';
        $paytr_test_mode = isset($_POST['paytr_test_mode']) ? '1' : '0';
        
        $iyzico_api_key = $_POST['iyzico_api_key'] ?? '';
        $iyzico_secret_key = $_POST['iyzico_secret_key'] ?? '';
        $iyzico_base_url = $_POST['iyzico_base_url'] ?? 'https://sandbox-api.iyzipay.com';
        
        $_SESSION['success_message'] = getActiveLang() == 'en' ? "Payment method settings updated successfully." : "Ödeme yöntemi ayarları başarıyla güncellendi.";
    } catch (PDOException $e) {
        // Hata durumunda transaction'ı geri al
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        $_SESSION['error_message'] = getActiveLang() == 'en' ? "An error occurred while updating settings: " : "Ayarlar güncellenirken bir hata oluştu: " . $e->getMessage();
        error_log("Ödeme ayarları güncelleme hatası: " . $e->getMessage());
    }
}

$page_title = getActiveLang() == 'en' ? "Payment Method Settings - Admin Panel" : "Ödeme Yöntemi Ayarları - Admin Paneli";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; ?></p>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo getActiveLang() == 'en' ? 'Payment Method Settings' : 'Ödeme Yöntemi Ayarları'; ?></h1>
        </div>
        
        <form method="POST" class="space-y-6">
            <!-- Ödeme Yöntemleri -->
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo getActiveLang() == 'en' ? 'Active Payment Methods' : 'Aktif Ödeme Yöntemleri'; ?></h2>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input id="paytr_active" name="paytr_active" type="checkbox" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo isset($payment_methods['paytr']) && $payment_methods['paytr']['active'] ? 'checked' : ''; ?>>
                        <label for="paytr_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            <?php echo getActiveLang() == 'en' ? 'PayTR (Credit Card, Bank Card)' : 'PayTR (Kredi Kartı, Banka Kartı)'; ?>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input id="iyzico_active" name="iyzico_active" type="checkbox" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo isset($payment_methods['iyzico']) && $payment_methods['iyzico']['active'] ? 'checked' : ''; ?>>
                        <label for="iyzico_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            iyzico <?php echo getActiveLang() == 'en' ? '(Credit Card, Bank Card)' : '(Kredi Kartı, Banka Kartı)'; ?>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- PayTR Ayarları -->
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo getActiveLang() == 'en' ? 'PayTR Settings' : 'PayTR Ayarları'; ?></h2>
                
                <div class="space-y-4">
                    <div>
                        <label for="paytr_merchant_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Merchant ID
                        </label>
                        <div class="relative">
                            <input type="password" id="paytr_merchant_id" name="paytr_merchant_id" value="<?php echo htmlspecialchars($paytr_merchant_id); ?>" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <button type="button" onclick="togglePasswordVisibility('paytr_merchant_id')" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="paytr_merchant_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Merchant Key
                        </label>
                        <div class="relative">
                            <input type="password" id="paytr_merchant_key" name="paytr_merchant_key" value="<?php echo htmlspecialchars($paytr_merchant_key); ?>" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <button type="button" onclick="togglePasswordVisibility('paytr_merchant_key')" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="paytr_merchant_salt" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Merchant Salt
                        </label>
                        <div class="relative">
                            <input type="password" id="paytr_merchant_salt" name="paytr_merchant_salt" value="<?php echo htmlspecialchars($paytr_merchant_salt); ?>" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <button type="button" onclick="togglePasswordVisibility('paytr_merchant_salt')" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input id="paytr_test_mode" name="paytr_test_mode" type="checkbox" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo $paytr_test_mode === '1' ? 'checked' : ''; ?>>
                        <label for="paytr_test_mode" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            <?php echo getActiveLang() == 'en' ? 'Test Mode (No real payment is processed when active)' : 'Test Modu (Aktif olduğunda gerçek ödeme alınmaz)'; ?>
                        </label>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900 text-blue-800 dark:text-blue-200 p-4 rounded-lg text-sm">
                        <p class="mb-2"><strong><?php echo getActiveLang() == 'en' ? 'Important:' : 'Önemli:'; ?></strong> <?php echo getActiveLang() == 'en' ? 'You need to add the following callback URLs to your PayTR panel for the PayTR integration:' : 'PayTR entegrasyonu için aşağıdaki callback URL\'lerini PayTR panelinize eklemeniz gerekiyor:'; ?></p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li><?php echo getActiveLang() == 'en' ? 'Success URL:' : 'Başarılı URL:'; ?> <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded"><?php echo getSetting('site_url', 'http://localhost'); ?>/includes/paytr_success.php</code></li>
                            <li><?php echo getActiveLang() == 'en' ? 'Error URL:' : 'Hata URL:'; ?> <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded"><?php echo getSetting('site_url', 'http://localhost'); ?>/includes/paytr_fail.php</code></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- iyzico Ayarları -->
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo getActiveLang() == 'en' ? 'iyzico Settings' : 'iyzico Ayarları'; ?></h2>
                
                <div class="space-y-4">
                    <div>
                        <label for="iyzico_api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            API Key
                        </label>
                        <div class="relative">
                            <input type="password" id="iyzico_api_key" name="iyzico_api_key" value="<?php echo htmlspecialchars($iyzico_api_key); ?>" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <button type="button" onclick="togglePasswordVisibility('iyzico_api_key')" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="iyzico_secret_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Secret Key
                        </label>
                        <div class="relative">
                            <input type="password" id="iyzico_secret_key" name="iyzico_secret_key" value="<?php echo htmlspecialchars($iyzico_secret_key); ?>" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <button type="button" onclick="togglePasswordVisibility('iyzico_secret_key')" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="iyzico_base_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Base URL
                        </label>
                        <select id="iyzico_base_url" name="iyzico_base_url" class="bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <option value="https://sandbox-api.iyzipay.com" <?php echo $iyzico_base_url === 'https://sandbox-api.iyzipay.com' ? 'selected' : ''; ?>>Test (Sandbox)</option>
                            <option value="https://api.iyzipay.com" <?php echo $iyzico_base_url === 'https://api.iyzipay.com' ? 'selected' : ''; ?>>Canlı (Production)</option>
                        </select>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900 text-blue-800 dark:text-blue-200 p-4 rounded-lg text-sm">
                        <p><strong><?php echo getActiveLang() == 'en' ? 'Note:' : 'Not:'; ?></strong> <?php echo getActiveLang() == 'en' ? 'You need to install the iyzipay-php library via Composer for the iyzico integration. Run the command' : 'iyzico entegrasyonu için iyzipay-php kütüphanesini Composer ile kurmanız gerekmektedir. Tam entegrasyon için'; ?> <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded">composer require iyzico/iyzipay-php</code> <?php echo getActiveLang() == 'en' ? 'for complete integration.' : 'komutunu çalıştırın.'; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md">
                    <?php echo getActiveLang() == 'en' ? 'Save Settings' : 'Ayarları Kaydet'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript için gerekli kod -->
<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}
</script>

<?php include 'includes/footer.php'; ?>
