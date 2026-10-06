<?php
declare(strict_types=1);
/**
 * VampqweEngine — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 * @email	   doktor_try@mail.ru
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */
// =========================================================================
// 1. Автозагрузка
// =========================================================================
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/functions/spl.php';
}
// =========================================================================
// 1.1 Конвенция «точка истины» для числовых env-опций 
//   Значения вида "0"/"6379" остаются строками; Config больше НЕ превращает
//   их в "false". Здесь приводим "true"/"false" к 1/0 для php-ини-настроек.
// =========================================================================
function config_bool_to_int(?string $value): int
{
    return in_array(strtolower(trim((string)$value)), ['true', '1', 'yes', 'on'], true) ? 1 : 0;
}

// =========================================================================
// 2. Базовая инициализация ядра
//    ВАЖНО: порядок имеет значение!
//    Route → File → Session → Logger::setLogDir → Container
// =========================================================================

// 2.1. Базовые пути (должны быть установлены ДО создания Config/Logger)
Route::setBasePath(dirname(__DIR__));
File::setBaseDir(dirname(__DIR__));

// =========================================================================
// 3. DI-контейнер (создан выше на шаге 2.2 — там же зарегистрирован Config)
// =========================================================================
$di = new Container();
Container::setGlobal($di);

// =========================================================================
// 4. Регистрация ядра (singleton — один экземпляр на запрос)
// =========================================================================

// 4.1. Config — один и тот же singleton!)
$di->singleton(Config::class, fn() => new Config());

// 4.2. TimeDate — один на всё приложение (зависит от Config)
$di->singleton(TimeDate::class, fn(Container $c) => new TimeDate(
    $c->get(Config::class)
));

// 4.3. Logger — registry-singleton, но в DI регистрируем дефолтный
// Logger::setLogDir('storage/logs');
$di->singleton(Logger::class, fn() => Logger::getInstance('app.log'));

// 4.4. DataBase — одно соединение на запрос (зависит от Config и Logger)
$di->singleton(DataBase::class, fn(Container $c) => new DataBase(
    $c->get(Config::class),
    $c->get(Logger::class)
));

// 4.5. DbQuery — factory (новый экземпляр, т.к. может быть нужен с разными настройками)
$di->factory(DbQuery::class, fn(Container $c) => new DbQuery(
    $c->get(DataBase::class),
    $c->get(Logger::class)
));

// 4.6. Auth/session modules — Redis-backed sessions + account service layer
$di->factory(SessionService::class, fn(Container $c) => new SessionService(
    $c->get(Config::class)
));
$di->factory(AuthService::class, fn(Container $c) => new AuthService(
    $c->get(Config::class),
    $c->get(DbQuery::class),
    $c->get(Logger::class),
    $c->get(SessionService::class)
));
$di->factory(FileService::class, fn(Container $c) => new FileService(
    $c->get(DbQuery::class),
    $c->get(Config::class),
    $c->get(Logger::class)
));

// =========================================================================
// 5. Регистрация сервисов приложения (factory — новые экземпляры)
//
// Фабрики регистрируются только при доступности ВСЕХ зависимостей —
// ошибка конфигурации видна сразу (fail-fast), а не лениво при первом get().
// =========================================================================

/**
 * Проверяет доступность класса с гарантированной попыткой автозагрузки.
 */
function class_available(string $class): bool{
    return class_exists($class, false) || class_exists($class, true);
}

// Template — factory (каждый раз новый, чтобы не было пересечений переменных)
$di->factory(Template::class, fn() => new Template());

// Map — factory (каждый раз новый, чтобы не было пересечений переменных)
$di->factory(Map::class, fn() => new Map());

// Validator — factory
if (class_available(Validator::class)) {
    $di->factory(Validator::class, fn(Container $c) => new Validator(
        $c->get(DbQuery::class)
    ));
}

// SchemaService — factory (новый экземпляр для админки)
if (class_available(SchemaService::class) && class_available(Validator::class)) {
    $di->factory(SchemaService::class, fn(Container $c) => new SchemaService(
        $c->get(DbQuery::class),
        $c->get(Logger::class),
        $c->get(Validator::class)
    ));
}

// Schema — singleton (один на запрос, зависит от SchemaService)
// ВАЖНО: регистрируем ПЕРЕД PageController!
if (class_available(Schema::class) && class_available(SchemaService::class)) {
    $di->singleton(Schema::class, fn(Container $c) => new Schema(
        $c->get(SchemaService::class),
        $c->get(Config::class),
        $c->get(Logger::class)
    ));
}

// Если есть PageService и PageController — регистрируем их
if (class_available(PageService::class)) {
    $di->factory(PageService::class, fn(Container $c) => new PageService(
        $c->get(DbQuery::class),
        $c->get(Logger::class),
        $c->get(Config::class)
    ));
}
if (class_available(PageController::class)) {
    $di->factory(PageController::class, fn(Container $c) => new PageController(
        $c->get(PageService::class),
        $c->get(Config::class),
        class_available(Schema::class) ? $c->get(Schema::class) : null,
        $c->get(Logger::class)
    ));
}

// =========================================================================
// 5,1 Панель администратора
// =========================================================================
if (class_available(PageSettingsService::class)) {
    $di->factory(PageSettingsService::class, fn(Container $c) => new PageSettingsService(
        $c->get(DbQuery::class),
        $c->get(Logger::class),
        $c->get(Validator::class)
    ));
}

// =========================================================================
// 5,2 Eager-резолвинг дешёвых синглтонов ядра (пункт 11)
//     Config уже создан на шаге ; TimeDate/Logger создаём сразу —
//     это объекты без сетевых side-effects. Ошибка конфигурации или
//     битой автозагрузки будет видна немедленно (fail-fast).
//     DataBase НЕ трогаем: PDO-коннект остаётся ленивым, чтобы деградация
//     без БД обрабатывалась каскадом PageController, а не fatal в bootstrap.
// =========================================================================
$di->get(TimeDate::class);
$di->get(Logger::class);

// =========================================================================
// 7. Настройка отображения ошибок
// =========================================================================
// Режим отладки берётся из конфигурации (APP_DEBUG в .env/config.env),
// а не захардкожен. В продакшене ошибки не выводятся в HTML,
// но пишутся в лог (error_reporting остаётся максимальным).
$appDebug = $di->get(Config::class)->isDebug();
define('APP_DEBUG', $appDebug);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);