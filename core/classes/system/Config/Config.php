<?php
declare(strict_types = 1);

/**
 * Класс Config — работа с .env файлами
 * Прямой доступ к переменным окружения через getEnv()
 */
class Config {
    private array $envVars = [];
    private string $envPath;

    public function __construct(string $envFile = 'config.env') {
        // Определяем путь
        if (strpos($envFile, '/') === 0 || strpos($envFile, ':') === 1) {
            $this->envPath = $envFile;
        } else {
            $this->envPath = Route::getPathCore() . $envFile;
        }

        // Загружаем корневой .env (если есть)
        $baseEnvPath = dirname(Route::getPathCore()) . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($baseEnvPath)) {
            $this->loadEnvFile($baseEnvPath);
        }

        // Загружаем config.env (обязательный)
        if (file_exists($this->envPath)) {
            $this->loadEnvFile($this->envPath);
        } else {
            throw new ConfigException("Файл конфигурации не найден: $envFile");
        }
    }

    private function loadEnvFile(string $path): void {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = $this->unquoteValue(trim($value));
                $this->envVars[$key] = $value;
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }

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
     * Получает значение переменной окружения
     */
    public function getEnv(string $varName, mixed $default = null): mixed {
        if (array_key_exists($varName, $this->envVars)) {
            return $this->envVars[$varName];
        }
        $envValue = getenv($varName);
        if ($envValue !== false) {
            return $envValue;
        }
        if (isset($_ENV[$varName])) {
            return $_ENV[$varName];
        }
        if (isset($_SERVER[$varName])) {
            return $_SERVER[$varName];
        }
        return $default;
    }

    /**
     * Устанавливает значение переменной окружения
     */
    public function setEnv(string $varName, mixed $value): void {
        $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        $this->envVars[$varName] = $stringValue;
        putenv("$varName=$stringValue");
    }

    /**
     * Возвращает все загруженные переменные окружения
     */
    public function getAllEnv(): array {
        return $this->envVars;
    }

    /**
     * Сохраняет переменные окружения в .env файл
     */
    public function saveEnv(?string $path = null): void {
        $filePath = $path ?? $this->envPath;
        $content = "# Файл конфигурации проекта\n";
        $content .= "# Сгенерировано: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($this->envVars as $key => $value) {
            $content .= "$key=$value\n";
        }

        $file = new File($filePath);
        $file->createFile('w');
        $file->putToFile($content);
        $file->closeFile();
    }
}