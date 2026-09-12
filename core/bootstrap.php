<?php
declare(strict_types=1);

// =========================================================================
// 1. Автозагрузка
// =========================================================================
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
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

// =========================================================================
// 2. Базовая инициализация ядра
//    ВАЖНО: порядок имеет значение!
//    Route → File → Session → Logger::setLogDir → Container
// =========================================================================

// 2.1. Базовые пути (должны быть установлены ДО создания Config/Logger)
Route::setBasePath(dirname(__DIR__));
File::setBaseDir(dirname(__DIR__));

// 2.2. Сессии (если класс Session существует)
if (class_exists('Session')) {
    Session::initSessionHandler();
}

// 2.3. Папка для логов (опционально, по умолчанию core/log/)
// Logger::setLogDir('storage/logs');

// =========================================================================
// 3. DI-контейнер
// =========================================================================
$di = new Container();
Container::setGlobal($di);

// =========================================================================
// 4. Регистрация ядра (singleton — один экземпляр на запрос)
// =========================================================================

// 4.1. Config — читает .env, один на всё приложение
$di->singleton(Config::class, fn() => new Config());

// 4.2. TimeDate — один на всё приложение (зависит от Config)
$di->singleton(TimeDate::class, fn(Container $c) => new TimeDate(
    $c->get(Config::class)
));

// 4.3. Logger — registry-singleton, но в DI регистрируем дефолтный
$di->singleton(Logger::class, fn() => Logger::getInstance('app.log'));

// 4.4. DataBase — одно соединение на запрос (зависит от Config и Logger)
$di->singleton(DataBase::class, fn(Container $c) => new DataBase(
    $c->get(Config::class),
    $c->get(Logger::class)
));

$di->singleton(AccountManagementSystem::class, fn(Container $c) => new AccountManagementSystem(
    $c->get(Config::class),
    $c->get(DataBase::class),
    $c->get(Logger::class),
    $c->get(Map::class)
));

// 4.5. DbQuery — factory (новый экземпляр, т.к. может быть нужен с разными настройками)
$di->factory(DbQuery::class, fn(Container $c) => new DbQuery(
    $c->get(DataBase::class),
    $c->get(Logger::class)
));

// =========================================================================
// 5. Регистрация сервисов приложения (factory — новые экземпляры)
// =========================================================================

// Template — factory (каждый раз новый, чтобы не было пересечений переменных)
$di->factory(Template::class, fn() => new Template());
$di->factory(Map::class, fn() => new Map());

// Если есть PageService и PageController — регистрируем их
if (class_exists('PageService')) {
    $di->factory(PageService::class, fn(Container $c) => new PageService(
        $c->get(DataBase::class),
        $c->get(Logger::class)
    ));
}

if (class_exists('PageController')) {
    $di->factory(PageController::class, fn(Container $c) => new PageController(
        $c->get(PageService::class),
        $c->get(Config::class),
        $c->get(Template::class)
    ));
}

// =========================================================================
// 6. Экспорт контейнера
// =========================================================================
$GLOBALS['di'] = $di;

// =========================================================================
// 7. Настройка отображения ошибок
// =========================================================================
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}