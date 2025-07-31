<?php
/**
 * AI Article Bot
 * Yapay zeka API'leri kullanarak otomatik makale üretir
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AIArticleImageManager.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AIArticleBot {
    private $db;
    private $client;
    private $logFile;
    private $categories;
    private $topics;
    private $imageManager;
    
    public function __construct() {
        global $db, $ai_bot_topics;
        
        $this->db = $db;
        $this->client = new Client();
        $this->logFile = AI_BOT_LOG_FILE;
        $this->topics = $ai_bot_topics;
        $this->imageManager = new AIArticleImageManager();
        
        // Log dizinini oluştur
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        // Kategorileri yükle
        $this->loadCategories();
        
        // AI settings tablosunu oluştur
        $this->initializeSettingsTable();
    }
    
    /**
     * AI settings tablosunu oluşturur
     */
    private function initializeSettingsTable() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_bot_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Articles tablosuna gerekli kolonları ekle
            $this->initializeArticlesTable();
        } catch (PDOException $e) {
            $this->log("UYARI: AI settings tablosu oluşturulamadı: " . $e->getMessage());
        }
    }
    
    /**
     * Articles tablosuna gerekli kolonları ekler
     */
    private function initializeArticlesTable() {
        try {
            // Tags kolonu var mı kontrol et
            $result = $this->db->query("SHOW COLUMNS FROM articles LIKE 'tags'");
            if ($result->rowCount() === 0) {
                $this->db->exec("ALTER TABLE articles ADD COLUMN tags VARCHAR(500) DEFAULT NULL AFTER content");
                $this->log("Tags kolonu articles tablosuna eklendi.");
            }
            
            // Slug kolonu var mı kontrol et  
            $result = $this->db->query("SHOW COLUMNS FROM articles LIKE 'slug'");
            if ($result->rowCount() === 0) {
                $this->db->exec("ALTER TABLE articles ADD COLUMN slug VARCHAR(200) DEFAULT NULL AFTER title");
                $this->log("Slug kolonu articles tablosuna eklendi.");
                
                // Unique index ekle
                try {
                    $this->db->exec("CREATE UNIQUE INDEX idx_slug ON articles (slug)");
                    $this->log("Slug unique index eklendi.");
                } catch (PDOException $e) {
                    // Index zaten varsa hata vermesin
                    if ($e->getCode() !== '42000') {
                        $this->log("UYARI: Slug index oluşturulamadı: " . $e->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            $this->log("UYARI: Articles tablosu kolonları kontrol edilemedi: " . $e->getMessage());
        }
    }
    
    /**
     * Veritabanından AI ayarını getirir
     */
    private function getAiSetting($key, $default = '') {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM ai_bot_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['setting_value'] : $default;
        } catch (PDOException $e) {
            $this->log("UYARI: Ayar okunamadı ($key): " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * API anahtarını güvenli şekilde getirir
     */
    private function getApiKey($provider) {
        // Önce veritabanından dene
        $dbKey = $this->getAiSetting($provider . '_api_key');
        if (!empty($dbKey)) {
            return $dbKey;
        }
        
        // Fallback: config.php sabitlerinden
        switch ($provider) {
            case 'gemini':
                return defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
            case 'grok':
                return defined('GROK_API_KEY') ? GROK_API_KEY : '';
            case 'huggingface':
                return defined('HUGGINGFACE_API_KEY') ? HUGGINGFACE_API_KEY : '';
            default:
                return '';
        }
    }
    
    /**
     * Bot'un aktif olup olmadığını kontrol eder
     */
    public function isBotEnabled() {
        // Önce veritabanından kontrol et
        $dbSetting = $this->getAiSetting('bot_enabled', '1');
        if ($dbSetting === '0') {
            return false;
        }
        
        // Config dosyasından kontrol et
        return defined('AI_BOT_ENABLED') ? AI_BOT_ENABLED : true;
    }
    
    /**
     * Varsayılan provider'ı getirir
     */
    private function getDefaultProvider() {
        // Önce veritabanından dene
        $dbProvider = $this->getAiSetting('default_provider');
        if (!empty($dbProvider)) {
            return $dbProvider;
        }
        
        // Fallback: config.php sabitinden
        return defined('AI_BOT_DEFAULT_PROVIDER') ? AI_BOT_DEFAULT_PROVIDER : 'gemini';
    }
    
    /**
     * Veritabanından kategorileri yükler
     */
    private function loadCategories() {
        try {
            $stmt = $this->db->query("SELECT id, name FROM categories WHERE status = 'active'");
            $this->categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($this->categories)) {
                $this->log("UYARI: Aktif kategori bulunamadı!");
            }
        } catch (PDOException $e) {
            $this->log("HATA: Kategoriler yüklenirken hata: " . $e->getMessage());
            $this->categories = [];
        }
    }
    
    /**
     * Ana makale üretme fonksiyonu
     */
    public function generateAndPublishArticle($provider = null) {
        try {
            if (!$this->isBotEnabled()) {
                $this->log("AI Bot devre dışı.");
                return false;
            }
            
            $provider = $provider ?: $this->getDefaultProvider();
            
            // Benzersiz konu bulmak için maksimum deneme sayısı
            $maxAttempts = 10;
            $topicData = null;
            $isTopicUnique = false;
            
            // Benzersiz bir konu bulana kadar dene
            for ($attempt = 1; $attempt <= $maxAttempts && !$isTopicUnique; $attempt++) {
                // Rastgele konu ve kategori seç
                $topicData = $this->getRandomTopic();
                $topic = $topicData['topic'];
                $categoryId = $topicData['category_id'];
                $categoryName = $topicData['category_name'];
                
                // Bu konu ve kategori kombinasyonunun benzeri var mı?
                $isTopicUnique = !$this->isSimilarTopicExists($topic, $categoryId);
                
                if (!$isTopicUnique) {
                    $this->log("Deneme #$attempt: Konu '$topic' ($categoryName) daha önce kullanılmış, yeni konu seçiliyor...");
                }
            }
            
            if (!$isTopicUnique) {
                $this->log("UYARI: $maxAttempts deneme sonrası benzersiz konu bulunamadı. En son seçilen konu ile devam ediliyor.");
            }
            
            $topic = $topicData['topic'];
            $categoryId = $topicData['category_id'];
            $categoryName = $topicData['category_name'];
            
            $this->log("Seçilen kategori: $categoryName (ID: $categoryId), konu: $topic");
            
            // Makale üret
            $article = $this->generateArticle($topic, $provider);
            
            if (!$article) {
                $this->log("HATA: Makale üretilemedi.");
                return false;
            }
            
            // Benzersiz başlık kontrolü
            if ($this->isTitleExists($article['title'])) {
                $this->log("UYARI: Benzer başlık zaten var, başlığa benzersiz ek yapılıyor.");
                $article['title'] = $this->makeUniqueTitle($article['title']);
            }
            
            // Kategori bilgisini makaleye ekle
            $article['category_id'] = $categoryId;
            $article['category_name'] = $categoryName;
            
            // Makale için resimleri indir
            $this->log("Makale için resimler indiriliyor...");
            $images = $this->imageManager->downloadImagesForArticle($categoryName, $topic, $article['title']);
            
            // Resimleri makaleye ekle
            if (!empty($images)) {
                // Kapak resmi
                if (isset($images['cover'])) {
                    // AIArticleImageManager artık dizi veya string döndürebilir
                    if (is_array($images['cover'])) {
                        $article['featured_image'] = $images['cover']['filename'];
                    } else {
                        $article['featured_image'] = $images['cover'];
                    }
                    $this->log("Kapak resmi eklendi: " . $article['featured_image']);
                }
                
                // İçerik resimlerini makale metnine ekle
                $this->log("İçerik resimlerini makale metnine ekliyorum...");
                $content_before = $article['content'];
                $article['content'] = $this->insertImagesIntoContent($article['content'], $images);
                
                // İçerik değişip değişmediğini kontrol et (resim eklenip eklenmediğini anlamak için)
                if ($content_before !== $article['content']) {
                    $this->log("Makale içeriğine resimler başarıyla eklendi.");
                } else {
                    $this->log("UYARI: İçerik resmi eklenemedi, içerik değişmedi.");
                }
            } else {
                $this->log("UYARI: Hiç resim indirilemedi.");
            }
            
            // Makaleyi veritabanına kaydet
            $articleId = $this->saveArticle($article);
            
            if ($articleId) {
                $this->log("BAŞARILI: Makale kaydedildi - ID: $articleId, Kategori: $categoryName, Başlık: " . $article['title']);
                return $articleId;
            } else {
                $this->log("HATA: Makale kaydedilemedi.");
                return false;
            }
            
        } catch (Exception $e) {
            $this->log("HATA: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rastgele kategori ve konu seçer - veritabanı kategorilerine göre
     */
    private function getRandomTopic() {
        // Önce veritabanından rastgele kategori seç
        if (empty($this->categories)) {
            // Kategoriler yüklenemediği için fallback yap
            $categoryKeys = array_keys($this->topics);
            $randomCategory = $categoryKeys[array_rand($categoryKeys)];
            $randomSubtopic = $this->topics[$randomCategory][array_rand($this->topics[$randomCategory])];
            
            $this->log("UYARI: Veritabanı kategorisi kullanılamadı, fallback konu: $randomSubtopic");
            return [
                'topic' => $randomSubtopic,
                'category_id' => null,
                'category_name' => $randomCategory
            ];
        }
        
        // Rastgele kategori seç
        $randomCategory = $this->categories[array_rand($this->categories)];
        $categoryId = $randomCategory['id'];
        $categoryName = $randomCategory['name'];
        
        // Kategori adına göre konu belirle
        $topic = $this->getTopicByCategory($categoryName);
        
        $this->log("Seçilen kategori: $categoryName (ID: $categoryId), konu: $topic");
        
        return [
            'topic' => $topic,
            'category_id' => $categoryId,
            'category_name' => $categoryName
        ];
    }
    
    /**
     * Kategori adına göre uygun konu belirler
     */
    private function getTopicByCategory($categoryName) {
        $categoryName = mb_strtolower($categoryName, 'UTF-8');
        
        // Geniş kategorilere göre daha zengin konu havuzu
        $categoryMapping = [
            // Teknoloji kategorileri
            'teknoloji' => [
                'yapay zeka ve günlük hayat', 'teknoloji trendleri', 'dijital dönüşüm', 
                'siber güvenlik', 'blockchain teknolojisi', 'metaverse', 'büyük veri analizi',
                'nesnelerin interneti', 'sanal gerçeklik', 'artırılmış gerçeklik', 'akıllı şehirler',
                'bulut bilişim', 'robotik', 'otomasyon sistemleri', 'yazılım geliştirme',
                'yapay zeka etiği', 'veri gizliliği', 'dijital detoks', 'kodlama eğitimi',
                'kuantum bilişim', 'sürdürülebilir teknoloji', '5G ve ötesi', 'biyoteknoloji'
            ],
            'bilgisayar' => [
                'yapay zeka uygulamaları', 'oyun geliştirme', 'web teknolojileri', 
                'donanım yenilikleri', 'veri depolama teknolojileri', 'işletim sistemleri',
                'masaüstü virtualizasyon', 'bilgisayar güvenliği', 'özgür yazılım',
                'bulut tabanlı hizmetler', 'kodlama dilleri', 'mikroişlemciler',
                'bilgisayar ağları', 'grafik kartları', 'süper bilgisayarlar',
                'giyilebilir teknoloji', 'retro bilgisayarlar', 'sunucu teknolojileri'
            ],
            'yazılım' => [
                'yazılım geliştirme metodolojileri', 'programlama dilleri', 'uygulama geliştirme', 
                'mobil yazılım', 'gömülü sistemler', 'DevOps', 'açık kaynak yazılım',
                'yazılım testleri', 'veri tabanı sistemleri', 'yazılım mimarisi',
                'API geliştirme', 'mikroservisler', 'yapay zeka algoritmaları',
                'kullanıcı arayüzü tasarımı', 'kod kalitesi', 'dağıtık sistemler',
                'sürekli entegrasyon', 'yazılım lisanslama', 'yazılım eğitimi'
            ],
            'internet' => [
                'dijital pazarlama', 'arama motoru optimizasyonu', 'içerik yönetimi', 
                'sosyal medya stratejileri', 'e-ticaret', 'web güvenliği',
                'çevrimiçi gizlilik', 'internet hukuku', 'web hosting',
                'bulut bilişim', 'internet protokolleri', 'web standartları',
                'alan adları', 'internet hızları', 'web erişilebilirliği',
                'internet bağımlılığı', 'dijital itibar yönetimi', 'çevrimiçi topluluklar',
                'veri ekonomisi', 'internet ansiklopedileri', 'podcast yayıncılığı'
            ],
            
            // Sağlık kategorileri
            'sağlık' => [
                'sağlıklı yaşam', 'bağışıklık sistemi güçlendirme', 'kronik hastalıklar', 
                'zihinsel sağlık', 'stres yönetimi', 'uyku kalitesi',
                'holistik sağlık', 'alternatif tıp', 'sağlık teknolojileri',
                'tıbbi inovasyonlar', 'önleyici sağlık', 'biyoteknoloji',
                'genetik testler', 'kişiselleştirilmiş tıp', 'aşılar',
                'yaşlanma karşıtı yöntemler', 'çevresel sağlık', 'sağlık politikaları',
                'dijital sağlık', 'giyilebilir sağlık cihazları', 'sağlık eşitsizlikleri'
            ],
            'tıp' => [
                'modern tıp yöntemleri', 'cerrahi inovasyonlar', 'telemedicine', 
                'ilaç araştırmaları', 'gen tedavisi', 'tıbbi yapay zeka',
                'kanser tedavileri', 'organ nakli', 'kök hücre araştırmaları',
                'nadir hastalıklar', 'nöroloji', 'kardiyoloji',
                'pediatri', 'geriatri', 'psikiyatri',
                'tıbbi etik', 'bulaşıcı hastalıklar', 'aşı geliştirme',
                'genom düzenleme', 'biyonik uzuvlar', 'robotik cerrahi'
            ],
            'beslenme' => [
                'dengeli beslenme', 'besin takviyeleri', 'sürdürülebilir beslenme', 
                'vejetaryen ve vegan beslenme', 'probiyotikler', 'antioksidanlar',
                'makro ve mikro besinler', 'metabolizma sağlığı', 'diyet trendleri',
                'detoks diyetleri', 'gıda intoleransları', 'beslenme ve bağışıklık',
                'besin değerleri', 'su ve hidrasyon', 'besin etiketleri okuma',
                'yerel ve mevsimsel beslenme', 'karbonhidratlar', 'proteinler',
                'sağlıklı yağlar', 'şeker tüketimi', 'gıda katkı maddeleri'
            ],
            'spor' => [
                'egzersiz türleri', 'evde fitness', 'spor ve zihinsel sağlık', 
                'antrenman programları', 'performans geliştirme', 'spor yaralanmaları',
                'spor beslenmesi', 'kardiyovasküler egzersizler', 'dayanıklılık antrenmanı',
                'esneklik ve mobilite', 'spor psikolojisi', 'aktif yaşam',
                'spor teknolojileri', 'takım sporları', 'bireysel sporlar',
                'doğa sporları', 'su sporları', 'kış sporları',
                'olimpik sporlar', 'ekstrem sporlar', 'yoga ve pilates'
            ],
            
            // Eğitim kategorileri
            'eğitim' => [
                'öğrenme yöntemleri', 'eğitim teknolojileri', 'uzaktan eğitim', 
                'yaşam boyu öğrenme', 'STEM eğitimi', 'erken çocukluk eğitimi',
                'eğitimde yapay zeka', 'öğrenci merkezli eğitim', 'yenilikçi pedagoji',
                'öğrenme güçlükleri', 'eğitimde eşitlik', 'okul sistemleri',
                'eğitim reformları', 'öğretmen eğitimi', 'müfredat geliştirme',
                'ölçme ve değerlendirme', 'eğitimde dijitalleşme', 'küresel eğitim',
                'karakter eğitimi', 'yaratıcı eğitim', 'sanat eğitimi'
            ],
            'öğrenme' => [
                'hızlı öğrenme teknikleri', 'beyin gelişimi', 'bellek güçlendirme', 
                'motivasyon ve öğrenme', 'öğrenme stilleri', 'kritik düşünme',
                'problem çözme becerileri', 'yaratıcı düşünme', 'dil öğrenimi',
                'matematiksel düşünme', 'bilişsel bilimler', 'öğrenme psikolojisi',
                'nörogelişim', 'okuma becerileri', 'konsantrasyon artırma',
                'not alma teknikleri', 'sınav hazırlık', 'yaşam boyu öğrenme',
                'öğrenme engelleri', 'dikkat geliştirme', 'kavram haritaları'
            ],
            'kariyer' => [
                'kariyer gelişimi', 'iş hayatında başarı', 'geleceğin meslekleri', 
                'profesyonel beceriler', 'liderlik', 'girişimcilik',
                'iş arama stratejileri', 'CV hazırlama', 'mülakat teknikleri',
                'networking', 'uzaktan çalışma', 'dijital nomadlık',
                'iş-yaşam dengesi', 'kariyer değişimi', 'mesleki eğitim',
                'çevrimiçi portföyler', 'freelance çalışma', 'yan gelir kaynakları',
                'profesyonel marka oluşturma', 'mentorluk', 'kariyer koçluğu'
            ],
            'gelişim' => [
                'kişisel gelişim', 'özgüven geliştirme', 'alışkanlık oluşturma', 
                'zaman yönetimi', 'verimlilik', 'hedef belirleme',
                'öz disiplin', 'duygusal zeka', 'iletişim becerileri',
                'stres yönetimi', 'mindfulness', 'pozitif psikoloji',
                'uyku optimizasyonu', 'sabah rutinleri', 'meditasyon',
                'kişisel finans', 'beyin egzersizleri', 'yaratıcılık geliştirme',
                'sosyal beceriler', 'dinleme becerileri', 'empati geliştirme'
            ],
            
            // Bilim kategorileri
            'bilim' => [
                'bilimsel keşifler', 'fizik dünyası', 'uzay araştırmaları', 
                'kimya yenilikleri', 'biyoloji ve yaşam', 'jeoloji',
                'astronomi', 'kuantum fiziği', 'evrim teorisi',
                'iklim bilimi', 'nörobilim', 'bilim tarihi',
                'bilimsel yöntem', 'biyoteknoloji', 'nanoteknoloji',
                'sürdürülebilir bilim', 'bilim ve etik', 'popüler bilim',
                'bilimsel okuryazarlık', 'bilim felsefesi', 'bilimsel merak'
            ],
            'araştırma' => [
                'araştırma metodolojileri', 'veri analizi', 'bilimsel yayınlar', 
                'akademik araştırma', 'pazar araştırması', 'kullanıcı deneyimi araştırması',
                'nitel araştırma', 'nicel araştırma', 'vaka çalışmaları',
                'literatür taraması', 'deneysel tasarım', 'araştırma etiği',
                'hipotez oluşturma', 'araştırma fonları', 'disiplinler arası araştırma',
                'açık erişim bilim', 'inovasyon araştırması', 'toplumsal araştırmalar',
                'araştırma ve geliştirme', 'keşifsel araştırma', 'bilimsel iş birliği'
            ],
            'çevre' => [
                'sürdürülebilirlik', 'iklim değişikliği', 'biyoçeşitlilik', 
                'yenilenebilir enerji', 'su kaynakları', 'hava kirliliği',
                'geri dönüşüm', 'plastik kirliliği', 'ekolojik ayak izi',
                'doğa koruma', 'çevre dostu yaşam', 'yeşil binalar',
                'karbon ayak izi', 'orman koruması', 'deniz ekosistemi',
                'tarım ve çevre', 'sürdürülebilir ulaşım', 'temiz enerji',
                'döngüsel ekonomi', 'sıfır atık', 'çevresel politikalar'
            ],
            'uzay' => [
                'uzay keşfi', 'astronomi', 'exogezegenler', 
                'Mars keşfi', 'uzay istasyonları', 'uzay turizmi',
                'kara delikler', 'yıldız oluşumu', 'galaksiler',
                'asteroidler ve kuyruklu yıldızlar', 'uzay teleskobu', 'uzay hukuku',
                'uzay madenciliği', 'uzay kolonizasyonu', 'uzay aracı teknolojisi',
                'uzayda yaşam', 'kozmoloji', 'uzay ve zaman',
                'güneş sistemi', 'uzay tarihi', 'SETI araştırmaları'
            ],
            
            // Yaşam kategorileri
            'yaşam' => [
                'yaşam tarzı', 'minimalizm', 'sürdürülebilir yaşam', 
                'ev dekorasyonu', 'seyahat', 'hobi edinme',
                'dijital yaşam', 'sosyal ilişkiler', 'sağlıklı rutinler',
                'bütçe yönetimi', 'zaman yönetimi', 'iş-yaşam dengesi',
                'aile hayatı', 'evde verimlilik', 'yaşam memnuniyeti',
                'sosyal medya detoksu', 'ev organizasyonu', 'hayat becerileri',
                'kendine zaman ayırma', 'sabah rutinleri', 'kişisel bakım'
            ],
            'kültür' => [
                'kültürel miras', 'dünya kültürleri', 'popüler kültür', 
                'kültürel kimlik', 'gelenekler', 'festival ve kutlamalar',
                'dil ve kültür', 'müzik tarihi', 'sanat akımları',
                'edebiyat dünyası', 'sinema kültürü', 'yemek kültürü',
                'kültürel çeşitlilik', 'kültürel değişim', 'mitoloji',
                'folklor', 'yerli kültürler', 'dijital kültür',
                'kültür ve toplum', 'kültürlerarası iletişim', 'kültürel antropoloji'
            ],
            'seyahat' => [
                'gezi rehberleri', 'macera seyahatleri', 'kültür turları', 
                'gastronomi turizmi', 'eko-turizm', 'bütçe dostu seyahat',
                'lüks seyahat', 'solo seyahat', 'aile seyahatleri',
                'seyahat fotoğrafçılığı', 'dijital göçebelik', 'ulaşım ipuçları',
                'konaklama seçenekleri', 'seyahat sigortası', 'yerel deneyimler',
                'gizli kalmış yerler', 'şehir turları', 'tarih ve seyahat',
                'doğa yürüyüşleri', 'kış tatili destinasyonları', 'plaj tatilleri'
            ],
            'hobi' => [
                'yaratıcı hobiler', 'el işi projeleri', 'koleksiyon yapma', 
                'bahçecilik', 'yemek pişirme', 'fotoğrafçılık',
                'müzik çalma', 'çizim ve resim', 'yazı yazma',
                'el sanatları', 'ahşap işleri', 'dijital sanat',
                'dikiş ve nakış', 'seramik yapımı', 'kitap okuma',
                'spor ve hobiler', 'doğa hobiler', 'teknoloji hobiler',
                'zeka oyunları', 'bilim deneyleri', 'model yapımı'
            ],
            
            // Diğer kategoriler
            'ekonomi' => [
                'ekonomi trendleri', 'finansal okuryazarlık', 'yatırım stratejileri', 
                'kripto para ekonomisi', 'sürdürülebilir ekonomi', 'dijital ekonomi',
                'küresel ekonomi', 'mikro ekonomi', 'makro ekonomi',
                'ekonomik politikalar', 'gelir eşitsizliği', 'tüketici davranışları',
                'ekonomik büyüme', 'enflasyon', 'ekonomik göstergeler',
                'iş ekonomisi', 'davranışsal ekonomi', 'ekonomi tarihi',
                'ekonomi ve teknoloji', 'ekonomi eğitimi', 'ekonomik krizler'
            ],
            'finans' => [
                'kişisel finans', 'yatırım temelleri', 'emeklilik planlaması', 
                'borç yönetimi', 'bütçe oluşturma', 'vergi planlaması',
                'finansal bağımsızlık', 'pasif gelir', 'borsa yatırımı',
                'gayrimenkul yatırımı', 'kripto para yatırımı', 'finansal hedefler',
                'sigorta planlaması', 'finansal teknoloji', 'banka hizmetleri',
                'finansal eğitim', 'aile bütçesi', 'öğrenci kredileri',
                'emeklilik fonları', 'finansal acil durum fonu', 'miras planlaması'
            ],
            'iş' => [
                'girişimcilik', 'iş stratejileri', 'küçük işletmeler', 
                'e-ticaret', 'iş modelleri', 'pazarlama',
                'satış teknikleri', 'müşteri ilişkileri', 'insan kaynakları',
                'liderlik becerileri', 'takım yönetimi', 'şirket kültürü',
                'iş etiği', 'iş hukuku', 'dijital dönüşüm',
                'iş analizi', 'inovasyon', 'proje yönetimi',
                'risk yönetimi', 'kriz yönetimi', 'işletme finansmanı'
            ],
            'politika' => [
                'siyaset bilimi', 'küresel ilişkiler', 'demokrasi', 
                'insan hakları', 'seçim sistemleri', 'siyasi partiler',
                'siyasi düşünce akımları', 'kamu politikaları', 'uluslararası ilişkiler',
                'diplomasi', 'göç politikaları', 'çevre politikaları',
                'sağlık politikaları', 'eğitim politikaları', 'savunma politikaları',
                'siyasi liderlik', 'politik ekonomi', 'toplumsal hareketler',
                'siyasi sistemler', 'vatandaşlık', 'politik iletişim'
            ],
            'hukuk' => [
                'temel hukuk bilgisi', 'hukuk sistemleri', 'anayasa hukuku', 
                'ceza hukuku', 'medeni hukuk', 'iş hukuku',
                'tüketici hakları', 'telif hakları', 'patent hukuku',
                'dijital hukuk', 'siber hukuk', 'hukuk etiği',
                'insan hakları hukuku', 'çevre hukuku', 'uluslararası hukuk',
                'aile hukuku', 'sözleşme hukuku', 'gayrimenkul hukuku',
                'adalet sistemi', 'alternatif uyuşmazlık çözümü', 'hukuk tarihi'
            ],
            'sanat' => [
                'sanat tarihi', 'çağdaş sanat', 'dijital sanat', 
                'resim', 'heykel', 'fotoğrafçılık',
                'grafik tasarım', 'enstalasyon sanatı', 'performans sanatı',
                'video sanatı', 'sanat teorisi', 'sanat eleştirisi',
                'sanat koleksiyonu', 'sanat pazarı', 'sanat müzeleri',
                'sanat eğitimi', 'sanat terapisi', 'sokak sanatı',
                'sanat ve toplum', 'sanat ve teknoloji', 'sanat ve politika'
            ]
        ];
        
        // En uygun kategoriyi bul
        foreach ($categoryMapping as $key => $topics) {
            if (strpos($categoryName, $key) !== false) {
                $selectedTopic = $topics[array_rand($topics)];
                $this->log("Kategori eşleştirme: '$categoryName' -> '$key' -> '$selectedTopic'");
                return $selectedTopic;
            }
        }
        
        // Genel Konular - Eşleşme bulunamazsa
        $generalTopics = [
            'güncel konular', 'yaşam tarzı', 'kişisel gelişim', 'bilgi ve kültür',
            'toplumsal konular', 'günlük yaşam', 'pratik bilgiler', 'genel kültür',
            'doğa ve çevre', 'dijital dönüşüm', 'sağlıklı yaşam', 'sosyal medya',
            'zaman yönetimi', 'yaratıcılık', 'iletişim becerileri', 'duygusal zeka',
            'verimlilik', 'öğrenme stratejileri', 'yaşam kalitesi', 'motivasyon',
            'ilişkiler', 'aile yaşamı', 'bilinçli farkındalık', 'beyin sağlığı',
            'uyku kalitesi', 'stres azaltma', 'zihinsel dinginlik', 'doğal yaşam',
            'beslenme düzeni', 'hareket ve egzersiz', 'bağışıklık güçlendirme'
        ];
        
        $selectedTopic = $generalTopics[array_rand($generalTopics)];
        $this->log("Kategori eşleştirme bulunamadı: '$categoryName' -> genel konu: '$selectedTopic'");
        
        return $selectedTopic;
    }
    
    /**
     * Makale içeriğine resimleri yerleştirir
     */
    private function insertImagesIntoContent($content, $images) {
        if (empty($images)) {
            $this->log("Resim ekleme: Resim yok, içerik olduğu gibi kalacak");
            return $content;
        }
        
        // H2 başlıklarını bul ve resim ekleme noktalarını belirle
        $h2Pattern = '/<h2[^>]*>(.*?)<\/h2>/i';
        preg_match_all($h2Pattern, $content, $h2Matches, PREG_OFFSET_CAPTURE);
        
        if (empty($h2Matches[0])) {
            // H2 yoksa paragraflara göre böl
            $paragraphs = explode('</p>', $content);
            $insertPoints = [];
            
            if (count($paragraphs) >= 3) {
                $insertPoints[] = round(count($paragraphs) / 3);
                $insertPoints[] = round(2 * count($paragraphs) / 3);
            }
        } else {
            // H2'lere göre resim yerleştirme noktaları
            $insertPoints = [];
            $h2Count = count($h2Matches[0]);
            
            if ($h2Count >= 2) {
                $insertPoints[] = 1; // İkinci H2'den sonra
                if ($h2Count >= 3) {
                    $insertPoints[] = 2; // Üçüncü H2'den sonra
                }
            }
        }
        
        // Resimleri içeriğe ekle
        $imageIndex = 0;
        $availableImages = [];
        
        // İçerik resimlerini hazırla
        foreach ($images as $key => $image) {
            if (strpos($key, 'content_') === 0 && $image) {
                $availableImages[] = $image;
                $this->log("Eklenecek içerik resmi: " . (is_array($image) ? json_encode($image) : $image));
            }
        }
        
        if (empty($availableImages)) {
            $this->log("İçerik resmi yok, içerik olduğu gibi kalacak");
            return $content;
        }
        
        $this->log("Eklenecek resim sayısı: " . count($availableImages));
        
        // H2 tabanlı ekleme
        if (!empty($h2Matches[0])) {
            $offset = 0;
            
            foreach ($insertPoints as $point) {
                if ($point < count($h2Matches[0]) && $imageIndex < count($availableImages)) {
                    $image = $availableImages[$imageIndex];
                    $insertPos = $h2Matches[0][$point][1] + strlen($h2Matches[0][$point][0]) + $offset;
                    
                    $imageHtml = $this->createImageHtml($image);
                    $content = substr_replace($content, $imageHtml, $insertPos, 0);
                    
                    $offset += strlen($imageHtml);
                    $imageIndex++;
                    $this->log("H2 tabanlı resim eklendi: #" . $imageIndex);
                }
            }
        } else {
            // Paragraf tabanlı ekleme
            $paragraphs = explode('</p>', $content);
            $insertedCount = 0;
            
            foreach ($insertPoints as $point) {
                if ($point < count($paragraphs) - 1 && $insertedCount < count($availableImages)) {
                    $image = $availableImages[$insertedCount];
                    $imageHtml = $this->createImageHtml($image);
                    
                    $paragraphs[$point] .= '</p>' . $imageHtml;
                    $insertedCount++;
                    $this->log("Paragraf tabanlı resim eklendi: #" . $insertedCount);
                }
            }
            
            $content = implode('</p>', $paragraphs);
        }
        
        return $content;
    }
    
    /**
     * Resim için HTML oluşturur
     */
    private function createImageHtml($image) {
        // $image string (dosya yolu) veya array (resim bilgileri) olabilir
        if (is_array($image)) {
            if (isset($image['url'])) {
                $imageUrl = htmlspecialchars($image['url']);
            } elseif (isset($image['filename'])) {
                $imageUrl = '/uploads/ai_images/' . htmlspecialchars($image['filename']);
            } else {
                // Geçersiz resim dizisi - log ve geriye boş div döndür
                $this->log("HATA: Geçersiz resim dizisi: " . json_encode($image));
                return "<div class='article-image-placeholder'></div>";
            }
        } else {
            // String olarak geliyorsa dosya yolu olarak kabul et
            if (strpos($image, '/') === 0) {
                // Zaten / ile başlıyorsa, olduğu gibi kullan
                $imageUrl = htmlspecialchars($image);
            } else {
                // Aksi halde uploads/ai_images/ altında olduğunu varsay
                $imageUrl = '/uploads/ai_images/' . htmlspecialchars($image);
            }
        }
        
        $imageAlt = 'Makale Görseli';
        
        $this->log("Resim HTML oluşturuldu: " . $imageUrl);
        
        return "<div class='article-image' style='margin: 20px 0; text-align: center;'>
                    <img src='$imageUrl' alt='$imageAlt' style='max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' loading='lazy'>
                </div>";
    }
    
    /**
     * AI provider'a göre makale üretir
     */
    private function generateArticle($topic, $provider) {
        switch ($provider) {
            case 'gemini':
                return $this->generateWithGemini($topic);
            case 'grok':
                return $this->generateWithGrok($topic);
            case 'huggingface':
                return $this->generateWithHuggingFace($topic);
            default:
                throw new Exception("Geçersiz AI provider: $provider");
        }
    }
    
    /**
     * Google Gemini ile makale üretir
     */
    private function generateWithGemini($topic) {
        $apiKey = $this->getApiKey('gemini');
        if (empty($apiKey)) {
            throw new Exception("Gemini API key tanımlanmamış");
        }
        
        $prompt = $this->buildPrompt($topic);
        
        try {
            $response = $this->client->post('https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1000,
                    ]
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                throw new Exception("Gemini API'den geçersiz yanıt");
            }
            
            return $this->parseGeneratedContent($data['candidates'][0]['content']['parts'][0]['text']);
            
        } catch (RequestException $e) {
            throw new Exception("Gemini API hatası: " . $e->getMessage());
        }
    }
    
    /**
     * xAI Grok ile makale üretir
     */
    private function generateWithGrok($topic) {
        $apiKey = $this->getApiKey('grok');
        if (empty($apiKey)) {
            throw new Exception("Grok API key tanımlanmamış");
        }
        
        $prompt = $this->buildPrompt($topic);
        
        try {
            $response = $this->client->post('https://api.x.ai/v1/chat/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ],
                'json' => [
                    'model' => 'grok-beta',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new Exception("Grok API'den geçersiz yanıt");
            }
            
            return $this->parseGeneratedContent($data['choices'][0]['message']['content']);
            
        } catch (RequestException $e) {
            throw new Exception("Grok API hatası: " . $e->getMessage());
        }
    }
    
    /**
     * Hugging Face ile makale üretir
     */
    private function generateWithHuggingFace($topic) {
        $apiKey = $this->getApiKey('huggingface');
        if (empty($apiKey)) {
            throw new Exception("Hugging Face API key tanımlanmamış");
        }
        
        $prompt = $this->buildPrompt($topic);
        
        try {
            $response = $this->client->post('https://api-inference.huggingface.co/models/microsoft/DialoGPT-large', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'inputs' => $prompt,
                    'parameters' => [
                        'max_length' => 1000,
                        'temperature' => 0.7,
                        'do_sample' => true
                    ]
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data[0]['generated_text'])) {
                throw new Exception("Hugging Face API'den geçersiz yanıt");
            }
            
            return $this->parseGeneratedContent($data[0]['generated_text']);
            
        } catch (RequestException $e) {
            throw new Exception("Hugging Face API hatası: " . $e->getMessage());
        }
    }
    
    /**
     * AI için prompt oluşturur
     */
    private function buildPrompt($topic) {
        // Günün tarihini al ve formatla
        $currentDate = date('d.m.Y');
        $currentYear = date('Y');
        
        // Rastgele yazı stili seçenekleri
        $styles = [
            'bilgilendirici ve detaylı',
            'ilgi çekici ve akıcı',
            'karşılaştırmalı ve analitik',
            'sohbet tarzı ve samimi',
            'açıklayıcı ve öğretici',
            'güncel ve yenilikçi',
            'derinlemesine ve kapsamlı',
            'pratik ve uygulanabilir'
        ];
        $randomStyle = $styles[array_rand($styles)];
        
        // Rastgele hedef kitle seçenekleri
        $audiences = [
            'genel okuyucular',
            'meraklı araştırmacılar',
            'profesyoneller',
            'gençler',
            'yetişkinler',
            'öğrenciler',
            'yaşlılar',
            'ebeveynler',
            'meslek sahipleri'
        ];
        $randomAudience = $audiences[array_rand($audiences)];
        
        // Benzersiz anahtar kelime eklemek için önemli sektörler/alanlar
        $domains = [
            'teknoloji', 'sağlık', 'eğitim', 'spor', 'bilim', 
            'sanat', 'ekonomi', 'finans', 'doğa', 'çevre', 
            'psikoloji', 'beslenme', 'iş dünyası', 'sosyal medya',
            'dijital', 'kişisel gelişim', 'yaşam tarzı', 'tarih',
            'toplum', 'kültür', 'bilgi'
        ];
        $randomDomain1 = $domains[array_rand($domains)];
        $randomDomain2 = $domains[array_rand($domains)];
        
        return "Lütfen '$topic' konusunda $randomStyle bir Türkçe makale yazın. Makale $currentDate tarihinde $randomAudience için yazılıyor ve $randomDomain1 ve $randomDomain2 alanlarına da değinmeli. Makale şu kriterlere uygun olmalı:

1. Başlık benzersiz ve çekici olmalı, maksimum 60 karakter içinde özgün olmalı ve $currentYear yılına uygun olmalı
2. Makale 450-550 kelime arasında olmalı
3. Makale yapısı: Giriş paragrafı, 3-4 ana bölüm (H2 başlıklar ile), sonuç paragrafı
4. Her bölümde 2-3 paragraf bulunmalı, paragraflar 3-4 cümleden oluşmalı
5. Alt başlıklar (H2) yaratıcı ve benzersiz olmalı, yaygın başlıklar kullanmaktan kaçının
6. Makalede güncel ve özgün bilgilendirici içerik olmalı, bilinen genel bilgileri tekrarlamaktan kaçının
7. Makale hem bilgilendirici hem de okuyucunun ilgisini çekecek şekilde yazılmalı
8. Konuya farklı bir bakış açısı veya yaklaşım getirin, sıradan ve klişe anlatımlardan kaçının
9. En az 5 tane özgün, konu ile alakalı ve spesifik anahtar kelime/etiket ekleyin

Yanıtınızı şu HTML formatında verin:
BAŞLIK: [makale başlığı]
İÇERİK: 
<p>[Giriş paragrafı - konuyu tanıtan kısa bir giriş]</p>

<h2>[İlk Ana Başlık]</h2>
<p>[İlk bölüm birinci paragraf]</p>
<p>[İlk bölüm ikinci paragraf]</p>

<h2>[İkinci Ana Başlık]</h2>
<p>[İkinci bölüm birinci paragraf]</p>
<p>[İkinci bölüm ikinci paragraf]</p>

<h2>[Üçüncü Ana Başlık]</h2>
<p>[Üçüncü bölüm birinci paragraf]</p>
<p>[Üçüncü bölüm ikinci paragraf]</p>

<p>[Sonuç paragrafı - konuyu özetleyen ve değerlendiren sonuç]</p>

ETİKETLER: [virgülle ayrılmış 4-6 adet etiket]

Önemli: Her paragraf <p> etiketleri arasında olmalı, başlıklar <h2> etiketleri arasında olmalı. Makaleyi yazın:";
    }
    
    /**
     * AI'dan gelen içeriği parse eder
     */
    private function parseGeneratedContent($content) {
        $content = trim($content);
        
        // Başlık çıkar
        if (preg_match('/BAŞLIK:\s*(.+?)(?=\n|İÇERİK:)/s', $content, $titleMatches)) {
            $title = trim($titleMatches[1]);
        } else {
            // Fallback: İlk satırı başlık olarak al
            $lines = explode("\n", $content);
            $title = trim($lines[0]);
        }
        
        // İçerik çıkar
        if (preg_match('/İÇERİK:\s*(.+?)(?=ETİKETLER:|$)/s', $content, $contentMatches)) {
            $articleContent = trim($contentMatches[1]);
        } else {
            // Fallback: Başlıktan sonrasını içerik olarak al
            $parts = explode("\n", $content, 2);
            $articleContent = isset($parts[1]) ? trim($parts[1]) : $content;
        }
        
        // Etiketler çıkar
        if (preg_match('/ETİKETLER:\s*(.+?)$/s', $content, $tagMatches)) {
            $tags = trim($tagMatches[1]);
        } else {
            // Fallback: Konuya göre otomatik etiket oluştur
            $tags = $this->generateDefaultTags();
        }
        
        // Başlığı 100 karakterle sınırla
        if (strlen($title) > 100) {
            $title = substr($title, 0, 97) . '...';
        }
        
        // İçeriği formatla ve temizle
        $articleContent = $this->formatContent($articleContent);
        $articleContent = $this->cleanContent($articleContent);
        
        return [
            'title' => $title,
            'content' => $articleContent,
            'tags' => $tags
        ];
    }
    
    /**
     * İçeriği formatlar - HTML yapısı yoksa ekler
     */
    private function formatContent($content) {
        // Eğer içerik zaten HTML formatında ise doğrudan döndür
        if (strpos($content, '<p>') !== false || strpos($content, '<h2>') !== false) {
            return $content;
        }
        
        // Düz metin ise HTML formatına çevir
        $lines = explode("\n", $content);
        $formatted = '';
        $inParagraph = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Boş satır
            if (empty($line)) {
                if ($inParagraph) {
                    $formatted .= '</p>' . "\n\n";
                    $inParagraph = false;
                }
                continue;
            }
            
            // Başlık tespiti (büyük harf ile başlayan ve kısa satırlar)
            if (mb_strlen($line) < 50 && preg_match('/^[A-ZÇĞIŞÜÖ]/', $line) && !$inParagraph) {
                $formatted .= '<h2>' . $line . '</h2>' . "\n";
                continue;
            }
            
            // Normal paragraf
            if (!$inParagraph) {
                $formatted .= '<p>';
                $inParagraph = true;
            }
            
            $formatted .= $line . ' ';
        }
        
        // Son paragrafı kapat
        if ($inParagraph) {
            $formatted .= '</p>';
        }
        
        return $formatted;
    }
    
    /**
     * İçeriği temizler ve güvenli hale getirir
     */
    private function cleanContent($content) {
        // İzin verilen HTML taglerini koruyarak temizle
        $allowedTags = '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote>';
        $content = strip_tags($content, $allowedTags);
        
        // Boş paragrafları temizle
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        
        // Fazla boşlukları temizle ama paragraf yapısını koru
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Paragraflar arası fazla boşlukları düzenle
        $content = preg_replace('/(<\/p>)\s*(<h[1-6])/', '$1' . "\n\n" . '$2', $content);
        $content = preg_replace('/(<\/h[1-6]>)\s*(<p>)/', '$1' . "\n" . '$2', $content);
        $content = preg_replace('/(<\/p>)\s*(<p>)/', '$1' . "\n\n" . '$2', $content);
        
        // HTML özelliklerini güvenli hale getir (XSS koruması için)
        $content = preg_replace('/<([^>]+)>/', '<$1>', $content);
        
        return trim($content);
    }
    
    /**
     * Varsayılan etiketler oluşturur
     */
    private function generateDefaultTags() {
        $defaultTags = ['makale', 'blog', 'güncel', 'bilgi'];
        return implode(', ', $defaultTags);
    }
    
    /**
     * Makaleyi veritabanına kaydeder
     */
    private function saveArticle($article) {
        try {
            // Kategori ID'sini al - eğer makale datası içinde varsa onu kullan
            $categoryId = null;
            
            if (isset($article['category_id']) && !empty($article['category_id'])) {
                // Makale datası içinde kategori ID var, onu kullan
                $categoryId = $article['category_id'];
                $categoryName = $article['category_name'] ?? 'Bilinmeyen';
                $this->log("Makale için belirlenen kategori kullanılıyor: ID $categoryId ($categoryName)");
            } else {
                // Fallback: Rastgele kategori seç
                if (empty($this->categories)) {
                    throw new Exception("Aktif kategori bulunamadı");
                }
                
                $randomCategory = $this->categories[array_rand($this->categories)];
                $categoryId = $randomCategory['id'];
                $this->log("FALLBACK: Rastgele kategori seçildi: ID $categoryId ({$randomCategory['name']})");
            }
            
            // Slug oluştur
            $slug = $this->generateSlug($article['title']);
            
            // Aynı slug varsa sayı ekle
            $originalSlug = $slug;
            $counter = 1;
            while ($this->slugExists($slug)) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO articles (title, content, category_id, tags, slug, author_id, status, featured_image, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, 'published', ?, NOW(), NOW())
            ");
            
            $featuredImage = isset($article['featured_image']) ? $article['featured_image'] : null;
            
            $stmt->execute([
                $article['title'],
                $article['content'],
                $categoryId,
                $article['tags'],
                $slug,
                $featuredImage
            ]);
            
            $articleId = $this->db->lastInsertId();
            $this->log("Makale veritabanına kaydedildi: ID $articleId, Kategori ID: $categoryId, Slug: $slug");
            
            return $articleId;
            
        } catch (PDOException $e) {
            $this->log("HATA: Makale kaydedilirken hata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Slug oluşturur
     */
    private function generateSlug($title) {
        $slug = mb_strtolower($title, 'UTF-8');
        
        // Türkçe karakterleri dönüştür
        $tr = ['ş','ğ','ı','ü','ö','ç','Ş','Ğ','İ','Ü','Ö','Ç'];
        $en = ['s','g','i','u','o','c','s','g','i','u','o','c'];
        $slug = str_replace($tr, $en, $slug);
        
        // Özel karakterleri temizle
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        
        // Boşlukları tire ile değiştir
        $slug = preg_replace('/\s+/', '-', trim($slug));
        
        // Birden fazla tireyi tek tireye indir
        $slug = preg_replace('/-+/', '-', $slug);
        
        return trim($slug, '-');
    }
    
    /**
     * Slug'ın var olup olmadığını kontrol eder
     */
    private function slugExists($slug) {
        $stmt = $this->db->prepare("SELECT id FROM articles WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Benzersiz başlık oluşturur
     */
    private function makeUniqueTitle($title) {
        // Başlığın sonuna benzersiz bir ek ekler
        $adjectives = [
            '2023\'te', '2024\'te', 'Modern', 'Güncel', 'Yeni', 'Kapsamlı', 
            'Detaylı', 'Günümüzde', 'Popüler', 'Pratik', 'Etkili', 
            'Başarılı', 'Dikkat Çeken', 'İlginç', 'Önemli', 'Bilinmeyen',
            'Geleceğin', 'Evrensel', 'Yaratıcı', 'Temel', 'İleri Düzey',
            'Kolay', 'Hızlı', 'Derin', 'Geniş Çaplı', 'Özgün'
        ];
        
        // Rastgele bir sıfat seç
        $randomAdjective = $adjectives[array_rand($adjectives)];
        
        // Başlık sonuna ekle
        if (mb_strlen($title) > 50) {
            // Başlık zaten uzunsa kısalt
            $shortTitle = mb_substr($title, 0, 50);
            $pos = mb_strrpos($shortTitle, ' ');
            if ($pos !== false) {
                $shortTitle = mb_substr($shortTitle, 0, $pos);
            }
            return $shortTitle . ': ' . $randomAdjective . ' Yaklaşım';
        } else {
            // Normal başlığın sonuna ekle
            return $title . ': ' . $randomAdjective . ' Yaklaşım';
        }
    }
    
    /**
     * Benzer başlık var mı kontrol eder
     */
    private function isTitleExists($title) {
        // Başlığın ilk 5 kelimesini al
        $words = explode(' ', $title);
        $words = array_slice($words, 0, 5);
        $titleStart = implode(' ', $words);
        
        // % işareti SQL LIKE ifadesi için kaçış karakterleri ekle
        $titleStart = str_replace(['%', '_'], ['\\%', '\\_'], $titleStart);
        
        // Benzer başlık ara
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM articles WHERE title LIKE ?");
        $stmt->execute([$titleStart . '%']);
        $count = $stmt->fetchColumn();
        
        return $count > 0;
    }
    
    /**
     * Benzer konu ve kategori kombinasyonu var mı kontrol eder
     */
    private function isSimilarTopicExists($topic, $categoryId) {
        try {
            // Son 100 makaleyi kontrol et
            $stmt = $this->db->prepare("
                SELECT a.title, c.name as category_name, a.created_at 
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                WHERE a.category_id = ? 
                ORDER BY a.created_at DESC 
                LIMIT 100
            ");
            $stmt->execute([$categoryId]);
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Konuyu kelimelere ayır
            $topicWords = explode(' ', mb_strtolower($topic));
            
            foreach ($articles as $article) {
                $title = mb_strtolower($article['title']);
                
                // Eğer konu kelimeleri başlıkta geçiyorsa benzer sayılır
                $matchingWords = 0;
                foreach ($topicWords as $word) {
                    if (mb_strlen($word) > 3 && mb_strpos($title, $word) !== false) {
                        $matchingWords++;
                    }
                }
                
                // %60 veya daha fazla kelime eşleşiyorsa benzer sayılır
                $matchThreshold = count($topicWords) * 0.6;
                if ($matchingWords >= $matchThreshold) {
                    $this->log("Benzer konu tespit edildi: '{$article['title']}' (Kategori: {$article['category_name']}, Tarih: {$article['created_at']})");
                    return true;
                }
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("HATA: Benzer konu kontrolü yapılamadı: " . $e->getMessage());
            return false; // Hata durumunda False döndür, tekrar etmemesi için
        }
    }
    
    /**
     * Log kaydı yapar
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Bot istatistiklerini getirir
     */
    public function getStats() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_articles,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_articles,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_articles,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_articles
                FROM articles 
                WHERE author_id = 1 AND status = 'published'
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->log("HATA: İstatistik alınırken hata: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Son üretilen makaleleri getirir
     */
    public function getRecentArticles($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, c.name as category_name
                FROM articles a
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE a.author_id = 1 AND a.status = 'published'
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->log("HATA: Son makaleler alınırken hata: " . $e->getMessage());
            return [];
        }
    }
}
