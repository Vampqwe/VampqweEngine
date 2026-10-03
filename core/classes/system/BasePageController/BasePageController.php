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
 *BasePageController — базовый абстрактный класс для 
 *
 * Предоставляет:
 *  - Общие зависимости (Config, Logger)
 *  - Путь к шаблонам
 *  - Универсальные методы рендера, редиректов, JSON-ответов
 *  - Безопасную работу с HTTP-заголовками
 *
 * Все конкретные контроллеры (PageController, ApiController и т.д.)
 * наследуются от этого класса, избавляясь от дублирования.
 *
 * Пример использования:
 *  class MyController extends BasePageController {
 *      public function __construct(Config $config, Logger $logger) {
 *          parent::__construct($config, $logger);
 *      }
 *      public function handle(string $uri): void {
 *          $html = $this->render('my-template.html', ['title' => 'Test']);
 *          echo $html;
 *      }
 *  }
 */
abstract class BasePageController
{
    protected Config $config;
    protected Logger $logger;
    protected string $templatesPath;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config        = $config;
        $this->logger        = $logger;
        $this->templatesPath = Route::getPathRoot() . '/templates/';
    }

    // =========================================================================
    // Работа с шаблонами
    // =========================================================================
    /**
     * Создаёт экземпляр Template из DI-контейнера
     */
    protected function createTemplate(): Template
    {
        return Container::getGlobal()->get(Template::class);
    }

    /**
     * Рендерит шаблон с переменными
     *
     * @param string $templateFile имя файла шаблона (относительно templates/)
     * @param array  $data переменные для подстановки
     * @param bool   $escape экранировать ли все значения (htmlspecialchars)
     * @return string отрендеренный HTML
     */
    protected function render(string $templateFile, array $data = [], bool $escape = false): string
    {
        $tpl = $this->createTemplate();
        $tpl->addTplFile($this->templatesPath . $templateFile);
        $tpl->assignArray($data, $escape);
        return $tpl->render();
    }

    /**
     * Рендерит шаблон и сразу выводит результат
     */
    protected function display(string $templateFile, array $data = [], bool $escape = false): void
    {
        echo $this->render($templateFile, $data, $escape);
    }

    /**
     * Рендерит шаблон с кэшированием (без учёта переменных)
     * Подходит для nav.html, footer.html — одинаковых для всех страниц
     */
    protected function renderCached(string $templateFile): string
    {
        $tpl = $this->createTemplate();
        $tpl->addTplFile($this->templatesPath . $templateFile);
        $tpl->enableCache()->setCacheByVariables(false);
        return $tpl->render();
    }

    // =========================================================================
    // HTTP-ответы
    // =========================================================================
    /**
     * Выполняет HTTP-редирект
     *
     * @param string $url абсолютный или относительный URL
     * @param int    $code HTTP-код (301 =永久, 302 = временно)
     */
    protected function redirect(string $url, int $code = 302): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header("Location: $url");
        }
        exit;
    }

    /**
     * Отправляет JSON-ответ
     *
     * @param array $data данные для JSON
     * @param int   $code HTTP-код
     */
    protected function json(array $data, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Устанавливает HTTP-код ответа (безопасно, если заголовки ещё не отправлены)
     */
    protected function setHttpStatus(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    /**
     * Устанавливает HTTP 404 Not Found
     */
    protected function notFound(): void
    {
        $this->setHttpStatus(404);
    }

    /**
     * Устанавливает HTTP 500 Internal Server Error
     */
    protected function serverError(): void
    {
        $this->setHttpStatus(500);
    }

    // =========================================================================
    // Утилиты
    // =========================================================================
    /**
     * Проверяет, является ли запрос AJAX (XMLHttpRequest)
     */
    protected function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Получает IP-адрес клиента (с учётом прокси)
     */
    protected function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}