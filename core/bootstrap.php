<?php
declare(strict_types=1);

// 1. Подключаем автозагрузчик Composer (если используется)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Fallback: кастомный автозагрузчик, если Composer не установлен
    spl_autoload_register(function (string $className): void {
        static $classMap = null;
        
        if ($classMap === null) {
            $classMap = [];
            $baseDir = __DIR__ . '/classes/';
            
            if (!is_dir($baseDir)) {
                return;
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $classFromFile = $file->getBasename('.php');
                    $classMap[$classFromFile] = $file->getPathname();
                }
            }
        }
        
        if (isset($classMap[$className])) {
            require_once $classMap[$className];
        }
    });
}

// 2. Инициализация ядра - ВАЖНО: сначала устанавливаем базовые пути ДО создания Bootstrap
Route::setBasePath(dirname(__DIR__));
File::setBaseDir(dirname(__DIR__));

// 4. Настройка отображения ошибок
// ВНИМАНИЕ: APP_DEBUG должен быть определен до подключения этого файла (например, в .env или index.php)
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    // Режим разработки: показываем все ошибки
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // Продакшен: скрываем ошибки от пользователя, логируем в файл
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL); // E_ALL нужен, чтобы ошибки уходили в лог-файл (error_log)
}
