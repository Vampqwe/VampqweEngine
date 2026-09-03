<?php
declare(strict_types = 1);

/**
 * Класс Config для работы с конфигурацией через .env файлы
 * Поддерживает загрузку переменных окружения из файлов .env и config.env
 */
class Config {
    private array $configArr = [];
    private array $envVars = [];
    private string $envPath;
    
    /**
     * @param string $envFile имя файла окружения (по умолчанию config.env в корне core/)
     * @throws ConfigException если файл не может быть прочитан
     */
    public function __construct(string $envFile = 'config.env') {
        // Определяем путь к файлу .env
        if (strpos($envFile, '/') === 0 || strpos($envFile, ':') === 1) {
            $this->envPath = $envFile;
        } else {
            $this->envPath = Route::getPathCore() . $envFile;
        }
        
        // Загружаем переменные окружения из основного .env (если существует)
        $baseEnvPath = dirname(Route::getPathCore()) . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($baseEnvPath)) {
            $this->loadEnvFile($baseEnvPath);
        }
        
        // Загружаем переменные окружения из config.env (или указанного файла)
        if (file_exists($this->envPath)) {
            $this->loadEnvFile($this->envPath);
        } else {
            throw new ConfigException("Файл конфигурации окружения не найден: $envFile");
        }
        
        // Формируем массив конфигурации из загруженных переменных окружения
        $this->buildConfigFromEnv();
    }
    
    /**
     * Загружает переменные окружения из указанного .env файла
     */
    private function loadEnvFile(string $path): void {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Пропускаем комментарии и пустые строки
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            
            // Разбираем строку KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Удаляем кавычки если они есть
                $value = $this->unquoteValue($value);
                
                // Сохраняем в массив
                $this->envVars[$key] = $value;
                
                // Также устанавливаем в системное окружение для совместимости
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }
    
    /**
     * Удаляет кавычки вокруг значения если они есть
     */
    private function unquoteValue(string $value): string {
        $value = trim($value);
        if ((strlen($value) >= 2) && 
            (($value[0] === '"' && $value[-1] === '"') || 
             ($value[0] === "'" && $value[-1] === "'"))) {
            return substr($value, 1, -1);
        }
        return $value;
    }
    
    /**
     * Строит массив конфигурации из переменных окружения
     * Группирует переменные по секциям на основе префиксов
     */
    private function buildConfigFromEnv(): void {
        // Маппинг имен переменных в имена параметров конфигурации
        $varToParamMap = [
            'DB_LOGIN' => 'db_login',
            'DB_PASSWORD' => 'db_password',
            'DB_HOST' => 'db_host',
            'DB_NAME' => 'db_name',
            'DB_CHARSET' => 'db_charset',
            'DB_DRIVER' => 'db_driver',
            'DEFAULT_TIMEZONE' => 'default_timezone',
            'DATE_TPL' => 'date_TPL',
            'TIME_TPL' => 'time_TPL',
            'ACTIVE_MODE' => 'active_mode',
            'SAVE_SESSION_HANDLER' => 'save_session_handler',
            'SAVE_SESSION_PATH_METHOD' => 'save_session_path_method',
            'SAVE_SESSION_PATH_HOST' => 'save_session_path_host',
            'SAVE_SESSION_PATH_PORT' => 'save_session_path_port',
            'SAVE_SESSION_PATH_AUTH' => 'save_session_path_auth',
        ];
        
        foreach ($varToParamMap as $envVar => $paramName) {
            if (array_key_exists($envVar, $this->envVars)) {
                // Определяем секцию по префиксу
                $section = $this->detectSection($envVar);
                if ($section !== null) {
                    $this->configArr[$section][$paramName] = $this->envVars[$envVar];
                }
            }
        }
    }
    
    /**
     * Определяет секцию конфигурации по имени переменной окружения
     */
    private function detectSection(string $varName): ?string {
        if (strpos($varName, 'DB_') === 0) {
            return 'dataBase';
        }
        if (in_array($varName, ['DEFAULT_TIMEZONE', 'DATE_TPL', 'TIME_TPL'])) {
            return 'dateTime';
        }
        if (strpos($varName, 'ACTIVE_') === 0) {
            return 'mode';
        }
        if (strpos($varName, 'SAVE_SESSION_') === 0) {
            return 'sessionHandler';
        }
        return null;
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
            if (!array_key_exists($key, $currentData)) {
                throw new ConfigException("Параметр конфигурации '$configName' не найден (ошибка на уровне ключа: '$key')");
            }

            $currentData = $currentData[$key];
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
    
    /**
     * Устанавливает значение переменной окружения
     * 
     * @param string $varName имя переменной
     * @param mixed $value значение
     */
    public function setEnv(string $varName, mixed $value): void {
        $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        $this->envVars[$varName] = $stringValue;
        putenv("$varName=$stringValue");
    }
    
    /**
     * Сохраняет текущие переменные окружения в .env файл
     * 
     * @param string|null $path путь к файлу (если null, используется текущий envPath)
     * @throws ConfigException если не удалось записать в файл
     */
    public function saveEnv(?string $path = null): void {
        $filePath = $path ?? $this->envPath;
        
        $content = "# Файл конфигурации проекта\n# Сгенерировано: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Группируем переменные по категориям
        $groups = [
            'Настройки базы данных' => ['DB_LOGIN', 'DB_PASSWORD', 'DB_HOST', 'DB_NAME', 'DB_CHARSET', 'DB_DRIVER'],
            'DateTime config' => ['DEFAULT_TIMEZONE', 'DATE_TPL', 'TIME_TPL'],
            'Режим работы' => ['ACTIVE_MODE'],
            'Настройки сессий' => ['SAVE_SESSION_HANDLER', 'SAVE_SESSION_PATH_METHOD', 'SAVE_SESSION_PATH_HOST', 'SAVE_SESSION_PATH_PORT', 'SAVE_SESSION_PATH_AUTH'],
        ];
        
        foreach ($groups as $groupName => $vars) {
            $content .= "# ===========================================\n";
            $content .= "# $groupName\n";
            $content .= "# ===========================================\n";
            
            foreach ($vars as $var) {
                $value = $this->envVars[$var] ?? '';
                $content .= "$var=$value\n";
            }
            
            $content .= "\n";
        }
        
        // Добавляем остальные переменные которые не вошли в группы
        $allGroupedVars = array_merge(...array_values($groups));
        $otherVars = array_diff(array_keys($this->envVars), $allGroupedVars);
        
        if (!empty($otherVars)) {
            $content .= "# ===========================================\n";
            $content .= "# Другие переменные\n";
            $content .= "# ===========================================\n";
            
            foreach ($otherVars as $var) {
                $value = $this->envVars[$var];
                $content .= "$var=$value\n";
            }
            
            $content .= "\n";
        }
        
        $file = new File($filePath);
        $file->createFile('w');
        $file->putToFile($content);
        $file->closeFile();
    }
}
