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
 * Logger — журнал событий приложения
 *
 * Registry-singleton по файлам логов.
 * Поддерживает уровни: error / warning / info / debug.
 *
 * Использование:
 *  // Стандартный лог (core/log/app.log)
 *  $logger = Logger::getInstance('app.log');
 *
 *  // Кастомный путь (admin/admCore/log/admin.log)
 *  $logger = Logger::getInstance('admin.log', 'admin/admCore/log');
 *
 *  $logger->error('Что-то сломалось');
 *  $logger->warning('Подозрительная активность');
 */
class Logger
{
    /** @var array<string, self> Registry экземпляров (ключ = путь к файлу) */
    private static array $instances = [];

    private string $filePath;
    private string $level;

    /** Уровни логирования (по возрастанию критичности) */
    private const LEVELS = [
        'debug'   => 0,
        'info'    => 1,
        'warning' => 2,
        'error'   => 3,
    ];

    /**
     * Приватный конструктор — используем getInstance()
     *
     * @param string $filename имя файла лога (например, 'app.log')
     * @param string|null $baseDir базовая директория (относительно корня проекта).
     *                             Если null — используется 'core/log'
     */
    private function __construct(string $filename, ?string $baseDir = null)
    {
        $rootPath = Route::getPathRoot();
        $baseDir  = $baseDir ?? 'core/log';

        // Нормализуем путь: убираем начальный/конечный слэш
        $baseDir = trim($baseDir, '/');

        $this->filePath = $rootPath . '/' . $baseDir . '/' . $filename;
        $this->level    = $this->resolveLogLevel();

        // Создаём директорию, если её нет
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                // Если не удалось создать — fallback на error_log
                error_log("Logger: не удалось создать директорию $dir");
            }
        }
    }

    /**
     * Получает (или создаёт) экземпляр логгера
     *
     * @param string      $filename имя файла лога
     * @param string|null $baseDir  базовая директория (null = 'core/log')
     * @return self
     */
    public static function getInstance(string $filename = 'app.log', ?string $baseDir = null): self
    {
        // Уникальный ключ = путь к файлу (чтобы admin.log в разных директориях не конфликтовали)
        $key = ($baseDir ?? 'core/log') . '/' . $filename;

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($filename, $baseDir);
        }

        return self::$instances[$key];
    }

    /**
     * Определяет минимальный уровень логирования из конфига
     */
    private function resolveLogLevel(): string
    {
        try {
            $config = Container::getGlobal()->get(Config::class);
            $level  = strtolower((string)$config->getEnv('LOG_LEVEL', 'debug'));
            return isset(self::LEVELS[$level]) ? $level : 'debug';
        } catch (Throwable) {
            return 'debug';
        }
    }

    // =========================================================================
    // Публичные методы логирования
    // =========================================================================
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    // =========================================================================
    // Внутренняя логика
    // =========================================================================
    /**
     * Записывает сообщение в лог (если уровень достаточен)
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Проверяем, что уровень сообщения >= минимального уровня логгера
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->level] ?? 0)) {
            return;
        }

        $line = $this->formatLine($level, $message, $context);

        // Пытаемся записать в файл
        if (!@file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX)) {
            // Fallback: если файл недоступен — используем error_log
            error_log($line);
        }
    }

    /**
     * Форматирует строку лога
     * Формат: [2026-10-03 14:30:45] [ERROR] Сообщение | context
     */
    private function formatLine(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelStr  = strtoupper($level);

        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return "[$timestamp] [$levelStr] $message$contextStr" . PHP_EOL;
    }

    /**
     * Возвращает путь к файлу лога (для отладки)
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }
}