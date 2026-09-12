<?php
declare(strict_types=1);

/**
 * Пример работы с классом Config
 *
 * Этот файл демонстрирует все основные возможности класса Config
 * для работы с .env файлами конфигурации
 *
 * =========================================
 * БЫСТРЫЙ СТАРТ
 * =========================================
 *
 * 1. Подключение:
 *    require_once 'path/to/vendor/autoload.php';
 *    $config = new Config('config.env');
 *
 * 2. Получение значения из БД:
 *    $dbHost = $config->getConfig('dataBase.db_host');
 *
 * 3. Получение переменной окружения:
 *    $dbLogin = $config->getEnv('DB_LOGIN');
 *
 * 4. Проверка наличия параметра:
 *    if ($config->hasConfig('dataBase.db_host')) { ... }
 *
 * 5. Установка переменной окружения:
 *    $config->setEnv('MY_VAR', 'value');
 *
 * 6. Сохранение в .env файл:
 *    $config->saveEnv();
 */

// Подключаем автозагрузчик
require_once __DIR__ . '/../vendor/autoload.php';


echo "===========================================\n";
echo "Пример работы с классом Config\n";
echo "===========================================\n\n";

try {
    // =========================================
    // 1. Создание экземпляра Config
    // =========================================
    echo "1. Создаём экземпляр Config:\n";
    echo "   Загружаем config.env из core/...\n";

    $config = new Config('config.env');
    echo "   ✓ Config успешно загружен\n\n";

    // =========================================
    // 2. Получение значений из конфигурации
    // =========================================
    echo "2. Получение значений через getConfig():\n";

    // Точечная нотация: секция.параметр
    $dbLogin = $config->getConfig('dataBase.db_login');
    echo "   DB Login: $dbLogin\n";

    $dbHost = $config->getConfig('dataBase.db_host');
    echo "   DB Host: $dbHost\n";

    $dbName = $config->getConfig('dataBase.db_name');
    echo "   DB Name: $dbName\n";

    $timezone = $config->getConfig('dateTime.default_timezone');
    echo "   Timezone: $timezone\n";

    $activeMode = $config->getConfig('mode.active_mode');
    echo "   Active Mode: $activeMode\n";

    $sessionHandler = $config->getConfig('sessionHandler.save_session_handler');
    echo "   Session Handler: $sessionHandler\n\n";

    // =========================================
    // 3. Получение переменных окружения
    // =========================================
    echo "3. Получение переменных окружения через getEnv():\n";

    $dbLoginEnv = $config->getEnv('DB_LOGIN');
    echo "   DB_LOGIN (из .env): $dbLoginEnv\n";

    $dbPassword = $config->getEnv('DB_PASSWORD');
    echo "   DB_PASSWORD: $dbPassword\n";

    $saveSessionHost = $config->getEnv('SAVE_SESSION_PATH_HOST');
    echo "   SAVE_SESSION_PATH_HOST: $saveSessionHost\n\n";

    // =========================================
    // 4. Проверка наличия параметров
    // =========================================
    echo "4. Проверка наличия параметров через hasConfig():\n";

    if ($config->hasConfig('dataBase.db_host')) {
        echo "   ✓ dataBase.db_host существует\n";
    }

    if (!$config->hasConfig('dataBase.nonexistent_param')) {
        echo "   ✓ dataBase.nonexistent_param не существует (как и ожидалось)\n";
    }

    echo "\n";

    // =========================================
    // 5. Получение всей конфигурации
    // =========================================
    echo "5. Получение всей конфигурации через getAll():\n";

    $allConfig = $config->getAll();
    echo "   Всего секций: " . count($allConfig) . "\n";
    echo "   Секции: " . implode(', ', array_keys($allConfig)) . "\n\n";

    // =========================================
    // 6. Получение всех переменных окружения
    // =========================================
    echo "6. Получение всех переменных окружения через getAllEnv():\n";

    $allEnv = $config->getAllEnv();
    echo "   Всего переменных: " . count($allEnv) . "\n";
    echo "   Переменные: " . implode(', ', array_keys($allEnv)) . "\n\n";

    // =========================================
    // 7. Установка переменной окружения
    // =========================================
    echo "7. Установка переменной окружения через setEnv():\n";

    $config->setEnv('CUSTOM_VAR', 'custom_value');
    echo "   Установили CUSTOM_VAR = custom_value\n";
    echo "   Получаем: " . $config->getEnv('CUSTOM_VAR') . "\n\n";

    // =========================================
    // 8. Обработка ошибок
    // =========================================
    echo "8. Обработка ошибок:\n";

    try {
        $nonExistent = $config->getConfig('dataBase.nonexistent');
    } catch (ConfigException $e) {
        echo "   ✓ Ошибка поймана: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // =========================================
    // 9. Практический пример: подключение к БД
    // =========================================
    echo "9. Практический пример - подготовка DSN для подключения к БД:\n";

    $driver = $config->getConfig('dataBase.db_driver');
    $host = $config->getConfig('dataBase.db_host');
    $dbname = $config->getConfig('dataBase.db_name');
    $charset = $config->getConfig('dataBase.db_charset');
    $login = $config->getConfig('dataBase.db_login');
    $password = $config->getConfig('dataBase.db_password');

    $dsn = "$driver:host=$host;dbname=$dbname;charset=$charset";
    echo "   DSN: $dsn\n";
    echo "   Username: $login\n";
    echo "   Password: " . str_repeat('*', strlen($password)) . "\n\n";

    // =========================================
    // 10. Сохранение изменений в .env файл
    // =========================================
    echo "10. Сохранение изменений в .env файл (пример):\n";
    echo "    Если нужно сохранить изменения, используйте:\n";
    echo "    \$config->saveEnv(); // сохранит в текущий файл\n";
    echo "    \$config->saveEnv('path/to/new.env'); // сохранит в новый файл\n\n";

    echo "===========================================\n";
    echo "Все примеры выполнены успешно!\n";
    echo "===========================================\n";

} catch (ConfigException $e) {
    echo "❌ Ошибка Config: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Общая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
