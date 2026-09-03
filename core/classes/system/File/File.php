<?php
declare(strict_types = 1);

class File {
    
    private string $file;
    /** @var resource|null */
    private $openFile = null;
    private int $writenByte = 0;
    
    /**
     * Базовый путь для ограничения доступа к файлам (защита от path traversal)
     * Обязательное поле - устанавливается при первом вызове setBaseDir() или по умолчанию
     */
    private static ?string $baseDir = null;
    
    /**
     * Устанавливает базовую директорию для всех операций с файлами
     * @throws InvalidArgumentException если директория не существует
     */
    public static function setBaseDir(string $dir): void {
        $realPath = realpath($dir);
        if ($realPath === false) {
            throw new InvalidArgumentException("Директория не существует: $dir");
        }
        self::$baseDir = $realPath;
    }
    
    /**
     * Проверяет и нормализует путь к файлу
     * @throws FileException если путь содержит недопустимые символы или выходит за пределы baseDir
     */
    private function normalizePath(string $path): string {
        // Запрещаем символы ".." для предотвращения path traversal
        if (strpos($path, '..') !== false) {
            throw new FileException("Недопустимый путь: использование '..' запрещено: $path");
        }
        
        // Если базовая директория не установлена, используем текущую рабочую директорию как baseDir
        if (self::$baseDir === null) {
            self::$baseDir = getcwd() ?: '/';
        }
        
        // Нормализуем путь
        $normalizedPath = str_replace('\\', '/', $path);
        
        // Для абсолютных путей - используем как есть
        if (strpos($normalizedPath, '/') === 0 || strpos($normalizedPath, ':') === 1) {
            // Это абсолютный путь
            $checkPath = file_exists($normalizedPath) ? realpath($normalizedPath) : realpath(dirname($normalizedPath));
        } else {
            // Относительный путь - добавляем baseDir
            $fullPath = self::$baseDir . '/' . $normalizedPath;
            $checkPath = file_exists($fullPath) ? realpath($fullPath) : realpath(dirname($fullPath));
            if ($checkPath !== false) {
                $normalizedPath = $fullPath;
            }
        }
        
        if ($checkPath === false) {
            throw new FileException("Путь не существует: $path");
        }
        
        // Проверяем что путь находится внутри базовой директории
        if (strpos($checkPath, self::$baseDir) !== 0) {
            throw new FileException("Доступ к файлу вне разрешённой директории: $path");
        }
        
        return $normalizedPath;
    }

    public function __construct(string $file) {
        $this->file = $this->normalizePath($file);
    }

    public function getFile(): string {
        return $this->file;
    }

    public function existsFile(): bool {
        return file_exists($this->getFile());
    }

    /**
     * @throws FileException если файл не существует или не может быть прочитан
     */
    public function parseIni(bool $processSections = false): array|false {
        if (!$this->existsFile()) {
            throw new FileException("Файл не существует: {$this->file}");
        }
        $result = parse_ini_file($this->getFile(), $processSections);
        if ($result === false) {
            throw new FileException("Ошибка чтения INI файла: {$this->file}");
        }
        return $result;
    }

    /**
     * @throws FileException если файл не может быть создан
     */
    public function createFile(string $mode = 'w'): void {
        $this->openFile = fopen($this->getFile(), $mode);
        if ($this->openFile === false) {
            throw new FileException("Не удалось создать файл: {$this->file}");
        }
    }

    /**
     * @return string|null содержимое файла или null если файл не существует
     * @throws FileException если файл не может быть прочитан
     */
    public function readFile(): ?string {
        if (!$this->existsFile()) {
            return null;
        }
        $content = file_get_contents($this->getFile());
        if ($content === false) {
            throw new FileException("Не удалось прочитать файл: {$this->file}");
        }
        return $content;
    }

    public function closeFile(): void {
        if ($this->openFile !== null) {
            fclose($this->openFile);
            $this->openFile = null;
        }
    }

    /**
     * @throws FileException если запись не удалась
     */
    public function putToFile(string $string, int $mode = FILE_APPEND): int|false {
        $result = file_put_contents($this->getFile(), $string, $mode);
        if ($result === false) {
            throw new FileException("Не удалось записать в файл: {$this->file}");
        }
        $this->writenByte = $result;
        return $this->writenByte;
    }
    
    /**
     * Удаляет файл
     * @throws FileException если удаление не удалось
     */
    public function deleteFile(): void {
        if ($this->existsFile() && !unlink($this->getFile())) {
            throw new FileException("Не удалось удалить файл: {$this->file}");
        }
    }
    
    /**
     * Деструктор автоматически закрывает открытый файл
     */
    public function __destruct() {
        $this->closeFile();
    }
    
    /**
     * Проверяет可读ность файла
     */
    public function isReadable(): bool {
        return is_readable($this->getFile());
    }
    
    /**
     * Проверяет записываемость файла
     */
    public function isWritable(): bool {
        return is_writable($this->getFile());
    }
    
    /**
     * Возвращает размер файла в байтах
     * @throws FileException если файл не существует
     */
    public function getSize(): int {
        if (!$this->existsFile()) {
            throw new FileException("Файл не существует: {$this->file}");
        }
        $size = filesize($this->getFile());
        if ($size === false) {
            throw new FileException("Не удалось получить размер файла: {$this->file}");
        }
        return $size;
    }
}