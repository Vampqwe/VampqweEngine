<?php
declare(strict_types = 1);

class Logger {

    private File $file;
    private TimeDate $timeDate;
    
    /**
     * @param string $fileLog имя файла лога (относительно core/log/)
     * @throws FileException если файл лога не может быть создан
     */
    public function __construct(string $fileLog = 'log.txt') {
        $logPath = Route::getPathCoreLog() . $fileLog;
        $this->file = new File($logPath);
        $this->timeDate = new TimeDate();
        
        // Гарантируем существование директории логов
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
    }

    /**
     * Записывает сообщение в лог
     * @param string $textLog текст сообщения
     * @param string $typeLog тип/категория сообщения
     * @throws FileException если запись в лог не удалась
     */
    public function inLog(string $textLog, string $typeLog = 'main'): void {
        // Санитизация текста лога: удаляем переносы строк для защиты от Log Injection
        $sanitizedText = str_replace(["\r", "\n"], '', $textLog);
        $sanitizedType = str_replace(["\r", "\n"], '', $typeLog);
        
        $timestamp = $this->timeDate->getDate() . '__' . $this->timeDate->getTime();
        $string = "($sanitizedType)$timestamp---> $sanitizedText" . PHP_EOL;
        
        $this->file->putToFile($string);
    }
    
    /**
     * Записывает сообщение с уровнем ERROR
     */
    public function error(string $message): void {
        $this->inLog($message, 'ERROR');
    }
    
    /**
     * Записывает сообщение с уровнем WARNING
     */
    public function warning(string $message): void {
        $this->inLog($message, 'WARNING');
    }
    
    /**
     * Записывает сообщение с уровнем INFO
     */
    public function info(string $message): void {
        $this->inLog($message, 'INFO');
    }
    
    /**
     * Записывает сообщение с уровнем DEBUG
     */
    public function debug(string $message): void {
        $this->inLog($message, 'DEBUG');
    }
    
    /**
     * Алиас для inLog (для обратной совместимости с тестами)
     * @param string $typeLog тип/категория сообщения
     * @param string $textLog текст сообщения
     */
    public function writeLog(string $typeLog, string $textLog): bool {
        try {
            $this->inLog($textLog, $typeLog);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
    
    /**
     * Закрывает файл лога
     */
    public function close(): void {
        // Файл закроется автоматически в деструкторе File
    }
}