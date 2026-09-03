<?php
declare(strict_types = 1);
/**
 * Класс Bootstrap отвечает за первичную настройку окружения приложения:
 * маршрутизация, файловая система, обработка сессий.
 */
final class Bootstrap {
    
    //Глобальные константы (опционально)
    final public const APP_NAME = "VampqweEngine";
    final public const APP_VERSION = "0.0.1";

    /**
     * Настраивает обработчик сессий PHP на основе конфигурации.
     * Поддерживает стандартный файловой handler и Redis.
     * Создает объект Config только при вызове этого метода.
     *
     * @return void
     * @throws ConfigException Если в конфигурации отсутствуют необходимые параметры
     */
    public static function setSessionHandler(): void 
    {
        $Config = new Config();
        $handler = $Config->getConfig("sessionHandler.save_session_handler");
        
        switch ($handler) {
            case "file":
                // Используем стандартный файловой обработчик PHP
                return;
                
            case "redis":
                // Формируем строку подключения к Redis
                $method = $Config->getConfig("sessionHandler.save_session_path_method");
                $host   = $Config->getConfig("sessionHandler.save_session_path_host");
                $port   = $Config->getConfig("sessionHandler.save_session_path_port");
                
                $redisPath = "{$method}://{$host}:{$port}";
                
                // Если в конфиге указан пароль для Redis, добавляем его в строку подключения
                if ($Config->hasConfig("sessionHandler.save_session_path_auth")) {
                    $auth = $Config->getConfig("sessionHandler.save_session_path_auth");
                    if (!empty($auth)) {
                        $redisPath .= "?auth={$auth}";
                    }
                }
                
                // Применяем настройки сессий
                ini_set('session.save_handler', 'redis');
                ini_set('session.save_path', $redisPath);
                break;
                
            default:
                // Если указан неизвестный handler, можно выбросить исключение или использовать file
                throw new ConfigException("Неизвестный обработчик сессий: {$handler}");
        }
    }
}
