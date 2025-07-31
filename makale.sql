-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 31 Tem 2025, 14:18:11
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `makale`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `type`, `user_id`, `message`, `link`, `related_id`, `is_read`, `created_at`) VALUES
(45, 'new_user', NULL, 'test adlı yeni bir kullanıcı kaydoldu', '/admin/users.php?id=2', 2, 1, '2025-07-29 12:28:46'),
(46, 'new_article', NULL, 'test tarafından \"deneme\" başlıklı yeni bir makale eklendi', '/admin/articles.php?action=edit&id=1', 1, 1, '2025-07-29 12:29:38');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ai_bot_settings`
--

CREATE TABLE `ai_bot_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `ai_bot_settings`
--

INSERT INTO `ai_bot_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'gemini_api_key', '', '2025-07-19 10:49:55', '2025-07-29 12:23:54'),
(2, 'grok_api_key', '', '2025-07-19 10:49:55', '2025-07-29 12:23:54'),
(3, 'huggingface_api_key', '', '2025-07-19 10:49:55', '2025-07-29 12:23:54'),
(4, 'default_provider', 'gemini', '2025-07-19 10:49:55', '2025-07-29 12:23:54'),
(5, 'bot_enabled', '0', '2025-07-19 10:49:55', '2025-07-29 12:23:54');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `is_premium` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `view_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `article_featured`
--

CREATE TABLE `article_featured` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `article_headlines`
--

CREATE TABLE `article_headlines` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `position` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `article_views`
--

CREATE TABLE `article_views` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `banned_users`
--

CREATE TABLE `banned_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `banned_by` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_ip_banned` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Ban kaydının aktif olup olmadığı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bot_visits`
--

CREATE TABLE `bot_visits` (
  `id` int(11) NOT NULL,
  `bot_name` varchar(100) NOT NULL,
  `user_agent` text NOT NULL,
  `visit_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `page_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `reply` text DEFAULT NULL,
  `reply_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `online_bots`
--

CREATE TABLE `online_bots` (
  `id` int(11) NOT NULL,
  `bot_name` varchar(100) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `visit_count` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `online_guests`
--

CREATE TABLE `online_guests` (
  `id` int(11) NOT NULL,
  `guest_id` varchar(50) NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip_address` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `online_guests`
--

INSERT INTO `online_guests` (`id`, `guest_id`, `last_activity`, `ip_address`) VALUES
(568, 'guest_688b5ef47bbbe', '2025-07-31 12:17:58', '::1');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `package` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `performance_logs`
--

CREATE TABLE `performance_logs` (
  `id` int(11) NOT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `load_time` decimal(10,4) DEFAULT NULL,
  `memory_usage` int(11) DEFAULT NULL,
  `query_count` int(11) DEFAULT NULL,
  `query_time` decimal(10,4) DEFAULT NULL,
  `cache_hits` int(11) DEFAULT NULL,
  `cache_misses` int(11) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `performance_settings`
--

CREATE TABLE `performance_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `css_minify` tinyint(4) DEFAULT 1,
  `js_minify` tinyint(4) DEFAULT 1,
  `html_minify` tinyint(4) DEFAULT 1,
  `image_optimization` tinyint(4) DEFAULT 1,
  `cache_enabled` tinyint(4) DEFAULT 1,
  `gzip_compression` tinyint(4) DEFAULT 1,
  `browser_caching` tinyint(4) DEFAULT 1,
  `lazy_loading` tinyint(4) DEFAULT 1,
  `critical_css` tinyint(4) DEFAULT 1,
  `preload_fonts` tinyint(4) DEFAULT 1,
  `cache_duration` int(11) DEFAULT 3600,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `performance_settings`
--

INSERT INTO `performance_settings` (`id`, `css_minify`, `js_minify`, `html_minify`, `image_optimization`, `cache_enabled`, `gzip_compression`, `browser_caching`, `lazy_loading`, `critical_css`, `preload_fonts`, `cache_duration`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 3600, '2025-06-28 12:54:48');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `plans`
--

INSERT INTO `plans` (`id`, `name`, `price`, `duration`, `description`, `features`, `created_at`) VALUES
(16, 'Premium Aylık', 29.99, 30, 'Aylık premium üyelik', 'Premium özelliklere erişim', '2025-06-17 21:27:19'),
(17, 'Premium Yıllık', 239.99, 365, 'Yıllık premium üyelik', 'Premium özelliklere erişim', '2025-06-17 21:27:19'),
(18, 'Premium Hediye', 0.00, 30, 'Hediye premium üyelik', 'Premium özelliklere erişim', '2025-06-17 21:27:19'),
(19, 'Ücretsiz', 0.00, 0, 'Ücretsiz üyelik', 'Temel özellikler', '2025-06-17 21:27:27');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `refund_requests`
--

CREATE TABLE `refund_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cookie_text` varchar(255) DEFAULT '',
  `cookie_button` varchar(255) DEFAULT '',
  `cookie_position` varchar(255) DEFAULT '',
  `cookie_enabled` varchar(255) DEFAULT '1',
  `cookie_bg_color` varchar(255) DEFAULT '',
  `cookie_text_color` varchar(255) DEFAULT '',
  `cookie_button_color` varchar(255) DEFAULT '',
  `cookie_button_text_color` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `cookie_text`, `cookie_button`, `cookie_position`, `cookie_enabled`, `cookie_bg_color`, `cookie_text_color`, `cookie_button_color`, `cookie_button_text_color`) VALUES
(1, 'ad_header', '', '2025-06-13 23:31:15', 'Bu site, deneyiminizi geliştirmek için çerezleri kullanmaktadır. Sitemizi kullanmaya devam ederek, <a href=\"/cerez-politikasi.php\" class=\"text-blue-600 hover:underline\">çerez politikamızı</a> kabul etmiş olursunuz.', '', 'bottom-left', '1', '#f3f2f2', '#2f2d2d', '#231f1f', '#c9b6b6'),
(2, 'ad_sidebar_top', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(3, 'ad_sidebar_bottom', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(4, 'ad_article_top', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(5, 'ad_article_bottom', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(6, 'ad_between_articles', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(7, 'ad_article_middle', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(8, 'ad_footer_top', '', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(9, 'ad_status', 'active', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(10, 'ad_article_interval', '3', '2025-06-13 23:31:15', '', '', '', '1', '', '', '', ''),
(11, 'site_title', 'Localhost', '2025-06-13 23:31:32', '', '', '', '1', '', '', '', ''),
(12, 'site_description', 'deneme test', '2025-06-13 23:31:32', '', '', '', '1', '', '', '', ''),
(13, 'site_keywords', 'deneme,test', '2025-06-13 23:31:32', '', '', '', '1', '', '', '', ''),
(14, 'posts_per_page', '9', '2025-06-13 23:31:32', '', '', '', '1', '', '', '', ''),
(15, 'allow_comments', '1', '2025-06-13 23:31:32', '', '', '', '1', '', '', '', ''),
(151, 'premium_price', '30', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(152, 'premium_discount', '0', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(153, 'premium_duration', '30', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(154, 'premium_description', 'Reklamsız deneyim - Tüm reklamlar kaldırılır\r\nÖzel İçerikler - Sadece Premium üyelere özel\r\nVe daha fazlası - Gelecek özelliklere erken erişim', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(155, 'google_analytics', '', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(156, 'google_verification', '', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(157, 'yandex_verification', '', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(158, 'bing_verification', '', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(159, 'auto_meta_tags', '1', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(160, 'robots_txt', '', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(161, 'use_canonical', '1', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(162, 'social_facebook', 'https://www.facebook.com/', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(163, 'social_twitter', 'https://x.com/', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(164, 'social_instagram', 'https://www.instagram.com/', '2025-06-15 13:29:47', '', '', '', '1', '', '', '', ''),
(219, 'premium_price_monthly', '29.00', '2025-06-15 13:36:53', '', '', '', '1', '', '', '', ''),
(220, 'premium_price_yearly', '299.00', '2025-06-15 13:36:53', '', '', '', '1', '', '', '', ''),
(304, 'premium_faq_cancel', 'Profil sayfanızdan üyelik ayarlarına giderek istediğiniz zaman premium üyeliğinizi iptal edebilirsiniz. İptal ettiğinizde, bitiş tarihine kadar premium avantajlardan yararlanmaya devam edersiniz.', '2025-06-15 13:49:54', '', '', '', '1', '', '', '', ''),
(305, 'premium_faq_payment', 'Ödeme yapmak için kredi kartı, banka kartı veya havale/EFT yöntemlerini kullanabilirsiniz. Tüm ödemeleriniz 256-bit SSL ile şifrelenerek güvenle saklanır.', '2025-06-15 13:49:54', '', '', '', '1', '', '', '', ''),
(306, 'premium_faq_features', 'Premium üyelik; reklamsız deneyim, özel içeriklere erişim, öncelikli destek ve gelecek özelliklere erken erişim gibi avantajlar sunar.', '2025-06-15 13:49:54', '', '', '', '1', '', '', '', ''),
(307, 'premium_faq_refund', 'Satın alma işleminden sonraki 7 gün içerisinde, herhangi bir sebeple memnun kalmazsanız, tam iade garantisi sunmaktayız.', '2025-06-15 13:49:54', '', '', '', '1', '', '', '', ''),
(315, 'support_email', 'destek@siteadi.com', '2025-06-15 13:49:54', '', '', '', '1', '', '', '', ''),
(658, 'pagination_type', 'numbered', '2025-06-16 11:32:03', '', '', '', '1', '', '', '', ''),
(661, 'google_search_console', '', '2025-06-16 11:32:03', '', '', '', '1', '', '', '', ''),
(662, 'meta_title_format', '%title% | %site_name%', '2025-06-16 11:32:03', '', '', '', '1', '', '', '', ''),
(663, 'meta_description', '', '2025-06-16 11:32:03', '', '', '', '1', '', '', '', ''),
(664, 'canonical_url_type', 'current', '2025-06-16 11:32:03', '', '', '', '1', '', '', '', ''),
(690, 'premium_monthly_price', '29.99', '2025-06-16 11:34:41', '', '', '', '1', '', '', '', ''),
(691, 'premium_yearly_price', '239.99', '2025-06-16 11:34:41', '', '', '', '1', '', '', '', ''),
(692, 'premium_yearly_discount', '33', '2025-06-16 11:34:41', '', '', '', '1', '', '', '', ''),
(703, 'social_linkedin', '', '2025-06-16 11:34:41', '', '', '', '1', '', '', '', ''),
(704, 'social_youtube', '', '2025-06-16 11:34:41', '', '', '', '1', '', '', '', ''),
(961, 'premium_features', 'Reklamsız deneyim - Tüm reklamlar kaldırılır\r\nÖzel içeriklere erişim - Sadece premium üyelere özel içerikler\r\nMesaj gönderimi - Premium üyelere özel mesajlaşma\r\nVe daha fazlası... - Gelecek özelliklere erişim', '2025-06-16 11:50:26', '', '', '', '1', '', '', '', ''),
(1007, 'seo_title_format', '%title% - %sitename%', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1008, 'seo_meta_desc_limit', '160', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1009, 'seo_canonical_format', '%protocol%://%domain%%path%', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1010, 'seo_custom_meta', '', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1011, 'seo_default_image', '/assets/img/social-default.jpg', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1012, 'seo_open_graph', '1', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1013, 'seo_twitter_cards', '1', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1014, 'seo_twitter_site', '@siteadi', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1015, 'seo_fb_page_id', '', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1016, 'sitemap_enabled', '1', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1017, 'sitemap_filename', 'sitemap.xml', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1018, 'sitemap_frequency', 'daily', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1019, 'sitemap_priority_home', '1.0', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1020, 'sitemap_priority_categories', '0.8', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1021, 'sitemap_priority_articles', '0.6', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1022, 'sitemap_priority_pages', '0.5', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1023, 'seo_allow_indexing', '1', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1024, 'seo_noindex_pages', '/aramalar/\r\n', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1025, 'seo_archives_robots', 'index,follow', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1026, 'seo_google_verification', '', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1027, 'seo_bing_verification', '', '2025-06-16 11:57:40', '', '', '', '1', '', '', '', ''),
(1264, 'ad_footer', '', '2025-06-16 16:11:44', '', '', '', '1', '', '', '', ''),
(1478, 'favicon', '', '2025-06-19 11:37:59', '', '', '', '1', '', '', '', ''),
(2290, 'turnstile_enabled', '0', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2291, 'turnstile_site_key', '', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2292, 'turnstile_secret_key', '', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2293, 'turnstile_login', '1', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2294, 'turnstile_register', '1', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2295, 'turnstile_contact', '1', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2296, 'turnstile_article', '1', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2297, 'turnstile_theme', 'auto', '2025-06-21 17:07:20', '', '', '', '1', '', '', '', ''),
(2951, 'smtp_enabled', '1', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2952, 'smtp_host', '', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2953, 'smtp_port', '587', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2954, 'smtp_secure', 'tls', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2955, 'smtp_username', '', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2956, 'smtp_password', '', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2957, 'smtp_from_email', '', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(2958, 'smtp_from_name', 'Localhost', '2025-06-24 19:37:34', '', '', '', '1', '', '', '', ''),
(3226, 'speed_enable_cache', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3227, 'speed_cache_lifetime', '3600', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3228, 'speed_exclude_urls', '/admin/\\n/login.php\\n/register.php', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3229, 'speed_minify_html', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3230, 'speed_minify_css', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3231, 'speed_minify_js', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3232, 'speed_optimize_images', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3233, 'speed_lazy_load', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3234, 'speed_gzip_compression', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3235, 'speed_browser_cache', '1', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3236, 'speed_browser_cache_time', '604800', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3237, 'speed_cdn_url', '', '2025-06-25 08:36:49', '', '', '', '1', '', '', '', ''),
(3285, 'cookie_consent_enabled', '1', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3286, 'cookie_consent_position', 'bottom-right', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3287, 'cookie_consent_text', 'Bu site, deneyiminizi geliştirmek için çerezleri kullanmaktadır. Sitemizi kullanmaya devam ederek, çerezler politikamızı kabul etmiş olursunuz.', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3288, 'cookie_consent_button_text', 'Kabul Et', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3289, 'cookie_consent_link_text', 'Daha Fazla Bilgi', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3290, 'cookie_consent_bg_color', '#2d3748', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3291, 'cookie_consent_text_color', '#ffffff', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3292, 'cookie_consent_button_bg_color', '#3b82f6', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3293, 'cookie_consent_button_text_color', '#ffffff', '2025-07-03 18:03:38', '', '', '', '1', '', '', '', ''),
(3395, 'site_url', '', '2025-07-03 19:19:44', '', '', '', '1', '', '', '', ''),
(3446, 'headline_enabled', '1', '2025-07-03 19:25:58', '', '', '', '1', '', '', '', ''),
(3447, 'headline_count', '5', '2025-07-03 19:25:58', '', '', '', '1', '', '', '', ''),
(3448, 'headline_style', 'top_articles_v2', '2025-07-03 19:25:58', '', '', '', '1', '', '', '', ''),
(3449, 'headline_auto_rotate', '1', '2025-07-03 19:25:58', '', '', '', '1', '', '', '', ''),
(3450, 'headline_rotation_speed', '5000', '2025-07-03 19:25:58', '', '', '', '1', '', '', '', ''),
(3558, 'ad_mobile_sticky', '', '2025-07-04 20:15:30', '', '', '', '1', '', '', '', ''),
(3559, 'show_recent_articles', '0', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3560, 'recent_articles_count', '2', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3561, 'recent_articles_title', 'Son Eklenen Makaleler', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3562, 'show_popular_articles', '1', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3563, 'popular_articles_count', '4', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3564, 'popular_articles_title', 'Popüler Makaleler', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3565, 'show_featured_articles', '1', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3566, 'featured_articles_count', '4', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3567, 'featured_articles_title', 'Öne Çıkan Makaleler', '2025-07-19 09:57:13', '', '', '', '1', '', '', '', ''),
(3930, 'site_logo', '', '2025-07-21 12:47:42', '', '', '', '1', '', '', '', ''),
(5125, 'site_logo_dark', '', '2025-07-23 13:27:50', '', '', '', '1', '', '', '', ''),
(5165, 'ad_header_below', '', '2025-07-23 14:32:08', '', '', '', '1', '', '', '', ''),
(5657, 'paytr_merchant_id', '', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5658, 'paytr_merchant_key', '', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5659, 'paytr_merchant_salt', '', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5660, 'paytr_test_mode', '1', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5661, 'iyzico_api_key', '', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5662, 'iyzico_secret_key', '', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5663, 'iyzico_base_url', 'https://sandbox-api.iyzipay.com', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', ''),
(5664, 'payment_methods', '{\"paytr\":{\"active\":0,\"name\":\"PayTR\"},\"iyzico\":{\"active\":1,\"name\":\"iyzico\"}}', '2025-07-27 18:00:09', '', '', '', '1', '', '', '', '');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','expired') DEFAULT 'active',
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `order_num` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `team_members`
--

INSERT INTO `team_members` (`id`, `user_id`, `name`, `title`, `avatar`, `bio`, `order_num`, `is_active`) VALUES
(1, NULL, 'bilgi', 'üye', '', '', 1, 1),
(3, 1, 'Admin', 'Admin', '', '', 2, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `premium_until` date DEFAULT NULL,
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `can_post` tinyint(1) DEFAULT 0,
  `approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `register_date` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `youtube` varchar(50) DEFAULT NULL COMMENT 'YouTube kanal ID',
  `tiktok` varchar(50) DEFAULT NULL COMMENT 'TikTok kullanıcı adı',
  `github` varchar(50) DEFAULT NULL COMMENT 'GitHub kullanıcı adı',
  `last_activity` timestamp NULL DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `password`, `avatar`, `role`, `is_premium`, `premium_until`, `status`, `can_post`, `approved`, `created_at`, `bio`, `location`, `website`, `twitter`, `facebook`, `instagram`, `linkedin`, `register_date`, `last_login`, `last_ip`, `youtube`, `tiktok`, `github`, `last_activity`, `is_online`) VALUES
(1, 'admin', 'Bulent', 'admin@local.ben', '$2y$10$2CCnweRZBCjG0mks8Uvyc.8txmBxL3DV8hRQ4iQmKuB0iwCs8BpM2', 'default-avatar.jpg', 'admin', 0, NULL, 'active', 0, 0, '2025-06-13 23:31:26', '4', 'dasdas', 'https://example.com', '1', '2', '3', '4', '2025-06-16 23:17:01', '2025-07-31 15:18:02', NULL, '5', '6', '7', '2025-07-31 12:18:04', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_blocks`
--

CREATE TABLE `user_blocks` (
  `id` int(11) NOT NULL,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_messages`
--

CREATE TABLE `user_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `is_deleted_by_sender` tinyint(1) DEFAULT 0,
  `is_deleted_by_receiver` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_remember_tokens`
--

CREATE TABLE `user_remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `ai_bot_settings`
--
ALTER TABLE `ai_bot_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Tablo için indeksler `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `idx_slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Tablo için indeksler `article_featured`
--
ALTER TABLE `article_featured`
  ADD PRIMARY KEY (`id`),
  ADD KEY `article_id` (`article_id`);

--
-- Tablo için indeksler `article_headlines`
--
ALTER TABLE `article_headlines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `article_id` (`article_id`),
  ADD KEY `status` (`status`),
  ADD KEY `position` (`position`);

--
-- Tablo için indeksler `article_views`
--
ALTER TABLE `article_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `article_id` (`article_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `banned_users`
--
ALTER TABLE `banned_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ban` (`user_id`),
  ADD KEY `idx_banned_user` (`user_id`),
  ADD KEY `idx_banned_by` (`banned_by`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Tablo için indeksler `bot_visits`
--
ALTER TABLE `bot_visits`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_parent_category` (`parent_id`);

--
-- Tablo için indeksler `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `article_id` (`article_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_comments_parent` (`parent_id`);

--
-- Tablo için indeksler `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `online_bots`
--
ALTER TABLE `online_bots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_agent` (`user_agent`(100),`ip_address`);

--
-- Tablo için indeksler `online_guests`
--
ALTER TABLE `online_guests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guest_id` (`guest_id`);

--
-- Tablo için indeksler `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Tablo için indeksler `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `performance_logs`
--
ALTER TABLE `performance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_page_url` (`page_url`(255)),
  ADD KEY `idx_load_time` (`load_time`);

--
-- Tablo için indeksler `performance_settings`
--
ALTER TABLE `performance_settings`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Tablo için indeksler `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Tablo için indeksler `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Tablo için indeksler `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_block` (`blocker_id`,`blocked_id`),
  ADD KEY `idx_blocker` (`blocker_id`),
  ADD KEY `idx_blocked` (`blocked_id`);

--
-- Tablo için indeksler `user_messages`
--
ALTER TABLE `user_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_status` (`status`);

--
-- Tablo için indeksler `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- Tablo için AUTO_INCREMENT değeri `ai_bot_settings`
--
ALTER TABLE `ai_bot_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Tablo için AUTO_INCREMENT değeri `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `article_featured`
--
ALTER TABLE `article_featured`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Tablo için AUTO_INCREMENT değeri `article_headlines`
--
ALTER TABLE `article_headlines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Tablo için AUTO_INCREMENT değeri `article_views`
--
ALTER TABLE `article_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- Tablo için AUTO_INCREMENT değeri `banned_users`
--
ALTER TABLE `banned_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Tablo için AUTO_INCREMENT değeri `bot_visits`
--
ALTER TABLE `bot_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Tablo için AUTO_INCREMENT değeri `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `online_bots`
--
ALTER TABLE `online_bots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `online_guests`
--
ALTER TABLE `online_guests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=569;

--
-- Tablo için AUTO_INCREMENT değeri `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Tablo için AUTO_INCREMENT değeri `performance_logs`
--
ALTER TABLE `performance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Tablo için AUTO_INCREMENT değeri `refund_requests`
--
ALTER TABLE `refund_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Tablo için AUTO_INCREMENT değeri `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6238;

--
-- Tablo için AUTO_INCREMENT değeri `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `user_blocks`
--
ALTER TABLE `user_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `user_messages`
--
ALTER TABLE `user_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Tablo için AUTO_INCREMENT değeri `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `article_featured`
--
ALTER TABLE `article_featured`
  ADD CONSTRAINT `article_featured_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `article_headlines`
--
ALTER TABLE `article_headlines`
  ADD CONSTRAINT `article_headlines_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `article_views`
--
ALTER TABLE `article_views`
  ADD CONSTRAINT `article_views_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `banned_users`
--
ALTER TABLE `banned_users`
  ADD CONSTRAINT `banned_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `banned_users_ibfk_2` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_parent_category` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD CONSTRAINT `refund_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `refund_requests_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `user_blocks_ibfk_1` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_blocks_ibfk_2` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_messages`
--
ALTER TABLE `user_messages`
  ADD CONSTRAINT `user_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  ADD CONSTRAINT `user_remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
