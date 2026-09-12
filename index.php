<?php
declare(strict_types = 1);
require_once __DIR__."/core/bootstrap.php";
ini_set('display_errors', 1);
error_reporting(E_ALL);


try {
    $AMS = $di->get(AccountManagementSystem::class);
}catch (exc $exc) {
    echo $exc->getMessage();
}

		if (empty($_COOKIE['PHPSESSID'])) {
    		session_id(Helper::getUUIDv4());
		}

        session_start();
        if (!isset($_SESSION['sess_user_data'])):
			$_SESSION['sess_user_data']['login'] = "Гость";
			$_SESSION['sess_user_data']['role'] = "visiter";
		else:
			return;
		endif;

// var_dump($_SESSION);
;

//$Redis = new Redis();

// if (!class_exists('Redis')) {
//     exit("Критическая ошибка: Расширение phpredis НЕ включено в настройках PHP OpenServer!");
// }

// try {
//     $redis = new Redis();
//     // Пробуем подключиться с таймаутом, чтобы скрипт не зависал
//     $redis->connect('127.0.1.55', 6379, 2.0); 
//     echo "Успешное подключение! Ответ: " . $redis->ping();
// } catch (Exception $e) {
//     echo "Сервер Redis выключен или недоступен: " . $e->getMessage();
// }
?>
<meta charset="UTF-8">
<title>{{title}}</title>

<!-- Basic Meta -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="{{description}}">
<meta name="keywords" content="{{keywords}}">
<meta name="author" content="{{author}}">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="{{theme_color}}">

<!-- SEO -->
<link rel="canonical" href="{{canonical}}">

<!-- Open Graph -->
<meta property="og:locale" content="{{og_locale}}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{site_name}}">
<meta property="og:title" content="{{title}}">
<meta property="og:description" content="{{description}}">
<meta property="og:url" content="{{canonical}}">
<meta property="og:image" content="{{og_image}}">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{title}}">
<meta name="twitter:description" content="{{description}}">
<meta name="twitter:image" content="{{og_image}}">
<meta name="twitter:site" content="{{twitter_site}}">

<!-- Geo -->
<meta name="geo.region" content="{{geo_region}}">
<meta name="geo.placename" content="{{geo_placename}}">
<meta name="geo.position" content="{{geo_position}}">
<meta name="ICBM" content="{{geo_position}}">

<!-- Icons / PWA -->
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">

<!-- Styles -->
<link rel="stylesheet" href="/assets/css/style.css">
