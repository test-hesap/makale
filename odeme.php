<?php
require_once 'includes/config.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Premium üyelik kontrolü
$is_premium = $_SESSION['is_premium'] ?? $user['is_premium'] ?? 0;
$premium_until = $_SESSION['premium_until'] ?? $user['premium_until'] ?? null;

// Premium üyelik aktif mi?
$active_premium = $is_premium && $premium_until && strtotime($premium_until) >= time();

// Paket bilgisini al
$package = $_GET['package'] ?? 'monthly';
if (!in_array($package, ['monthly', 'yearly'])) {
    $package = 'monthly';
}

// Fiyat bilgilerini ayarlardan al
$stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('premium_monthly_price', 'premium_yearly_price', 'premium_yearly_discount', 'payment_methods')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}

// Ödeme yöntemlerini al
$payment_methods = isset($settings['payment_methods']) ? json_decode($settings['payment_methods'], true) : [];
if (empty($payment_methods)) {
    $payment_methods = [
        'paytr' => ['active' => true, 'name' => 'PayTR'],
        'iyzico' => ['active' => true, 'name' => 'iyzico']
    ];
}

// Varsayılan değerler
$monthly_price = $settings['premium_monthly_price'] ?? '29.99';
$yearly_price = $settings['premium_yearly_price'] ?? '239.99';
$yearly_discount = $settings['premium_yearly_discount'] ?? '33';

// Seçilen pakete göre fiyat
$price = ($package === 'monthly') ? $monthly_price : $yearly_price;
$packageName = ($package === 'monthly') ? 'Aylık Premium' : 'Yıllık Premium';
$packageDuration = ($package === 'monthly') ? '1 ay' : '1 yıl';

// Ödeme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    
    // PayTR ödeme yöntemi seçildiyse
    if ($payment_method === 'paytr' && isset($payment_methods['paytr']) && $payment_methods['paytr']['active']) {
        header("Location: includes/paytr_payment.php?package=$package&price=$price");
        exit();
    }
    
    // iyzico ödeme yöntemi seçildiyse
    if ($payment_method === 'iyzico' && isset($payment_methods['iyzico']) && $payment_methods['iyzico']['active']) {
        header("Location: includes/iyzico_payment.php?package=$package&price=$price");
        exit();
    }
    
    $error_message = "Geçersiz ödeme yöntemi seçildi.";
}

$page_title = "Ödeme - " . getSetting('site_name', 'Site Adı');
require_once 'templates/header.php';
?>

<div class="container mx-auto py-8 px-4">
    <div class="max-w-2xl mx-auto space-y-8">
        <h1 class="text-3xl font-bold text-center mb-8">Ödeme Sayfası</h1>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Sipariş Özeti -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Sipariş Özeti</h2>
            
            <div class="border-b pb-4 mb-4">
                <div class="flex justify-between items-center">
                    <span class="font-medium"><?php echo $packageName; ?></span>
                    <span class="font-bold"><?php echo number_format(floatval($price), 2, ',', '.'); ?> ₺</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">Süre: <?php echo $packageDuration; ?></p>
            </div>
            
            <div class="flex justify-between items-center font-bold text-lg">
                <span>Toplam</span>
                <span><?php echo number_format(floatval($price), 2, ',', '.'); ?> ₺</span>
            </div>
        </div>
        
        <!-- Ödeme Yöntemleri -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Ödeme Yöntemi Seçin</h2>
            
            <form method="POST" class="space-y-6">
                <div class="space-y-4">
                    <?php foreach ($payment_methods as $key => $method): ?>
                        <?php if ($method['active']): ?>
                            <div class="relative">
                                <input type="radio" id="payment_<?php echo $key; ?>" name="payment_method" value="<?php echo $key; ?>" class="absolute top-4 left-4" <?php echo ($key === 'paytr') ? 'checked' : ''; ?>>
                                <label for="payment_<?php echo $key; ?>" class="block border rounded-lg p-4 pl-12 hover:bg-gray-50 cursor-pointer">
                                    <div class="font-semibold text-lg"><?php echo $method['name']; ?></div>
                                    <p class="text-gray-600 text-sm mt-2">
                                        <?php if ($key === 'paytr'): ?>
                                            Kredi kartı, banka kartı veya hesabınızla güvenli ödeme yapın.
                                        <?php elseif ($key === 'iyzico'): ?>
                                            Tüm kredi kartları ve banka kartları ile güvenli ödeme.
                                        <?php endif; ?>
                                    </p>
                                </label>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                    Ödemeyi Tamamla
                </button>
                
                <p class="text-center text-xs text-gray-500 mt-4">
                    Ödeme yapmadan önce <a href="#" class="text-blue-600 hover:underline">kullanım şartlarını</a> okumanızı öneririz.
                </p>
            </form>
        </div>
        
        <!-- Güvenli Ödeme Bilgisi -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Güvenli Ödeme</h2>
            
            <div class="flex items-center space-x-4 mb-4">
                <i class="fas fa-lock text-green-500 text-2xl"></i>
                <div>
                    <h3 class="font-semibold">128-bit SSL Koruması</h3>
                    <p class="text-sm text-gray-600">Tüm ödeme işlemleriniz güvenli bağlantı ile korunmaktadır.</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <i class="fas fa-credit-card text-blue-500 text-2xl"></i>
                <div>
                    <h3 class="font-semibold">Güvenli Ödeme</h3>
                    <p class="text-sm text-gray-600">Kart bilgileriniz hiçbir şekilde sistemimizde saklanmaz.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
