<?php
declare(strict_types = 1);
class Config {
    private array $configArr = [];
    private array $envVars = [];

    /**
     * @param string $configFile имя файла конфигурации (абсолютный путь или относительно core/)
     * @throws ConfigException если файл не может быть прочитан или создан
     */
    public function __construct(string $configFile = 'config.ini') {
        // Загружаем переменные окружения из .env
        $this->loadEnv();
        
        // Если передан абсолютный путь - используем его, иначе добавляем путь к core/
        if (strpos($configFile, '/') === 0 || strpos($configFile, ':') === 1) {
            $configPath = $configFile;
        } else {
            $configPath = Route::getPathCore() . $configFile;
        }

        try {
            $file = new File($configPath);

            if ($file->existsFile()) {
                // Парсим INI с поддержкой секций (true возвращает многомерный массив)
                $result = $file->parseIni(true);

                if ($result === false) {
                    throw new ConfigException("Ошибка парсинга конфигурационного файла: $configFile");
                }

                // Подставляем переменные окружения вместо плейсхолдеров ${VAR_NAME}
                $this->configArr = $this->substituteEnvVars($result);
            } else {
                // Создаём новый файл конфигурации
                $file->createFile();
                $file->closeFile();
                throw new ConfigException("Файл конфигурации не найден и был создан: $configFile. Заполните его данными.");
            }
        } catch (FileException $e) {
            throw new ConfigException("Ошибка работы с файлом конфигурации: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Загружает переменные окружения из файла .env в корне проекта
     */
    private function loadEnv(): void {
        // Ищем .env в корне проекта (на уровень выше core/)
        $envPath = dirname(Route::getPathCore()) . DIRECTORY_SEPARATOR . '.env';
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Пропускаем комментарии
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Разбираем строку KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Сохраняем в массив для подстановки в конфиг
                    $this->envVars[$key] = $value;
                    
                    // Также устанавливаем в $_ENV для совместимости
                    if (!getenv($key)) {
                        putenv("$key=$value");
                    }
                }
            }
        }
    }

    /**
     * Рекурсивно подставляет переменные окружения вместо плейсхолдеров ${VAR_NAME}
     */
    private function substituteEnvVars(array $config): array {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->substituteEnvVars($value);
            } elseif (is_string($value)) {
                // Ищем плейсхолдеры вида ${VAR_NAME}
                $config[$key] = preg_replace_callback('/\$\{([A-Z_][A-Z0-9_]*)\}/', function($matches) {
                    $varName = $matches[1];
                    return $this->envVars[$varName] ?? $matches[0];
                }, $value);
            }
        }
        return $config;
    }

    /**
     * Получает значение переменной окружения из .env файла
     * 
     * @param string $varName имя переменной окружения
     * @param mixed $default значение по умолчанию, если переменная не найдена
     * @return mixed значение переменной окружения или default
     */
    public function getEnv(string $varName, mixed $default = null): mixed {
        // Сначала проверяем наш загруженный массив
        if (array_key_exists($varName, $this->envVars)) {
            return $this->envVars[$varName];
        }
        
        // Затем проверяем системное окружение
        $envValue = getenv($varName);
        if ($envValue !== false) {
            return $envValue;
        }
        
        // Проверяем $_ENV
        if (isset($_ENV[$varName])) {
            return $_ENV[$varName];
        }
        
        // Проверяем $_SERVER
        if (isset($_SERVER[$varName])) {
            return $_SERVER[$varName];
        }
        
        return $default;
    }

    /**
     * Получает значение из конфигурации по имени.
     * Поддерживает точечную нотацию для вложенных секций (например: 'dataBase.db_login').
     *
     * @param string $configName имя параметра (может содержать точки для доступа к секциям)
     * @return mixed значение параметра
     * @throws ConfigException если параметр не найден
     */
    public function getConfig(string $configName): mixed {
        // Разбиваем путь по точкам: ['dataBase', 'db_login']
        $keys = explode('.', $configName);
        $currentData = $this->configArr;

        foreach ($keys as $key) {
            // Проверяем, существует ли текущий ключ и является ли он массивом (если это не последний элемент)
            if (!array_key_exists($key, $currentData)) {
                throw new ConfigException("Параметр конфигурации '$configName' не найден (ошибка на уровне ключа: '$key')");
            }

            $currentData = $currentData[$key];

            // Если это не последний ключ в цепочке, но значение уже не массив - ошибка структуры
            // (хотя в INI секции всегда массивы, проверка лишней не будет)
        }

        return $currentData;
    }

    /**
     * Проверяет наличие параметра в конфигурации (поддерживает точечную нотацию).
     */
    public function hasConfig(string $configName): bool {
        try {
            $this->getConfig($configName);
            return true;
        } catch (ConfigException) {
            return false;
        }
    }

    /**
     * Возвращает все параметры конфигурации (многомерный массив).
     */
    public function getAll(): array {
        return $this->configArr;
    }
    
    /**
     * Возвращает все загруженные переменные окружения из .env
     */
    public function getAllEnv(): array {
        return $this->envVars;
    }
}
