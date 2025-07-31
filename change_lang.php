<?php
/**
 * Dil değiştirme sayfası
 */

// Oturum başlat
session_start();

// Functions dosyasını dahil et
require_once 'includes/functions.php';

// Dil parametresini al
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en']) ? $_GET['lang'] : 'tr';

// Dili değiştir
switchLang($lang);

// Yönlendirme URL'si
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

// URL güvenlik kontrolü - sadece aynı sitedeki URL'lere izin ver
if (strpos($redirect, 'http') === 0) {
    $redirect = 'index.php';
}

// Kullanıcıyı geri yönlendir
header("Location: $redirect");
exit;
