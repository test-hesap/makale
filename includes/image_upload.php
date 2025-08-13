<?php
/**
 * Güvenli Resim Yükleme İşleyicisi
 * Makale öne çıkan görsel yükleme için güvenli sistem
 */

// Gerekli dosyaları dahil et
require_once __DIR__ . '/config.php';

function handleImageUpload($file, $upload_dir = 'uploads/featured_images/') {
    // Sonuç dizisi
    $result = [
        'success' => false,
        'message' => '',
        'file_path' => '',
        'file_url' => ''
    ];
    
    // Dosya kontrolü
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Dosya yükleme hatası';
        return $result;
    }
    
    // Dosya boyutu kontrolü (maksimum 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxFileSize) {
        $result['message'] = 'Dosya boyutu çok büyük (maksimum 5MB)';
        return $result;
    }
    
    // Dosya uzantısı kontrolü
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        $result['message'] = 'Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir';
        return $result;
    }
    
    // MIME type kontrolü
    $allowedMimes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimes)) {
        $result['message'] = 'Geçersiz dosya formatı';
        return $result;
    }
    
    // Resim boyutu kontrolü
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $result['message'] = 'Geçersiz resim dosyası';
        return $result;
    }
    
    // Maksimum boyut kontrolü (4000x4000)
    if ($imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
        $result['message'] = 'Resim boyutu çok büyük (maksimum 4000x4000 piksel)';
        return $result;
    }
    
    // Upload dizinini oluştur
    $full_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $upload_dir;
    if (!file_exists($full_upload_dir)) {
        if (!mkdir($full_upload_dir, 0755, true)) {
            $result['message'] = 'Upload dizini oluşturulamadı';
            return $result;
        }
    }
    
    // Güvenli dosya adı oluştur
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $full_upload_dir . $fileName;
    $fileUrl = '/' . $upload_dir . $fileName;
    
    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Dosya izinlerini ayarla
        chmod($filePath, 0644);
        
        $result['success'] = true;
        $result['message'] = 'Dosya başarıyla yüklendi';
        $result['file_path'] = $filePath;
        $result['file_url'] = $fileUrl;
    } else {
        $result['message'] = 'Dosya yüklenirken hata oluştu';
    }
    
    return $result;
}

/**
 * AJAX resim yükleme endpoint'i
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    // Oturum kontrolü
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Oturum gerekli']);
        exit;
    }
    
    // CSRF token kontrolü (isteğe bağlı)
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
        exit;
    }
    
    // Resim yükleme
    $result = handleImageUpload($_FILES['image']);
    
    // JSON yanıt döndür
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>
