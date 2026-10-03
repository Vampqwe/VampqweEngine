<?php
declare(strict_types = 1);
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
class File {
    private string $file;
    /** @var resource|null */
    private $openFile = null;
    private int $writenByte = 0;
    private static ?string $baseDir = null;

    public static function setBaseDir(string $dir): void {
        $realPath = realpath($dir);
        if ($realPath === false) {
            throw new InvalidArgumentException("Директория не существует: $dir");
        }
        self::$baseDir = $realPath;
    }

    private function normalizePath(string $path): string {
        if (strpos($path, '..') !== false) {
            throw new FileException("Недопустимый путь: использование '..' запрещено: $path");
        }

        if (self::$baseDir === null) {
            self::$baseDir = getcwd() ?: '/';
        }

        $normalizedPath = str_replace('\\', '/', $path);

        if (strpos($normalizedPath, '/') === 0 || strpos($normalizedPath, ':') === 1) {
            $checkPath = file_exists($normalizedPath) ? realpath($normalizedPath) : realpath(dirname($normalizedPath));
        } else {
            $fullPath = self::$baseDir . '/' . $normalizedPath;
            $checkPath = file_exists($fullPath) ? realpath($fullPath) : realpath(dirname($fullPath));
            if ($checkPath !== false) {
                $normalizedPath = $fullPath;
            }
        }

        if ($checkPath === false) {
            throw new FileException("Путь не существует: $path");
        }

        if (strpos($checkPath, self::$baseDir) !== 0) {
            throw new FileException("Доступ к файлу вне разрешённой директории: $path");
        }

        return $normalizedPath;
    }

    public function __construct(string $file) {
        $this->file = $this->normalizePath($file);
    }

    public function getFile(): string { return $this->file; }
    public function existsFile(): bool { return file_exists($this->getFile()); }

    public function createFile(string $mode = 'w'): void {
        $this->openFile = fopen($this->getFile(), $mode);
        if ($this->openFile === false) {
            throw new FileException("Не удалось создать файл: {$this->file}");
        }
    }

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

    public function putToFile(string $string, int $mode = FILE_APPEND): int|false {
        $result = file_put_contents($this->getFile(), $string, $mode);
        if ($result === false) {
            throw new FileException("Не удалось записать в файл: {$this->file}");
        }
        $this->writenByte = $result;
        return $this->writenByte;
    }

    public function deleteFile(): void {
        if ($this->existsFile() && !unlink($this->getFile())) {
            throw new FileException("Не удалось удалить файл: {$this->file}");
        }
    }

    public function __destruct() {
        $this->closeFile();
    }

    public function isReadable(): bool { return is_readable($this->getFile()); }
    public function isWritable(): bool { return is_writable($this->getFile()); }

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