<?php
require_once '../includes/config.php';
checkAuth(true); // true parametresi admin kontrolü için

// Başlık ve açıklama
$page_title = t('admin_backup');
$page_description = t('admin_backup_description');

// Yedek klasörlerini oluştur
$backup_dir = '../backups';
$db_backup_dir = $backup_dir . '/database';
$files_backup_dir = $backup_dir . '/files';

// Klasörlerin varlığını kontrol et, yoksa oluştur
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}
if (!file_exists($db_backup_dir)) {
    mkdir($db_backup_dir, 0755, true);
}
if (!file_exists($files_backup_dir)) {
    mkdir($files_backup_dir, 0755, true);
}

// Veritabanı yedeği alma fonksiyonu
function backupDatabase($db_config, $output_file) {
    // Windows XAMPP için mysqldump yolunu belirle
    $mysqldump_cmd = 'mysqldump';
    if (PHP_OS == 'WINNT') {
        // Windows için XAMPP MySQL bin dizinini kontrol et
        $xampp_mysqldump = 'C:\xampp\mysql\bin\mysqldump.exe';
        if (file_exists($xampp_mysqldump)) {
            $mysqldump_cmd = $xampp_mysqldump;
        }
    }
    
    // Çıktı dosyasının yazılabilir olduğunu kontrol et
    $output_dir = dirname($output_file);
    if (!is_writable($output_dir)) {
        error_log(t('admin_output_dir_not_writable') . ': ' . $output_dir);
        return false;
    }
    
    // MySQL kimlik bilgilerini dosyaya yazarak güvenli bağlantı sağla
    $tmp_file = tempnam(sys_get_temp_dir(), 'mysql_');
    if ($tmp_file === false) {
        error_log(t('admin_temp_file_creation_error'));
        return false;
    }
    
    // MySQL config dosyası oluştur
    file_put_contents($tmp_file, "[client]\nhost=\"{$db_config['host']}\"\nuser=\"{$db_config['username']}\"\npassword=\"{$db_config['password']}\"\n");
    chmod($tmp_file, 0600); // Sadece kullanıcının okumasına izin ver
    
    $command = sprintf(
        '"%s" --defaults-extra-file=%s %s > %s',
        $mysqldump_cmd,
        escapeshellarg($tmp_file),
        escapeshellarg($db_config['database']),
        escapeshellarg($output_file)
    );
    
    // Komut hakkında debug bilgisi (şifre gizli olduğu için güvenli)
    error_log(t('admin_running_db_backup_command'));
    
    // Komutu çalıştır
    exec($command, $output, $return_var);
    
    // Geçici dosyayı sil
    unlink($tmp_file);
    
    if ($return_var !== 0) {
        error_log(t('admin_db_backup_error') . ': ' . $return_var);
        error_log(t('admin_output') . ': ' . implode("\n", $output));
        return false;
    }
    
    // Dosya boyutunu kontrol et
    if (!file_exists($output_file) || filesize($output_file) < 10) {
        error_log(t('admin_db_backup_file_error') . ': ' . 
                 (file_exists($output_file) ? filesize($output_file) . ' ' . t('admin_bytes') : t('admin_file_not_exists')));
        return false;
    }
    
    // Windows'ta gzip kullanmayalım, XAMPP'ta sorun çıkarabilir
    // $command = sprintf('gzip -f %s', escapeshellarg($output_file));
    // exec($command);
    
    return file_exists($output_file);
}

// Dosya yedeği alma fonksiyonu
function backupFiles($source_dir, $output_file) {
    // ZipArchive sınıfının varlığını kontrol et
    if (!class_exists('ZipArchive')) {
        $alternative_available = extension_loaded('zip');
        error_log(t('admin_ziparchive_not_found') . '. ' . 
                 ($alternative_available ? t('admin_zip_extension_available') : t('admin_zip_extension_not_available')));
        
        // Kullanıcıya PHP Zip modülünü nasıl etkinleştireceğine dair bilgi
        global $error_message;
        $error_message = t('admin_php_zip_extension_help');
        
        return false;
    }
    
    // Yedeklenecek dizinler
    $dirs_to_backup = [
        '../uploads',
        '../assets',
        '../templates',
        '../admin',
        '../includes',
        '../vendor',
        '..'  // Ana dizin için de ekleyelim
    ];
    
    // Hariç tutulacak dizinler
    $excluded_dirs = [
        '../backups',
        '../logs',
        '../vendor/composer/tmp'
    ];
    
    // Yedek dizini yoksa oluştur
    $backup_dir = dirname($output_file);
    if (!file_exists($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            error_log(t('admin_backup_dir_creation_error') . ": " . $backup_dir);
            return false;
        }
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($output_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== true) {
        error_log(t('admin_zip_file_creation_error') . ': ' . $output_file . '. ' . t('admin_error_code') . ': ' . $result);
        return false;
    }
    
    foreach ($dirs_to_backup as $dir) {
        if (!file_exists($dir)) {
            error_log(t('admin_directory_not_found_skipping') . ": " . $dir);
            continue;
        }
        
        try {
            // Windows yollarını düzgün işlemek için normalleştirelim
            $dir = rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/';
            $base_path = rtrim(str_replace('\\', '/', realpath('..')), '/') . '/';
            
            $dir_iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
            
            foreach ($iterator as $file) {
                $file_path = str_replace('\\', '/', $file->getRealPath());
                
                // Klasörleri atla
                if ($file->isDir()) {
                    continue;
                }
                
                // Hariç tutulan dizinleri kontrol et
                $exclude = false;
                foreach ($excluded_dirs as $excluded_dir) {
                    $excluded_dir = rtrim(str_replace('\\', '/', realpath($excluded_dir) ?: $excluded_dir), '/') . '/';
                    if (strpos($file_path, $excluded_dir) === 0) {
                        $exclude = true;
                        break;
                    }
                }
                
                if ($exclude) {
                    continue;
                }
                
                // Yedeklenecek dosyaları filtrele
                if (strpos($file_path, '/temp/') !== false || 
                    strpos($file_path, '/backups/') !== false ||
                    strpos($file_path, '/logs/') !== false) {
                    continue;
                }
                
                // Dosya boyutu çok büyükse atla (25MB üzeri)
                if ($file->getSize() > 25 * 1024 * 1024) {
                    error_log(t('admin_file_too_large_skipping') . ": " . $file_path . " (" . humanFilesize($file->getSize()) . ")");
                    continue;
                }
                
                // ZIP içindeki yolu oluştur - ana dizine göre relatif olsun
                $relative_path = str_replace($base_path, '', $file_path);
                
                // ZIP'e ekle
                if (!$zip->addFile($file_path, $relative_path)) {
                    error_log(t('admin_file_add_to_zip_error') . ": " . $file_path);
                }
            }
        } catch (Exception $e) {
            error_log(t('admin_directory_backup_error') . ": " . $dir . " - " . $e->getMessage());
        }
    }
    
    // ZIP'i kapat
    if (!$zip->close()) {
        error_log(t('admin_zip_close_error') . ": " . $output_file);
        return false;
    }
    
    // Arşiv dosyasını kontrol et
    if (!file_exists($output_file)) {
        error_log(t('admin_zip_file_not_created') . ": " . $output_file);
        return false;
    }
    
    $filesize = filesize($output_file);
    if ($filesize < 100) { // Çok küçük bir ZIP dosyası muhtemelen boştur
        error_log(t('admin_zip_file_too_small') . ": " . $output_file . " (" . humanFilesize($filesize) . ")");
        // Yine de başarılı sayalım, belki hiç dosya eklenmemiştir
    } else {
        error_log(t('admin_zip_backup_success') . ": " . $output_file . " (" . humanFilesize($filesize) . ")");
    }
    
    return file_exists($output_file);
}

// İnsan okunabilir dosya boyutu dönüştürme fonksiyonu
function humanFilesize($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $step = 1024;
    $i = 0;
    
    while (($size / $step) > 0.9) {
        $size = $size / $step;
        $i++;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

// Yedekleme ayarlarını kaydet
if (isset($_POST['save_settings'])) {
    $auto_backup = $_POST['auto_backup'] ?? 'weekly';
    $retention_period = intval($_POST['retention_period'] ?? 30);
    $backup_content = isset($_POST['backup_content']) ? $_POST['backup_content'] : ['database'];
    
    // Ayarları veritabanına kaydet
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                          ON DUPLICATE KEY UPDATE setting_value = :value");
    
    $stmt->execute([':key' => 'backup_frequency', ':value' => $auto_backup]);
    $stmt->execute([':key' => 'backup_retention', ':value' => $retention_period]);
    $stmt->execute([':key' => 'backup_content', ':value' => json_encode($backup_content)]);
    
    // Settings dizisini güncelle
    $settings['auto_backup'] = $auto_backup;
    $settings['retention_period'] = $retention_period;
    $settings['backup_content'] = $backup_content;
    
    $success_message = "Yedekleme ayarları başarıyla kaydedildi!";
}

// Mevcut yedekleme ayarlarını al
$settings = [
    'auto_backup' => 'weekly',
    'retention_period' => '30',
    'backup_content' => ['database']
];

try {
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('backup_frequency', 'backup_retention', 'backup_content')");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] == 'backup_frequency') {
            $settings['auto_backup'] = $row['setting_value'];
            $auto_backup = $row['setting_value'];
        } elseif ($row['setting_key'] == 'backup_retention') {
            $settings['retention_period'] = $row['setting_value'];
            $retention_period = $row['setting_value'];
        } elseif ($row['setting_key'] == 'backup_content') {
            $settings['backup_content'] = json_decode($row['setting_value'], true) ?: ['database'];
        }
    }
} catch (Exception $e) {
    // İlk kurulum olabilir, varsayılanları kullan
    $auto_backup = $settings['auto_backup'];
    $retention_period = $settings['retention_period'];
}

// Yedeği sil
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $encoded_file = $_GET['delete'];
    $file_to_delete = realpath(base64_decode($encoded_file));
    
    // Yol güvenliği kontrolü
    $is_valid_path = false;
    $backup_real_path = realpath($backup_dir);
    
    if ($file_to_delete !== false && $backup_real_path !== false) {
        // Backup dizininin içinde olduğundan emin olalım
        $is_valid_path = (strpos($file_to_delete, $backup_real_path) === 0);
    }
    
    if ($is_valid_path && file_exists($file_to_delete) && is_file($file_to_delete)) {
        try {
            // Windows'ta bazen dosya erişimi engellenir, bu yüzden tekrar deneyelim
            $max_attempts = 3;
            $deleted = false;
            
            for ($i = 0; $i < $max_attempts && !$deleted; $i++) {
                if (@unlink($file_to_delete)) {
                    $deleted = true;
                    $success_message = t('admin_backup_deleted_success');
                    break;
                } else {
                    // Dosya meşgul olabilir, biraz bekleyelim
                    if (function_exists('opcache_reset')) {
                        @opcache_reset(); // OPcache'i temizleyelim
                    }
                    clearstatcache(true, $file_to_delete);
                    sleep(1);
                }
            }
            
            if (!$deleted) {
                $error = error_get_last();
                $error_msg = isset($error['message']) ? $error['message'] : t('admin_unknown_error');
                $error_message = t('admin_backup_delete_error') . ": " . $error_msg;
                error_log(t('admin_backup_delete_error_log') . ": " . $file_to_delete . " - " . $error_msg);
            }
        } catch (Exception $e) {
            $error_message = t('admin_file_delete_exception') . ": " . $e->getMessage();
            error_log($error_message);
        }
    } else {
        if (!$is_valid_path) {
            $error_message = t('admin_invalid_backup_path');
            error_log(t('admin_invalid_backup_path_log') . ": " . ($file_to_delete ?: t('admin_unresolved_path') . ': ' . $encoded_file));
        } elseif (!file_exists($file_to_delete)) {
            $error_message = t('admin_file_to_delete_not_found');
            error_log(t('admin_file_to_delete_not_found_log') . ": " . $file_to_delete);
        } else {
            $error_message = t('admin_invalid_backup_file');
            error_log(t('admin_invalid_backup_file_type') . ": " . $file_to_delete);
        }
    }
}

// Yedek alma işlemi
if (isset($_POST['create_backup'])) {
    $timestamp = date('Y-m-d_H-i-s');
    
    if ($_POST['create_backup'] == 'database') {
        // Veritabanı yedeği alma işlemi
        $output_file = $db_backup_dir . '/db_backup_' . $timestamp . '.sql';
        
        // Veritabanı yapılandırmasını al
        $db_config = [
            'host' => DB_HOST,
            'username' => DB_USER,
            'password' => DB_PASS,
            'database' => DB_NAME
        ];
        
        if (backupDatabase($db_config, $output_file)) {
            $success_message = t('admin_database_backup_success');
        } else {
            $error_message = t('admin_database_backup_error');
        }
    } elseif ($_POST['create_backup'] == 'files') {
        // Dosya yedeği alma işlemi
        $output_file = $files_backup_dir . '/files_backup_' . $timestamp . '.zip';
        
        // Dosya yedeği alma işlemini başlat
        error_log(t('admin_creating_file_backup') . ": " . $output_file);
        
        // Yedekleme işlemini dene
        $start_time = microtime(true);
        if (backupFiles('../', $output_file)) {
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            $filesize = filesize($output_file);
            
            $success_message = t('admin_file_backup_success') . " " . t('admin_size') . ": " . humanFilesize($filesize) . 
                              " (" . t('admin_process_time') . ": {$duration} " . t('admin_seconds') . ")";
            error_log($success_message);
        } else {
            $error_message = t('admin_file_backup_error');
            error_log($error_message);
        }
    }
}

// Mevcut yedekleri listele
$backups = [];

// Veritabanı yedeklerini listele
if (file_exists($db_backup_dir)) {
    $files = glob($db_backup_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            try {
                $filesize = filesize($file);
                $filemtime = filemtime($file);
                
                $backups[] = [
                    'name' => 'DB: ' . str_replace(['db_backup_', '.sql', '.gz'], '', $filename),
                    'type' => 'database',
                    'size' => humanFilesize($filesize),
                    'date' => date('d.m.Y H:i', $filemtime),
                    'download_url' => 'download.php?file=' . base64_encode($file),
                    'delete_url' => 'backup.php?delete=' . base64_encode($file),
                    'path' => $file
                ];
            } catch (Exception $e) {
                error_log('Yedek dosya bilgisi alınırken hata: ' . $e->getMessage() . ' - Dosya: ' . $file);
            }
        }
    }
}

// Dosya yedeklerini listele
if (file_exists($files_backup_dir)) {
    $files = glob($files_backup_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            try {
                $filesize = filesize($file);
                $filemtime = filemtime($file);
                
                $backups[] = [
                    'name' => 'Files: ' . str_replace(['files_backup_', '.zip'], '', $filename),
                    'type' => 'files',
                    'size' => humanFilesize($filesize),
                    'date' => date('d.m.Y H:i', $filemtime),
                    'download_url' => 'download.php?file=' . base64_encode($file),
                    'delete_url' => 'backup.php?delete=' . base64_encode($file),
                    'path' => $file
                ];
            } catch (Exception $e) {
                error_log('Yedek dosya bilgisi alınırken hata: ' . $e->getMessage() . ' - Dosya: ' . $file);
            }
        }
    }
}

// Tarihe göre sırala (en yeniler üstte)
usort($backups, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

include 'includes/header.php';
?>

<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo $page_title; ?>
        </h1>
    </div>

    <main class="px-4 py-6">
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 dark:bg-green-900 dark:text-green-200" role="alert">
                <p class="font-medium"><?php echo t('admin_success'); ?>!</p>
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 dark:bg-red-900 dark:text-red-200" role="alert">
                <p class="font-medium"><?php echo t('admin_error'); ?>!</p>
                <p><?php echo $error_message; ?></p>
                
                <?php if (!class_exists('ZipArchive')): ?>
                <div class="mt-3">
                    <p class="font-medium"><?php echo t('admin_php_zip_extension_missing'); ?></p>
                    <p><?php echo t('admin_enable_php_zip_extension'); ?>:</p>
                    <ol class="list-decimal ml-6 mt-2">
                        <li><?php echo t('admin_open_xampp_control_panel'); ?></li>
                        <li><?php echo t('admin_click_config_for_apache'); ?></li>
                        <li><?php echo t('admin_select_php_ini'); ?></li>
                        <li><?php echo t('admin_find_extension_zip_line'); ?></li>
                        <li><?php echo t('admin_remove_semicolon'); ?></li>
                        <li><?php echo t('admin_save_and_restart_apache'); ?></li>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Yeni Yedek Oluştur -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo t('admin_create_new_backup'); ?></h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Veritabanı Yedeği -->
                <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4"><?php echo t('admin_database_backup'); ?></h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                        <?php echo t('admin_database_backup_description'); ?>
                    </p>
                    <form method="post" action="">
                        <input type="hidden" name="create_backup" value="database">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i> <?php echo t('admin_create_database_backup'); ?>
                        </button>
                    </form>
                </div>

                <!-- Dosya Yedeği -->
                <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4"><?php echo t('admin_file_backup'); ?></h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                        <?php echo t('admin_file_backup_description'); ?>
                    </p>
                    <form method="post" action="">
                        <input type="hidden" name="create_backup" value="files">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 transition-colors duration-200">
                            <i class="fas fa-file-archive mr-2"></i> <?php echo t('admin_create_file_backup'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Yedek Listesi -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo t('admin_existing_backups'); ?></h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_backup_name'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_type'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_size'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_date'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo t('admin_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"><?php echo t('admin_no_backups_yet'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $backup): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($backup['name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $backup['type'] === 'database' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200'; ?>">
                                            <?php echo $backup['type'] === 'database' ? t('admin_database') : t('admin_files'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($backup['size']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($backup['date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="<?php echo htmlspecialchars($backup['download_url']); ?>" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 mr-3" title="<?php echo t('admin_download'); ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($backup['delete_url']); ?>" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" onclick="return confirm('<?php echo t('admin_confirm_backup_delete'); ?>');" title="<?php echo t('admin_delete'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Yedekleme Ayarları -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php echo t('admin_backup_settings'); ?></h2>
            
            <form method="post" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Otomatik Yedekleme -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" for="auto_backup">
                            <?php echo t('admin_automatic_backup'); ?>
                        </label>
                        <select id="auto_backup" name="auto_backup" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="daily" <?php echo ($settings['auto_backup'] ?? '') === 'daily' ? 'selected' : ''; ?>><?php echo t('admin_daily'); ?></option>
                            <option value="weekly" <?php echo ($settings['auto_backup'] ?? 'weekly') === 'weekly' ? 'selected' : ''; ?>><?php echo t('admin_weekly'); ?></option>
                            <option value="monthly" <?php echo ($settings['auto_backup'] ?? '') === 'monthly' ? 'selected' : ''; ?>><?php echo t('admin_monthly'); ?></option>
                            <option value="never" <?php echo ($settings['auto_backup'] ?? '') === 'never' ? 'selected' : ''; ?>><?php echo t('admin_no_automatic_backup'); ?></option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <?php echo t('admin_automatic_backup_note'); ?> 
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">0 3 * * * php <?php echo realpath(__DIR__ . '/../ai_bot_cron.php'); ?> backup</code> 
                        </p>
                    </div>
                    
                    <!-- Saklama Süresi -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" for="retention_period">
                            <?php echo t('admin_backup_retention_period'); ?>
                        </label>
                        <select id="retention_period" name="retention_period" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="7" <?php echo ($settings['retention_period'] ?? '') == 7 ? 'selected' : ''; ?>><?php echo t('admin_7_days'); ?></option>
                            <option value="14" <?php echo ($settings['retention_period'] ?? '') == 14 ? 'selected' : ''; ?>><?php echo t('admin_14_days'); ?></option>
                            <option value="30" <?php echo ($settings['retention_period'] ?? 30) == 30 ? 'selected' : ''; ?>><?php echo t('admin_30_days'); ?></option>
                            <option value="90" <?php echo ($settings['retention_period'] ?? '') == 90 ? 'selected' : ''; ?>><?php echo t('admin_90_days'); ?></option>
                            <option value="365" <?php echo ($settings['retention_period'] ?? '') == 365 ? 'selected' : ''; ?>><?php echo t('admin_1_year'); ?></option>
                        </select>
                    </div>
                </div>
                
                <!-- Yedeklenecek İçerik -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <?php echo t('admin_backup_content'); ?>
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input id="backup_database" name="backup_content[]" value="database" type="checkbox" 
                                   <?php echo in_array('database', $settings['backup_content'] ?? ['database']) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                            <label for="backup_database" class="ml-2 block text-sm text-gray-700 dark:text-gray-300"><?php echo t('admin_database'); ?></label>
                        </div>
                        <div class="flex items-center">
                            <input id="backup_files" name="backup_content[]" value="files" type="checkbox"
                                   <?php echo in_array('files', $settings['backup_content'] ?? []) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                            <label for="backup_files" class="ml-2 block text-sm text-gray-700 dark:text-gray-300"><?php echo t('admin_files'); ?></label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="save_settings" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition-colors duration-200">
                        <i class="fas fa-save mr-2"></i> <?php echo t('admin_save_settings'); ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
