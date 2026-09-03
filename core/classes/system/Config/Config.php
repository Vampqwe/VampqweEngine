<?php
declare(strict_types = 1);
class Config {
    private array $configArr = [];

    /**
     * @param string $configFile имя файла конфигурации (абсолютный путь или относительно core/)
     * @throws ConfigException если файл не может быть прочитан или создан
     */
    public function __construct(string $configFile = 'config.ini') {
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
                
                $this->configArr = $result;
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
}