<?php
declare(strict_types=1);
/**
* Front Controller — точка входа VampqweEngine
*/
require_once __DIR__ . "/core/bootstrap.php";

$di     = Container::getGlobal();
$logger = $di->get(Logger::class);

// =========================================================================
// 1. Получение и нормализация URI (единая точка истины)
// =========================================================================
// Url::getRequestUri() сам разберётся с REDIRECT_URL, PATH_INFO и REQUEST_URI,
// а затем вернёт чистый нормализованный путь (без query string).
$normalizedPath = Url::getRequestUri();
$uri            = '/' . $normalizedPath; // '/' для корня

// Защита от прямого обращения к front controller: /index.php — не страница
if ($normalizedPath === 'index.php') {
    $uri = '/';
    $normalizedPath = '';
}
// =========================================================================
// 3. Служебные URL (sitemap.xml, robots.txt и т.д.)
// =========================================================================
$serviceHandler = Route::getServiceHandler($uri);
if ($serviceHandler !== null) {
    if (str_ends_with($serviceHandler, '.php')) {
        require_once $serviceHandler;
    } else {
        Route::outputStaticFile($serviceHandler);
    }
    exit;
}

// =========================================================================
// 4. Глобальный обработчик исключений (только для реальных сбоев)
// =========================================================================
set_exception_handler(function (Throwable $e) use ($di, $logger): void {
    // 1. Логируем сбой
    try {
        $logger->error('Необработанное исключение: ' . $e->getMessage()
            . ' | ' . $e->getFile() . ':' . $e->getLine());
    } catch (Throwable $logError) {
        error_log('Logger failed: ' . $logError->getMessage());
    }

    // 2. Пытаемся отрендерить красивую 500 через PageController
    try {
        if ($di->has(PageController::class)) {
            $controller = $di->get(PageController::class);
            $controller->handleError($e);
            return;
        }
    } catch (Throwable $renderError) {
        try {
            $logger->error('PageController handleError() упал: ' . $renderError->getMessage());
        } catch (Throwable) {
            // Игнорируем
        }
    }

    // 3. Fallback: красивый шаблон через include (если контроллер недоступен)
    if (!headers_sent()) {
        http_response_code(500);
    }
    renderFallbackErrorPage($e);
});

// =========================================================================
// 5. Передаём управление PageController
// Он сам разберётся: это главная, статья, или вернёт 404 из БД/дефолт
// =========================================================================
$controller = $di->get(PageController::class);
$controller->handle($uri);

// =========================================================================
// Fallback-рендер страницы 500 (когда PageController недоступен)
// =========================================================================
function renderFallbackErrorPage(Throwable $e): void
{
    $isDebug = defined('APP_DEBUG') && APP_DEBUG;

    $errorTitle   = '500 — Внутренняя ошибка сервера';
    $errorMessage = $isDebug
        ? $e->getMessage()
        : 'Извините, на сервере произошла ошибка. Пожалуйста, попробуйте позже.';
    $errorFile    = $isDebug ? $e->getFile() . ':' . $e->getLine() : '';
    $errorTrace   = $isDebug ? $e->getTraceAsString() : '';

    $fallbackTemplate = Route::getPathRoot() . '/templates/500-fallback.html';

    if (is_file($fallbackTemplate)) {
        include $fallbackTemplate;
        return;
    }

    // Последний рубеж — если даже шаблон удалён
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Ошибка сервера</title></head>'
        . '<body><h1>500 — Внутренняя ошибка сервера</h1>'
        . '<p>Извините, на сервере произошла ошибка.</p></body></html>';
}