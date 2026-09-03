<?php
declare(strict_types = 1);
require_once __DIR__."/core/bootstrap.php";
ini_set('display_errors', 1);
error_reporting(E_ALL);
try {
    $AMS = new AccountManagementSystem();
}catch (exc $exc) {
    echo $exc->getMessage();
}

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
