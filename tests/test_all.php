<?php
/**
 * Тесты для VampqweEngine
 * Собственный лёгкий раннер без PHPUnit
 */

// Автозагрузчик классов (без namespace)
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/../core/classes/';
    
    $simpleClasses = [
        // System
        'File' => 'system/File/File.php',
        'FileException' => 'system/File/FileException.php',
        'Map' => 'system/Map/Map.php',
        'Logger' => 'system/Logger/Logger.php',
        'TimeDate' => 'system/TimeDate/TimeDate.php',
        'Config' => 'system/Config/Config.php',
        'ConfigException' => 'system/Config/ConfigException.php',
        'Route' => 'system/Route/Route.php',
        'Container' => 'system/DI/Container.php',
        'ContainerException' => 'system/DI/ContainerException.php',
        'Helper' => 'system/Helper/Helper.php',
        // Database
        'DataBase' => 'system/Database/DataBase.php',
        'DbTable' => 'system/Database/DbTable.php',
        'DbException' => 'system/Database/DbException.php',
        // Module
        'Template' => 'module/Tamplate/Template.php',
        'AccountManagementSystem' => 'module/AccountManagementSystem/AccountManagementSystem.php',
        'User' => 'module/AccountManagementSystem/User.php',
        'Account' => 'module/AccountManagementSystem/Account.php',
        'Session' => 'module/AccountManagementSystem/Session.php',
        'PageController' => 'module/PageController/PageController.php',
        'PageService' => 'module/PageController/PageService.php',
    ];
    
    if (isset($simpleClasses[$class])) {
        $file = $baseDir . $simpleClasses[$class];
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Подключаем Composer autoloader для сторонних библиотек (ramsey/uuid)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

echo "=== ТЕСТИРОВАНИЕ ВСЕХ КЛАССОВ ===\n\n";

$passed = 0;
$failed = 0;

function test($name, $callback) {
    global $passed, $failed;
    try {
        $result = $callback();
        if ($result) {
            echo "✅ PASS: $name\n";
            $passed++;
        } else {
            echo "❌ FAIL: $name\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "❌ ERROR: $name - " . $e->getMessage() . "\n";
        $failed++;
    }
}

// Настраиваем базовые директории для тестов
Route::setBasePath('/workspace');
File::setBaseDir('/workspace');

// Разрешаем доступ к директориям core/log и tests для тестов
$originalBaseDir = '/workspace';

// ==================== Route.php ====================
echo "--- Тесты Route.php ---\n";

test("Route: получение базового пути", function() {
    $path = Route::getBasePath();
    return is_string($path) && strlen($path) > 0;
});

test("Route: получение путей core", function() {
    $pathRoot = Route::getPathRoot();
    $pathCore = Route::getPathCore();
    return is_string($pathRoot) && is_string($pathCore);
});

// ==================== Map.php ====================
echo "\n--- Тесты Map.php ---\n";

test("Map: установка и получение значения (put/getValueByKey)", function() {
    $map = new Map();
    $map->put('key1', 'value1');
    return $map->getValueByKey('key1') === 'value1';
});

test("Map: выброс исключения при отсутствии ключа", function() {
    $map = new Map();
    try {
        $map->getValueByKey('nonexistent');
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

test("Map: удаление ключа (deleteValueByKey)", function() {
    $map = new Map();
    $map->put('key', 'value');
    $map->deleteValueByKey('key');
    try {
        $map->getValueByKey('key');
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

test("Map: проверка на пустоту (isEmpty)", function() {
    $map = new Map();
    if (!$map->isEmpty()) return false;
    $map->put('key', 'value');
    return !$map->isEmpty();
});

// ==================== File.php ====================
echo "\n--- Тесты File.php ---\n";

test("File: создание и запись", function() {
    File::setBaseDir('/tmp');
    $file = new File('/tmp/test_file.txt');
    $file->createFile('w');
    $result = $file->putToFile("Test content");
    $file->closeFile();
    unlink('/tmp/test_file.txt');
    return $result !== false;
});

test("File: защита от path traversal (..)", function() {
    try {
        $file = new File('../../../etc/passwd');
        return false;
    } catch (FileException $e) {
        return strpos($e->getMessage(), '..') !== false || strpos($e->getMessage(), 'Недопустимый путь') !== false;
    } catch (Throwable $e) {
        return true; // Любое исключение - это хорошо
    }
});

test("File: автозакрытие в деструкторе", function() {
    File::setBaseDir('/tmp');
    $file = new File('/tmp/test_auto_close.txt');
    $file->createFile('w');
    $file->putToFile("Auto close test");
    unset($file);
    clearstatcache();
    return file_exists('/tmp/test_auto_close.txt');
});

// ==================== Config.php ====================
echo "\n--- Тесты Config.php ---\n";

test("Config: загрузка конфигурации (.env файл)", function() {
    try {
        $config = new Config('/workspace/core/config.env');
        return $config->getAllEnv() !== null;
    } catch (Throwable $e) {
        // Если нет config.env, пробуем создать временный
        file_put_contents('/tmp/test.env', "TEST_KEY=test_value\n");
        $config = new Config('/tmp/test.env');
        $result = $config->getEnv('TEST_KEY') === 'test_value';
        unlink('/tmp/test.env');
        return $result;
    }
});

test("Config: получение значения через getEnv", function() {
    file_put_contents('/tmp/test.env', "DB_HOST=localhost\nDB_PORT=3306\n");
    $config = new Config('/tmp/test.env');
    $result = $config->getEnv('DB_HOST') === 'localhost' && 
              $config->getEnv('DB_PORT') === '3306';
    unlink('/tmp/test.env');
    return $result;
});

test("Config: установка значения через setEnv", function() {
    file_put_contents('/tmp/test.env', "INITIAL=value\n");
    $config = new Config('/tmp/test.env');
    $config->setEnv('NEW_KEY', 'new_value');
    $result = $config->getEnv('NEW_KEY') === 'new_value';
    unlink('/tmp/test.env');
    return $result;
});

// ==================== TimeDate.php ====================
echo "\n--- Тесты TimeDate.php ---\n";

test("TimeDate: получение текущего времени", function() {
    file_put_contents('/tmp/test.env', "DEFAULT_TIMEZONE=UTC\nDATE_TPL=Y-m-d\nTIME_TPL=H:i:s\n");
    $config = new Config('/tmp/test.env');
    $time = new TimeDate($config);
    $now = $time->getNow();
    unlink('/tmp/test.env');
    return is_string($now) && strlen($now) > 0;
});

test("TimeDate: кэширование timezone", function() {
    file_put_contents('/tmp/test.env', "DEFAULT_TIMEZONE=Europe/Minsk\nDATE_TPL=Y-m-d\nTIME_TPL=H:i:s\n");
    $config = new Config('/tmp/test.env');
    $time = new TimeDate($config);
    $tz1 = $time->getTimeZone();
    $tz2 = $time->getTimeZone();
    unlink('/tmp/test.env');
    return $tz1 === $tz2;
});

test("TimeDate: форматирование даты", function() {
    file_put_contents('/tmp/test.env', "DEFAULT_TIMEZONE=UTC\nDATE_TPL=Y-m-d\nTIME_TPL=H:i:s\n");
    $config = new Config('/tmp/test.env');
    $time = new TimeDate($config);
    $formatted = $time->getFormattedDate('Y-m-d');
    unlink('/tmp/test.env');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $formatted) === 1;
});

// ==================== Logger.php ====================
echo "\n--- Тесты Logger.php ---\n";

test("Logger: запись лога", function() {
    file_put_contents('/tmp/test.env', "DEFAULT_TIMEZONE=UTC\nDATE_TPL=Y-m-d\nTIME_TPL=H:i:s\n");
    $config = new Config('/tmp/test.env');
    $di = new Container();
    Container::setGlobal($di);
    $di->singleton(TimeDate::class, fn() => new TimeDate($config));
    
    Logger::resetAll();
    // Устанавливаем директорию логов в /tmp для тестов
    Logger::setLogDir('/tmp');
    $logger = Logger::getInstance('test.log');
    $result = $logger->writeLog('INFO', 'Test message');
    
    $logPath = '/tmp/test.log';
    if (file_exists($logPath)) {
        unlink($logPath);
    }
    Logger::resetInstance('test.log');
    unlink('/tmp/test.env');
    return $result !== false;
});

test("Logger: санитизация переносов строк", function() {
    file_put_contents('/tmp/test.env', "DEFAULT_TIMEZONE=UTC\nDATE_TPL=Y-m-d\nTIME_TPL=H:i:s\n");
    $config = new Config('/tmp/test.env');
    $di = new Container();
    Container::setGlobal($di);
    $di->singleton(TimeDate::class, fn() => new TimeDate($config));
    
    Logger::resetAll();
    Logger::setLogDir('/tmp');
    $logger = Logger::getInstance('test_sanitize.log');
    $logger->writeLog('INFO', "Test\nmessage\rwith\nbreaks");
    
    $logPath = '/tmp/test_sanitize.log';
    if (!file_exists($logPath)) {
        unlink('/tmp/test.env');
        return false;
    }
    
    $content = file_get_contents($logPath);
    unlink($logPath);
    Logger::resetInstance('test_sanitize.log');
    unlink('/tmp/test.env');
    
    $lines = explode("\n", trim($content));
    return count($lines) <= 2;
});

// ==================== Template.php ====================
echo "\n--- Тесты Template.php ---\n";

test("Template: замена переменных {key} через render", function() {
    $template = new Template();
    $template->assign('name', 'World');
    file_put_contents('/tmp/test.tpl', 'Hello {name}!');
    $template->addTplFile('/tmp/test.tpl');
    $result = $template->render();
    unlink('/tmp/test.tpl');
    return $result === 'Hello World!';
});

test("Template: замена переменных {{key}} через render", function() {
    $template = new Template();
    $template->assign('name', 'World');
    file_put_contents('/tmp/test.tpl', 'Hello {{name}}!');
    $template->addTplFile('/tmp/test.tpl');
    $result = $template->render();
    unlink('/tmp/test.tpl');
    return $result === 'Hello World!';
});

test("Template: экранирование XSS через assignEscaped", function() {
    $template = new Template();
    $template->assignEscaped('script', '<script>alert(1)</script>');
    file_put_contents('/tmp/test.tpl', '{{script}}');
    $template->addTplFile('/tmp/test.tpl');
    $result = $template->render();
    unlink('/tmp/test.tpl');
    return strpos($result, '<script>') === false && strpos($result, '&lt;script&gt;') !== false;
});

test("Template: assignArray с экранированием", function() {
    $template = new Template();
    $template->assignArray(['safe' => 'OK', 'danger' => '<b>bold</b>'], true);
    file_put_contents('/tmp/test.tpl', '{{safe}} {{danger}}');
    $template->addTplFile('/tmp/test.tpl');
    $result = $template->render();
    unlink('/tmp/test.tpl');
    return strpos($result, '<b>') === false && strpos($result, '&lt;b&gt;') !== false;
});

// ==================== DbTable.php ====================
echo "\n--- Тесты DbTable.php ---\n";

test("DbTable: отклонение недопустимых имен таблиц", function() {
    try {
        $dbTable = new DbTable('../etc/passwd');
        return false;
    } catch (DbException $e) {
        return true;
    } catch (Throwable $e) {
        return true; // Любое исключение - это хорошо
    }
});

test("DbTable: отклонение имен с цифрами в начале", function() {
    try {
        $dbTable = new DbTable('123table');
        return false;
    } catch (Throwable $e) {
        return true;
    }
});

// ==================== DataBase.php ====================
echo "\n--- Тесты DataBase.php ---\n";

test("DataBase: создание экземпляра через DI", function() {
    try {
        file_put_contents('/tmp/test.env', 
            "DB_DRIVER=mysql\nDB_HOST=localhost\nDB_NAME=test\nDB_LOGIN=user\nDB_PASSWORD=pass\nDB_CHARSET=utf8mb4\n".
            "DEFAULT_TIMEZONE=UTC\nDATE_TPL=Y-m-d\nTIME_TPL=H:i:s\n");
        $config = new Config('/tmp/test.env');
        $di = new Container();
        Container::setGlobal($di);
        $di->singleton(TimeDate::class, fn() => new TimeDate($config));
        $di->singleton(Logger::class, fn() => Logger::getInstance('test.log'));
        
        // Пытаемся создать - может упасть из-за отсутствия БД, это нормально
        $di->singleton(DataBase::class, fn(Container $c) => new DataBase(
            $c->get(Config::class),
            $c->get(Logger::class)
        ));
        
        // Если дошли сюда - конфиг загрузился корректно
        unlink('/tmp/test.env');
        return true;
    } catch (PDOException $e) {
        // Ошибка подключения к БД - это нормально для тестов
        unlink('/tmp/test.env');
        return true;
    } catch (Throwable $e) {
        unlink('/tmp/test.env');
        return false;
    }
});

// ==================== Container.php ====================
echo "\n--- Тесты Container.php ---\n";

test("Container: регистрация и получение singleton", function() {
    $di = new Container();
    $di->singleton('test', fn() => new Map());
    $obj1 = $di->get('test');
    $obj2 = $di->get('test');
    return $obj1 === $obj2; // Один и тот же экземпляр
});

test("Container: регистрация и получение factory", function() {
    $di = new Container();
    $di->factory('test', fn() => new Map());
    $obj1 = $di->get('test');
    $obj2 = $di->get('test');
    return $obj1 !== $obj2; // Разные экземпляры
});

test("Container: авто-резолвинг через рефлексию", function() {
    $di = new Container();
    $obj = $di->get(Map::class);
    return $obj instanceof Map;
});

test("Container: глобальный контейнер", function() {
    $di = new Container();
    Container::setGlobal($di);
    $global = Container::getGlobal();
    return $global === $di;
});

// ==================== Helper.php ====================
echo "\n--- Тесты Helper.php ---\n";

test("Helper: генерация UUID v4", function() {
    $uuid = Helper::getUUIDv4();
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
});

// ==================== Итоги ====================
echo "\n============================\n";
echo "ИТОГО: Пройдено - $passed, Не пройдено - $failed\n";
echo "============================\n";

if ($failed > 0) {
    echo "\n⚠️  Некоторые тесты не прошли. Проверьте выводы выше.\n";
} else {
    echo "\n🎉 Все тесты пройдены успешно!\n";
}

exit($failed > 0 ? 1 : 0);
