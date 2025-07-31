<?php
/**
 * AI Article Bot Cron Job
 * Bu script cron job ile günlük çalıştırılacak
 */

// PHP sürüm kontrolü
$requiredPhpVersion = '8.1.0';
if (version_compare(PHP_VERSION, $requiredPhpVersion, '<')) {
    echo "HATA: Bu script PHP $requiredPhpVersion veya daha yüksek bir sürüm gerektirir.\n";
    echo "Şu anda kullanılan PHP sürümü: " . PHP_VERSION . "\n";
    echo "Lütfen doğru PHP sürümüyle çalıştırın. Örnek:\n";
    echo "/opt/cpanel/ea-php81/root/usr/bin/php " . __FILE__ . "\n";
    exit(1);
}

require_once __DIR__ . '/includes/AIArticleBot.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Memory ve zaman limiti artır
ini_set('memory_limit', '256M');
set_time_limit(300); // 5 dakika

try {
    echo "AI Article Bot başlatılıyor...\n";
    
    $bot = new AIArticleBot();
    
    // Makale üret ve yayınla
    $articleId = $bot->generateAndPublishArticle();
    
    if ($articleId) {
        echo "Başarılı: Makale ID $articleId ile oluşturuldu.\n";
        
        // İstatistikleri göster
        $stats = $bot->getStats();
        if ($stats) {
            echo "Bot İstatistikleri:\n";
            echo "- Toplam Makale: {$stats['total_articles']}\n";
            echo "- Bugün: {$stats['today_articles']}\n";
            echo "- Bu Hafta: {$stats['week_articles']}\n";
            echo "- Bu Ay: {$stats['month_articles']}\n";
        }
    } else {
        echo "Hata: Makale oluşturulamadı.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "HATA: " . $e->getMessage() . "\n";
    exit(1);
}

echo "AI Article Bot tamamlandı.\n";
?>
