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

// Ayarlardan PayTR bilgilerini al
$paytr_merchant_id = getSetting('paytr_merchant_id', '');
$paytr_merchant_key = getSetting('paytr_merchant_key', '');
$paytr_merchant_salt = getSetting('paytr_merchant_salt', '');

// PayTR bilgileri eksikse hata ver
if (empty($paytr_merchant_id) || empty($paytr_merchant_key) || empty($paytr_merchant_salt)) {
    $_SESSION['error_message'] = "Ödeme sistemi ayarları eksik. Lütfen site yöneticisiyle iletişime geçin.";
    header("Location: /premium.php");
    exit();
}

// Ödeme için gerekli parametreleri hazırla
$merchant_id = $paytr_merchant_id;
$merchant_key = $paytr_merchant_key;
$merchant_salt = $paytr_merchant_salt;

// Test modu aktif mi?
$test_mode = getSetting('paytr_test_mode', '1');

// Sipariş numarası oluştur
$order_id = time() . rand(1000, 9999);

// Kullanıcı IP adresi
$user_ip = $_SERVER['REMOTE_ADDR'];

// Sepet bilgisi
$package_name = ($package === 'monthly') ? 'Aylık Premium Üyelik' : 'Yıllık Premium Üyelik';
$basket = base64_encode(json_encode(array(
    array($package_name, $price, 1)
)));

// Süre hesapla
$duration = ($package === 'monthly') ? '+1 month' : '+1 year';
$end_date = date('Y-m-d', strtotime($duration));

// Ödeme sonrası yönlendirilecek URL'ler
$merchant_ok_url = getSetting('site_url', 'http://localhost') . "/odeme_sonuc.php?status=success";
$merchant_fail_url = getSetting('site_url', 'http://localhost') . "/odeme_sonuc.php?status=failed";

// Ek parametreler
$user_basket = $basket;
$user_name = $user['username'];
$user_address = "Premium Üyelik";
$user_phone = $user['phone'] ?? "5555555555";
$email = $user['email'];
$payment_amount = $price * 100; // TL'den kuruş'a çevirme
$currency = "TL";
$test_mode = $test_mode;
$debug_on = 0; // Debug modu

// Hash string
$hash_str = $merchant_id . $user_ip . $order_id . $email . $payment_amount . $user_basket . $test_mode . $currency;
$paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

// PayTR için veritabanına kayıt ekle
$stmt = $db->prepare("
    INSERT INTO payment_transactions 
    (user_id, payment_method, package, amount, order_id, status, created_at) 
    VALUES (?, 'paytr', ?, ?, ?, 'pending', NOW())
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

// POST verisini hazırla
$post_vals = array(
    'merchant_id' => $merchant_id,
    'user_ip' => $user_ip,
    'merchant_oid' => $order_id,
    'email' => $email,
    'payment_amount' => $payment_amount,
    'paytr_token' => $paytr_token,
    'user_basket' => $user_basket,
    'debug_on' => $debug_on,
    'no_installment' => 0,
    'max_installment' => 0,
    'user_name' => $user_name,
    'user_address' => $user_address,
    'user_phone' => $user_phone,
    'merchant_ok_url' => $merchant_ok_url,
    'merchant_fail_url' => $merchant_fail_url,
    'notification_url' => getSetting('site_url', 'http://localhost') . "/includes/paytr_callback.php",
    'test_mode' => $test_mode,
    'timeout_limit' => 30,
    'currency' => $currency,
    'lang' => 'tr'
);

// cURL ile PayTR'ye istek gönder
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$result = @curl_exec($ch);

if (curl_errno($ch)) {
    die("PAYTR IFRAME connection error. err:" . curl_error($ch));
}
curl_close($ch);

$result = json_decode($result, 1);

if ($result['status'] == 'success') {
    $token = $result['token'];
    
    // Token'ı veritabanına kaydet
    $stmt = $db->prepare("UPDATE payment_transactions SET token = ? WHERE id = ?");
    $stmt->execute([$token, $transaction_id]);
    
    // PayTR iframe sayfasını göster
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ödeme Sayfası - <?php echo getSetting('site_name', 'Site Adı'); ?></title>
        <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
        <style>
            body { margin: 0; padding: 0; }
            .paytr-container { width: 100%; height: 100vh; display: flex; justify-content: center; align-items: center; }
            iframe { width: 100%; height: 100vh; border: none; }
            .back-link { position: fixed; top: 20px; left: 20px; background: #fff; padding: 10px 15px; border-radius: 5px; text-decoration: none; color: #333; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <a href="/odeme.php" class="back-link">← Geri Dön</a>
        <div class="paytr-container">
            <iframe src="https://www.paytr.com/odeme/guvenli/<?php echo $token; ?>" id="paytriframe" frameborder="0" scrolling="no"></iframe>
        </div>
        <script>iFrameResize({}, '#paytriframe');</script>
    </body>
    </html>
    <?php
    exit;
} else {
    // Hata durumunda
    $_SESSION['error_message'] = "Ödeme sistemi hatası: " . $result['reason'];
    header("Location: /odeme.php");
    exit();
}
?>
