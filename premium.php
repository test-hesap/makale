<?php
require_once 'includes/config.php';

// Premium makale bilgilerini al
$article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;
$article = null;

if ($article_id > 0) {
    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, u.username
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id
        WHERE a.id = ? AND a.status = 'published'
    ");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Premium üyelik kontrolü
$is_premium = $_SESSION['is_premium'] ?? $user['is_premium'] ?? 0;
$premium_until = $_SESSION['premium_until'] ?? $user['premium_until'] ?? null;

// Premium üyelik aktif mi?
$active_premium = $is_premium && $premium_until && strtotime($premium_until) >= time();

// Premium üyelik satın alma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_premium'])) {
    $package = $_POST['package'] ?? '';
    
    if (empty($package) || !in_array($package, ['monthly', 'yearly'])) {
        $error_message = "Lütfen geçerli bir paket seçiniz.";
    } else {
        // Fiyat bilgilerini ayarlardan al
        $stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('premium_monthly_price', 'premium_yearly_price')");
        $pricing = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pricing[$row['key']] = $row['value'];
        }
        
        // Ödeme miktarını belirle
        $payment_amount = 0;
        if ($package === 'monthly') {
            $payment_amount = floatval($pricing['premium_monthly_price'] ?? 29.99);
        } else {
            $payment_amount = floatval($pricing['premium_yearly_price'] ?? 239.99);
        }
        
        // Bu kısımda gerçek bir ödeme işlemi entegrasyonu olmalıdır
        // (Örn: iyzico, PayTR, Stripe, PayPal vb.)
        // Şu an için basitçe premium üyeliği aktif ediyoruz
        
        $current_date = date('Y-m-d');
        $expiry_date = null;
        
        if ($package === 'monthly') {
            // Aylık paket - 1 ay ekle
            $expiry_date = date('Y-m-d', strtotime('+1 month'));
        } else {
            // Yıllık paket - 1 yıl ekle
            $expiry_date = date('Y-m-d', strtotime('+1 year'));
        }
          try {
            // Transaction başlat
            $db->beginTransaction();
            
            // Plan bilgisini tespit et
            $plan_name = ($package === 'monthly') ? 'Premium Aylık' : 'Premium Yıllık';
            $plan_duration = ($package === 'monthly') ? '1 ay' : '1 yıl';
            
            // Plan tablosunda kayıt var mı kontrol et
            $check_plan = $db->prepare("SELECT id, price FROM plans WHERE name = ?");
            $check_plan->execute([$plan_name]);
            $plan = $check_plan->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                // Plan yoksa ekle
                $insert_plan = $db->prepare("INSERT INTO plans (name, description, price, duration) VALUES (?, ?, ?, ?)");
                $insert_plan->execute([$plan_name, $plan_duration . ' süreli premium üyelik', $payment_amount, $plan_duration]);
                $plan_id = $db->lastInsertId();
            } else {
                // Plan var ama fiyatı güncel değilse güncelle
                $plan_id = $plan['id'];
                if ($plan['price'] != $payment_amount) {
                    $update_price = $db->prepare("UPDATE plans SET price = ? WHERE id = ?");
                    $update_price->execute([$payment_amount, $plan_id]);
                }
            }
            
            // Abonelik kaydını ekle
            $insert_sub = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $insert_sub->execute([$user_id, $plan_id, 'active', date('Y-m-d'), $expiry_date]);
            
            // Veritabanında premium üyeliği güncelle
            $update_stmt = $db->prepare("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?");
            $update_stmt->execute([$expiry_date, $user_id]);
            
            // Transaction'ı tamamla
            $db->commit();
            
            // Session'ı güncelle
            $_SESSION['is_premium'] = 1;
            $_SESSION['premium_until'] = $expiry_date;
            
            $success_message = "Premium üyeliğiniz başarıyla aktifleştirildi! Bitiş tarihi: " . date('d.m.Y', strtotime($expiry_date));
            
            // Aktif premium durumunu güncelle
            $active_premium = true;
            $premium_until = $expiry_date;        } catch (PDOException $e) {
            // Hata durumunda transaction'ı geri al
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error_message = "Premium üyelik işlemi sırasında bir hata oluştu: " . $e->getMessage();
            error_log("Premium üyelik hatası: " . $e->getMessage());
        }
    }
}

// Üyelik uzatma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_premium'])) {
    $package = $_POST['package'] ?? '';
    
    if (empty($package) || !in_array($package, ['monthly', 'yearly'])) {
        $error_message = "Lütfen geçerli bir paket seçiniz.";
    } else {
        // Fiyat bilgilerini ayarlardan al
        $stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('premium_monthly_price', 'premium_yearly_price')");
        $pricing = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pricing[$row['key']] = $row['value'];
        }
        
        // Ödeme miktarını belirle
        $payment_amount = 0;
        if ($package === 'monthly') {
            $payment_amount = floatval($pricing['premium_monthly_price'] ?? 29.99);
        } else {
            $payment_amount = floatval($pricing['premium_yearly_price'] ?? 239.99);
        }
        
        // Mevcut bitiş tarihinden başla
        $current_expiry = $premium_until;
        $new_expiry = null;
        
        if ($package === 'monthly') {
            // Aylık paket - 1 ay ekle
            $new_expiry = date('Y-m-d', strtotime($current_expiry . ' +1 month'));
        } else {
            // Yıllık paket - 1 yıl ekle
            $new_expiry = date('Y-m-d', strtotime($current_expiry . ' +1 year'));
        }
          try {
            // Transaction başlat
            $db->beginTransaction();
            
            // Plan bilgisini tespit et
            $plan_name = ($package === 'monthly') ? 'Premium Aylık' : 'Premium Yıllık';
            $plan_duration = ($package === 'monthly') ? '1 ay' : '1 yıl';
            
            // Plan tablosunda kayıt var mı kontrol et
            $check_plan = $db->prepare("SELECT id, price FROM plans WHERE name = ?");
            $check_plan->execute([$plan_name]);
            $plan = $check_plan->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                // Plan yoksa ekle
                $insert_plan = $db->prepare("INSERT INTO plans (name, description, price, duration) VALUES (?, ?, ?, ?)");
                $insert_plan->execute([$plan_name, $plan_duration . ' süreli premium üyelik', $payment_amount, $plan_duration]);
                $plan_id = $db->lastInsertId();
            } else {
                // Plan var ama fiyatı güncel değilse güncelle
                $plan_id = $plan['id'];
                if ($plan['price'] != $payment_amount) {
                    $update_price = $db->prepare("UPDATE plans SET price = ? WHERE id = ?");
                    $update_price->execute([$payment_amount, $plan_id]);
                }
            }
            
            // Abonelik kaydını ekle
            $insert_sub = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $insert_sub->execute([$user_id, $plan_id, 'active', date('Y-m-d'), $new_expiry]);
            
            // Veritabanında premium üyeliği güncelle
            $update_stmt = $db->prepare("UPDATE users SET premium_until = ? WHERE id = ?");
            $update_stmt->execute([$new_expiry, $user_id]);
            
            // Transaction'ı tamamla
            $db->commit();
            
            // Session'ı güncelle
            $_SESSION['premium_until'] = $new_expiry;
            
            $success_message = "Premium üyeliğiniz başarıyla uzatıldı! Yeni bitiş tarihi: " . date('d.m.Y', strtotime($new_expiry));
            
            // Bitiş tarihini güncelle
            $premium_until = $new_expiry;
        } catch (PDOException $e) {
            // Hata durumunda transaction'ı geri al
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error_message = "Premium üyelik uzatma işlemi sırasında bir hata oluştu: " . $e->getMessage();
            error_log("Premium üyelik uzatma hatası: " . $e->getMessage());
        }
    }
}

require_once 'templates/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Premium Üyelik</h1>
        <p class="text-gray-600 mt-2">Reklamsız ve ayrıcalıklı bir deneyim için premium üyeliğe geçin</p>
    </div>
    
    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($article): ?>
    <!-- Premium Makale Bilgisi -->
    <div class="bg-purple-100 border border-purple-300 text-purple-700 px-4 py-5 rounded-lg mb-8">
        <div class="flex items-center mb-4">
            <i class="fas fa-lock-alt text-purple-500 text-2xl mr-3"></i>
            <h2 class="text-xl font-bold">Bu içerik Premium üyelere özeldir</h2>
        </div>
        
        <div class="bg-white p-4 rounded-lg shadow-sm mb-4">
            <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($article['title']); ?></h3>
            <p class="text-gray-600 mb-2">
                <span class="inline-block mr-3"><i class="fas fa-folder mr-1"></i> <?php echo htmlspecialchars($article['category_name']); ?></span>
                <span class="inline-block mr-3"><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($article['username']); ?></span>
                <span class="inline-block"><i class="fas fa-calendar mr-1"></i> <?php echo date('d.m.Y', strtotime($article['created_at'])); ?></span>
            </p>
            <div class="mt-3">
                <p class="text-gray-700">Bu premium makaleyi okumak için Premium üyeliğinizin olması gerekmektedir.</p>
            </div>
        </div>
        
        <p class="mb-4">Premium üyelik satın alarak sitemizin tüm premium içeriklerine erişebilir, reklamları görmeden kesintisiz okuma yapabilirsiniz.</p>
        
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="flex justify-center space-x-4">
                <a href="login.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-sign-in-alt mr-2"></i> Giriş Yap
                </a>
                <a href="register.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-user-plus mr-2"></i> Kayıt Ol
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Premium Üyelik Bilgisi -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Premium Üyelik Durumu</h2>
            
            <?php if ($active_premium): ?>
                <div class="py-4 px-6 bg-yellow-100 text-yellow-800 rounded-lg flex items-center mb-4">
                    <i class="fas fa-crown text-yellow-500 text-2xl mr-4"></i>
                    <div>
                        <p class="font-bold text-lg">Premium Üyesiniz</p>
                        <p>Bitiş tarihi: <?php echo date('d.m.Y', strtotime($premium_until)); ?></p>
                    </div>
                </div>
                  <?php
                // Premium özellikleri ayarlardan al
                $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'premium_features'");
                $stmt->execute();
                $featuresRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $features = $featuresRow ? explode("\n", $featuresRow['value']) : [
                    'Reklamsız deneyim',
                    'Özel içeriklere erişim',
                    'Öncelikli destek',
                    'Ve daha fazlası...'
                ];
                ?>
                <div class="mt-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Premium Üyelik Ayrıcalıkları:</h3>
                    <ul class="list-disc pl-5 space-y-2">
                        <?php foreach ($features as $feature): ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="py-4 px-6 bg-gray-100 text-gray-800 rounded-lg flex items-center mb-4">
                    <i class="fas fa-user text-gray-500 text-2xl mr-4"></i>
                    <div>
                        <p class="font-bold text-lg">Standart Üyesiniz</p>
                        <p>Premium üyelik avantajlarından faydalanamıyorsunuz</p>
                    </div>
                </div>
                
                <?php
                // Premium özellikleri ayarlardan al
                $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'premium_features'");
                $stmt->execute();
                $featuresRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $features = $featuresRow ? explode("\n", $featuresRow['value']) : [
                    'Reklamsız deneyim - Tüm reklamlar kaldırılır',
                    'Özel içerikler - Sadece premium üyelere özel içerikler',
                    'Öncelikli destek - Sorularınız öncelikli yanıtlanır',
                    'Ve daha fazlası - Gelecek özelliklere erken erişim'
                ];
                ?>
                <div class="mt-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Premium Üyelik Ayrıcalıkları:</h3>
                    <ul class="list-disc pl-5 space-y-2 text-gray-600">
                        <?php foreach ($features as $feature): 
                            $parts = explode(' - ', $feature, 2);
                            $title = $parts[0];
                            $description = $parts[1] ?? '';
                        ?>
                            <li>
                                <span class="font-semibold text-green-600"><?php echo htmlspecialchars($title); ?></span>
                                <?php if($description): ?> - <?php echo htmlspecialchars($description); ?><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
    <!-- Premium Üyelik Paketleri -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <?php echo $active_premium ? 'Üyeliğinizi Uzatın' : 'Premium Üyelik Paketleri'; ?>
            </h2>
            
            <?php
            // Fiyat bilgilerini ayarlardan al
            $stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('premium_monthly_price', 'premium_yearly_price', 'premium_yearly_discount')");
            $pricing = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pricing[$row['key']] = $row['value'];
            }
            
            // Varsayılan değerler
            $monthly_price = $pricing['premium_monthly_price'] ?? '29.99';
            $yearly_price = $pricing['premium_yearly_price'] ?? '239.99';
            $yearly_discount = $pricing['premium_yearly_discount'] ?? '33';
            
            // Aylık ortalama fiyat hesapla
            $monthly_avg = number_format(floatval($yearly_price) / 12, 2, '.', '');
            ?>
            
            <form method="POST" class="space-y-6">                <div class="space-y-4">                    <div class="relative">
                        <input type="radio" id="package_monthly" name="package" value="monthly" class="absolute top-4 left-4" onchange="updatePackageStyles()">
                        <label for="package_monthly" id="monthly_label" class="block border dark:border-gray-700 rounded-lg p-4 pl-12 hover:bg-gray-50 dark:hover:bg-[#333333] dark:bg-[#292929] cursor-pointer">
                            <div class="font-semibold text-lg dark:text-white">Aylık Paket</div>
                            <div class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1"><?php echo number_format(floatval($monthly_price), 2, ',', '.'); ?> ₺ <span class="text-sm text-gray-500 dark:text-gray-400 font-normal">/ay</span></div>
                            <p class="text-gray-600 dark:text-gray-300 text-sm mt-2">30 günlük premium deneyim</p>
                        </label>
                    </div>
                      <div class="relative">
                        <input type="radio" id="package_yearly" name="package" value="yearly" class="absolute top-4 left-4" checked onchange="updatePackageStyles()">
                        <label for="package_yearly" id="yearly_label" class="block border border-blue-300 bg-blue-50 dark:bg-[#292929] dark:border-blue-700 rounded-lg p-4 pl-12 hover:bg-blue-100 dark:hover:bg-[#333333] cursor-pointer">
                            <div class="flex items-center">
                                <div class="font-semibold text-lg dark:text-white">Yıllık Paket</div>
                                <span class="ml-2 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs font-semibold py-1 px-2 rounded">%<?php echo $yearly_discount; ?> İNDİRİM</span>
                            </div>                            <div class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1"><?php echo number_format(floatval($yearly_price), 2, ',', '.'); ?> ₺ <span class="text-sm text-gray-500 dark:text-gray-400 font-normal">/yıl</span></div>
                            <p class="text-gray-600 dark:text-gray-300 text-sm mt-2">365 günlük premium deneyim (aylık sadece <?php echo number_format(floatval($monthly_avg), 0, '', ''); ?> ₺)</p>
                        </label>
                    </div>
                </div>
                
                <?php if ($active_premium): ?>
                <button type="submit" name="extend_premium" class="w-full bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                    Üyeliği Uzat
                </button>
                <?php else: ?>
                <a href="odeme.php?package=<?php echo isset($_POST['package']) ? $_POST['package'] : 'yearly'; ?>" class="block w-full bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors text-center">
                    Premium Üye Ol
                </a>
                <?php endif; ?>
                
                <?php if (!$active_premium): ?>
                    <p class="text-center text-xs text-gray-500 mt-4">
                        Ödeme yapmadan önce <a href="#" class="text-blue-600 hover:underline">kullanım şartlarını</a> okumanızı öneririz.
                    </p>
                <?php endif; ?>
            </form>
            
            <script>
                function updatePackageStyles() {
                    const monthlyRadio = document.getElementById('package_monthly');
                    const yearlyRadio = document.getElementById('package_yearly');
                    const monthlyLabel = document.getElementById('monthly_label');
                    const yearlyLabel = document.getElementById('yearly_label');
                    const premiumLink = document.querySelector('a[href^="odeme.php"]');
                    
                    // Sınıf ayarları
                    const activeClasses = 'border border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-[#292929]';
                    const inactiveClasses = 'border dark:border-gray-700 dark:bg-[#292929]';
                    
                    if (monthlyRadio.checked) {
                        // Aylık seçili
                        monthlyLabel.className = 'block ' + activeClasses + ' rounded-lg p-4 pl-12 hover:bg-blue-100 dark:hover:bg-[#333333] cursor-pointer';
                        yearlyLabel.className = 'block ' + inactiveClasses + ' rounded-lg p-4 pl-12 hover:bg-gray-50 dark:hover:bg-[#333333] cursor-pointer';
                        
                        // Ödeme sayfası linkini güncelle
                        if (premiumLink) {
                            premiumLink.href = 'odeme.php?package=monthly';
                        }
                    } else {
                        // Yıllık seçili
                        yearlyLabel.className = 'block ' + activeClasses + ' rounded-lg p-4 pl-12 hover:bg-blue-100 dark:hover:bg-[#333333] cursor-pointer';
                        monthlyLabel.className = 'block ' + inactiveClasses + ' rounded-lg p-4 pl-12 hover:bg-gray-50 dark:hover:bg-[#333333] cursor-pointer';
                        
                        // Ödeme sayfası linkini güncelle
                        if (premiumLink) {
                            premiumLink.href = 'odeme.php?package=yearly';
                        }
                    }
                }
                
                // Sayfa yüklendiğinde seçilen paketi belirle
                document.addEventListener('DOMContentLoaded', function() {
                    updatePackageStyles();
                    
                    // Paket seçimine tıklanınca hemen link güncellensin
                    const monthlyRadio = document.getElementById('package_monthly');
                    const yearlyRadio = document.getElementById('package_yearly');
                    
                    if (monthlyRadio) {
                        monthlyRadio.addEventListener('change', updatePackageStyles);
                    }
                    
                    if (yearlyRadio) {
                        yearlyRadio.addEventListener('change', updatePackageStyles);
                    }
                });
            </script>
        </div>
    </div>
    
    <!-- Premium Üyelik SSS -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Sıkça Sorulan Sorular</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-lg text-gray-800">Premium üyeliğimi nasıl iptal ederim?</h3>
                <p class="text-gray-600 mt-1">Profil sayfanızdan üyelik ayarlarına giderek istediğiniz zaman premium üyeliğinizi iptal edebilirsiniz. İptal ettiğinizde, bitiş tarihine kadar premium avantajlardan yararlanmaya devam edersiniz.</p>
            </div>
            
            <div>
                <h3 class="font-semibold text-lg text-gray-800">Ödememi nasıl yapabilirim?</h3>
                <p class="text-gray-600 mt-1">Ödeme yapmak için kredi kartı, banka kartı veya havale/EFT yöntemlerini kullanabilirsiniz. Tüm ödemeleriniz 256-bit SSL ile şifrelenerek güvenle saklanır.</p>
            </div>
            
            <div>
                <h3 class="font-semibold text-lg text-gray-800">Premium üyelik hangi özellikleri sunuyor?</h3>
                <p class="text-gray-600 mt-1">Premium üyelik; reklamsız deneyim, özel içeriklere erişim, öncelikli destek ve gelecek özelliklere erken erişim gibi avantajlar sunar. Ayrıca düzenli olarak yeni premium özellikler eklemekteyiz.</p>
            </div>
            
            <div>
                <h3 class="font-semibold text-lg text-gray-800">İade politikanız nedir?</h3>
                <p class="text-gray-600 mt-1">Satın alma işleminden sonraki 7 gün içerisinde, herhangi bir sebeple memnun kalmazsanız, tam iade garantisi sunmaktayız. İade talepleriniz için destek@siteadi.com adresine e-posta gönderebilirsiniz.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
