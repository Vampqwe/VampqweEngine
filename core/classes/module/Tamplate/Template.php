<?php
declare(strict_types = 1);

/**
 * Класс для работы с шаблонами
 * Поддерживает подстановку переменных в шаблон с экранированием HTML
 */
class Template {

    private ?File $file = null;
    private Map $map;
    private Map $escapedMap;

    public function __construct() {
        $this->map = new Map();
        $this->escapedMap = new Map();
    }

    /**
     * Добавляет файл шаблона
     * @param string $tplFile путь к файлу шаблона
     * @throws FileException если файл не найден
     */
    public function addTplFile(string $tplFile): void {
        $this->file = new File($tplFile);
        if (!$this->file->existsFile()) {
            throw new FileException("Шаблон не найден: $tplFile");
        }
    }

    /**
     * Читает содержимое файла шаблона
     * @throws FileException если файл не существует
     */
    public function readTplFile(): string {
        if ($this->file === null) {
            throw new FileException("Файл шаблона не установлен");
        }
        $content = $this->file->readFile();
        return $content !== null ? $content : '';
    }

    /**
     * Присваивает значение переменной шаблона
     * @param string $key имя переменной
     * @param mixed $value значение (приводится к строке)
     */
    public function assign(string $key, mixed $value): void {
        $this->map->put($key, (string)$value);
    }

    /**
     * Присваивает экранированное значение переменной шаблона (защита от XSS)
     * @param string $key имя переменной
     * @param mixed $value значение
     * @param int $flags флаги для htmlspecialchars (по умолчанию ENT_QUOTES | ENT_HTML5)
     * @param string $encoding кодировка (по умолчанию UTF-8)
     */
    public function assignEscaped(string $key, mixed $value, int $flags = ENT_QUOTES | ENT_HTML5, string $encoding = 'UTF-8'): void {
        $stringValue = (string)$value;
        $escapedValue = htmlspecialchars($stringValue, $flags, $encoding);
        $this->escapedMap->put($key, $escapedValue);
    }

    /**
     * Присваивает массив переменных шаблону
     * @param array<string, mixed> $data массив данных
     * @param bool $escape экранировать ли значения (по умолчанию false)
     */
    public function assignArray(array $data, bool $escape = false): void {
        foreach ($data as $key => $value) {
            if ($escape) {
                $this->assignEscaped((string)$key, $value);
            } else {
                $this->assign((string)$key, $value);
            }
        }
    }

    /**
     * Рендерит шаблон с подстановкой переменных
     * @return string отрендеренное содержимое
     * @throws FileException если файл шаблона не установлен
     */
    public function render(): string {
        $content = $this->readTplFile();
        
        // Сначала подставляем экранированные переменные
        foreach ($this->escapedMap->getArrayObject() as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string)$value, $content);
            $content = str_replace('{' . $key . '}', (string)$value, $content);
        }
        
        // Затем обычные переменные (неэкранированные)
        foreach ($this->map->getArrayObject() as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string)$value, $content);
            $content = str_replace('{' . $key . '}', (string)$value, $content);
        }
        
        return $content;
    }

    /**
     * Выводит отрендеренный шаблон
     * @throws FileException если файл шаблона не установлен
     */
    public function display(): void {
        echo $this->render();
    }
    
    /**
     * Очищает все присвоенные переменные
     */
    public function clearAssignments(): void {
        $this->map = new Map();
        $this->escapedMap = new Map();
    }
}