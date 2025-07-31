<?php
require_once 'includes/config.php';
$page_title = getActiveLang() == 'en' ? "Privacy Policy" : "Gizlilik Politikası";
$meta_description = getActiveLang() == 'en' ? "Information about our site's privacy policy and protection of personal data." : "Sitemizin gizlilik politikası ve kişisel verilerin korunmasına ilişkin bilgiler.";
$meta_keywords = getActiveLang() == 'en' ? "privacy policy, personal data, data protection" : "gizlilik politikası, kişisel veriler, veri koruma";

require_once 'templates/header.php';
?>

<main class="container mx-auto px-4 py-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white"><?php echo getActiveLang() == 'en' ? 'Privacy Policy' : 'Gizlilik Politikası'; ?></h1>
        
        <div class="prose max-w-none dark:prose-invert">
            <p class="mb-4"><?php echo getActiveLang() == 'en' ? 'Last Updated: ' : 'Son Güncelleme: '; ?><?php echo date('d.m.Y'); ?></p>
            
            <?php if (getActiveLang() == 'en'): ?>
            <!-- English Content -->
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">1. Overview</h2>
                <p>This privacy policy explains how your personal data is collected, used, and protected when you visit our website or use our services.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">2. Information We Collect</h2>
                <p>We may collect the following information when you use our site:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Registration information such as name, surname, email address</li>
                    <li>Profile information and content uploaded by users</li>
                    <li>Usage data such as IP address, browser type, visited pages</li>
                    <li>Information collected through cookies</li>
                </ul>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">3. How We Use Information</h2>
                <p>We use the information we collect for the following purposes:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>To manage your account and provide our services</li>
                    <li>To improve the site and its content</li>
                    <li>To personalize user experience</li>
                    <li>For security and verification processes</li>
                    <li>To fulfill our legal obligations</li>
                </ul>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">4. Cookies and Tracking Technologies</h2>
                <p>We use cookies and similar technologies on our site. These are used to improve user experience, analyze site usage, and personalize our services.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">5. Data Sharing</h2>
                <p>We do not share your personal data with third parties without your explicit consent, except for legal obligations. However, we may share your information in the following circumstances:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>To fulfill our legal obligations</li>
                    <li>With our service providers (such as data processing, hosting)</li>
                    <li>In case of company merger or acquisition</li>
                </ul>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">6. Data Security</h2>
                <p>We take appropriate technical and organizational measures to protect your personal data. However, please note that data transmission over the internet is not 100% secure.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">7. Your Rights</h2>
                <p>You have the following rights regarding your personal data:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Right to request access to your data</li>
                    <li>Right to request correction of your data</li>
                    <li>Right to request deletion of your data</li>
                    <li>Right to object to data processing</li>
                    <li>Right to data portability</li>
                </ul>
                <p>To exercise these rights, you can contact us through our <a href="<?php echo getSetting('site_url'); ?>/contact" class="text-blue-500 hover:text-blue-700">contact page</a>.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">8. Changes to This Policy</h2>
                <p>We may make changes to this privacy policy from time to time. When we make changes, we will publish the updated policy on this page and notify our users of significant changes.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">9. Contact Us</h2>
                <p>If you have any questions about our privacy policy, please contact us through our <a href="<?php echo getSetting('site_url'); ?>/contact" class="text-blue-500 hover:text-blue-700">contact page</a>.</p>
            </section>
            <?php else: ?>
            <!-- Turkish Content -->
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">1. Genel Bakış</h2>
                <p>Bu gizlilik politikası, web sitemizi ziyaret ettiğinizde veya hizmetlerimizi kullandığınızda kişisel verilerinizin nasıl toplandığını, kullanıldığını ve korunduğunu açıklamaktadır.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">2. Toplanan Bilgiler</h2>
                <p>Sitemizi kullanırken aşağıdaki bilgileri toplayabiliriz:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Ad, soyad, e-posta adresi gibi kayıt bilgileri</li>
                    <li>Profil bilgileri ve kullanıcı tarafından yüklenen içerikler</li>
                    <li>IP adresi, tarayıcı türü, ziyaret edilen sayfalar gibi kullanım verileri</li>
                    <li>Çerezler aracılığıyla toplanan bilgiler</li>
                </ul>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">3. Bilgilerin Kullanımı</h2>
                <p>Topladığımız bilgileri aşağıdaki amaçlarla kullanmaktayız:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Hesabınızı yönetmek ve hizmetlerimizi sunmak</li>
                    <li>Siteyi ve içeriğini geliştirmek</li>
                    <li>Kullanıcı deneyimini kişiselleştirmek</li>
                    <li>Güvenlik ve doğrulama işlemleri</li>
                    <li>Yasal yükümlülüklerimizi yerine getirmek</li>
                </ul>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">4. Çerezler ve İzleme Teknolojileri</h2>
                <p>Sitemizde çerezler ve benzer teknolojiler kullanmaktayız. Bunlar, kullanıcı deneyimini geliştirmek, site kullanımını analiz etmek ve hizmetlerimizi kişiselleştirmek amacıyla kullanılmaktadır.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">5. Veri Paylaşımı</h2>
                <p>Kişisel verilerinizi, yasal zorunluluklar dışında, açık rızanız olmadan üçüncü taraflarla paylaşmamaktayız. Ancak aşağıdaki durumlarda bilgilerinizi paylaşabiliriz:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Yasal yükümlülüklerimizi yerine getirmek için</li>
                    <li>Hizmet sağlayıcılarımız ile (veri işleme, hosting gibi)</li>
                    <li>Şirket birleşmesi veya satın alması durumunda</li>
                </ul>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">6. Veri Güvenliği</h2>
                <p>Kişisel verilerinizi korumak için uygun teknik ve organizasyonel önlemler almaktayız. Ancak, internet üzerinden veri iletiminin %100 güvenli olmadığını unutmayın.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">7. Kullanıcı Hakları</h2>
                <p>Kişisel verilerinizle ilgili aşağıdaki haklara sahipsiniz:</p>
                <ul class="list-disc pl-5 mb-4">
                    <li>Verilerinize erişim talep etme</li>
                    <li>Verilerinizin düzeltilmesini talep etme</li>
                    <li>Verilerinizin silinmesini talep etme</li>
                    <li>Veri işlemeye itiraz etme</li>
                    <li>Veri taşınabilirliği</li>
                </ul>
                <p>Bu haklarınızı kullanmak için bizimle <a href="<?php echo getSetting('site_url'); ?>/iletisim" class="text-blue-500 hover:text-blue-700">iletişim sayfası</a> üzerinden iletişime geçebilirsiniz.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">8. Değişiklikler</h2>
                <p>Bu gizlilik politikasında zaman zaman değişiklikler yapabiliriz. Değişiklikler yapıldığında, bu sayfada güncellenmiş politikayı yayınlayacağız ve önemli değişiklikler durumunda kullanıcılarımızı bilgilendireceğiz.</p>
            </section>
            
            <section class="mb-6">
                <h2 class="text-2xl font-semibold mb-3">9. İletişim</h2>
                <p>Gizlilik politikamız hakkında sorularınız varsa, lütfen bizimle <a href="<?php echo getSetting('site_url'); ?>/iletisim" class="text-blue-500 hover:text-blue-700">iletişim sayfası</a> üzerinden iletişime geçin.</p>
            </section>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
require_once 'templates/footer.php';
?>
