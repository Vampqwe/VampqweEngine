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
 * Service — базовый абстрактный класс для всех сервисов приложения
 *
 * Предоставляет:
 *  - Общие зависимости (DbQuery, Logger)
 *  - Безопасное выполнение запросов с логированием (safeQuery)
 *  - Универсальный in-memory кэш (getCached / clearCache)
 *
 * Все конкретные сервисы (PageService, SchemaService, MenuService и т.д.)
 * наследуются от этого класса, избавляясь от дублирования.
 *
 * Пример использования:
 *  class MyService extends Service {
 *      public function __construct(DbQuery $db, Logger $logger) {
 *          parent::__construct($db, $logger);
 *      }
 *      public function getItem(int $id): ?array {
 *          return $this->getCached("item:$id", fn() =>
 *              $this->safeQuery(
 *                  fn() => $this->db->find('items', $id) ?: null,
 *                  "Ошибка получения элемента ID=$id",
 *                  null
 *              )
 *          );
 *      }
 *  }
 */
abstract class Service
{
    /** @var DbQuery Экземпляр Query Builder'а */
    protected DbQuery $db;

    /** @var Logger Экземпляр логгера */
    protected Logger $logger;

    /** @var array Универсальный in-memory кэш (ключ → значение) */
    protected array $cache = [];

    public function __construct(DbQuery $db, Logger $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }

    // =========================================================================
    // Безопасное выполнение запросов
    // =========================================================================
    /**
     * Выполняет callback с обработкой ошибок и логированием
     *
     * Устраняет дублирование try/catch + $this->logger->error() в каждом методе.
     *
     * @param callable $callback функция для выполнения
     * @param string   $errorMessage сообщение для лога при ошибке
     * @param mixed    $default значение по умолчанию при ошибке
     * @return mixed результат callback или default
     */
    protected function safeQuery(callable $callback, string $errorMessage, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $this->logger->error("$errorMessage: " . $e->getMessage());
            return $default;
        }
    }

    // =========================================================================
    // Универсальный in-memory кэш
    // =========================================================================
    /**
     * Получает значение из кэша или загружает через callback
     *
     * @param string   $key уникальный ключ кэша (например, "slug:kachestvo-zhizni")
     * @param callable $loader функция загрузки данных (вызывается только при отсутствии в кэше)
     * @return mixed закэшированное значение
     */
    protected function getCached(string $key, callable $loader): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $value = $loader();
        $this->cache[$key] = $value;
        return $value;
    }

    /**
     * Очищает in-memory кэш
     *
     * @param string|null $key если null — очищается весь кэш, иначе только указанный ключ
     */
    public function clearCache(?string $key = null): void
    {
        if ($key === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$key]);
        }
    }
}