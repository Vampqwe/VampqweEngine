<?php
declare(strict_types = 1);

/**
 * Класс для работы с датой и временем
 * Поддерживает Dependency Injection для конфигурации
 */
final class TimeDate {
    
    private Config $config;
    private string $dateFormat;
    private string $timeFormat;
    private ?string $cachedTimezone = null;

    /**
     * @param Config|null $config Конфигурация (если null, создаётся новая)
     * @throws ConfigException если конфигурация некорректна
     */
    public function __construct(?Config $config = null) {
        $this->config = $config ?? new Config();
        
        $timezone = $this->config->getConfig('dateTime.default_timezone');
        $this->cachedTimezone = (string)$timezone;
        date_default_timezone_set($this->cachedTimezone);
        
        $this->dateFormat = (string)$this->config->getConfig('dateTime.date_TPL');
        $this->timeFormat = (string)$this->config->getConfig('dateTime.time_TPL');
    }

    /**
     * Возвращает текущую дату в формате из конфигурации
     */
    public function getDate(): string {
        return date($this->dateFormat);
    }

    /**
     * Возвращает текущее время в формате из конфигурации
     */
    public function getTime(): string {
        return date($this->timeFormat);
    }

    /**
     * Возвращает текущий часовой пояс (из кэша)
     */
    public function getTimeZone(): string {
        return $this->cachedTimezone ?? date_default_timezone_get();
    }

    /**
     * Возвращает дату в указанном формате
     * @param string|null $format формат даты (если null, используется формат из конфигурации)
     */
    public function getFormattedDate(?string $format = null): string {
        return date($format ?? $this->dateFormat);
    }

    /**
     * Возвращает время в указанном формате
     * @param string|null $format формат времени (если null, используется формат из конфигурации)
     */
    public function getFormattedTime(?string $format = null): string {
        return date($format ?? $this->timeFormat);
    }

    /**
     * Возвращает timestamp
     */
    public function getTimestamp(): int {
        return time();
    }
    
    /**
     * Возвращает текущую дату и время (алиас для обратной совместимости)
     * @return string
     */
    public function getNow(): string {
        return date($this->dateFormat . ' ' . $this->timeFormat);
    }
    
    /**
     * Возвращает дату в указанном формате (алиас для обратной совместимости)
     * @param string $format формат даты
     * @return string
     */
    public function getDateFormatted(string $format): string {
        return date($format);
    }
}
