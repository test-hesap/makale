<?php
require_once 'config.php';

// Kullanıcı kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$package = isset($_GET['package']) ? $_GET['package'] : 'monthly';
$price = isset($_GET['price']) ? floatval($_GET['price']) : 29.99;

// Kullanıcı bilgilerini al
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: /login.php");
    exit();
}

// Ayarlardan iyzico bilgilerini al
$iyzico_api_key = getSetting('iyzico_api_key', '');
$iyzico_secret_key = getSetting('iyzico_secret_key', '');
$iyzico_base_url = getSetting('iyzico_base_url', 'https://sandbox-api.iyzipay.com');

// iyzico bilgileri eksikse hata ver
if (empty($iyzico_api_key) || empty($iyzico_secret_key)) {
    $_SESSION['error_message'] = "Ödeme sistemi ayarları eksik. Lütfen site yöneticisiyle iletişime geçin.";
    header("Location: /premium.php");
    exit();
}

// iyzico için gerekli kütüphaneleri kontrol et
if (!file_exists('../vendor/iyzico/iyzipay-php/samples/config.php')) {
    $_SESSION['error_message'] = "iyzico kütüphanesi bulunamadı. Lütfen site yöneticisiyle iletişime geçin.";
    header("Location: /premium.php");
    exit();
}

// Sipariş numarası oluştur
$order_id = time() . rand(1000, 9999);

// Süre hesapla
$duration = ($package === 'monthly') ? '+1 month' : '+1 year';
$end_date = date('Y-m-d', strtotime($duration));

// Paket bilgisini hazırla
$package_name = ($package === 'monthly') ? 'Aylık Premium Üyelik' : 'Yıllık Premium Üyelik';

// iyzico için veritabanına kayıt ekle
$stmt = $db->prepare("
    INSERT INTO payment_transactions 
    (user_id, payment_method, package, amount, order_id, status, created_at) 
    VALUES (?, 'iyzico', ?, ?, ?, 'pending', NOW())
");
$stmt->execute([$user_id, $package, $price, $order_id]);
$transaction_id = $db->lastInsertId();

// Kullanıcının satın alma bilgilerini session'a kaydet
$_SESSION['payment_info'] = [
    'transaction_id' => $transaction_id,
    'package' => $package,
    'price' => $price,
    'order_id' => $order_id,
    'end_date' => $end_date
];

// iyzico kütüphanesini kullanmak için gerekli yapılandırma
$iyzico_config = [
    'apiKey' => $iyzico_api_key,
    'secretKey' => $iyzico_secret_key,
    'baseUrl' => $iyzico_base_url
];

// Eğer iyzico kütüphanesi yüklenmemişse basit bir form ile devam edelim
if (!class_exists('Iyzipay\Options')) {
    // Ödeme formunu göster
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>iyzico Ödeme - <?php echo getSetting('site_name', 'Site Adı'); ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    </head>
    <body class="bg-gray-100">
        <div class="container mx-auto py-10 px-4">
            <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl font-bold text-center mb-6">iyzico Ödeme</h1>
                
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-2">Sipariş Bilgileri</h2>
                    <div class="border-b pb-3">
                        <div class="flex justify-between">
                            <span>Paket:</span>
                            <span class="font-medium"><?php echo $package_name; ?></span>
                        </div>
                        <div class="flex justify-between mt-2">
                            <span>Tutar:</span>
                            <span class="font-medium"><?php echo number_format($price, 2, ',', '.'); ?> ₺</span>
                        </div>
                    </div>
                </div>
                
                <form action="/includes/iyzico_process.php" method="post" class="space-y-4">
                    <input type="hidden" name="transaction_id" value="<?php echo $transaction_id; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kart Üzerindeki İsim</label>
                        <input type="text" name="card_holder" class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kart Numarası</label>
                        <input type="text" name="card_number" class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="1234 5678 9012 3456" maxlength="19" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Son Kullanma Tarihi</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="expiry_month" class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="AA" maxlength="2" required>
                                <input type="text" name="expiry_year" class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="YY" maxlength="2" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                            <input type="text" name="cvv" class="w-full px-4 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="123" maxlength="4" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Ödemeyi Tamamla
                    </button>
                </form>
                
                <div class="mt-6 flex justify-center">
                    <a href="/odeme.php" class="text-blue-600 hover:underline">Geri Dön</a>
                </div>
                
                <div class="mt-6 pt-6 border-t text-center text-xs text-gray-500">
                    <p>Bu ödeme iyzico güvencesi ile yapılmaktadır.</p>
                    <div class="flex justify-center mt-2">
                        <img src="https://www.iyzico.com/assets/images/content/logo.svg" alt="iyzico" class="h-6">
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            // Kart numarası formatı
            document.querySelector('input[name="card_number"]').addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                let formattedValue = '';
                
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
                
                e.target.value = formattedValue;
            });
        </script>
    </body>
    </html>
    <?php
    exit;
} else {
    // iyzico kütüphanesi yüklüyse standart iyzico akışını kullan
    // Not: Bu kısım iyzico PHP kütüphanesi yüklü olduğunda çalışacak
    require_once '../vendor/autoload.php';
    
    // iyzico konfigürasyonu ve işlemler burada devam edecek
    
    // Uyarı mesajı göster
    $_SESSION['warning_message'] = "iyzico entegrasyonu için tam kütüphane kurulumu yapılmalı. Lütfen site yöneticisiyle iletişime geçin.";
    header("Location: /odeme.php");
    exit();
}
?>
