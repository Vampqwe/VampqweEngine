<?php
declare(strict_types=1);

/**
 * Класс подключения к базе данных
 * Реализует паттерн Singleton и использует композицию вместо наследования от PDO.
 */
class DataBase 
{
    private static ?self $instance = null;
    
    private PDO $pdo;
    private Logger $logger;
    
    private array $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];
    
    /**
     * Конструктор сделан приватным, чтобы запретить прямой вызов new DataBase()
     */
    private function __construct(Config $config, Logger $logger) 
    {
        $this->logger = $logger;
        
        try {
            $dsn = sprintf(
                '%s:dbname=%s;host=%s;charset=%s',
                $config->getConfig('dataBase.db_driver'),
                $config->getConfig('dataBase.db_name'),
                $config->getConfig('dataBase.db_host'),
                $config->getConfig('dataBase.db_charset')
            );
            
            $user = $config->getConfig('dataBase.db_login');
            $password = $config->getConfig('dataBase.db_password');
            
            // Создаем экземпляр PDO и храним его внутри класса (Композиция)
            $this->pdo = new PDO($dsn, $user, $password, $this->options);
            
        } catch (ConfigException $e) {
            $this->logger->error("Ошибка конфигурации БД: " . $e->getMessage());
            throw $e;
        } catch (PDOException $e) {
            $this->logger->error("Ошибка подключения к БД: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Возвращает единственный экземпляр DataBase (Singleton)
     * Для корректной работы DI лучше, чтобы этот метод вызывался из DI-контейнера,
     * либо зависимости передавались явно при первом вызове.
     */
    public static function getInstance(Config $config, Logger $logger): self 
    {
        if (self::$instance === null) {
            self::$instance = new self($config, $logger);
        }
        return self::$instance;
    }
    
    /**
     * Возвращает реальный экземпляр PDO для выполнения запросов
     */
    public function getPdo(): PDO 
    {
        return $this->pdo;
    }
    
    /**
     * Сбрасывает экземпляр Singleton (только для тестов!)
     */
    public static function resetInstance(): void 
    {
        self::$instance = null;
    }
    
    /**
     * Запрещает клонирование
     */
    private function __clone() {}
    
    /**
     * Запрещает десериализацию
     */
    public function __wakeup() 
    {
        throw new Exception("Cannot unserialize singleton DataBase");
    }
}