<?php
declare(strict_types=1);
/**
 * Front Controller админ-панели
 */

require_once __DIR__ . "/../core/bootstrap.php";

// =========================================================================
// 1. Отдельный логгер для админки
// =========================================================================
$adminLogger = Logger::getInstance('admin.log', 'admin/admCore/log');

// Регистрируем его в DI, чтобы контроллеры/сервисы админки получали его автоматически
$di = Container::getGlobal();

// Регистрируем adminLogger как отдельный сервис (чтобы не конфликтовал с основным)
$di->singleton('admin.logger', fn() => $adminLogger);

// =========================================================================
// 2. Автозагрузка классов админки
// =========================================================================
spl_autoload_register(function (string $class): void {
    $paths = [
        __DIR__ . "/admCore/controllers/{$class}.php",
        __DIR__ . "/admCore/services/{$class}.php",
    ];
    foreach ($paths as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// =========================================================================
// 3. Обработка URI
// =========================================================================
$rawUri = $_SERVER['REDIRECT_URL']
    ?? ($_SERVER['PATH_INFO'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));

$normalizedPath = Url::normalize($rawUri);
if (str_starts_with($normalizedPath, 'admin/')) {
    $normalizedPath = substr($normalizedPath, 6);
}
if ($normalizedPath === 'admin.php') {
    $normalizedPath = '';
}

// =========================================================================
// 4. Статические assets
// =========================================================================
if (preg_match('#^assets/#', $normalizedPath)) {
    $filePath = __DIR__ . '/' . $normalizedPath;
    if (is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'svg'  => 'image/svg+xml',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    exit;
}

// =========================================================================
// 5. Глобальный обработчик ошибок (в админский лог!)
// =========================================================================
set_exception_handler(function (Throwable $e) use ($adminLogger): void {
    $adminLogger->error('Необработанное исключение: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<h1>Admin Error</h1><pre>'
            . htmlspecialchars($e->getMessage()) . "\n"
            . htmlspecialchars($e->getTraceAsString())
            . '</pre>';
    } else {
        echo '<h1>Ошибка админ-панели</h1><p>Попробуйте обновить страницу.</p>';
    }
});

$adminLogger->info('Админка загружена', ['uri' => $normalizedPath]);

// =========================================================================
// 4. Маршрутизация
// =========================================================================
$routes = [
    ''                  => ['DashboardController', 'index'],
    'dashboard'         => ['DashboardController', 'index'],
    'pages'             => ['PageSettingsController', 'index'],
    'pages/create'      => ['PageSettingsController', 'create'],
    'pages/store'       => ['PageSettingsController', 'store'],
    'pages/edit'        => ['PageSettingsController', 'edit'],
    'pages/update'      => ['PageSettingsController', 'update'],
    'pages/delete'      => ['PageSettingsController', 'delete'],
    'users'             => ['UserSettingsController', 'index'],
];

// Разбираем URI
$segments = $normalizedPath !== '' ? explode('/', $normalizedPath) : [];

// Ищем маршрут: сначала точное совпадение, потом с учётом ID в конце
$routeKey   = $segments[0] ?? '';
$subRoute   = $segments[1] ?? '';
$resourceId = isset($segments[2]) ? (int)$segments[2] : 0;

// Составные маршруты: pages/edit/5, pages/delete/3
$fullRouteKey = $routeKey !== '' && $subRoute !== '' ? "$routeKey/$subRoute" : $routeKey;

if (isset($routes[$fullRouteKey])) {
    [$controllerClass, $method] = $routes[$fullRouteKey];
} elseif (isset($routes[$routeKey])) {
    [$controllerClass, $method] = $routes[$routeKey];
} else {
    $controllerClass = 'DashboardController';
    $method = 'index';
}

// =========================================================================
// 5. Запуск контроллера
// =========================================================================
try {
    $controller = $di->get($controllerClass);
    if (!method_exists($controller, $method)) {
        throw new RuntimeException("Метод $controllerClass::$method() не найден");
    }

    // Если есть ID в URL — передаём его в метод
    if ($resourceId > 0) {
        $controller->$method($resourceId);
    } else {
        $controller->$method();
    }
} catch (Throwable $e) {
    if (isset($controller) && method_exists($controller, 'handleError')) {
        $controller->handleError($e);
    } else {
        throw $e;
    }
}