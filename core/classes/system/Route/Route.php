<?php
declare(strict_types = 1);

final class Route {
    
    private static ?string $basePath = null;
    
    /**
     * Устанавливает базовый путь (альтернатива DOCUMENT_ROOT)
     */
    public static function setBasePath(string $path): void {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new InvalidArgumentException("Базовый путь не существует: $path");
        }
        self::$basePath = $realPath;
    }
    
    /**
     * Возвращает базовый путь
     */
    public static function getBasePath(): string {
        if (self::$basePath !== null) {
            return self::$basePath;
        }
        
        // Fallback к DOCUMENT_ROOT с проверкой
        if (!isset($_SERVER['DOCUMENT_ROOT'])) {
            throw new RuntimeException('DOCUMENT_ROOT не установлен и базовый путь не задан');
        }
        
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            throw new RuntimeException('DOCUMENT_ROOT не существует');
        }
        
        return $docRoot;
    }
    
    public static function getPathRoot(): string {
        return self::getBasePath();
    }
    
    public static function getPathCore(): string {
        return self::getPathRoot() . '/core/';
    }
    
    public static function getPathCoreDb(): string {
        return self::getPathCore() . 'db/';
    }
    
    public static function getPathCoreLog(): string {
        return self::getPathCore() . 'log/';
    }
}