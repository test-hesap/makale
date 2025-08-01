<?php
require_once 'includes/config.php';
$page_title = getActiveLang() == 'en' ? "Cookie Policy" : "Çerez Politikası";
include 'templates/header.php'; ?>

<div class="container mx-auto px-4 py-0 mt-1">
    <!-- Boşluk eklendi -->
</div>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-8">
        <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-gray-200"><?php echo getActiveLang() == 'en' ? 'Cookie Policy' : 'Çerez Politikası'; ?></h1>
        
        <div class="prose dark:prose-invert max-w-none">
            <p class="mb-4"><?php echo getActiveLang() == 'en' ? 'Last updated: ' : 'Son güncelleme tarihi: '; ?><?php echo date('d.m.Y'); ?></p>
            
            <?php if (getActiveLang() == 'en'): ?>
            <!-- English Content -->
            <h2 class="text-2xl font-semibold mb-4 mt-8">What is a Cookie?</h2>
            <p class="mb-4">Cookies are small text files that are stored on your device when you visit a website. These files store information necessary for the proper functioning of the website and your preferences. Cookies are generally used to remember your session information, analyze website traffic, personalize content, and improve advertising experience.</p>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Types of Cookies We Use</h2>
            <p class="mb-4">The following types of cookies are used on our website:</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">1. Essential Cookies</h3>
            <p class="mb-4">These cookies are necessary for the basic functions of our website. Without them, the site would not work as expected, and basic features such as logging in and filling out forms would be disabled. You cannot disable these cookies.</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">2. Preference Cookies</h3>
            <p class="mb-4">These cookies help us remember how you use our website, such as your language preference or regional location. These cookies provide you with a more personalized experience without having to reset your preferences each time you visit our website.</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">3. Statistics Cookies</h3>
            <p class="mb-4">These cookies collect information about how visitors use our website. For example, which pages are most visited or how much time users spend on the site. This information helps us improve site performance and user experience.</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">4. Marketing Cookies</h3>
            <p class="mb-4">These cookies are used to track advertisements you view on our website or other websites. These cookies are used to show you advertisements more relevant to you and your interests.</p>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Cookie Management</h2>
            <p class="mb-4">Most web browsers accept cookies automatically, but if you wish, you can change your browser settings to reject or block certain cookies. Please note that changing your browser settings may affect the functionality of our website.</p>
            
            <p class="mb-4">For more information on how to manage cookies, you can check your browser's help page:</p>
            
            <ul class="list-disc pl-8 mb-4">
                <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Google Chrome</a></li>
                <li><a href="https://support.mozilla.org/en-US/kb/clear-cookies-and-site-data-firefox" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Mozilla Firefox</a></li>
                <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Microsoft Edge</a></li>
                <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Safari</a></li>
            </ul>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Policy Changes</h2>
            <p class="mb-4">We may update our Cookie Policy from time to time. It is recommended that you regularly check this page for changes. We will do our best to inform you about significant changes to this policy.</p>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Contact Us</h2>
            <p class="mb-4">If you have any questions or concerns about our cookie policy or the processing of your personal data, please do not hesitate to contact us:</p>
            
            <p class="mb-4">
                Email: <a href="mailto:<?php echo htmlspecialchars(getSetting('site_email', 'info@example.com')); ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"><?php echo htmlspecialchars(getSetting('site_email', 'info@example.com')); ?></a><br>
                Website: <a href="<?php echo htmlspecialchars(getSetting('site_url', 'https://example.com')); ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"><?php echo htmlspecialchars(getSetting('site_url', 'https://example.com')); ?></a><br>
            </p>
            <?php else: ?>
            <!-- Turkish Content -->
            <h2 class="text-2xl font-semibold mb-4 mt-8">Çerez Nedir?</h2>
            <p class="mb-4">Çerezler (cookies), bir web sitesini ziyaret ettiğinizde tarayıcınız tarafından cihazınızda depolanan küçük metin dosyalarıdır. Bu dosyalar, web sitesinin düzgün çalışması için gerekli bilgileri ve tercihleri saklar. Çerezler genellikle oturum bilgilerinizi hatırlamak, web sitesi trafiğini analiz etmek, içeriği kişiselleştirmek ve reklam deneyimini geliştirmek için kullanılır.</p>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Kullandığımız Çerez Türleri</h2>
            <p class="mb-4">Sitemizde aşağıdaki türde çerezler kullanılmaktadır:</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">1. Gerekli Çerezler</h3>
            <p class="mb-4">Bu çerezler web sitemizin temel işlevleri için gereklidir. Bunlar olmadan sitemiz beklendiği gibi çalışmaz ve oturum açma, form doldurma gibi temel özellikler devre dışı kalır. Bu çerezleri devre dışı bırakamazsınız.</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">2. Tercih Çerezleri</h3>
            <p class="mb-4">Bu çerezler, web sitemizi nasıl kullandığınızı hatırlamamıza yardımcı olur, örneğin dil tercihiniz veya bölge konumunuz gibi. Bu çerezler, web sitemizi her ziyaretinizde tercihleri yeniden ayarlamak zorunda kalmadan size daha kişiselleştirilmiş bir deneyim sağlar.</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">3. İstatistik Çerezleri</h3>
            <p class="mb-4">Bu çerezler, ziyaretçilerin web sitemizi nasıl kullandıkları hakkında bilgi toplar. Örneğin, hangi sayfaların en çok ziyaret edildiği veya kullanıcıların sitede ne kadar zaman geçirdiği gibi. Bu bilgiler, site performansını ve kullanıcı deneyimini iyileştirmemize yardımcı olur.</p>
            
            <h3 class="text-xl font-medium mb-3 mt-6">4. Pazarlama Çerezleri</h3>
            <p class="mb-4">Bu çerezler, web sitemizde veya diğer web sitelerinde görüntülediğiniz reklamları izlemek için kullanılır. Bu çerezler, size ve ilgi alanlarınıza daha uygun reklamlar göstermek için kullanılır.</p>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Çerez Yönetimi</h2>
            <p class="mb-4">Çoğu web tarayıcısı, çerezleri otomatik olarak kabul eder, ancak isterseniz tarayıcı ayarlarınızı değiştirerek çerezleri reddedebilir veya belirli çerezleri engelleyebilirsiniz. Tarayıcı ayarlarınızı değiştirmenin web sitemizin işlevselliğini etkileyebileceğini unutmayın.</p>
            
            <p class="mb-4">Çerezleri nasıl yönetebileceğiniz hakkında daha fazla bilgi için tarayıcınızın yardım sayfasına bakabilirsiniz:</p>
            
            <ul class="list-disc pl-8 mb-4">
                <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Google Chrome</a></li>
                <li><a href="https://support.mozilla.org/tr/kb/cerezleri-silme-web-sitelerinin-bilgilerini-kaldirma" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Mozilla Firefox</a></li>
                <li><a href="https://support.microsoft.com/tr-tr/microsoft-edge/microsoft-edge-de-tanımlama-bilgilerini-silme-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Microsoft Edge</a></li>
                <li><a href="https://support.apple.com/tr-tr/guide/safari/sfri11471/mac" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Safari</a></li>
            </ul>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">Politika Değişiklikleri</h2>
            <p class="mb-4">Çerez Politikamızı zaman zaman güncelleyebiliriz. Bu sayfada yapılan değişiklikleri düzenli olarak kontrol etmeniz önerilir. Bu politikada yapılan önemli değişiklikler hakkında sizi bilgilendirmek için elimizden geleni yapacağız.</p>
            
            <h2 class="text-2xl font-semibold mb-4 mt-8">İletişim</h2>
            <p class="mb-4">Çerez politikamız veya kişisel verilerinizin işlenmesi hakkında herhangi bir sorunuz veya endişeniz varsa, lütfen bizimle iletişime geçmekten çekinmeyin:</p>
            
            <p class="mb-4">
                E-posta: <a href="mailto:<?php echo htmlspecialchars(getSetting('site_email', 'info@example.com')); ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"><?php echo htmlspecialchars(getSetting('site_email', 'info@example.com')); ?></a><br>
                Web sitesi: <a href="<?php echo htmlspecialchars(getSetting('site_url', 'https://example.com')); ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"><?php echo htmlspecialchars(getSetting('site_url', 'https://example.com')); ?></a><br>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
