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
/**
 * PageController — контроллер для рендеринга страниц
 *
 * Наследует базовый класс BasePageController, используя:
 *  - createTemplate() — вместо дублирования в каждом методе
 *  - renderCached() — для nav.html и footer.html
 *  - setHttpStatus() — для установки HTTP-кодов
 *
 * Обработка ошибок:
 *  - handleError() НЕ логирует — это делает set_exception_handler в index.php
 *  - handleError() НЕ перебрасывает исключение
 *  - Пытается показать красивую страницу ошибки (БД → файл → хардкод)
 *
 * Рендеринг:
 *  - Все блоки собираются отдельно (head, nav, content, footer, script)
 *  - Единый метод renderLayout() собирает их в layout.html
 *  - JS-скрипты подтягиваются ТОЛЬКО из БД (поле pages.scripts JSON)
 */
class PageController extends BasePageController
{
    private PageService $pageService;
    private ?Schema     $schema;

    public function __construct(
        PageService $pageService,
        Config $config,
        ?Schema $schema = null,
        ?Logger $logger = null
    ) {
        parent::__construct($config, $logger ?? Logger::getInstance('app.log'));
        $this->pageService = $pageService;
        $this->schema      = $schema;
    }

    // =========================================================================
    // Точка входа
    // =========================================================================
    public function handle(string $uri): void
    {
        $_SERVER['REQUEST_URI'] = $uri;

        try {
            $pageData = $this->pageService->getPageByUri($uri);
            if (($pageData['page_type'] ?? '') === '404') {
                $this->notFound(); // ← используем метод из BaseController
            }
            $this->renderPage($pageData, $uri);
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    // =========================================================================
    // Рендеринг обычных страниц
    // =========================================================================
    private function renderPage(array $pageData, string $uri): void
    {
        $headHtml     = $this->renderHead($pageData, $uri);
        $schemaMarkup = '';
        if ($this->schema !== null) {
            try {
                $schemaMarkup = $this->schema->renderForPage($pageData);
            } catch (Throwable $e) {
                $this->logger->warning('Ошибка генерации Schema.org: ' . $e->getMessage());
            }
        }

        // ⭐ Используем renderCached() из BaseController
        $navHtml    = $this->renderCached('nav.html');
        $footerHtml = $this->renderCached('footer.html');
        $content    = '<main class="content">' . ($pageData['content'] ?? '') . '</main>';
        $scripts    = $this->renderScripts($pageData);

        $this->renderLayout([
            'head-meta'  => $headHtml,
            'schema.org' => $schemaMarkup,
            'nav'        => $navHtml,
            'bloks'      => '',
            'content'    => $content,
            'footer'     => $footerHtml,
            'script'     => $scripts,
        ]);
    }

    // =========================================================================
    // Единый метод сборки layout.html
    // =========================================================================
    private function renderLayout(array $blocks): void
    {
        $this->display('layout.html', $blocks);
    }

    // =========================================================================
    // Рендеринг <head>
    // =========================================================================
    private function renderHead(array $pageData, string $uri): string
    {
        $pageType = (string)($pageData['page_type'] ?? 'article');

        if (!empty($pageData['canonical'])) {
            $canonical = $pageData['canonical'];
        } elseif ($pageType === '404' || $pageType === '500') {
            $canonical = Url::buildCanonical('', $this->config);
        } else {
            $canonical = Url::buildCanonical($pageData['slug'] ?? '', $this->config);
        }

        $ogType     = $pageType === 'home' ? 'website' : 'article';
        $metaRobots = $this->resolveMetaRobots($pageData, $pageType);
        $ogImage    = $this->resolveOgImage($pageData);

        return $this->render('head-full.html', [
            'title'          => $pageData['title'] ?? 'Главная',
            'description'    => $pageData['description'] ?? '',
            'keywords'       => $pageData['keywords'] ?? '',
            'canonical'      => $canonical,
            'meta_robots'    => $metaRobots,
            'og:title'       => $pageData['og_title'] ?? $pageData['title'] ?? '',
            'og:description' => $pageData['og_description'] ?? $pageData['description'] ?? '',
            'og:type'        => $ogType,
            'og_image'       => $ogImage,
        ], true); // ← escape = true
    }

    private function resolveMetaRobots(array $pageData, string $pageType): string
    {
        if ($pageType === '404' || $pageType === '500') {
            return 'noindex, nofollow';
        }

        $robots = $pageData['meta_robots'] ?? '';
        if ($robots === '' || $robots === null) {
            return 'index, follow';
        }

        return (string)$robots;
    }

    private function resolveOgImage(array $pageData): string
    {
        $ogImage = $pageData['og_image'] ?? '';

        if ($ogImage === '' || $ogImage === null) {
            return Url::build('/assets/images/og-default.jpg', $this->config);
        }

        $ogImage = (string)$ogImage;

        if (str_starts_with($ogImage, '/')) {
            return Url::build($ogImage, $this->config);
        }

        return $ogImage;
    }

    // =========================================================================
    // JS-скрипты страницы (ТОЛЬКО из БД)
    // =========================================================================
    private function renderScripts(array $pageData): string
    {
        $html = '';
        $scripts = $pageData['scripts'] ?? null;

        if (!empty($scripts)) {
            if (is_string($scripts)) {
                $scripts = json_decode($scripts, true);
            }

            if (is_array($scripts)) {
                foreach ($scripts as $src) {
                    $safeSrc = htmlspecialchars(trim((string)$src), ENT_QUOTES, 'UTF-8');
                    if ($safeSrc !== '') {
                        $html .= '<script src="' . $safeSrc . '" defer></script>' . PHP_EOL;
                    }
                }
            }
        }

        return trim($html);
    }

    // =========================================================================
    // Обработка ошибок
    // =========================================================================
    public function handleError(Throwable $e): void
    {
        $this->serverError(); // ← используем метод из BaseController

        try {
            $this->renderErrorPage($e);
        } catch (Throwable $renderError) {
            try {
                $this->logger->error(
                    'Каскадная ошибка при рендере страницы 500: ' . $renderError->getMessage()
                    . ' | Исходная ошибка: ' . $e->getMessage()
                );
            } catch (Throwable $logError) {
                // Даже логгер упал — ничего не поделать
            }
        }
    }

    private function renderErrorPage(Throwable $e): void
    {
        $pageData = $this->tryGetErrorPageFromDb();
        if ($pageData !== null) {
            $this->renderErrorFromDb($pageData, $e);
            return;
        }

        $staticFile = $this->templatesPath . '500.html';
        if (is_file($staticFile)) {
            $content = file_get_contents($staticFile);
            if ($content !== false) {
                $content = str_replace(
                    ['{{message}}', '{{error}}'],
                    [
                        APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Внутренняя ошибка сервера',
                        APP_DEBUG ? htmlspecialchars($e->getTraceAsString()) : '',
                    ],
                    $content
                );
                echo $content;
                return;
            }
        }

        $this->renderHardcodedError($e);
    }

    private function tryGetErrorPageFromDb(): ?array
    {
        try {
            return $this->pageService->get500Page();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function renderErrorFromDb(array $pageData, Throwable $e): void
    {
        $this->renderLayout([
            'head-meta'  => $this->renderErrorHead($pageData),
            'schema.org' => '',
            'nav'        => $this->renderCached('nav.html'),
            'bloks'      => '',
            'content'    => '<main class="content">' . ($pageData['content'] ?? '') . '</main>',
            'footer'     => $this->renderCached('footer.html'),
            'script'     => '',
        ]);
    }

    private function renderErrorHead(array $pageData): string
    {
        return $this->render('head-full.html', [
            'title'          => $pageData['title'] ?? 'Ошибка сервера',
            'description'    => $pageData['description'] ?? 'Внутренняя ошибка сервера',
            'keywords'       => '',
            'canonical'      => Url::buildCanonical('', $this->config),
            'meta_robots'    => 'noindex, nofollow',
            'og:title'       => $pageData['og_title'] ?? $pageData['title'] ?? 'Ошибка сервера',
            'og:description' => $pageData['og_description'] ?? 'Внутренняя ошибка сервера',
            'og:type'        => 'website',
            'og_image'       => Url::build('/assets/images/og-default.jpg', $this->config),
        ], true);
    }

    private function renderHardcodedError(Throwable $e): void
    {
        $message = APP_DEBUG
            ? '<h1>Ошибка: ' . htmlspecialchars($e->getMessage()) . '</h1>'
                . '<p>Файл: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>'
                . '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
            : '<h1>500 — Внутренняя ошибка сервера</h1>'
                . '<p>Извините, на сервере произошла ошибка. Пожалуйста, попробуйте позже.</p>';

        echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ошибка сервера</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
max-width: 800px; margin: 50px auto; padding: 20px; color: #333; }
h1 { color: #c0392b; }
pre { background: #f5f5f5; padding: 15px; overflow-x: auto; border-radius: 4px;
font-size: 13px; }
</style>
</head>
<body>' . $message . '</body>
</html>';
    }
}