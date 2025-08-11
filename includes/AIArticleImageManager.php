<?php
/**
 * AI Article Image Manager
 * Google'dan kategori tabanlı resim indirme ve işleme sınıfı
 */

class AIArticleImageManager {
    private $db;
    private $uploadsDir;
    private $client;
    private $logFile;
    
    // Google Custom Search API (ücretsiz günlük 100 arama)
    private $searchApiKey;
    private $searchEngineId;
    
    // Unsplash API (alternatif - daha kaliteli resimler)
    private $unsplashAccessKey;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->uploadsDir = __DIR__ . '/../uploads/ai_images/';
        $this->client = new \GuzzleHttp\Client();
        $this->logFile = AI_BOT_LOG_FILE;
        
        // API anahtarları - önce veritabanından, sonra config.php'den
        $this->searchApiKey = $this->getAiSetting('google_search_api_key') ?: (defined('GOOGLE_SEARCH_API_KEY') ? GOOGLE_SEARCH_API_KEY : '');
        $this->searchEngineId = $this->getAiSetting('google_search_engine_id') ?: (defined('GOOGLE_SEARCH_ENGINE_ID') ? GOOGLE_SEARCH_ENGINE_ID : '');
        $this->unsplashAccessKey = $this->getAiSetting('unsplash_access_key') ?: (defined('UNSPLASH_ACCESS_KEY') ? UNSPLASH_ACCESS_KEY : '');
        
        // Upload dizinini oluştur
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
        
        // GD kütüphanesi kontrolü
        if (!extension_loaded('gd')) {
            $this->log("UYARI: GD kütüphanesi yüklü değil. Resim optimizasyonu ve yerel placeholder oluşturma devre dışı.");
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
     * Kategori ve konuya göre resim arar ve indirir
     */
    public function downloadImagesForArticle($category, $topic, $title) {
        $images = [];
        $maxRetries = 3; // Bir resim için maksimum deneme sayısı
        
        try {
            // Arama sorguları için metin hazırlama
            $categoryEn = $this->translateQuery($category);
            $topicEn = $this->translateQuery($topic);
            
            // Ana anahtar kelimeleri çıkarma
            $topicWords = $this->extractKeywords($topic);
            $titleWords = $this->extractKeywords($title);
            
            // Sorgu varyasyonları oluştur (daha alakalı sonuçlar için)
            $searchQueries = [
                'category' => $categoryEn,
                'topic' => $topicEn,
                'combined' => $categoryEn . ' ' . $topicEn,
                'specific' => count($topicWords) > 0 ? $this->translateQuery($topicWords[array_rand($topicWords)]) : $topicEn
            ];
            
            // Alternatif anahtar kelimeler (retry için)
            $alternativeKeywords = [];
            
            // Anahtar kelimeleri ekle (tekrarları önle)
            foreach ($topicWords as $word) {
                if (!in_array($word, $alternativeKeywords)) {
                    $alternativeKeywords[] = $word;
                }
            }
            
            foreach ($titleWords as $word) {
                if (!in_array($word, $alternativeKeywords)) {
                    $alternativeKeywords[] = $word;
                }
            }
            
            $this->log("Resim arama sorguları: " . json_encode($searchQueries));
            $this->log("Alternatif anahtar kelimeler: " . json_encode($alternativeKeywords));
            
            // Kapak resmi - kategori ve konu kombinasyonu kullan
            $coverQuery = $searchQueries['combined'];
            $coverImage = null;
            $retryCount = 0;
            
            while ($coverImage === null && $retryCount < $maxRetries) {
                if ($retryCount > 0) {
                    // Alternatif sorgu oluştur
                    if (count($alternativeKeywords) > 0) {
                        $randKeyword = $alternativeKeywords[array_rand($alternativeKeywords)];
                        $coverQuery = $this->translateQuery($randKeyword) . ' ' . $categoryEn;
                        $this->log("Kapak resmi retry #$retryCount sorgusu: $coverQuery");
                    }
                }
                
                $coverImage = $this->searchAndDownloadImage($coverQuery, $searchQueries['category'], 'cover');
                $retryCount++;
            }
            
            if ($coverImage) {
                $images['cover'] = $coverImage;
                $this->log("Kapak resmi indirildi. Sorgu: $coverQuery");
            }
            
            // İçerik resmi 1 - spesifik konu detayı kullan
            $contentQuery1 = $searchQueries['specific'];
            $contentImage1 = null;
            $retryCount = 0;
            
            while ($contentImage1 === null && $retryCount < $maxRetries) {
                if ($retryCount > 0) {
                    // Alternatif sorgu oluştur
                    if (count($alternativeKeywords) > 0) {
                        $randKeyword = $alternativeKeywords[array_rand($alternativeKeywords)];
                        $contentQuery1 = $this->translateQuery($randKeyword) . ' ' . $topicEn;
                        $this->log("İçerik resmi 1 retry #$retryCount sorgusu: $contentQuery1");
                    }
                }
                
                $contentImage1 = $this->searchAndDownloadImage($contentQuery1, $searchQueries['topic'], 'content_1');
                $retryCount++;
            }
            
            if ($contentImage1) {
                $images['content_1'] = $contentImage1;
                $this->log("İçerik resmi 1 indirildi. Sorgu: $contentQuery1");
            }
            
            // İçerik resmi 2 - başlıktan anahtar kelime kullan
            $contentQuery2 = count($titleWords) > 0 ? 
                $this->translateQuery($titleWords[array_rand($titleWords)]) . ' ' . $searchQueries['category'] : 
                $searchQueries['topic'];
            
            $contentImage2 = null;
            $retryCount = 0;
            
            while ($contentImage2 === null && $retryCount < $maxRetries) {
                if ($retryCount > 0) {
                    // Alternatif sorgu oluştur
                    if (count($alternativeKeywords) > 0) {
                        $randKeyword = $alternativeKeywords[array_rand($alternativeKeywords)];
                        $contentQuery2 = $this->translateQuery($randKeyword) . ' ' . $searchQueries['category'];
                        $this->log("İçerik resmi 2 retry #$retryCount sorgusu: $contentQuery2");
                    }
                }
                
                $contentImage2 = $this->searchAndDownloadImage($contentQuery2, $searchQueries['combined'], 'content_2');
                $retryCount++;
            }
            
            if ($contentImage2) {
                $images['content_2'] = $contentImage2;
                $this->log("İçerik resmi 2 indirildi. Sorgu: $contentQuery2");
            }
            
            $this->log("Resim indirme tamamlandı: " . count($images) . " resim");
            
        } catch (Exception $e) {
            $this->log("HATA: Resim indirme başarısız: " . $e->getMessage());
        }
        
        return $images;
    }
    
    /**
     * Metinden anahtar kelimeleri çıkarır
     */
    private function extractKeywords($text) {
        $text = mb_strtolower(trim($text), 'UTF-8');
        
        // Türkçe stopwords (durma kelimeleri) - daha kapsamlı liste
        $stopwords = [
            've', 'veya', 'ile', 'için', 'gibi', 'ama', 'fakat', 'ancak', 'lakin',
            'da', 'de', 'ki', 'den', 'dan', 'mi', 'mu', 'mı', 'ne', 'ya', 'nasıl',
            'bir', 'bu', 'şu', 'o', 'onun', 'onlar', 'biz', 'siz', 'ben', 'sen',
            'şey', 'her', 'çok', 'daha', 'kadar', 'sonra', 'önce', 'yani', 'eğer',
            'ise', 'acaba', 'belki', 'tüm', 'hiç', 'bazı', 'birkaç', 'birçok'
        ];
        
        // Özel karakterleri temizle ve kelimelere ayır
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text);
        
        // Stopwords ve kısa kelimeleri kaldır
        $keywords = [];
        foreach ($words as $word) {
            // En az 3 karakter olan ve stopwords listesinde olmayan kelimeleri al
            if (mb_strlen($word, 'UTF-8') > 2 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }
        
        // Anahtar kelime bulunamadıysa en az bir kelime dön
        if (empty($keywords) && !empty($words)) {
            // En uzun kelimeyi bul
            $longestWord = '';
            foreach ($words as $word) {
                if (mb_strlen($word, 'UTF-8') > mb_strlen($longestWord, 'UTF-8')) {
                    $longestWord = $word;
                }
            }
            
            if (!empty($longestWord)) {
                $keywords[] = $longestWord;
            }
        }
        
        return $keywords;
    }
    
    /**
     * Unsplash'dan resim arar ve indirir
     */
    private function searchAndDownloadImage($query, $fallbackQuery, $type) {
        if (empty($this->unsplashAccessKey)) {
            return $this->searchGoogleImages($query, $fallbackQuery, $type);
        }
        
        try {
            // Sorguları temizle
            $query = trim($query);
            $fallbackQuery = trim($fallbackQuery);
            
            // Hem Türkçe hem İngilizce sorgularla arama yap
            $turkishQuery = $query; // Orijinal Türkçe sorgu
            $englishQuery = $this->translateQuery($query); // İngilizce çevirisi
            
            // Önce Türkçe sorgu ile dene
            $this->log("Unsplash Türkçe arama sorgusu: '$turkishQuery'");
            
            $response = $this->client->get('https://api.unsplash.com/search/photos', [
                'headers' => [
                    'Authorization' => 'Client-ID ' . $this->unsplashAccessKey
                ],
                'query' => [
                    'query' => $turkishQuery,
                    'per_page' => 20,  // Daha fazla sonuç getir
                    'orientation' => $type === 'cover' ? 'landscape' : 'landscape',
                    'content_filter' => 'high',
                    'order_by' => 'relevant'
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!empty($data['results'])) {
                $results = $data['results'];
                $resultCount = count($results);
                $this->log("Unsplash Türkçe sorgu " . $resultCount . " sonuç döndürdü");
                
                // En alakalı sonuçları önceliklendirmek için ilk 5 sonuç (varsa) arasından seç
                $selectionRange = min(5, $resultCount);
                $selectedIndex = mt_rand(0, $selectionRange - 1);
                $photo = $results[$selectedIndex];
                
                return $this->downloadImage($photo['urls']['regular'], $type, $photo['id']);
            } else {
                $this->log("Unsplash Türkçe sorgu sonuç bulunamadı, İngilizce çeviri deneniyor");
                
                // İngilizce çeviri ile tekrar dene
                $this->log("İngilizce arama sorgusu: '$englishQuery' (Orijinal: '$turkishQuery')");
                
                $engResponse = $this->client->get('https://api.unsplash.com/search/photos', [
                    'headers' => [
                        'Authorization' => 'Client-ID ' . $this->unsplashAccessKey
                    ],
                    'query' => [
                        'query' => $englishQuery,
                        'per_page' => 20,
                        'orientation' => $type === 'cover' ? 'landscape' : 'landscape',
                        'content_filter' => 'high',
                        'order_by' => 'relevant'
                    ]
                ]);
                
                $engData = json_decode($engResponse->getBody(), true);
                
                if (!empty($engData['results'])) {
                    $results = $engData['results'];
                    $resultCount = count($results);
                    $this->log("Unsplash İngilizce sorgu " . $resultCount . " sonuç döndürdü");
                    
                    $selectionRange = min(5, $resultCount);
                    $selectedIndex = mt_rand(0, $selectionRange - 1);
                    $photo = $results[$selectedIndex];
                    
                    return $this->downloadImage($photo['urls']['regular'], $type, $photo['id']);
                }
                
                // Fallback sorgu ile tekrar dene
                if ($fallbackQuery !== $query) {
                    $turkishFallback = $fallbackQuery;
                    $fallbackEnglish = $this->translateQuery($fallbackQuery);
                    
                    // Önce Türkçe fallback deneyim
                    $this->log("Türkçe fallback sorgusu: $turkishFallback");
                    
                    $fallbackResponse = $this->client->get('https://api.unsplash.com/search/photos', [
                        'headers' => [
                            'Authorization' => 'Client-ID ' . $this->unsplashAccessKey
                        ],
                        'query' => [
                            'query' => $turkishFallback,
                            'per_page' => 20,
                            'orientation' => $type === 'cover' ? 'landscape' : 'landscape',
                            'content_filter' => 'high'
                        ]
                    ]);
                    
                    $fallbackData = json_decode($fallbackResponse->getBody(), true);
                    
                    if (!empty($fallbackData['results'])) {
                        $results = $fallbackData['results'];
                        $resultCount = count($results);
                        $this->log("Türkçe fallback " . $resultCount . " sonuç döndürdü");
                        
                        $selectedIndex = mt_rand(0, $resultCount - 1);
                        $photo = $results[$selectedIndex];
                        
                        return $this->downloadImage($photo['urls']['regular'], $type, $photo['id']);
                    }
                    
                    // İngilizce fallback dene
                    $this->log("İngilizce fallback sorgusu: $fallbackEnglish");
                    
                    $fallbackResponseEng = $this->client->get('https://api.unsplash.com/search/photos', [
                        'headers' => [
                            'Authorization' => 'Client-ID ' . $this->unsplashAccessKey
                        ],
                        'query' => [
                            'query' => $fallbackEnglish,
                            'per_page' => 20,
                            'orientation' => $type === 'cover' ? 'landscape' : 'landscape',
                            'content_filter' => 'high'
                        ]
                    ]);
                    
                    $fallbackDataEng = json_decode($fallbackResponseEng->getBody(), true);
                    
                    if (!empty($fallbackDataEng['results'])) {
                        $results = $fallbackDataEng['results'];
                        $resultCount = count($results);
                        $this->log("İngilizce fallback " . $resultCount . " sonuç döndürdü");
                        
                        $selectedIndex = mt_rand(0, $resultCount - 1);
                        $photo = $results[$selectedIndex];
                        
                        return $this->downloadImage($photo['urls']['regular'], $type, $photo['id']);
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("Unsplash hatası: " . $e->getMessage());
        }
        
        // Unsplash başarısız olursa Google'a geç
        return $this->searchGoogleImages($query, $fallbackQuery, $type);
    }
    
    /**
     * Google Custom Search ile resim arar
     */
    private function searchGoogleImages($query, $fallbackQuery, $type) {
        if (empty($this->searchApiKey) || empty($this->searchEngineId)) {
            return $this->useStockImage($type);
        }
        
        try {
            // Sorguları temizle
            $query = trim($query);
            $fallbackQuery = trim($fallbackQuery);
            
            // Hem Türkçe hem İngilizce sorgularla arama
            $turkishQuery = $query; // Orijinal Türkçe sorgu
            $englishQuery = $this->translateQuery($query); // İngilizce çevirisi
            
            // Önce Türkçe sorgu ile dene
            $this->log("Google Türkçe arama sorgusu: '$turkishQuery'");
            
            $response = $this->client->get('https://www.googleapis.com/customsearch/v1', [
                'query' => [
                    'key' => $this->searchApiKey,
                    'cx' => $this->searchEngineId,
                    'q' => $turkishQuery,
                    'searchType' => 'image',
                    'imgSize' => 'large',
                    'imgType' => 'photo',
                    'safe' => 'active',
                    'num' => 10,
                    'fileType' => 'jpg,png',
                    'rights' => 'cc_publicdomain,cc_attribute,cc_sharealike',
                    'imgColorType' => 'color'  // Renkli resimler tercih et
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!empty($data['items'])) {
                $items = $data['items'];
                $itemCount = count($items);
                $this->log("Google Türkçe sorgu " . $itemCount . " sonuç döndürdü");
                
                // En alakalı sonuçları önceliklendirmek için ilk 5 sonuç (varsa) arasından seç
                $selectionRange = min(5, $itemCount);
                $selectedIndex = mt_rand(0, $selectionRange - 1);
                $image = $items[$selectedIndex];
                
                return $this->downloadImage($image['link'], $type, 'google_' . time());
            } else {
                $this->log("Google Türkçe sorgu sonuç bulunamadı, İngilizce çeviri deneniyor");
                
                // İngilizce çeviri ile tekrar dene
                $this->log("Google İngilizce arama sorgusu: '$englishQuery'");
                
                $engResponse = $this->client->get('https://www.googleapis.com/customsearch/v1', [
                    'query' => [
                        'key' => $this->searchApiKey,
                        'cx' => $this->searchEngineId,
                        'q' => $englishQuery,
                        'searchType' => 'image',
                        'imgSize' => 'large',
                        'imgType' => 'photo',
                        'safe' => 'active',
                        'num' => 10,
                        'fileType' => 'jpg,png',
                        'rights' => 'cc_publicdomain,cc_attribute,cc_sharealike'
                    ]
                ]);
                
                $engData = json_decode($engResponse->getBody(), true);
                
                if (!empty($engData['items'])) {
                    $items = $engData['items'];
                    $itemCount = count($items);
                    $this->log("Google İngilizce sorgu " . $itemCount . " sonuç döndürdü");
                    
                    $selectionRange = min(5, $itemCount);
                    $selectedIndex = mt_rand(0, $selectionRange - 1);
                    $image = $items[$selectedIndex];
                    
                    return $this->downloadImage($image['link'], $type, 'google_' . time());
                }
                
                // Fallback sorgu ile tekrar dene
                if ($fallbackQuery !== $query) {
                    $turkishFallback = $fallbackQuery;
                    $fallbackEnglish = $this->translateQuery($fallbackQuery);
                    
                    // Önce Türkçe fallback deneyim
                    $this->log("Google Türkçe fallback sorgusu: $turkishFallback");
                    
                    $fallbackResponse = $this->client->get('https://www.googleapis.com/customsearch/v1', [
                        'query' => [
                            'key' => $this->searchApiKey,
                            'cx' => $this->searchEngineId,
                            'q' => $turkishFallback,
                            'searchType' => 'image',
                            'imgSize' => 'large',
                            'imgType' => 'photo',
                            'safe' => 'active',
                            'num' => 10,
                            'fileType' => 'jpg,png'
                        ]
                    ]);
                    
                    $fallbackData = json_decode($fallbackResponse->getBody(), true);
                    
                    if (!empty($fallbackData['items'])) {
                        $items = $fallbackData['items'];
                        $itemCount = count($items);
                        $this->log("Google Türkçe fallback " . $itemCount . " sonuç döndürdü");
                        
                        $selectedIndex = mt_rand(0, $itemCount - 1);
                        $image = $items[$selectedIndex];
                        
                        return $this->downloadImage($image['link'], $type, 'google_' . time());
                    }
                    
                    // İngilizce fallback dene
                    $this->log("Google İngilizce fallback sorgusu: $fallbackEnglish");
                    
                    $fallbackResponseEng = $this->client->get('https://www.googleapis.com/customsearch/v1', [
                        'query' => [
                            'key' => $this->searchApiKey,
                            'cx' => $this->searchEngineId,
                            'q' => $fallbackEnglish,
                            'searchType' => 'image',
                            'imgSize' => 'large',
                            'imgType' => 'photo',
                            'safe' => 'active',
                            'num' => 10,
                            'fileType' => 'jpg,png'
                        ]
                    ]);
                    
                    $fallbackDataEng = json_decode($fallbackResponseEng->getBody(), true);
                    
                    if (!empty($fallbackDataEng['items'])) {
                        $items = $fallbackDataEng['items'];
                        $itemCount = count($items);
                        $this->log("Google İngilizce fallback " . $itemCount . " sonuç döndürdü");
                        
                        $selectedIndex = mt_rand(0, $itemCount - 1);
                        $image = $items[$selectedIndex];
                        
                        return $this->downloadImage($image['link'], $type, 'google_' . time());
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("Google Search hatası: " . $e->getMessage());
        }
        
        // Eğer buraya kadar geldiyse, tüm arama çabaları başarısız olmuş demektir
        return $this->useStockImage($type);
    }
    
    /**
     * Resmi indirir ve kaydeder
     */
    private function downloadImage($imageUrl, $type, $identifier) {
        try {
            $response = $this->client->get($imageUrl, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'AIArticleBot/1.0'
                ]
            ]);
            
            $imageData = $response->getBody()->getContents();
            $imageInfo = getimagesizefromstring($imageData);
            
            if (!$imageInfo) {
                throw new Exception("Geçersiz resim formatı");
            }
            
            // Dosya uzantısını belirle
            $extension = 'jpg';
            switch ($imageInfo['mime']) {
                case 'image/png':
                    $extension = 'png';
                    break;
                case 'image/gif':
                    $extension = 'gif';
                    break;
                case 'image/webp':
                    $extension = 'webp';
                    break;
            }
            
            // Makale başlığına göre SEO dostu dosya adı oluştur
            $titleSlug = '';
            
            // DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS kullanımı yerine
            // DEBUG_BACKTRACE_PROVIDE_OBJECT kullanarak fonksiyon argümanlarını da alalım
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5);
            
            // downloadImagesForArticle fonksiyonu çağrısını bul
            foreach ($backtrace as $trace) {
                if (isset($trace['function']) && $trace['function'] == 'downloadImagesForArticle') {
                    // Çağrı parametrelerini kontrol et
                    if (isset($trace['args']) && count($trace['args']) >= 3 && is_string($trace['args'][2])) {
                        $title = $trace['args'][2];
                        // Başlığı URL dostu hale getir
                        $titleSlug = $this->createSlugFromTitle($title);
                        break;
                    }
                }
            }
            
            // Başlık slug'ı oluşturabildiyse kullan, oluşturamadıysa eski format kullan
            // Yeni format: başlık_timestamp.jpg (cover_ ve content_ önekleri kaldırıldı)
            $timestamp = time();
            if (!empty($titleSlug)) {
                $filename = $titleSlug . '_' . $timestamp . '.' . $extension;
                
                // Log ile dosya adı formatını kaydet
                $this->log("SEO dostu resim adı oluşturuldu: $filename (Başlık: $titleSlug)");
            } else {
                // Eğer başlık bulunamazsa yine de önekleri kaldır ve tanımlayıcıyı kullan
                $filename = 'image_' . $identifier . '_' . $timestamp . '.' . $extension;
            }
            
            $filepath = $this->uploadsDir . $filename;
            
            file_put_contents($filepath, $imageData);
            
            // Metin ağırlıklı veya benzer resim kontrolü
            if ($this->isDuplicateOrTextHeavy($filepath)) {
                $this->log("Resim reddedildi (metin ağırlıklı veya benzer): $filename");
                unlink($filepath); // Reddettiğimiz resmi siliyoruz
                
                // Alternatif resim ara (farklı bir sorgu ile)
                return null;
            }
            
            // Resmi optimize et
            $optimizedPath = $this->optimizeImage($filepath, $type);
            
            // Optimize edilmiş dosya var mı kontrol et
            if (!file_exists($optimizedPath)) {
                $this->log("HATA: Optimize edilmiş dosya bulunamadı: $optimizedPath");
                // Eğer optimize edilmiş dosya yoksa orijinal dosyayı kullan
                if (file_exists($filepath)) {
                    $optimizedPath = $filepath;
                } else {
                    $this->log("HATA: Orijinal dosya da bulunamadı: $filepath");
                    return null; // Hiçbir dosya bulunamadıysa null dön
                }
            }
            
            // Dosyanın boyutunu güvenli bir şekilde al
            $fileSize = 0;
            if (file_exists($optimizedPath)) {
                $fileSize = filesize($optimizedPath);
            }
            
            return [
                'filename' => basename($optimizedPath),
                'path' => $optimizedPath,
                'url' => '/uploads/ai_images/' . basename($optimizedPath),
                'type' => $type, // Tip bilgisini geri döndürürken yine saklıyoruz (API için gerekebilir)
                'size' => $fileSize
            ];
            
        } catch (Exception $e) {
            $this->log("Resim indirme hatası: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Resim içinde çok fazla metin olup olmadığını kontrol eder
     * @param string $filepath Resim dosyasının yolu
     * @return bool Eğer resim çok fazla metin içeriyorsa true döner
     */
    private function isTextHeavyImage($filepath) {
        if (!extension_loaded('gd')) {
            return false; // GD yoksa kontrolü atlıyoruz
        }
        
        try {
            $imageInfo = getimagesize($filepath);
            if (!$imageInfo) {
                return false;
            }
            
            // Resmi yükle
            $source = null;
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filepath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filepath);
                    break;
                default:
                    return false;
            }
            
            if (!$source) {
                return false;
            }
            
            $width = imagesx($source);
            $height = imagesy($source);
            
            // Metin tespiti için basit bir algoritma kullanıyoruz
            // Resimdeki keskin kenarları ve kontrast değişimlerini analiz ediyoruz
            
            $edgeCount = 0;
            $sampleSize = 20; // Performans için örnekleme yapıyoruz
            $threshold = 40; // Kenar algılama eşiği (düşük değer = daha hassas)
            
            // Resmin belirli noktalarını örnekle
            for ($y = 0; $y < $height - 1; $y += $sampleSize) {
                for ($x = 0; $x < $width - 1; $x += $sampleSize) {
                    $rgb1 = imagecolorat($source, $x, $y);
                    $rgb2 = imagecolorat($source, $x + 1, $y);
                    $rgb3 = imagecolorat($source, $x, $y + 1);
                    
                    $r1 = ($rgb1 >> 16) & 0xFF;
                    $g1 = ($rgb1 >> 8) & 0xFF;
                    $b1 = $rgb1 & 0xFF;
                    
                    $r2 = ($rgb2 >> 16) & 0xFF;
                    $g2 = ($rgb2 >> 8) & 0xFF;
                    $b2 = $rgb2 & 0xFF;
                    
                    $r3 = ($rgb3 >> 16) & 0xFF;
                    $g3 = ($rgb3 >> 8) & 0xFF;
                    $b3 = $rgb3 & 0xFF;
                    
                    // Yatay ve dikey renk farkını hesapla
                    $diffX = abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
                    $diffY = abs($r1 - $r3) + abs($g1 - $g3) + abs($b1 - $b3);
                    
                    // Eğer fark eşikten büyükse, bu bir kenar olabilir
                    if ($diffX > $threshold || $diffY > $threshold) {
                        $edgeCount++;
                    }
                }
            }
            
            imagedestroy($source);
            
            // Toplam örnekleme sayısı
            $totalSamples = ceil($width / $sampleSize) * ceil($height / $sampleSize);
            
            // Kenar yoğunluğu - bu değer metinli resimlerde daha yüksek olur
            $edgeDensity = $edgeCount / $totalSamples;
            
            // Metin ağırlıklı resim eşiği - ayarlanabilir
            $textHeavyThreshold = 0.15;
            
            $isTextHeavy = $edgeDensity > $textHeavyThreshold;
            
            if ($isTextHeavy) {
                $this->log("Metin ağırlıklı resim tespit edildi. Kenar yoğunluğu: " . round($edgeDensity, 3));
            }
            
            return $isTextHeavy;
            
        } catch (Exception $e) {
            $this->log("Metin tespiti hatası: " . $e->getMessage());
            return false;
        }
    }

    /**
     * İki resim arasındaki benzerliği kontrol eder
     * @param string $filepath1 İlk resim dosyasının yolu
     * @param string $filepath2 İkinci resim dosyasının yolu
     * @return float Benzerlik oranı (0-1 arası, 1 tam benzerlik)
     */
    private function calculateImageSimilarity($filepath1, $filepath2) {
        if (!extension_loaded('gd')) {
            return 0; // GD yoksa kontrolü atlıyoruz
        }
        
        try {
            // İki resim de yoksa karşılaştırma yapılamaz
            if (!file_exists($filepath1) || !file_exists($filepath2)) {
                return 0;
            }
            
            // Resimleri yükle
            $image1 = $this->loadImageFromPath($filepath1);
            $image2 = $this->loadImageFromPath($filepath2);
            
            if (!$image1 || !$image2) {
                return 0;
            }
            
            // Karşılaştırma için küçük boyuta getir
            $thumbSize = 16; // 16x16 piksel - parmak izi boyutu
            $thumb1 = imagecreatetruecolor($thumbSize, $thumbSize);
            $thumb2 = imagecreatetruecolor($thumbSize, $thumbSize);
            
            imagecopyresampled($thumb1, $image1, 0, 0, 0, 0, $thumbSize, $thumbSize, imagesx($image1), imagesy($image1));
            imagecopyresampled($thumb2, $image2, 0, 0, 0, 0, $thumbSize, $thumbSize, imagesx($image2), imagesy($image2));
            
            // Resimleri gri tonlamaya çevir ve parmak izi oluştur
            $signature1 = $this->calculateImageSignature($thumb1, $thumbSize);
            $signature2 = $this->calculateImageSignature($thumb2, $thumbSize);
            
            // Hamming mesafesini hesapla (kaç bit farklı)
            $hammingDistance = 0;
            for ($i = 0; $i < count($signature1); $i++) {
                $hammingDistance += ($signature1[$i] != $signature2[$i]) ? 1 : 0;
            }
            
            // Benzerlik oranı hesapla (0-1 arası)
            $similarity = 1 - ($hammingDistance / count($signature1));
            
            // Bellekten temizle
            imagedestroy($image1);
            imagedestroy($image2);
            imagedestroy($thumb1);
            imagedestroy($thumb2);
            
            return $similarity;
            
        } catch (Exception $e) {
            $this->log("Resim benzerliği hesaplama hatası: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Resim dosyasını GD kaynağı olarak yükler
     */
    private function loadImageFromPath($filepath) {
        $imageInfo = getimagesize($filepath);
        if (!$imageInfo) {
            return null;
        }
        
        $source = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filepath);
                break;
            default:
                return null;
        }
        
        return $source;
    }
    
    /**
     * Resim için dijital parmak izi oluşturur
     */
    private function calculateImageSignature($image, $size) {
        $signature = [];
        
        // Gri tonlama değerlerini al
        $grayMatrix = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Gri ton değeri (luminance)
                $grayMatrix[$y][$x] = (int)(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
            }
        }
        
        // Ortalama gri ton değerini hesapla
        $total = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $total += $grayMatrix[$y][$x];
            }
        }
        $average = $total / ($size * $size);
        
        // Ortalamaya göre bit parmak izi oluştur
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $signature[] = ($grayMatrix[$y][$x] >= $average) ? 1 : 0;
            }
        }
        
        return $signature;
    }
    
    /**
     * Mevcut resimleri kontrol eder ve benzer veya metin ağırlıklı bir resim varsa true döner
     */
    private function isDuplicateOrTextHeavy($filepath) {
        // Metin ağırlıklı resim kontrolü
        if ($this->isTextHeavyImage($filepath)) {
            return true;
        }
        
        // Mevcut resimlere benzerlik kontrolü
        $recentImagesPath = $this->uploadsDir;
        $recentImages = glob($recentImagesPath . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
        
        // Son 100 resimle sınırla (performans için)
        $recentImages = array_slice($recentImages, -100);
        
        foreach ($recentImages as $existingImage) {
            // Aynı dosya ise atla
            if ($existingImage === $filepath) {
                continue;
            }
            
            $similarity = $this->calculateImageSimilarity($filepath, $existingImage);
            
            // 0.85 üzeri benzerlik çok yüksek benzerlik kabul edilir
            if ($similarity > 0.85) {
                $this->log("Benzer resim tespit edildi. Benzerlik: " . round($similarity, 3) . " - Mevcut: " . basename($existingImage));
                return true;
            }
        }
        
        return false;
    }

    /**
     * Resmi optimize eder (boyutlandır ve sıkıştır)
     */
    private function optimizeImage($filepath, $type) {
        // GD kütüphanesi yoksa optimizasyon yapma
        if (!extension_loaded('gd')) {
            $this->log("GD kütüphanesi yok, resim optimizasyonu atlanıyor: $filepath");
            return $filepath;
        }
        
        try {
            $imageInfo = getimagesize($filepath);
            if (!$imageInfo) {
                return $filepath;
            }
            
            // Hedef boyutları belirle
            $maxWidth = $type === 'cover' ? 800 : 600;
            $maxHeight = $type === 'cover' ? 400 : 400;
            
            // Resmi yükle
            $source = null;
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filepath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filepath);
                    break;
                default:
                    return $filepath;
            }
            
            if (!$source) {
                return $filepath;
            }
            
            // Orantılı boyutlandırma hesapla
            $originalWidth = imagesx($source);
            $originalHeight = imagesy($source);
            
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            
            if ($ratio >= 1) {
                imagedestroy($source);
                return $filepath; // Zaten küçük
            }
            
            $newWidth = round($originalWidth * $ratio);
            $newHeight = round($originalHeight * $ratio);
            
            // Yeni resim oluştur
            $destination = imagecreatetruecolor($newWidth, $newHeight);
            
            // PNG için şeffaflığı koru
            if ($imageInfo[2] == IMAGETYPE_PNG) {
                imagealphablending($destination, false);
                imagesavealpha($destination, true);
                $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
                imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            // Resmi yeniden boyutlandır
            imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            // Optimized dosya adı - SEO için başlık adını koru ama _opt son ekini kaldır
            $pathInfo = pathinfo($filepath);
            
            // Dosya adında cover_ öneki varsa kaldır ve SEO dostu formata dönüştür
            $filename = $pathInfo['filename'];
            if (strpos($filename, 'cover_') === 0) {
                $filename = substr($filename, 6); // "cover_" önekini kaldır
            } else if (strpos($filename, 'content_') === 0) {
                $filename = substr($filename, 8); // "content_" önekini kaldır
            }
            
            // Dosya yolunu oluştur ve varlığını kontrol edecek şekilde işlem yap
            $optimizedPath = $pathInfo['dirname'] . '/' . $filename . '.' . $pathInfo['extension'];
            
            // Log ile dönüşümü kaydet
            $this->log("Dosya dönüşümü: {$filepath} -> {$optimizedPath}");
            
            // Kaydet
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    imagejpeg($destination, $optimizedPath, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($destination, $optimizedPath, 6);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($destination, $optimizedPath);
                    break;
            }
            
            imagedestroy($source);
            imagedestroy($destination);
            
            // Optimize edilmiş dosyanın başarıyla oluşturulduğunu kontrol et
            if (file_exists($optimizedPath) && filesize($optimizedPath) > 0) {
                // Orijinal dosyayı sil
                if (file_exists($filepath) && $filepath != $optimizedPath) {
                    unlink($filepath);
                    $this->log("Orijinal dosya silindi: $filepath");
                }
                $this->log("Dosya başarıyla optimize edildi: $optimizedPath");
            } else {
                $this->log("HATA: Dosya optimizasyonu başarısız oldu. Orijinal dosya korunuyor: $filepath");
                return $filepath; // Optimizasyon başarısız oldu, orijinal dosyayı döndür
            }
            
            return $optimizedPath;
            
        } catch (Exception $e) {
            $this->log("Resim optimizasyonu hatası: " . $e->getMessage());
            return $filepath;
        }
    }
    
    /**
     * Stok resim kullan (API anahtarı yoksa)
     */
    private function useStockImage($type) {
        try {
            // Boyutları belirle
            $width = $type === 'cover' ? 800 : 600;
            $height = $type === 'cover' ? 400 : 300;
            
            // Resim tipi için renk tanımla
            $colors = [
                'cover' => ['bg' => [79, 70, 229], 'text' => [255, 255, 255], 'label' => 'Makale Kapak Resmi'],
                'content_1' => ['bg' => [16, 185, 129], 'text' => [255, 255, 255], 'label' => 'İçerik Görseli 1'],
                'content_2' => ['bg' => [245, 158, 11], 'text' => [255, 255, 255], 'label' => 'İçerik Görseli 2']
            ];
            
            $color = $colors[$type] ?? $colors['content_1'];
            // Placeholder için de tip öneki olmayan dosya adı
            $filename = 'placeholder_' . time() . '.png';
            $filepath = $this->uploadsDir . $filename;
            
            // GD kütüphanesi varsa PNG oluştur
            if (extension_loaded('gd')) {
                $image = imagecreatetruecolor($width, $height);
                
                // Arka plan rengi
                $bgColor = imagecolorallocate($image, $color['bg'][0], $color['bg'][1], $color['bg'][2]);
                imagefill($image, 0, 0, $bgColor);
                
                // Metin rengi
                $textColor = imagecolorallocate($image, $color['text'][0], $color['text'][1], $color['text'][2]);
                
                // Metin ekle (eğer font varsa)
                $fontSize = $width > 600 ? 24 : 18;
                $textX = $width / 2 - (strlen($color['label']) * $fontSize / 4);
                $textY = $height / 2;
                
                imagestring($image, 5, max(10, $textX), max(10, $textY - 10), $color['label'], $textColor);
                
                // Boyut bilgisini ekle
                $sizeText = "{$width}x{$height}";
                $sizeX = $width / 2 - (strlen($sizeText) * 10 / 2);
                imagestring($image, 3, max(10, $sizeX), $textY + 20, $sizeText, $textColor);
                
                // PNG olarak kaydet
                imagepng($image, $filepath);
                imagedestroy($image);
                
                $this->log("GD ile PNG placeholder oluşturuldu: $filename ({$width}x{$height})");
                
            } else {
                // GD yoksa SVG oluştur
                $filename = 'placeholder_' . time() . '.svg';
                $filepath = $this->uploadsDir . $filename;
                
                $bgColorHex = sprintf('#%02x%02x%02x', $color['bg'][0], $color['bg'][1], $color['bg'][2]);
                $textColorHex = sprintf('#%02x%02x%02x', $color['text'][0], $color['text'][1], $color['text'][2]);
                
                $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="' . $bgColorHex . '"/>
    <text x="50%" y="45%" text-anchor="middle" fill="' . $textColorHex . '" font-family="Arial, sans-serif" font-size="24" font-weight="bold">' . htmlspecialchars($color['label']) . '</text>
    <text x="50%" y="60%" text-anchor="middle" fill="' . $textColorHex . '" font-family="Arial, sans-serif" font-size="16">' . $width . 'x' . $height . '</text>
</svg>';
                
                file_put_contents($filepath, $svgContent);
                
                $this->log("SVG placeholder oluşturuldu (GD yok): $filename ({$width}x{$height})");
            }
            
            // Dosya var mı kontrol et
            if (!file_exists($filepath)) {
                $this->log("HATA: Placeholder dosyası oluşturulamadı: $filepath");
                return null;
            }
            
            $this->log("Placeholder başarıyla oluşturuldu: $filename");
            return 'uploads/ai_images/' . $filename;
            
        } catch (Exception $e) {
            $this->log("Placeholder oluşturma hatası: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Türkçe sorguyu İngilizce'ye çevir (geliştirilmiş)
     */
    private function translateQuery($query) {
        // Tam cümle/kelime çevirileri
        $phraseTranslations = [
            'yapay zeka' => 'artificial intelligence',
            'sosyal medya' => 'social media',
            'kişisel gelişim' => 'personal development',
            'zaman yönetimi' => 'time management',
            'çocuk gelişimi' => 'child development',
            'stres yönetimi' => 'stress management',
            'yaşam tarzı' => 'lifestyle',
            'doğal tedavi' => 'natural treatment',
            'dijital dönüşüm' => 'digital transformation',
            'siber güvenlik' => 'cyber security',
            'akıllı ev' => 'smart home',
            'veri güvenliği' => 'data security',
            'bulut teknolojileri' => 'cloud technologies',
            'uzay araştırmaları' => 'space research',
            'yenilenebilir enerji' => 'renewable energy',
            'sürdürülebilir yaşam' => 'sustainable living',
            'finansal özgürlük' => 'financial freedom',
            'sağlıklı yaşam' => 'healthy lifestyle',
            'evde çalışma' => 'work from home',
            'online eğitim' => 'online education',
            'uzaktan çalışma' => 'remote work',
            'günlük hayat' => 'daily life'
        ];
        
        // Kelime çevirileri - Türkçe kelimelerin İngilizce karşılıkları
        $wordTranslations = [
            'teknoloji' => 'technology',
            'sağlık' => 'health',
            'eğitim' => 'education',
            'spor' => 'sports',
            'bilim' => 'science',
            'sanat' => 'art',
            'müzik' => 'music',
            'yemek' => 'food',
            'seyahat' => 'travel',
            'doğa' => 'nature',
            'hayvan' => 'animal',
            'çevre' => 'environment',
            'ekonomi' => 'economy',
            'iş' => 'business',
            'girişimcilik' => 'entrepreneurship',
            'yazılım' => 'software',
            'programlama' => 'programming',
            'mobil' => 'mobile',
            'internet' => 'internet',
            'güvenlik' => 'security',
            'fintech' => 'fintech',
            'blockchain' => 'blockchain',
            'kripto' => 'cryptocurrency',
            'beslenme' => 'nutrition',
            'diyet' => 'diet',
            'egzersiz' => 'exercise',
            'yoga' => 'yoga',
            'meditasyon' => 'meditation',
            'kitap' => 'books',
            'film' => 'movies',
            'dizi' => 'tv series',
            'moda' => 'fashion',
            'dekorasyon' => 'decoration',
            'mimari' => 'architecture',
            'tasarım' => 'design',
            'minimalizm' => 'minimalism',
            'motivasyon' => 'motivation',
            'yaratıcılık' => 'creativity',
            'ilişkiler' => 'relationships',
            'evlilik' => 'marriage',
            'aile' => 'family',
            'ebeveynlik' => 'parenting',
            'psikoloji' => 'psychology',
            'mutluluk' => 'happiness',
            'finans' => 'finance',
            'yatırım' => 'investment',
            'borsa' => 'stock market',
            'bütçe' => 'budget',
            'tasarruf' => 'savings',
            'para' => 'money',
            'şirket' => 'company',
            'başarı' => 'success',
            'strateji' => 'strategy',
            'pazarlama' => 'marketing',
            'satış' => 'sales',
            'marka' => 'brand',
            'yönetim' => 'management',
            'liderlik' => 'leadership',
            'kariyer' => 'career',
            'hukuk' => 'law',
            'politika' => 'politics',
            'tarih' => 'history',
            'felsefe' => 'philosophy',
            'din' => 'religion',
            'kültür' => 'culture',
            'gelenek' => 'tradition',
            'festival' => 'festival',
            'tatil' => 'holiday',
            'turizm' => 'tourism',
            'otel' => 'hotel',
            'restoran' => 'restaurant',
            'yiyecek' => 'food',
            'içecek' => 'drink',
            'kahve' => 'coffee',
            'çay' => 'tea',
            'şarap' => 'wine',
            'bira' => 'beer',
            'su' => 'water',
            'doğal' => 'natural',
            'organik' => 'organic',
            'vegan' => 'vegan',
            'vejetaryen' => 'vegetarian',
            'sağlıklı' => 'healthy',
            'hastalık' => 'disease',
            'tedavi' => 'treatment',
            'ilaç' => 'medicine',
            'hastane' => 'hospital',
            'doktor' => 'doctor',
            'hemşire' => 'nurse',
            'diş' => 'dental',
            'göz' => 'eye',
            'kalp' => 'heart',
            'beyin' => 'brain',
            'kas' => 'muscle',
            'kemik' => 'bone',
            'kan' => 'blood',
            'sinir' => 'nerve',
            'kilo' => 'weight',
            'diyet' => 'diet',
            'vitamin' => 'vitamin',
            'mineral' => 'mineral',
            'protein' => 'protein',
            'karbonhidrat' => 'carbohydrate',
            'yağ' => 'fat',
            'şeker' => 'sugar',
            'tuz' => 'salt',
            'baharat' => 'spice',
            'çikolata' => 'chocolate',
            'meyve' => 'fruit',
            'sebze' => 'vegetable',
            'et' => 'meat',
            'balık' => 'fish',
            'tavuk' => 'chicken',
            'peynir' => 'cheese',
            'süt' => 'milk',
            'yoğurt' => 'yogurt',
            'ekmek' => 'bread',
            'pirinç' => 'rice',
            'makarna' => 'pasta',
            'pizza' => 'pizza',
            'hamburger' => 'hamburger',
            'tatlı' => 'dessert',
            'dondurma' => 'ice cream',
            'pasta' => 'cake',
            'kurabiye' => 'cookie',
            'çorba' => 'soup',
            'salata' => 'salad',
            'yemek' => 'meal',
            'kahvaltı' => 'breakfast',
            'öğle yemeği' => 'lunch',
            'akşam yemeği' => 'dinner',
            'atıştırmalık' => 'snack',
            'tarif' => 'recipe',
            'pişirme' => 'cooking',
            'fırınlama' => 'baking',
            'kızartma' => 'frying',
            'haşlama' => 'boiling',
            'ızgara' => 'grill',
            'sofra' => 'table',
            'tabak' => 'plate',
            'çatal' => 'fork',
            'bıçak' => 'knife',
            'kaşık' => 'spoon',
            'bardak' => 'glass',
            'fincan' => 'cup',
            'mutfak' => 'kitchen',
            'banyo' => 'bathroom',
            'yatak odası' => 'bedroom',
            'oturma odası' => 'living room',
            'bahçe' => 'garden',
            'balkon' => 'balcony'
        ];
        
        // Kelimeyi küçük harfe çevir
        $lowerQuery = mb_strtolower(trim($query), 'UTF-8');
        
        // Önce tam cümle/kelime çevirileri kontrol et
        foreach ($phraseTranslations as $tr => $en) {
            if (strpos($lowerQuery, $tr) !== false) {
                $lowerQuery = str_replace($tr, $en, $lowerQuery);
            }
        }
        
        // Sonra kelime çevirilerini kontrol et
        $words = preg_split('/\s+/', $lowerQuery);
        $translatedWords = [];
        
        foreach ($words as $word) {
            if (isset($wordTranslations[$word])) {
                $translatedWords[] = $wordTranslations[$word];
            } else {
                // Çevirisi olmayan kelimeleri olduğu gibi bırak
                $translatedWords[] = $word;
            }
        }
        
        $translatedQuery = implode(' ', $translatedWords);
        
        // Eğer çeviri yapılamadıysa orijinal sorguyu döndür
        if (empty($translatedQuery)) {
            return $query;
        }
        
        return $translatedQuery;
    }
    
    /**
     * Log mesajı yazar
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [AI-IMAGE] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Makale için resimleri temizle (makale silindiğinde)
     */
    public function cleanupArticleImages($articleId) {
        try {
            $pattern = $this->uploadsDir . "*_{$articleId}_*";
            $files = glob($pattern);
            
            foreach ($files as $file) {
                unlink($file);
            }
            
            $this->log("Makale resimleri temizlendi (ID: $articleId)");
        } catch (Exception $e) {
            $this->log("Resim temizleme hatası: " . $e->getMessage());
        }
    }
    
    /**
     * Başlıktan SEO dostu URL slug'ı oluşturur
     */
    private function createSlugFromTitle($title) {
        if (empty($title)) {
            return '';
        }
        
        // Türkçe karakterleri İngilizce karakterlere dönüştür
        $tr = array('ş','Ş','ı','İ','ğ','Ğ','ü','Ü','ö','Ö','ç','Ç');
        $en = array('s','S','i','I','g','G','u','U','o','O','c','C');
        $title = str_replace($tr, $en, $title);
        
        // Tüm karakterleri küçük harfe dönüştür
        $title = mb_strtolower($title, 'UTF-8');
        
        // Alfanumerik olmayan karakterleri tire ile değiştir
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        
        // Başta ve sonda kalan tireleri temizle
        $title = trim($title, '-');
        
        // Uzunluk sınırlaması (50 karakter ile sınırla)
        if (strlen($title) > 50) {
            $title = substr($title, 0, 50);
            // Son karakterin tire olma ihtimalini kontrol et
            $title = rtrim($title, '-');
        }
        
        return $title;
    }
}
