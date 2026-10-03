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
 * Template — шаблонизатор VampqweEngine
 *
 * Два режима рендеринга:
 *  - render()     — str_replace, безопасный, без выполнения PHP
 *  - renderPhp()  — include + ob_start, переменные доступны как $key
 *
 * Плейсхолдеры: {key} и {{key}} (оба поддерживаются)
 *
 * Кэширование:
 *  - enableCache() — включает файловый кэш
 *  - setCacheByVariables(true|false) — учитывать ли переменные в ключе кэша
 *  - Кэш-файлы имеют префикс cache_ для безопасной очистки
 *
 * Улучшения (v2):
 *  - clearCache() удаляет только кэш-файлы (префикс cache_)
 *  - renderPhp() использует замыкание вместо extract() в глобальной области
 *  - Проверка is_writable() для директории кэша
 *  - Логика кэша вынесена в withCache()
 *  - toString() логирует предупреждения вместо падения
 *  - json_encode() вместо serialize() для ключа кэша
 */
class Template
{
    /** @var string[] Пути к файлам шаблонов */
    private array $tplFiles = [];

    /** @var Map Обычные переменные (без экранирования) */
    private Map $map;

    /** @var Map Экранированные переменные (с htmlspecialchars) */
    private Map $escapedMap;

    /** @var bool Включено ли кэширование */
    private bool $cacheEnabled = false;

    /** @var bool Учитывать ли переменные в ключе кэша */
    private bool $cacheByVariables = true;

    /** @var int TTL кэша в секундах (0 = бесконечно, проверяется по mtime) */
    private int $cacheTtl = 0;

    /** @var string Директория для кэш-файлов */
    private string $cacheDir;

    /** @var string Префикс кэш-файлов (для безопасной очистки) */
    private const CACHE_PREFIX = 'cache_';

    /** @var string Расширение кэш-файлов */
    private const CACHE_EXT = '.html';

    public function __construct()
    {
        $this->map          = new Map();
        $this->escapedMap   = new Map();
        $this->cacheDir     = Route::getPathRoot() . '/core/cache/templates';
    }

    // =========================================================================
    // Добавление файлов шаблонов
    // =========================================================================

    /**
     * Добавляет файл шаблона
     */
    public function addTplFile(string $filePath): self
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Template: файл не найден: $filePath");
        }
        $this->tplFiles[] = $filePath;
        return $this;
    }

    /**
     * Читает все файлы шаблонов и объединяет их содержимое
     */
    private function readTplFiles(): string
    {
        $content = '';
        foreach ($this->tplFiles as $file) {
            $fileContent = file_get_contents($file);
            if ($fileContent === false) {
                throw new RuntimeException("Template: не удалось прочитать файл: $file");
            }
            $content .= $fileContent;
        }
        return $content;
    }

    // =========================================================================
    // Работа с переменными
    // =========================================================================

    /**
     * Присваивает переменную (без экранирования)
     */
    public function assign(string $key, mixed $value): self
    {
        $this->map->put($key, $value);
        return $this;
    }

    /**
     * Присваивает переменную с экранированием (htmlspecialchars)
     */
    public function assignEscaped(string $key, mixed $value): self
    {
        $this->escapedMap->put($key, $value);
        return $this;
    }

    /**
     * Присваивает массив переменных
     *
     * @param bool $escape Экранировать ли все значения
     */
    public function assignArray(array $data, bool $escape = false): self
    {
        foreach ($data as $key => $value) {
            if ($escape) {
                $this->assignEscaped((string)$key, $value);
            } else {
                $this->assign((string)$key, $value);
            }
        }
        return $this;
    }

    /**
     * Безопасно преобразует значение в строку
     * Массивы/объекты/ресурсы логируются как warning, возвращают ''
     */
    private function toString(mixed $value, string $key = ''): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        // Массивы, объекты, ресурсы — не преобразуем, логируем
        $type = gettype($value);
        if (defined('APP_DEBUG') && APP_DEBUG) {
            try {
                $logger = Container::getGlobal()->get(Logger::class);
                $logger->warning(
                    "Template: значение типа '$type' для ключа '$key' преобразовано в пустую строку"
                );
            } catch (Throwable) {
                // Logger недоступен — молча игнорируем
            }
        }
        return '';
    }

    // =========================================================================
    // Кэширование
    // =========================================================================

    /**
     * Включает кэширование
     */
    public function enableCache(): self
    {
        $this->cacheEnabled = true;
        return $this;
    }

    /**
     * Устанавливает, учитывать ли переменные в ключе кэша
     */
    public function setCacheByVariables(bool $enabled): self
    {
        $this->cacheByVariables = $enabled;
        return $this;
    }

    /**
     * Устанавливает TTL кэша в секундах
     */
    public function setCacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;
        return $this;
    }

    /**
     * Генерирует путь к файлу кэша
     */
    private function getCachePath(): string
    {
        return $this->cacheDir . '/' . self::CACHE_PREFIX . $this->getCacheKey() . self::CACHE_EXT;
    }

    /**
     * Генерирует ключ кэша на основе файлов шаблонов и (опционально) переменных
     */
    private function getCacheKey(): string
    {
        $parts = [];

        // Файлы шаблонов + их mtime
        foreach ($this->tplFiles as $file) {
            $parts[] = $file . ':' . (is_file($file) ? filemtime($file) : 0);
        }

        // Переменные (если включено)
        if ($this->cacheByVariables) {
            $vars = array_merge(
                $this->map->getArrayObject()->getArrayCopy(),
                $this->escapedMap->getArrayObject()->getArrayCopy()
            );
            // json_encode быстрее serialize и безопаснее
            $parts[] = json_encode($vars, JSON_UNESCAPED_UNICODE);
        }

        return md5(implode('|', $parts));
    }

    /**
     * Читает контент из кэша (если он актуален)
     */
    private function getFromCache(): ?string
    {
        $cachePath = $this->getCachePath();

        if (!is_file($cachePath)) {
            return null;
        }

        // Проверка TTL
        if ($this->cacheTtl > 0) {
            $mtime = filemtime($cachePath);
            if ($mtime !== false && (time() - $mtime) > $this->cacheTtl) {
                @unlink($cachePath);
                return null;
            }
        }

        // Проверка mtime исходных файлов шаблонов
        $cacheMtime = filemtime($cachePath);
        if ($cacheMtime === false) {
            return null;
        }
        foreach ($this->tplFiles as $file) {
            if (is_file($file) && filemtime($file) > $cacheMtime) {
                return null; // Шаблон новее кэша
            }
        }

        $content = file_get_contents($cachePath);
        return $content !== false ? $content : null;
    }

    /**
     * Сохраняет контент в кэш
     */
    private function saveToCache(string $content): void
    {
        $cachePath = $this->getCachePath();
        $cacheDir  = dirname($cachePath);

        // Проверка доступности директории для записи
        if (!is_dir($cacheDir)) {
            if (!@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
                $this->logCacheWarning("Не удалось создать директорию кэша: $cacheDir");
                return;
            }
        }

        if (!is_writable($cacheDir)) {
            $this->logCacheWarning("Директория кэша недоступна для записи: $cacheDir");
            return;
        }

        // Атомарная запись через временный файл
        $tempFile = $cachePath . '.tmp.' . getmypid();
        if (@file_put_contents($tempFile, $content, LOCK_EX) === false) {
            $this->logCacheWarning("Не удалось записать кэш: $cachePath");
            @unlink($tempFile);
            return;
        }

        if (!@rename($tempFile, $cachePath)) {
            @unlink($tempFile);
            $this->logCacheWarning("Не удалось переименовать временный файл кэша");
        }
    }

    /**
     * Логирует предупреждение о проблемах с кэшем
     */
    private function logCacheWarning(string $message): void
    {
        try {
            $logger = Container::getGlobal()->get(Logger::class);
            $logger->warning("Template cache: $message");
        } catch (Throwable) {
            // Logger недоступен — используем error_log
            error_log("Template cache: $message");
        }
    }

    /**
     * Оборачивает рендеринг логикой кэша
     * Устраняет дублирование между render() и renderPhp()
     */
    private function withCache(callable $renderer): string
    {
        if ($this->cacheEnabled) {
            $cached = $this->getFromCache();
            if ($cached !== null) {
                return $cached;
            }
        }

        $content = $renderer();

        if ($this->cacheEnabled) {
            $this->saveToCache($content);
        }

        return $content;
    }

    /**
     * Очищает кэш шаблонов
     * Удаляет ТОЛЬКО файлы с префиксом cache_ (безопасная очистка)
     *
     * @return int Количество удалённых файлов
     */
    public function clearCache(): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $pattern = $this->cacheDir . '/' . self::CACHE_PREFIX . '*' . self::CACHE_EXT;
        $files   = glob($pattern);
        $deleted = 0;

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    // =========================================================================
    // Рендеринг
    // =========================================================================

    /**
     * Рендерит шаблон через str_replace (безопасный режим, без выполнения PHP)
     */
    public function render(): string
    {
        return $this->withCache(function (): string {
            $content = $this->readTplFiles();

            // Объединяем оба Map в один массив для замены
            $replacements = [];

            // Обычные переменные: {key} и {{key}}
            foreach ($this->map->getArrayObject() as $key => $value) {
                $str = $this->toString($value, (string)$key);
                $replacements['{' . $key . '}']  = $str;
                $replacements['{{' . $key . '}}'] = $str;
            }

            // Экранированные переменные
            foreach ($this->escapedMap->getArrayObject() as $key => $value) {
                $str = $this->toString($value, (string)$key);
                $escaped = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
                $replacements['{' . $key . '}']  = $escaped;
                $replacements['{{' . $key . '}}'] = $escaped;
            }

            return strtr($content, $replacements);
        });
    }

    /**
     * Рендерит шаблон через include (переменные доступны как $key)
     *
     * Безопасность:
     *  - Используется замыкание для изоляции области видимости
     *  - extract() работает внутри замыкания, не затрагивая $this/$GLOBALS
     */
    public function renderPhp(): string
    {
        return $this->withCache(function (): string {
            $content = $this->readTplFiles();

            // Подготавливаем переменные (обычные + экранированные)
            $variables = [];
            foreach ($this->map->getArrayObject() as $key => $value) {
                $variables[(string)$key] = $this->toString($value, (string)$key);
            }
            foreach ($this->escapedMap->getArrayObject() as $key => $value) {
                $str = $this->toString($value, (string)$key);
                $variables[(string)$key] = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
            }

            // Записываем объединённый контент во временный файл
            $tempFile = $this->cacheDir . '/tpl_' . md5($content . microtime(true)) . '.php';
            $tempDir  = dirname($tempFile);

            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }

            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                throw new RuntimeException("Template: не удалось создать временный файл для renderPhp()");
            }

            try {
                // Изолируем область видимости через замыкание
                // extract() работает ВНУТРИ замыкания, не затрагивая $this объекта
                $renderer = static function (string $__file__, array $__vars__): string {
                    extract($__vars__, EXTR_SKIP);
                    ob_start();
                    include $__file__;
                    return ob_get_clean() ?: '';
                };

                return $renderer($tempFile, $variables);
            } finally {
                @unlink($tempFile);
            }
        });
    }

    /**
     * Рендерит и сразу выводит результат
     */
    public function display(): void
    {
        echo $this->render();
    }

    /**
     * Рендерит через renderPhp() и сразу выводит
     */
    public function displayPhp(): void
    {
        echo $this->renderPhp();
    }
}